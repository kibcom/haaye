<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ContentGridDay extends Model
{
    public ?int $id = null;
    public int $content_grid_id = 0;
    public int $week_number = 1;
    public int $day_of_week = 1;
    public string $post_type = 'Reel';
    public string $post_time = '10:00 AM';
    public int $platform_facebook = 0;
    public int $platform_instagram = 0;
    public int $platform_tiktok = 0;
    public int $platform_whatsapp = 0;
    public int $platform_telegram = 0;
    public int $platform_linkedin = 0;
    public int $platform_x = 0;
    public int $is_edited = 0;
    public int $is_posted = 0;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function table(): string
    {
        return 'content_grid_days';
    }

    /**
     * @return list<self>
     */
    public static function listForGrid(int $contentGridId): array
    {
        $rows = Database::query(
            'SELECT * FROM content_grid_days
             WHERE content_grid_id = ?
             ORDER BY week_number ASC, day_of_week ASC',
            [$contentGridId]
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function create(array $data): int
    {
        Database::query(
            'INSERT INTO content_grid_days (
                content_grid_id, week_number, day_of_week, post_type, post_time,
                platform_facebook, platform_instagram, platform_tiktok, platform_whatsapp,
                platform_telegram, platform_linkedin, platform_x,
                is_edited, is_posted, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['content_grid_id'],
                $data['week_number'],
                $data['day_of_week'],
                $data['post_type'],
                $data['post_time'],
                $data['platform_facebook'] ?? 0,
                $data['platform_instagram'] ?? 0,
                $data['platform_tiktok'] ?? 0,
                $data['platform_whatsapp'] ?? 0,
                $data['platform_telegram'] ?? 0,
                $data['platform_linkedin'] ?? 0,
                $data['platform_x'] ?? 0,
                $data['is_edited'] ?? 0,
                $data['is_posted'] ?? 0,
                $data['created_at'],
                $data['updated_at'],
            ]
        );

        return (int) Database::lastInsertId();
    }

    public static function updateById(int $id, int $contentGridId, array $data): void
    {
        $allowed = [
            'post_type', 'post_time',
            'platform_facebook', 'platform_instagram', 'platform_tiktok', 'platform_whatsapp',
            'platform_telegram', 'platform_linkedin', 'platform_x',
            'is_edited', 'is_posted', 'updated_at',
        ];
        $sets = [];
        $params = [];
        foreach ($allowed as $column) {
            if (!array_key_exists($column, $data)) {
                continue;
            }
            $sets[] = $column . ' = ?';
            $params[] = $data[$column];
        }
        if ($sets === []) {
            return;
        }
        $params[] = $id;
        $params[] = $contentGridId;
        Database::query(
            'UPDATE content_grid_days SET ' . implode(', ', $sets) . ' WHERE id = ? AND content_grid_id = ?',
            $params
        );
    }
}
