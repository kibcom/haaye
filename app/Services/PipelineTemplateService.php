<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\WorkflowTemplate;
use App\Models\WorkflowTemplateStage;

final class PipelineTemplateService
{
    public function create(int $organizationId, array $input, int $actorId): int
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException('Enter a template name.');
        }
        $mode = (string) ($input['progression_mode'] ?? 'automatic');
        if (!in_array($mode, ['automatic', 'manual', 'hybrid'], true)) {
            $mode = 'automatic';
        }
        $now = date('Y-m-d H:i:s');

        return WorkflowTemplate::create([
            'organization_id' => $organizationId,
            'name' => $name,
            'description' => trim((string) ($input['description'] ?? '')) ?: null,
            'progression_mode' => $mode,
            'is_active' => !empty($input['is_active']) ? 1 : 0,
            'created_by' => $actorId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function update(WorkflowTemplate $template, array $input): void
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException('Enter a template name.');
        }
        $mode = (string) ($input['progression_mode'] ?? $template->progression_mode);
        if (!in_array($mode, ['automatic', 'manual', 'hybrid'], true)) {
            $mode = $template->progression_mode;
        }
        WorkflowTemplate::updateById((int) $template->id, (int) $template->organization_id, [
            'name' => $name,
            'description' => trim((string) ($input['description'] ?? '')) ?: null,
            'progression_mode' => $mode,
            'is_active' => !empty($input['is_active']) ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function addStage(WorkflowTemplate $template, array $input): int
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException('Enter a stage name.');
        }
        $now = date('Y-m-d H:i:s');
        $orgId = (int) $template->organization_id;

        return WorkflowTemplateStage::create([
            'organization_id' => $orgId,
            'template_id' => (int) $template->id,
            'name' => $name,
            'description' => trim((string) ($input['description'] ?? '')) ?: null,
            'instructions' => trim((string) ($input['instructions'] ?? '')) ?: null,
            'sort_order' => WorkflowTemplateStage::nextSortOrder((int) $template->id, $orgId),
            'work_flow_role_id' => ((int) ($input['work_flow_role_id'] ?? 0)) ?: null,
            'default_assignee_user_id' => ((int) ($input['default_assignee_user_id'] ?? 0)) ?: null,
            'requires_approval' => !isset($input['requires_approval']) || !empty($input['requires_approval']) ? 1 : 0,
            'approver_user_id' => ((int) ($input['approver_user_id'] ?? 0)) ?: null,
            'auto_advance' => !isset($input['auto_advance']) || !empty($input['auto_advance']) ? 1 : 0,
            'deliverables_json' => trim((string) ($input['deliverables'] ?? '')) ?: null,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function updateStage(WorkflowTemplateStage $stage, array $input): void
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException('Enter a stage name.');
        }
        WorkflowTemplateStage::updateById((int) $stage->id, (int) $stage->organization_id, [
            'name' => $name,
            'description' => trim((string) ($input['description'] ?? '')) ?: null,
            'instructions' => trim((string) ($input['instructions'] ?? '')) ?: null,
            'sort_order' => max(1, (int) ($input['sort_order'] ?? $stage->sort_order)),
            'work_flow_role_id' => ((int) ($input['work_flow_role_id'] ?? 0)) ?: null,
            'default_assignee_user_id' => ((int) ($input['default_assignee_user_id'] ?? 0)) ?: null,
            'requires_approval' => !empty($input['requires_approval']) ? 1 : 0,
            'approver_user_id' => ((int) ($input['approver_user_id'] ?? 0)) ?: null,
            'auto_advance' => !empty($input['auto_advance']) ? 1 : 0,
            'deliverables_json' => trim((string) ($input['deliverables'] ?? '')) ?: null,
            'is_active' => !empty($input['is_active']) ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
