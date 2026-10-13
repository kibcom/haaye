<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Task;
use App\Models\TaskComment;
use App\Services\TaskAccess;
use App\Services\TaskService;

final class TaskCommentController extends Controller
{
    public function store(string $id): void
    {
        $task = $this->visibleTask($id);
        if (!TaskAccess::canComment($task)) {
            Response::forbidden();
        }

        try {
            (new TaskService())->addComment($task, (string) Request::input('body', ''), Request::file('attachment'), $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('tasks/' . $task->id);
        }

        flash('success', 'Comment has been added.');
        $this->redirect('tasks/' . $task->id);
    }

    public function destroy(string $id, string $commentId): void
    {
        $task = $this->visibleTask($id);
        if (!ctype_digit($commentId)) {
            Response::notFound();
        }

        $comment = TaskComment::findInTask((int) $commentId, (int) $task->id, (int) $task->organization_id);
        if (!$comment instanceof TaskComment) {
            Response::notFound();
        }

        try {
            (new TaskService())->deleteComment($task, $comment, $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('tasks/' . $task->id);
        }

        flash('success', 'Comment has been removed.');
        $this->redirect('tasks/' . $task->id);
    }

    private function visibleTask(string $id): Task
    {
        $this->authorize('tasks.view');
        if (!ctype_digit($id)) {
            Response::notFound();
        }

        $task = Task::findInOrganization((int) $id, $this->tenantId());
        if (!$task instanceof Task || !TaskAccess::canView($task)) {
            Response::notFound();
        }

        return $task;
    }
}
