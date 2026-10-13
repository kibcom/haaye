<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\WorkFlowRole;
use App\Models\WorkflowRun;
use App\Models\WorkflowTemplate;
use App\Models\WorkflowTemplateStage;

/**
 * Connected demo: employees by role, one shared password, project + pipeline run + tasks.
 */
final class Phase14DemoSetup
{
    public const DEMO_PASSWORD = 'DemoPass123!';

    /** @var list<array{0:string,1:string,2:string,3:string,4:string}> slug, name, email, title, code */
    private const EMPLOYEES = [
        ['general_manager', 'Abel Bekele', 'gm@haaye.com', 'General Manager / CEO', 'GM-1001'],
        ['operations_manager', 'Meron Assefa', 'ops@haaye.com', 'Operations Manager', 'OPS-1001'],
        ['sales', 'Sara Sales', 'sales@haaye.com', 'Sales Executive', 'SALES-001'],
        ['site_locator', 'Ruth Desta', 'locator@haaye.com', 'Site Locator', 'LOC-1001'],
        ['content_creator', 'Kidist Alemu', 'content@haaye.com', 'Content Creator', 'CNT-1001'],
        ['videographer', 'Samuel Haile', 'video@haaye.com', 'Videographer', 'VID-1001'],
        ['editor', 'Yonatan Kebede', 'editor@haaye.com', 'Editor', 'EDT-1001'],
        ['social_media_manager', 'Hana Girma', 'social@haaye.com', 'Social Media Manager', 'SMM-1001'],
        ['employee', 'Liya Tesfaye', 'employee@haaye.com', 'Team Member', 'EMP-1001'],
    ];

    public static function seed(): void
    {
        Phase14Setup::applySchema();
        Phase14Setup::seedPermissions();
        Phase10Setup::seedRoles();
        Phase10Setup::seedPermissions();
        self::ensureContentCreatorRole();
        Phase10Setup::seedPipelineRoles();
        Phase10Setup::seedSampleUsers();
        self::ensureEmployees();
        self::ensureConnectedDemo();
    }

    /** @return list<string> */
    public static function demoEmails(): array
    {
        $emails = array_column(self::EMPLOYEES, 2);
        $emails[] = 'owner@haaye.com';
        $emails[] = 'dawit@haaye.com';
        $emails[] = 'manager@haaye.com';
        $emails[] = 'finance@haaye.com';
        $emails[] = 'client@haaye.com';

        return $emails;
    }

    public static function isDemoUser(?string $email): bool
    {
        if ($email === null || $email === '') {
            return false;
        }

        $email = strtolower($email);
        if (in_array($email, array_map('strtolower', self::demoEmails()), true)) {
            return true;
        }

        return LuminaDemoSetup::isDemoUser($email);
    }

    /** @return list<array{role:string,name:string,email:string,password:string}> */
    public static function credentials(): array
    {
        $rows = [];
        foreach (self::EMPLOYEES as $item) {
            $rows[] = [
                'role' => $item[3],
                'name' => $item[1],
                'email' => $item[2],
                'password' => self::DEMO_PASSWORD,
            ];
        }
        $rows[] = [
            'role' => 'Owner',
            'name' => 'Hanna Tadesse',
            'email' => 'owner@haaye.com',
            'password' => self::DEMO_PASSWORD,
        ];

        return $rows;
    }

