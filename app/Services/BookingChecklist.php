<?php

declare(strict_types=1);

namespace App\Services;

final class BookingChecklist
{
    /** @var array<string, string> */
    public const EQUIPMENT = [
        'camera' => 'Camera',
        'tripod' => 'Tripod',
        'changed_batteries' => 'Changed Batteries',
        'memory_cards' => 'Memory Cards',
        'extra_memory_card' => 'Extra Memory Card',
        'gimbal' => 'Gimbal',
        'stands' => 'Stands',
        'reflector' => 'Reflector',
        'batteries' => 'Batteries',
        'lights' => 'Lights',
        'vest_uniform' => 'Vest/Uniform',
        'lens_filters' => 'Lens Filters',
        'soft_box' => 'Soft Box',
        'laptop' => 'Laptop',
        'roll_cable' => 'Roll Cable',
        'all_chargers' => 'All Chargers',
        'adapters' => 'Adapters',
        'microphone' => 'Microphone',
    ];

    /**
     * @return array{company:string,date:?string,equipment:array<string,array{checked:bool,note:string}>,custom:list<array{label:string,checked:bool}>,rentals:list<array{rent_from:string,equipments:string}>,represented_name:string,represented_signed:bool}
     */
    public static function defaults(?string $company = null, ?string $date = null): array
    {
        $equipment = [];
        foreach (self::EQUIPMENT as $key => $label) {
            $equipment[$key] = ['checked' => false, 'note' => '', 'label' => $label];
        }

        return [
            'company' => $company ?? '',
            'date' => $date ?? date('Y-m-d'),
            'equipment' => $equipment,
            'custom' => [
                ['label' => '', 'checked' => false],
                ['label' => '', 'checked' => false],
                ['label' => '', 'checked' => false],
            ],
            'rentals' => [
                ['rent_from' => '', 'equipments' => ''],
                ['rent_from' => '', 'equipments' => ''],
                ['rent_from' => '', 'equipments' => ''],
            ],
            'represented_name' => '',
            'represented_signed' => false,
        ];
    }

    /**
     * @return array{company:string,date:?string,equipment:array<string,array{checked:bool,note:string,label?:string}>,custom:list<array{label:string,checked:bool}>,rentals:list<array{rent_from:string,equipments:string}>,represented_name:string,represented_signed:bool}
     */
    public static function fromBooking(?\App\Models\Booking $booking): array
    {
        $base = self::defaults(
            $booking?->checklist_company ?: ($booking?->client_name ?? ''),
            $booking?->checklist_date ?: ($booking ? substr((string) $booking->starts_at, 0, 10) : date('Y-m-d'))
        );
        if (!$booking instanceof \App\Models\Booking) {
            return $base;
        }

        $equipment = json_decode((string) ($booking->equipment_json ?? ''), true);
        if (is_array($equipment)) {
            foreach (self::EQUIPMENT as $key => $label) {
                $row = $equipment[$key] ?? [];
                $base['equipment'][$key] = [
                    'checked' => !empty($row['checked']),
                    'note' => (string) ($row['note'] ?? ''),
                    'label' => $label,
                ];
            }
            if (!empty($equipment['_custom']) && is_array($equipment['_custom'])) {
                $base['custom'] = [];
                foreach ($equipment['_custom'] as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $base['custom'][] = [
                        'label' => (string) ($item['label'] ?? ''),
                        'checked' => !empty($item['checked']),
                    ];
                }
                while (count($base['custom']) < 3) {
                    $base['custom'][] = ['label' => '', 'checked' => false];
                }
            }
        }

        $rentals = json_decode((string) ($booking->rentals_json ?? ''), true);
        if (is_array($rentals) && $rentals !== []) {
            $base['rentals'] = [];
            foreach ($rentals as $rental) {
                if (!is_array($rental)) {
                    continue;
                }
                $base['rentals'][] = [
                    'rent_from' => (string) ($rental['rent_from'] ?? ''),
                    'equipments' => (string) ($rental['equipments'] ?? ''),
                ];
            }
            while (count($base['rentals']) < 3) {
                $base['rentals'][] = ['rent_from' => '', 'equipments' => ''];
            }
        }

        $base['company'] = (string) ($booking->checklist_company ?: $booking->client_name ?: '');
        $base['date'] = $booking->checklist_date ?: substr((string) $booking->starts_at, 0, 10);
        $base['represented_name'] = (string) ($booking->represented_name ?? '');
        $base['represented_signed'] = (int) ($booking->represented_signed ?? 0) === 1;

        return $base;
    }

