<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Booking extends Model
{
    public ?int $id = null;
    public int $organization_id = 0;
    public int $project_id = 0;
    public ?int $task_id = null;
    public string $title = '';
    public ?string $contact_name = null;
    public string $starts_at = '';
    public string $ends_at = '';
    public string $status = 'scheduled';
    public ?string $location = null;
    public ?string $notes = null;
    public ?string $checklist_company = null;
    public ?string $checklist_date = null;
    public ?string $equipment_json = null;
    public ?string $rentals_json = null;
    public ?string $represented_name = null;
    public int $represented_signed = 0;
    public string $checklist_status = 'draft';
    public ?int $submitted_to_user_id = null;
    public ?string $submitted_at = null;
    public ?int $reviewed_by = null;
    public ?string $reviewed_at = null;
    public ?string $review_note = null;
    public ?string $selected_equipment_json = null;
    public ?int $created_by = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public ?string $project_name = null;
    public ?string $client_name = null;
    public ?string $task_title = null;
    public ?string $submitted_to_name = null;
    public ?string $reviewed_by_name = null;

    public static function table(): string
    {
        return 'bookings';
    }

    public static function findInOrganization(int $id, int $organizationId): ?self
    {
        $row = Database::query(self::selectSql() . ' WHERE b.id = ? AND b.organization_id = ? LIMIT 1', [$id, $organizationId])->fetch();

        return $row ? self::hydrate($row) : null;
    }

    /** @return list<self> */
    public static function listFor(int $organizationId, ?int $projectId = null, ?string $status = null, ?int $onlyUserId = null): array
    {
        [$where, $params] = self::filters($organizationId, $projectId, $status, $onlyUserId);
        $rows = Database::query(
            self::selectSql() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY b.starts_at ASC, b.id ASC',
            $params
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    /** @return list<self> */
    public static function forProject(int $projectId, int $organizationId): array
    {
        return self::listFor($organizationId, $projectId);
    }

    public static function create(array $data): int
    {
        Database::query(
            'INSERT INTO bookings (
                organization_id, project_id, task_id, title, contact_name, starts_at, ends_at, status,
                location, notes, checklist_company, checklist_date, equipment_json, rentals_json,
                represented_name, represented_signed, checklist_status, submitted_to_user_id, submitted_at,
                reviewed_by, reviewed_at, review_note, selected_equipment_json,
                created_by, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['organization_id'],
                $data['project_id'],
                $data['task_id'] ?? null,
                $data['title'],
                $data['contact_name'],
                $data['starts_at'],
                $data['ends_at'],
                $data['status'],
                $data['location'],
                $data['notes'],
                $data['checklist_company'] ?? null,
                $data['checklist_date'] ?? null,
                $data['equipment_json'] ?? null,
                $data['rentals_json'] ?? null,
                $data['represented_name'] ?? null,
                $data['represented_signed'] ?? 0,
                $data['checklist_status'] ?? 'draft',
                $data['submitted_to_user_id'] ?? null,
                $data['submitted_at'] ?? null,
                $data['reviewed_by'] ?? null,
                $data['reviewed_at'] ?? null,
                $data['review_note'] ?? null,
                $data['selected_equipment_json'] ?? null,
                $data['created_by'],
                $data['created_at'],
                $data['updated_at'],
            ]
        );

        return (int) Database::lastInsertId();
    }

    public static function updateById(int $id, int $organizationId, array $data): void
    {
        $fields = [];
        $params = [];
        foreach ($data as $column => $value) {
            $fields[] = $column . ' = ?';
            $params[] = $value;
        }
        $params[] = $id;
        $params[] = $organizationId;
        Database::query(
            'UPDATE bookings SET ' . implode(', ', $fields) . ' WHERE id = ? AND organization_id = ?',
            $params
        );
    }

    public static function deleteInOrganization(int $id, int $organizationId): void
    {
        Database::query('DELETE FROM bookings WHERE id = ? AND organization_id = ?', [$id, $organizationId]);
    }

    /** @return array{0:list<string>,1:list<mixed>} */
    private static function filters(int $organizationId, ?int $projectId, ?string $status, ?int $onlyUserId): array
    {
        $where = ['b.organization_id = ?'];
        $params = [$organizationId];
        if ($projectId !== null && $projectId > 0) {
            $where[] = 'b.project_id = ?';
            $params[] = $projectId;
        }
        if ($status !== null && $status !== '') {
            $where[] = 'b.status = ?';
            $params[] = $status;
        }
        if ($onlyUserId !== null && $onlyUserId > 0) {
            $where[] = '(p.manager_user_id = ? OR EXISTS (
                SELECT 1 FROM project_members pm
                WHERE pm.project_id = b.project_id AND pm.user_id = ? AND pm.organization_id = b.organization_id
            ))';
            $params[] = $onlyUserId;
            $params[] = $onlyUserId;
        }

        return [$where, $params];
    }

    private static function selectSql(): string
    {
        return 'SELECT b.*, p.name AS project_name, c.name AS client_name, t.title AS task_title,
                su.name AS submitted_to_name, ru.name AS reviewed_by_name
            FROM bookings b
            INNER JOIN projects p ON p.id = b.project_id
            LEFT JOIN clients c ON c.id = p.client_id
            LEFT JOIN tasks t ON t.id = b.task_id
            LEFT JOIN users su ON su.id = b.submitted_to_user_id
            LEFT JOIN users ru ON ru.id = b.reviewed_by';
    }
}
