<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ContentGrid;
use App\Models\ContentGridDay;

final class ContentGridService
{
    /** @var list<string> */
    public const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    /** @var list<array{key:string,label:string,icon:string}> */
    public const PLATFORMS = [
        ['key' => 'facebook', 'label' => 'Facebook', 'icon' => 'facebook'],
        ['key' => 'instagram', 'label' => 'Instagram', 'icon' => 'instagram'],
        ['key' => 'tiktok', 'label' => 'TikTok', 'icon' => 'tiktok'],
        ['key' => 'whatsapp', 'label' => 'WhatsApp', 'icon' => 'whatsapp'],
        ['key' => 'telegram', 'label' => 'Telegram', 'icon' => 'telegram'],
        ['key' => 'linkedin', 'label' => 'LinkedIn', 'icon' => 'linkedin'],
        ['key' => 'x', 'label' => 'X', 'icon' => 'twitter-x'],
    ];

    /** @var list<string> */
    public const POST_TYPES = ['Reel', 'Graphics', 'Story', 'Carousel', 'No Post'];

    /** Social Media Manager, ops/GM, and other assign/manage roles. */
    public function canView(): bool
    {
        return Auth::can('work_flow.assign') || Auth::can('work_flow.manage');
    }

    /** Create and edit monthly grids. */
    public function canManage(): bool
    {
        return $this->canView();
    }

    /** Remove grids — managers only. */
    public function canDelete(): bool
    {
        return Auth::can('work_flow.manage');
    }

    public function create(int $organizationId, array $input, int $actorId): ContentGrid
    {
        $clientId = (int) ($input['client_id'] ?? 0);
        $client = Client::findInOrganization($clientId, $organizationId);
        if (!$client instanceof Client) {
            throw new \RuntimeException('Select a company (client) for this content grid.');
        }

        $start = trim((string) ($input['start_date'] ?? ''));
        $end = trim((string) ($input['end_date'] ?? ''));
        if ($start === '' || $end === '') {
            throw new \RuntimeException('Start date and end date are required.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            throw new \RuntimeException('Use a valid start and end date.');
        }
        if ($end < $start) {
            throw new \RuntimeException('End date must be on or after the start date.');
        }

        $notes = trim((string) ($input['notes'] ?? ''));
        if (strlen($notes) > 255) {
            throw new \RuntimeException('Notes may not be longer than 255 characters.');
        }

        $now = date('Y-m-d H:i:s');
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $id = ContentGrid::create([
                'organization_id' => $organizationId,
                'client_id' => $clientId,
                'start_date' => $start,
                'end_date' => $end,
                'notes' => $notes !== '' ? $notes : null,
                'created_by' => $actorId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (range(1, 4) as $week) {
                foreach (range(1, 7) as $day) {
                    [$postType, $postTime] = self::defaultForDay($day);
                    ContentGridDay::create([
                        'content_grid_id' => $id,
                        'week_number' => $week,
                        'day_of_week' => $day,
                        'post_type' => $postType,
                        'post_time' => $postTime,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        ActivityLog::record(
            $organizationId,
            $actorId,
            'content_grid.created',
            'Created monthly content grid for "' . $client->name . '".',
            'content_grid',
            $id
        );

        $grid = ContentGrid::findInOrganization($id, $organizationId);
        if (!$grid instanceof ContentGrid) {
            throw new \RuntimeException('The content grid could not be saved.');
        }

        return $grid;
    }

    public function update(ContentGrid $grid, array $input, int $actorId): void
    {
        $start = trim((string) ($input['start_date'] ?? $grid->start_date));
        $end = trim((string) ($input['end_date'] ?? $grid->end_date));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            throw new \RuntimeException('Use a valid start and end date.');
        }
        if ($end < $start) {
            throw new \RuntimeException('End date must be on or after the start date.');
        }

        $notes = trim((string) ($input['notes'] ?? ''));
        if (strlen($notes) > 255) {
            throw new \RuntimeException('Notes may not be longer than 255 characters.');
        }

        $now = date('Y-m-d H:i:s');
        ContentGrid::updateById((int) $grid->id, (int) $grid->organization_id, [
            'start_date' => $start,
            'end_date' => $end,
            'notes' => $notes !== '' ? $notes : null,
            'updated_at' => $now,
        ]);

        $days = $input['days'] ?? [];
        if (!is_array($days)) {
            throw new \RuntimeException('Invalid day payload.');
        }

        foreach ($days as $dayId => $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) $dayId;
            if ($id < 1) {
                continue;
            }

            $postType = trim((string) ($row['post_type'] ?? 'Reel'));
            if ($postType === '' || strlen($postType) > 80) {
                throw new \RuntimeException('Each day needs a post type up to 80 characters.');
            }
            $postTime = trim((string) ($row['post_time'] ?? '10:00 AM'));
            if ($postTime === '' || strlen($postTime) > 40) {
                throw new \RuntimeException('Each day needs a time up to 40 characters.');
            }

            $data = [
                'post_type' => $postType,
                'post_time' => $postTime,
                'is_edited' => !empty($row['is_edited']) ? 1 : 0,
                'is_posted' => !empty($row['is_posted']) ? 1 : 0,
                'updated_at' => $now,
            ];
            foreach (self::PLATFORMS as $platform) {
                $column = 'platform_' . $platform['key'];
                $data[$column] = !empty($row[$column]) ? 1 : 0;
            }

            ContentGridDay::updateById($id, (int) $grid->id, $data);
        }

        ActivityLog::record(
            (int) $grid->organization_id,
            $actorId,
            'content_grid.updated',
            'Updated monthly content grid for "' . ($grid->client_name ?: 'company') . '".',
            'content_grid',
            (int) $grid->id
        );
    }

    public function delete(ContentGrid $grid, int $actorId): void
    {
        ContentGrid::deleteInOrganization((int) $grid->id, (int) $grid->organization_id);
        ActivityLog::record(
            (int) $grid->organization_id,
            $actorId,
            'content_grid.deleted',
            'Removed monthly content grid for "' . ($grid->client_name ?: 'company') . '".',
            'content_grid',
            (int) $grid->id
        );
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function defaultForDay(int $dayOfWeek): array
    {
        return match ($dayOfWeek) {
            1, 3, 5 => ['Reel', '10:00 AM'],
            2, 4, 6 => ['Graphics', '10:00 AM'],
            default => ['No Post', 'Rest'],
        };
    }

    public static function dayName(int $dayOfWeek): string
    {
        return self::DAYS[$dayOfWeek - 1] ?? 'Day';
    }

    /**
     * @param list<ContentGridDay> $days
     * @return array<int, list<ContentGridDay>>
     */
    public static function groupByWeek(array $days): array
    {
        $weeks = [1 => [], 2 => [], 3 => [], 4 => []];
        foreach ($days as $day) {
            $week = (int) $day->week_number;
            if (!isset($weeks[$week])) {
                $weeks[$week] = [];
            }
            $weeks[$week][] = $day;
        }

        return $weeks;
    }
}
