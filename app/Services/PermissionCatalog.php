<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Keeps permission definitions + default role grants in sync for Roles UI.
 * Prefer this over calling every Phase*Setup from controllers.
 */
final class PermissionCatalog
{
    public static function sync(bool $withSchema = true): void
    {
        AuthorizationService::applySchema();

        if ($withSchema) {
            Phase12Setup::applySchema();
            Phase13Setup::applySchema();
            Phase14Setup::applySchema();
        }

        Phase2Setup::seedPermissions();
        Phase9Setup::seedPermissions();
        Phase10Setup::seedPermissions();
        Phase13Setup::seedPermissions();
        Phase14Setup::seedPermissions();
    }
}
