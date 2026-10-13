<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\InventoryService;
use App\Services\Phase13Setup;

final class InventoryController extends Controller
{
    public function store(): void
    {
        Phase13Setup::applySchema();
        if (!\App\Core\Auth::can('inventory.manage') && !\App\Core\Auth::can('bookings.create')) {
            Response::forbidden();
        }

        $wantsJson = str_contains((string) Request::header('Accept'), 'application/json')
            || (string) Request::input('ajax', '') === '1';

        try {
            $item = (new InventoryService())->create($this->tenantId(), Request::all(), (int) $this->actor()->id);
        } catch (\RuntimeException $e) {
            if ($wantsJson) {
                Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
            }
            flash('danger', $e->getMessage());
            $this->redirect((string) Request::input('redirect', 'bookings/create'));
        }

        if ($wantsJson) {
            Response::json([
                'ok' => true,
                'item' => [
                    'id' => (int) $item->id,
                    'name' => $item->name,
                    'ownership' => $item->ownership,
                    'vendor_name' => $item->vendor_name,
                ],
            ]);
        }

        flash('success', 'Inventory item added.');
        $this->redirect((string) Request::input('redirect', 'bookings/create'));
    }
}