    private static function ensureContentCreatorRole(): void
    {
        $now = date('Y-m-d H:i:s');
        $exists = Database::query('SELECT id FROM roles WHERE slug = ? LIMIT 1', ['content_creator'])->fetch();
        if ($exists) {
            Database::query(
                'UPDATE roles SET name = ?, description = ?, updated_at = ? WHERE id = ?',
                ['Content Creator', 'Prepares captions, scripts, and creative content for pipeline stages', $now, (int) $exists['id']]
            );
        } else {
            Database::query(
                'INSERT INTO roles (name, slug, description, scope, is_system, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 1, ?, ?)',
                [
                    'Content Creator',
                    'content_creator',
                    'Prepares captions, scripts, and creative content for pipeline stages',
                    'organization',
                    $now,
                    $now,
                ]
            );
        }

        $role = Role::findBySlug('content_creator');
        if (!$role instanceof Role) {
            return;
        }
        $slugs = [
            'dashboard.view', 'profile.view', 'profile.update',
            'tasks.view', 'tasks.submit', 'tasks.comment', 'tasks.attachments',
            'workspace.view', 'pipeline.view', 'projects.view', 'clients.view',
            'work_flow.view', 'deliverables.view', 'approvals.view', 'approvals.request',
        ];
        foreach ($slugs as $slug) {
            $perm = Database::query('SELECT id FROM permissions WHERE slug = ? LIMIT 1', [$slug])->fetch();
            if (!$perm) {
                continue;
            }
            $linked = Database::query(
                'SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ? LIMIT 1',
                [(int) $role->id, (int) $perm['id']]
            )->fetch();
            if ($linked) {
                continue;
            }
            Database::query(
                'INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)',
                [(int) $role->id, (int) $perm['id']]
            );
        }

        // Ensure Content Creator appears in work_flow roles.
        $orgs = Database::query('SELECT id FROM organizations')->fetchAll();
        foreach ($orgs as $org) {
            $orgId = (int) $org['id'];
            $wf = Database::query(
                'SELECT id FROM work_flow_roles WHERE organization_id = ? AND name = ? LIMIT 1',
                [$orgId, 'Content Creator']
            )->fetch();
            if ($wf) {
                continue;
            }
            $order = (int) Database::query(
                'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM work_flow_roles WHERE organization_id = ?',
                [$orgId]
            )->fetchColumn();
            WorkFlowRole::create([
                'organization_id' => $orgId,
                'name' => 'Content Creator',
                'description' => 'Captions, scripts, creative assets',
                'sort_order' => $order,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private static function ensureEmployees(): void
    {
        $org = Database::query('SELECT id FROM organizations ORDER BY id ASC LIMIT 1')->fetch();
        if (!$org) {
            return;
        }
        $orgId = (int) $org['id'];
        $now = date('Y-m-d H:i:s');
        $hash = password_hash(self::DEMO_PASSWORD, PASSWORD_DEFAULT);

        foreach (self::EMPLOYEES as $item) {
            $role = Role::findBySlug($item[0]);
            if (!$role instanceof Role) {
                continue;
            }
            $existing = Database::query(
                'SELECT id FROM users WHERE organization_id = ? AND email = ? LIMIT 1',
                [$orgId, $item[2]]
            )->fetch();
            if ($existing) {
                Database::query(
                    'UPDATE users SET role_id = ?, name = ?, job_title = ?, employee_code = ?, status = ?, password_hash = ?, updated_at = ?
                     WHERE id = ?',
                    [$role->id, $item[1], $item[3], $item[4], 'active', $hash, $now, (int) $existing['id']]
                );
                continue;
            }
            User::create([
                'organization_id' => $orgId,
                'role_id' => $role->id,
                'name' => $item[1],
                'email' => $item[2],
                'phone' => null,
                'job_title' => $item[3],
                'employee_code' => $item[4],
                'status' => 'active',
                'joining_date' => date('Y-m-d'),
                'password_hash' => $hash,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Keep classic demos on the same password.
        foreach (['owner@haaye.com', 'dawit@haaye.com', 'manager@haaye.com', 'finance@haaye.com', 'client@haaye.com'] as $email) {
            Database::query(
                'UPDATE users SET password_hash = ?, status = ?, updated_at = ? WHERE email = ?',
                [$hash, 'active', $now, $email]
            );
        }
    }

    private static function ensureConnectedDemo(): void
    {
        $org = Database::query('SELECT id FROM organizations ORDER BY id ASC LIMIT 1')->fetch();
        if (!$org) {
            return;
        }
        $orgId = (int) $org['id'];
        $now = date('Y-m-d H:i:s');
        TaskStatus::ensureLifecycleStatuses($orgId);

        $users = self::usersBySlug($orgId);
        $ceoId = $users['general_manager'] ?? $users['owner'] ?? $users['admin'] ?? null;
        $opsId = $users['operations_manager'] ?? $ceoId;
        if ($ceoId === null) {
            return;
        }

        $clientId = self::ensureClient($orgId, $users['sales'] ?? $ceoId, $now);
        $projectId = self::ensureProject($orgId, $clientId, $opsId ?? $ceoId, $now);
        self::ensureProjectMembers($orgId, $projectId, $users, $now);

        $templateId = self::ensureTemplateWithAssignees($orgId, $users, $ceoId, $now);
        $runId = self::ensurePipelineRun($orgId, $templateId, $projectId, $clientId, $ceoId, $users, $now);
        self::ensureRoleSampleTasks($orgId, $projectId, $clientId, $users, $ceoId, $now);
        self::ensureSharedOpsTasks($orgId, $projectId, $clientId, $users, $ceoId, $now);

        // Mark demo seeded.
        Database::query(
            'UPDATE organizations SET updated_at = ? WHERE id = ?',
            [$now, $orgId]
        );

        unset($runId);
    }

    /** @return array<string,int> */
    private static function usersBySlug(int $orgId): array
    {
        $rows = Database::query(
            'SELECT u.id, r.slug FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.organization_id = ?',
            [$orgId]
        )->fetchAll();
        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['slug']] = (int) $row['id'];
        }

        return $map;
    }

    private static function ensureClient(int $orgId, int $salesId, string $now): int
    {
        $row = Database::query(
            'SELECT id FROM clients WHERE organization_id = ? AND name = ? LIMIT 1',
            [$orgId, 'Haaye Demo Client']
        )->fetch();
        if ($row) {
            return (int) $row['id'];
        }

        return \App\Models\Client::create([
            'organization_id' => $orgId,
            'name' => 'Haaye Demo Client',
            'contact_name' => 'Demo Contact',
            'email' => 'client-demo@haaye.com',
            'phone' => '+251 91 000 0000',
            'city' => 'Addis Ababa',
            'country' => 'Ethiopia',
            'status' => 'active',
            'assigned_user_id' => $salesId,
            'notes' => 'Demo client linked to sample pipeline and tasks.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private static function ensureProject(int $orgId, int $clientId, int $managerId, string $now): int
    {
        $row = Database::query(
            'SELECT id FROM projects WHERE organization_id = ? AND name = ? LIMIT 1',
            [$orgId, 'Demo Marketing Campaign']
        )->fetch();
        if ($row) {
            return (int) $row['id'];
        }

        \App\Models\ProjectStatus::ensureDefaults($orgId);
        $status = \App\Models\ProjectStatus::findBySlug($orgId, 'active')
            ?? \App\Models\ProjectStatus::findBySlug($orgId, 'planning');

        return Project::create([
            'organization_id' => $orgId,
            'client_id' => $clientId,
            'name' => 'Demo Marketing Campaign',
            'code' => 'DEMO-CAMP-01',
            'description' => 'Connected demo: pipeline stages create real tasks for each department.',
            'status' => $status?->slug ?? 'active',
            'priority' => 'high',
            'manager_user_id' => $managerId,
            'start_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+30 days')),
            'billing_type' => 'fixed',
            'payment_status' => 'unpaid',
            'progress' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @param array<string,int> $users */
    private static function ensureProjectMembers(int $orgId, int $projectId, array $users, string $now): void
    {
        foreach ($users as $userId) {
            $exists = Database::query(
                'SELECT id FROM project_members WHERE project_id = ? AND user_id = ? LIMIT 1',
                [$projectId, $userId]
            )->fetch();
            if ($exists) {
                continue;
            }
            Database::query(
                'INSERT INTO project_members (organization_id, project_id, user_id, member_role, created_at)
                 VALUES (?, ?, ?, ?, ?)',
                [$orgId, $projectId, $userId, 'member', $now]
            );
        }
    }

    /** @param array<string,int> $users */
    private static function ensureTemplateWithAssignees(int $orgId, array $users, int $ceoId, string $now): int
    {
        $template = Database::query(
            'SELECT id FROM workflow_templates WHERE organization_id = ? AND name = ? LIMIT 1',
            [$orgId, 'Marketing Campaign Pipeline']
        )->fetch();
        if (!$template) {
            // Fall back to any active template, or create one.
            $template = Database::query(
                'SELECT id FROM workflow_templates WHERE organization_id = ? AND is_active = 1 ORDER BY id ASC LIMIT 1',
                [$orgId]
            )->fetch();
        }
        if (!$template) {
            Database::query(
                'INSERT INTO workflow_templates (organization_id, name, description, progression_mode, is_active, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 1, ?, ?, ?)',
                [
                    $orgId,
                    'Marketing Campaign Pipeline',
                    'Demo production sequence linked to real tasks.',
                    'automatic',
                    $ceoId,
                    $now,
                    $now,
                ]
            );
            $templateId = (int) Database::lastInsertId();
        } else {
            $templateId = (int) $template['id'];
            Database::query(
                'UPDATE workflow_templates SET progression_mode = ?, is_active = 1, updated_at = ? WHERE id = ?',
                ['automatic', $now, $templateId]
            );
        }

        $stageDefs = [
            ['Site Location', 'site_locator', 'Visit/research location. Upload photos and notes.'],
            ['Video Shooting', 'videographer', 'Record required footage and upload deliverables.'],
            ['Content Creation', 'content_creator', 'Prepare captions, scripts, or creative assets.'],
            ['Video Editing', 'editor', 'Upload the edited video and supporting files.'],
            ['Social Media Management', 'social_media_manager', 'Prepare/publish approved content with evidence links.'],
            ['Final Review', 'operations_manager', 'Manager reviews completed deliverables.'],
        ];

        $existingStages = WorkflowTemplateStage::forTemplate($templateId, $orgId, false);
        if ($existingStages === []) {
            $order = 1;
            foreach ($stageDefs as $def) {
                $roleId = self::workFlowRoleId($orgId, $def[0]);
                WorkflowTemplateStage::create([
                    'organization_id' => $orgId,
                    'template_id' => $templateId,
                    'name' => $def[0],
                    'description' => $def[2],
                    'instructions' => $def[2],
                    'sort_order' => $order,
                    'work_flow_role_id' => $roleId,
                    'default_assignee_user_id' => $users[$def[1]] ?? null,
                    'requires_approval' => 1,
                    'approver_user_id' => $ceoId,
                    'auto_advance' => 1,
                    'deliverables_json' => $def[2],
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $order++;
            }
        } else {
            foreach ($existingStages as $index => $stage) {
                $def = $stageDefs[$index] ?? null;
                $slug = $def[1] ?? null;
                WorkflowTemplateStage::updateById((int) $stage->id, $orgId, [
                    'default_assignee_user_id' => $slug && isset($users[$slug]) ? $users[$slug] : $stage->default_assignee_user_id,
                    'approver_user_id' => $ceoId,
                    'requires_approval' => 1,
                    'auto_advance' => 1,
                    'is_active' => 1,
                    'updated_at' => $now,
                ]);
            }
        }

        return $templateId;
    }

    private static function workFlowRoleId(int $orgId, string $name): ?int
    {
        $row = Database::query(
            'SELECT id FROM work_flow_roles WHERE organization_id = ? AND name = ? LIMIT 1',
            [$orgId, $name]
        )->fetch();
        if ($row) {
            return (int) $row['id'];
        }
        // Fuzzy match.
        $row = Database::query(
            'SELECT id FROM work_flow_roles WHERE organization_id = ? AND name LIKE ? LIMIT 1',
            [$orgId, '%' . explode(' ', $name)[0] . '%']
        )->fetch();

        return $row ? (int) $row['id'] : null;
    }

    /** @param array<string,int> $users */
    private static function ensurePipelineRun(
        int $orgId,
        int $templateId,
        int $projectId,
        int $clientId,
        int $ceoId,
        array $users,
        string $now
    ): int {
        $existing = Database::query(
            'SELECT id FROM workflow_runs WHERE organization_id = ? AND project_id = ? AND name = ? LIMIT 1',
            [$orgId, $projectId, 'Demo Marketing Campaign Pipeline']
        )->fetch();
        if ($existing) {
            self::ensureSalesTask($orgId, $projectId, $clientId, $users, $ceoId, $now);
            return (int) $existing['id'];
        }

        $templateStages = WorkflowTemplateStage::forTemplate($templateId, $orgId, true);
        $map = [
            0 => 'site_locator',
            1 => 'videographer',
            2 => 'content_creator',
            3 => 'editor',
            4 => 'social_media_manager',
            5 => 'operations_manager',
        ];

        $runId = WorkflowRun::create([
            'organization_id' => $orgId,
            'template_id' => $templateId,
            'project_id' => $projectId,
            'client_id' => $clientId,
            'name' => 'Demo Marketing Campaign Pipeline',
            'status' => 'active',
            'progression_mode' => 'automatic',
            'started_by' => $ceoId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $firstStageId = null;
        $firstAssignee = null;
        $order = 0;
        foreach ($templateStages as $tplStage) {
            $slug = $map[$order] ?? null;
            $assignee = ($slug && isset($users[$slug]))
                ? $users[$slug]
                : (int) ($tplStage->default_assignee_user_id ?? 0);
            $assignee = $assignee > 0 ? $assignee : ($users['employee'] ?? $ceoId);

            $stageId = \App\Models\WorkflowRunStage::create([
                'organization_id' => $orgId,
                'run_id' => $runId,
                'template_stage_id' => (int) $tplStage->id,
                'name' => $tplStage->name,
                'sort_order' => (int) $tplStage->sort_order,
                'status' => 'pending',
                'work_flow_role_id' => $tplStage->work_flow_role_id,
                'assignee_user_id' => $assignee,
                'requires_approval' => 1,
                'approver_user_id' => $ceoId,
                'auto_advance' => 1,
                'instructions' => $tplStage->instructions ?: $tplStage->description,
                'deliverables_json' => $tplStage->deliverables_json,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if ($firstStageId === null) {
                $firstStageId = $stageId;
                $firstAssignee = $assignee;
            }
            $order++;
        }

        if ($firstStageId !== null && $firstAssignee !== null) {
            $taskId = Task::create([
                'organization_id' => $orgId,
                'project_id' => $projectId,
                'client_id' => $clientId,
                'title' => 'Site Location',
                'description' => 'Visit/research location. Upload photos and notes.',
                'completion_criteria' => 'Photos, location notes, access details',
                'assignee_user_id' => $firstAssignee,
                'reporter_user_id' => $ceoId,
                'manager_user_id' => $ceoId,
                'work_flow_role_id' => self::workFlowRoleId($orgId, 'Site Locator'),
                'workflow_run_id' => $runId,
                'workflow_run_stage_id' => $firstStageId,
                'priority' => 'high',
                'status' => TaskLifecycle::ASSIGNED,
                'progress' => TaskStatus::progressForSlug(TaskStatus::forOrganization($orgId), TaskLifecycle::ASSIGNED),
                'due_date' => date('Y-m-d', strtotime('+5 days')),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            // Use actual first stage name if available.
            $firstStage = \App\Models\WorkflowRunStage::findInOrganization($firstStageId, $orgId);
            if ($firstStage instanceof \App\Models\WorkflowRunStage) {
                Task::updateById($taskId, $orgId, [
                    'title' => $firstStage->name,
                    'description' => $firstStage->instructions,
                    'updated_at' => $now,
                ]);
            }
            \App\Models\WorkflowRunStage::updateById($firstStageId, $orgId, [
                'status' => 'ready',
                'task_id' => $taskId,
                'activated_at' => $now,
                'updated_at' => $now,
            ]);
            WorkflowRun::updateById($runId, $orgId, [
                'current_stage_id' => $firstStageId,
                'updated_at' => $now,
            ]);
        }

        self::ensureSalesTask($orgId, $projectId, $clientId, $users, $ceoId, $now);

        return $runId;
    }

    /** @param array<string,int> $users */
    private static function ensureSalesTask(
        int $orgId,
        int $projectId,
        int $clientId,
        array $users,
        int $ceoId,
        string $now
    ): void {
        if (!isset($users['sales'])) {
            return;
        }
        $hasSales = Database::query(
            'SELECT id FROM tasks WHERE organization_id = ? AND project_id = ? AND title = ? LIMIT 1',
            [$orgId, $projectId, 'Confirm brief with client']
        )->fetch();
        if ($hasSales) {
            return;
        }
        Task::create([
            'organization_id' => $orgId,
            'project_id' => $projectId,
            'client_id' => $clientId,
            'title' => 'Confirm brief with client',
            'description' => 'Sales kickoff before production pipeline.',
            'assignee_user_id' => $users['sales'],
            'reporter_user_id' => $ceoId,
            'manager_user_id' => $ceoId,
            'work_flow_role_id' => self::workFlowRoleId($orgId, 'Sales'),
            'priority' => 'high',
            'status' => TaskLifecycle::ASSIGNED,
            'progress' => 10,
            'due_date' => date('Y-m-d', strtotime('+3 days')),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @param array<string,int> $users */
    private static function ensureRoleSampleTasks(
        int $orgId,
        int $projectId,
        int $clientId,
        array $users,
        int $ceoId,
        string $now
    ): void {
        $samples = [
            ['sales', 'Sales', 'Prepare proposal follow-up', 'Sales sample task on the demo campaign.'],
            ['site_locator', 'Site Locator', 'Shortlist 3 shoot locations', 'Bring photos and access notes.'],
            ['content_creator', 'Content Creator', 'Draft campaign captions', 'Write 5 caption options for social.'],
            ['videographer', 'Videographer', 'Gear checklist for shoot', 'Confirm camera, lights, audio kit.'],
            ['editor', 'Editor', 'Collect brand assets', 'Logos, fonts, and music references.'],
            ['social_media_manager', 'Social Media Manager', 'Plan posting calendar', 'Map posts for launch week.'],
            ['operations_manager', 'Operations Manager', 'Review production schedule', 'Confirm deadlines across stages.'],
            ['general_manager', 'General Manager', 'Approve campaign kickoff', 'CEO review of the demo pipeline.'],
        ];
        foreach ($samples as $index => $sample) {
            $slug = $sample[0];
            if (!isset($users[$slug])) {
                continue;
            }
            $title = $sample[2];
            $exists = Database::query(
                'SELECT id FROM tasks WHERE organization_id = ? AND project_id = ? AND title = ? LIMIT 1',
                [$orgId, $projectId, $title]
            )->fetch();
            if ($exists) {
                continue;
            }
            Task::create([
                'organization_id' => $orgId,
                'project_id' => $projectId,
                'client_id' => $clientId,
                'title' => $title,
                'description' => $sample[3],
                'assignee_user_id' => $users[$slug],
                'reporter_user_id' => $ceoId,
                'manager_user_id' => $ceoId,
                'work_flow_role_id' => self::workFlowRoleId($orgId, $sample[1]),
                'priority' => $index < 2 ? 'high' : 'medium',
                'status' => TaskLifecycle::ASSIGNED,
                'progress' => TaskStatus::progressForSlug(TaskStatus::forOrganization($orgId), TaskLifecycle::ASSIGNED),
                'due_date' => date('Y-m-d', strtotime('+' . (3 + $index) . ' days')),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /** @param array<string,int> $users */
    private static function ensureSharedOpsTasks(
        int $orgId,
        int $projectId,
        int $clientId,
        array $users,
        int $ceoId,
        string $now
    ): void {
        // Mirror pipeline stage tasks onto work_flow board if empty.
        Phase10Setup::seedSampleTasks();

        // Ensure every pipeline-linked task also has work_flow_role for board visibility.
        $rows = Database::query(
            'SELECT t.id, t.title, t.assignee_user_id, t.work_flow_role_id, t.workflow_run_stage_id
             FROM tasks t
             WHERE t.organization_id = ? AND t.project_id = ?',
            [$orgId, $projectId]
        )->fetchAll();
        foreach ($rows as $row) {
            if (!empty($row['work_flow_role_id'])) {
                continue;
            }
            $roleId = null;
            if (!empty($row['assignee_user_id'])) {
                $slug = Database::query(
                    'SELECT r.slug FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.id = ? LIMIT 1',
                    [(int) $row['assignee_user_id']]
                )->fetchColumn();
                $nameMap = [
                    'site_locator' => 'Site Locator',
                    'videographer' => 'Videographer',
                    'content_creator' => 'Content Creator',
                    'editor' => 'Editor',
                    'social_media_manager' => 'Social Media Manager',
                    'operations_manager' => 'Operations Manager',
                    'sales' => 'Sales',
                    'general_manager' => 'General Manager',
                ];
                if (is_string($slug) && isset($nameMap[$slug])) {
                    $roleId = self::workFlowRoleId($orgId, $nameMap[$slug]);
                }
            }
            if ($roleId) {
                Task::updateById((int) $row['id'], $orgId, [
                    'work_flow_role_id' => $roleId,
                    'updated_at' => $now,
                ]);
            }
        }
        unset($clientId, $users, $ceoId);
    }
}
