<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\InventoryItem;

final class InventoryService
{
    public function create(int $organizationId, array $input, int $actorId): InventoryItem
    {
        Phase13Setup::applySchema();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || strlen($name) > 160) {
            throw new \RuntimeException('Enter an item name up to 160 characters.');
        }
        $ownership = (string) ($input['ownership'] ?? 'owned');
        if (!in_array($ownership, ['owned', 'rental'], true)) {
            throw new \RuntimeException('Choose owned or rental.');
        }
        $vendor = trim((string) ($input['vendor_name'] ?? ''));
        if ($ownership === 'rental' && $vendor === '') {
            throw new \RuntimeException('Enter who this rental is from.');
        }

        $now = date('Y-m-d H:i:s');
        $id = InventoryItem::create([
            'organization_id' => $organizationId,
            'name' => $name,
            'ownership' => $ownership,
            'vendor_name' => $ownership === 'rental' ? $vendor : null,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        ActivityLog::record($organizationId, $actorId, 'inventory.created', 'Added inventory item "' . $name . '".', 'inventory_item', $id);
        $item = InventoryItem::findInOrganization($id, $organizationId);
        if (!$item instanceof InventoryItem) {
            throw new \RuntimeException('Inventory item could not be saved.');
        }

        return $item;
    }
}
