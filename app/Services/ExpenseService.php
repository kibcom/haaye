<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\FileUploader;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Expense;
use App\Models\Project;
use App\Models\User;

final class ExpenseService
{
    public function create(int $organizationId, array $input, ?array $receipt, int $actorId): Expense
    {
        $category = (string) ($input['category'] ?? '');
        if (!in_array($category, Expense::categories(), true)) {
            throw new \RuntimeException('Choose an expense category.');
        }

        $amount = $this->money((string) ($input['amount'] ?? ''));
        if ($this->cents($amount) <= 0) {
            throw new \RuntimeException('Enter an amount greater than zero.');
        }
        $spentOn = (string) ($input['spent_on'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $spentOn) || strtotime($spentOn) === false) {
            throw new \RuntimeException('Enter the expense date.');
        }

        $userId = $actorId;
        $requested = (int) ($input['user_id'] ?? 0);
        if ($requested > 0 && $requested !== $actorId) {
            if (!Auth::can('expenses.approve')) {
                throw new \RuntimeException('You can only record your own expenses.');
            }
            $user = User::findInOrganization($requested, $organizationId);
            if (!$user instanceof User || $user->role_slug === 'client' || $user->status !== 'active') {
                throw new \RuntimeException('Choose an active teammate.');
            }
            $userId = $requested;
        }

        $projectId = (int) ($input['project_id'] ?? 0);
        $project = null;
        $clientId = null;
        if ($projectId > 0) {
            $project = Project::findInOrganization($projectId, $organizationId);
            if (!$project instanceof Project || !ProjectAccess::canView($project)) {
                throw new \RuntimeException('Choose a project you can see.');
            }
            $clientId = (int) $project->client_id;
        } else {
            $requestedClient = (int) ($input['client_id'] ?? 0);
            if ($requestedClient > 0) {
                $client = Client::findInOrganization($requestedClient, $organizationId);
                if (!$client instanceof Client || $client->status === 'archived') {
                    throw new \RuntimeException('Choose a client.');
                }
                $clientId = (int) $client->id;
            }
        }

        $description = trim((string) ($input['description'] ?? ''));
        if (strlen($description) > 2000) {
            throw new \RuntimeException('The description may not be longer than 2000 characters.');
        }

        $file = FileUploader::privateDocument($receipt, 'expenses/' . $organizationId) ?? [];
        $now = date('Y-m-d H:i:s');
        $id = Expense::create([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'project_id' => $project ? (int) $project->id : null,
            'client_id' => $clientId,
            'category' => $category,
            'amount' => $amount,
            'spent_on' => $spentOn,
            'description' => $description !== '' ? $description : null,
            'status' => 'pending',
            'original_name' => $file['original_name'] ?? null,
            'stored_path' => $file['stored_path'] ?? null,
            'mime_type' => $file['mime_type'] ?? null,
            'size_bytes' => $file['size_bytes'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        ActivityLog::record($organizationId, $actorId, 'expense.created', 'Recorded a ' . $category . ' expense of ' . $amount . '.', 'expense', $id);
        $expense = Expense::findInOrganization($id, $organizationId);
        if (!$expense instanceof Expense) {
            throw new \RuntimeException('The expense could not be saved.');
        }

        return $expense;
    }

    public function decide(Expense $expense, string $decision, string $comment, int $actorId): void
    {
        if (!Auth::can('expenses.approve')) {
            throw new \RuntimeException('You cannot decide this expense.');
        }
        if ($expense->status !== 'pending') {
            throw new \RuntimeException('This expense has already been decided.');
        }
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new \RuntimeException('Choose approve or reject.');
        }
        $comment = trim($comment);
        if ($decision === 'rejected' && $comment === '') {
            throw new \RuntimeException('Add a comment when rejecting an expense.');
        }
        if (strlen($comment) > 2000) {
            throw new \RuntimeException('The comment may not be longer than 2000 characters.');
        }

        Expense::updateById((int) $expense->id, (int) $expense->organization_id, [
            'status' => $decision,
            'decided_by' => $actorId,
            'decision_comment' => $comment !== '' ? $comment : null,
            'decided_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        ActivityLog::record(
            (int) $expense->organization_id,
            $actorId,
            'expense.' . $decision,
            ucfirst($decision) . ' an expense.',
            'expense',
            (int) $expense->id
        );
    }

    public function delete(Expense $expense, int $actorId): void
    {
        if (!FinanceAccess::canDeleteExpense($expense)) {
            throw new \RuntimeException('You cannot delete this expense.');
        }
        if ($expense->stored_path) {
            FileUploader::deletePrivate($expense->stored_path);
        }
        Expense::deleteById((int) $expense->id, (int) $expense->organization_id);
        ActivityLog::record((int) $expense->organization_id, $actorId, 'expense.deleted', 'Deleted a pending expense.', 'expense', (int) $expense->id);
    }

    private function money(string $value): string
    {
        $value = trim($value);
        if (!preg_match('/^\d+(\.\d{1,2})?$/', $value)) {
            throw new \RuntimeException('Enter an amount with up to two decimal places.');
        }

        return number_format($this->cents($value) / 100, 2, '.', '');
    }

    private function cents(string $value): int
    {
        $parts = explode('.', number_format((float) $value, 2, '.', ''));

        return ((int) $parts[0] * 100) + (int) $parts[1];
    }
}
