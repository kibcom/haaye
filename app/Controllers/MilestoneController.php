<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectAccess;
use App\Services\ProjectService;

final class MilestoneController extends Controller
{
    public function store(string $projectId): void
    {
        $project = $this->managedProject($projectId);
        $data = $this->formData();
        if (!$this->validateMilestone($data, 'projects/' . $project->id)) {
            return;
        }

        try {
            (new ProjectService())->saveMilestone($project, null, $data, $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('projects/' . $project->id);
        }

        flash('success', 'Milestone has been added.');
        $this->redirect('projects/' . $project->id);
    }

    public function edit(string $projectId, string $milestoneId): void
    {
        $project = $this->managedProject($projectId);
        $milestone = $this->milestone($milestoneId, $project);

        $this->view('projects/milestone-form', [
            'title' => 'Edit milestone',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Projects', 'url' => 'projects'],
                ['label' => $project->name, 'url' => 'projects/' . $project->id],
                ['label' => 'Edit milestone'],
            ],
            'project' => $project,
            'milestone' => $milestone,
            'people' => User::listAssignable((int) $project->organization_id),
        ]);
    }

    public function update(string $projectId, string $milestoneId): void
    {
        $project = $this->managedProject($projectId);
        $milestone = $this->milestone($milestoneId, $project);
        $data = $this->formData();
        $back = 'projects/' . $project->id . '/milestones/' . $milestone->id . '/edit';

        if (!$this->validateMilestone($data, $back)) {
            return;
        }

        try {
            (new ProjectService())->saveMilestone($project, $milestone, $data, $this->actor()->id);
        } catch (\App\Services\WorkflowPending $pending) {
            flash('success', 'Milestone saved. ' . $pending->getMessage());
            $this->redirect('projects/' . $project->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect($back);
        }

        flash('success', 'Milestone has been updated.');
        $this->redirect('projects/' . $project->id);
    }

    public function destroy(string $projectId, string $milestoneId): void
    {
        $project = $this->managedProject($projectId);
        $milestone = $this->milestone($milestoneId, $project);
        (new ProjectService())->deleteMilestone($project, $milestone, $this->actor()->id);
        flash('success', 'Milestone has been removed.');
        $this->redirect('projects/' . $project->id);
    }

    private function formData(): array
    {
        return Request::only(['name', 'description', 'due_date', 'status', 'progress', 'owner_user_id']);
    }

    private function validateMilestone(array $data, string $back): bool
    {
        $validator = new Validator($data);
        if ($validator->validate([
            'name' => 'required|max:180',
            'status' => 'required|in:open,in_progress,completed,cancelled',
            'due_date' => 'date',
        ])) {
            return true;
        }

        $this->withErrors($validator->errors());
        $this->redirect($back);
        return false;
    }

    private function managedProject(string $id): Project
    {
        $this->authorize('projects.milestones.manage');
        if (!ctype_digit($id)) {
            Response::notFound();
        }

        $project = Project::findInOrganization((int) $id, $this->tenantId());
        if (!$project instanceof Project || !ProjectAccess::canManageMilestones($project)) {
            Response::forbidden();
        }

        return $project;
    }

    private function milestone(string $id, Project $project): Milestone
    {
        if (!ctype_digit($id)) {
            Response::notFound();
        }

        $milestone = Milestone::findInProject((int) $id, (int) $project->id, (int) $project->organization_id);
        if (!$milestone instanceof Milestone) {
            Response::notFound();
        }

        return $milestone;
    }
}
