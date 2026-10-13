<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\FileUploader;
use App\Core\Request;
use App\Core\Response;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Services\TaskAccess;
use App\Services\TaskService;

final class TaskAttachmentController extends Controller
{
    public function store(string $id): void
    {
        $task = $this->visibleTask($id);
        $file = Request::file('attachment');
        if ($file === null) {
            flash('danger', 'Choose a file to upload.');
            $this->redirect('tasks/' . $task->id);
        }

        try {
            (new TaskService())->addAttachment($task, $file, $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('tasks/' . $task->id);
        }

        flash('success', 'File has been added.');
        $this->redirect('tasks/' . $task->id);
    }

    public function download(string $id, string $attachmentId): void
    {
        $task = $this->visibleTask($id);
        $attachment = $this->attachment($task, $attachmentId);
        $path = FileUploader::privateAbsolutePath($attachment->stored_path);
        if ($path === null || !is_file($path)) {
            Response::notFound();
        }

        $name = str_replace(['"', "\r", "\n"], '', $attachment->original_name);
        if ($name === '') {
            $name = 'download';
        }

        header('Content-Type: ' . $attachment->mime_type);
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($path);
        exit;
    }

    public function destroy(string $id, string $attachmentId): void
    {
        $task = $this->visibleTask($id);
        $attachment = $this->attachment($task, $attachmentId);

        try {
            (new TaskService())->deleteAttachment($task, $attachment, $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('tasks/' . $task->id);
        }

        flash('success', 'File has been removed.');
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

    private function attachment(Task $task, string $attachmentId): TaskAttachment
    {
        if (!ctype_digit($attachmentId)) {
            Response::notFound();
        }

        $attachment = TaskAttachment::findInTask((int) $attachmentId, (int) $task->id, (int) $task->organization_id);
        if (!$attachment instanceof TaskAttachment) {
            Response::notFound();
        }

        return $attachment;
    }
}