    /**
     * @return array{checklist_company:?string,checklist_date:?string,equipment_json:string,rentals_json:string,selected_equipment_json:string,represented_name:?string,represented_signed:int,create_linked_task:bool,work_flow_role_id:int,assignee_user_id:int,submit_to_user_id:int,submit_now:bool}
     */
    public static function parseInput(array $input): array
    {
        $company = trim((string) ($input['checklist_company'] ?? ''));
        $date = trim((string) ($input['checklist_date'] ?? ''));
        if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new \RuntimeException('Use a valid checklist date.');
        }

        $ownedIds = [];
        $ownedIn = $input['owned_item_ids'] ?? [];
        if (is_array($ownedIn)) {
            foreach ($ownedIn as $id) {
                if (is_numeric($id) && (int) $id > 0) {
                    $ownedIds[] = (int) $id;
                }
            }
        }
        $ownedIds = array_values(array_unique($ownedIds));

        $rentals = [];
        $rentalsIn = $input['rentals'] ?? [];
        if (is_array($rentalsIn)) {
            foreach ($rentalsIn as $rental) {
                if (!is_array($rental)) {
                    continue;
                }
                $itemId = (int) ($rental['item_id'] ?? 0);
                $from = trim((string) ($rental['rent_from'] ?? ''));
                $eq = trim((string) ($rental['equipments'] ?? ''));
                if ($itemId < 1 && $from === '' && $eq === '') {
                    continue;
                }
                $rentals[] = [
                    'item_id' => $itemId > 0 ? $itemId : null,
                    'rent_from' => $from,
                    'equipments' => $eq,
                ];
            }
        }

        $selected = [
            'owned_ids' => $ownedIds,
            'rentals' => $rentals,
        ];

        // Keep legacy equipment_json compatible for show page: mark selected owned names.
        $equipment = [];
        foreach (self::EQUIPMENT as $key => $label) {
            $equipment[$key] = ['checked' => false, 'note' => '', 'label' => $label];
        }
        $equipment['_custom'] = [];
        $equipment['_owned_ids'] = $ownedIds;

        $represented = trim((string) ($input['represented_name'] ?? ''));

        return [
            'checklist_company' => $company !== '' ? $company : null,
            'checklist_date' => $date !== '' ? $date : null,
            'equipment_json' => json_encode($equipment, JSON_UNESCAPED_UNICODE),
            'rentals_json' => json_encode($rentals, JSON_UNESCAPED_UNICODE),
            'selected_equipment_json' => json_encode($selected, JSON_UNESCAPED_UNICODE),
            'represented_name' => $represented !== '' ? $represented : null,
            'represented_signed' => !empty($input['represented_signed']) ? 1 : 0,
            'create_linked_task' => !empty($input['create_linked_task']),
            'work_flow_role_id' => (int) ($input['work_flow_role_id'] ?? 0),
            'assignee_user_id' => (int) ($input['assignee_user_id'] ?? 0),
            'submit_to_user_id' => (int) ($input['submit_to_user_id'] ?? 0),
            'submit_now' => !empty($input['submit_now']),
        ];
    }

    /**
     * @return array{owned_ids:list<int>,rentals:list<array{item_id:?int,rent_from:string,equipments:string}>}
     */
    public static function selectedFromBooking(?\App\Models\Booking $booking): array
    {
        $empty = ['owned_ids' => [], 'rentals' => [['item_id' => null, 'rent_from' => '', 'equipments' => '']]];
        if (!$booking instanceof \App\Models\Booking) {
            return $empty;
        }
        $decoded = json_decode((string) ($booking->selected_equipment_json ?? ''), true);
        if (!is_array($decoded)) {
            $rentals = json_decode((string) ($booking->rentals_json ?? ''), true);
            return [
                'owned_ids' => [],
                'rentals' => is_array($rentals) && $rentals !== [] ? $rentals : $empty['rentals'],
            ];
        }
        $owned = [];
        foreach (($decoded['owned_ids'] ?? []) as $id) {
            if (is_numeric($id) && (int) $id > 0) {
                $owned[] = (int) $id;
            }
        }
        $rentals = [];
        foreach (($decoded['rentals'] ?? []) as $rental) {
            if (!is_array($rental)) {
                continue;
            }
            $rentals[] = [
                'item_id' => isset($rental['item_id']) && (int) $rental['item_id'] > 0 ? (int) $rental['item_id'] : null,
                'rent_from' => (string) ($rental['rent_from'] ?? ''),
                'equipments' => (string) ($rental['equipments'] ?? ''),
            ];
        }
        if ($rentals === []) {
            $rentals = $empty['rentals'];
        }

        return ['owned_ids' => $owned, 'rentals' => $rentals];
    }
}
