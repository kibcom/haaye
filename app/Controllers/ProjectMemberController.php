<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Services\ProjectAccess;
use App\Services\ProjectService;

final class ProjectMemberController extends Controller
{
    public function store(string $projectId): void
    {
        $project = $this->managedProject($projectId);
        $role = (string) Request::input('member_role', '');
        $ids = Request::input('user_ids', []);
        if (!is_array($ids)) {
            $ids = [];
        }
        $ids = array_values(array_unique(array_filter($ids, static function ($id): bool {
            return is_scalar($id) && ctype_digit((string) $id);
        })));

        if (!in_array($role, ['manager', 'member'], true)) {
            flash('danger', 'Choose a role for the selected people.');
            $this->redirect('projects/' . $project->id . '#members');
        }
        if ($ids === []) {
            flash('danger', 'Select at least one person.');
            $this->redirect('projects/' . $project->id . '#members');
        }

        try {
            $count = (new ProjectService())->addMembers($project, $ids, $role, $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('projects/' . $project->id . '#members');
        }

        flash('success', $count === 1 ? '1 person was added to the team.' : $count . ' people were added to the team.');
        $this->redirect('projects/' . $project->id . '#members');
    }

    public function update(string $projectId, string $memberId): void
    {
        $project = $this->managedProject($projectId);
        if (!ctype_digit($memberId)) {
            Response::notFound();
        }

        $member = ProjectMember::findInProject((int) $memberId, (int) $project->id, (int) $project->organization_id);
        if (!$member instanceof ProjectMember) {
            Response::notFound();
        }

        $role = (string) Request::input('member_role', '');
        try {
            (new ProjectService())->updateMemberRole($project, $member, $role, $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('projects/' . $project->id . '#members');
        }

        flash('success', 'The role has been updated.');
        $this->redirect('projects/' . $project->id . '#members');
    }

    public function destroy(string $projectId, string $memberId): void
    {
        $project = $this->managedProject($projectId);
        if (!ctype_digit($memberId)) {
            Response::notFound();
        }

        $member = ProjectMember::findInProject((int) $memberId, (int) $project->id, (int) $project->organization_id);
        if (!$member instanceof ProjectMember) {
            Response::notFound();
        }

        try {
            (new ProjectService())->removeMember($project, $member, $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('projects/' . $project->id . '#members');
        }

        flash('success', 'Project member has been removed.');
        $this->redirect('projects/' . $project->id . '#members');
    }

    private function managedProject(string $id): Project
    {
        $this->authorize('projects.members.manage');
        if (!ctype_digit($id)) {
            Response::notFound();
        }

        $project = Project::findInOrganization((int) $id, $this->tenantId());
        if (!$project instanceof Project || !ProjectAccess::canManageMembers($project)) {
            Response::forbidden();
        }

        return $project;
    }
}
