<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class Phase11Setup
{
    public static function applySchema(): void
    {
        $sql = file_get_contents(BASE_PATH . '/database/migrations/011_content_grids.sql');
        if ($sql === false) {
            throw new \RuntimeException('Content grid migration file is missing.');
        }

        $pdo = Database::connection();
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }
    }

    public static function seedPermissions(): void
    {
        // Content grids reuse work_flow.* permissions seeded in Phase 10.
        Phase10Setup::seedPermissions();
    }
}
