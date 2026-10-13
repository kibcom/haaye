<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Models\Client;
use App\Models\Lead;

/**
 * Sales / CRM visibility: own interacted accounts vs company-wide.
 * Toggle via leads.view_all / clients.view_all on Roles.
 */
final class CrmAccess
{
    public static function seesAllLeads(): bool
    {
        return Auth::can('leads.view_all');
    }

    public static function seesAllClients(): bool
    {
        return Auth::can('clients.view_all');
    }

    public static function restrictLeadOwnerId(): ?int
    {
        if (self::seesAllLeads()) {
            return null;
        }

        return Auth::id();
    }

    public static function restrictClientAssigneeId(): ?int
    {
        if (self::seesAllClients()) {
            return null;
        }

        return Auth::id();
    }

    public static function canViewLead(Lead $lead): bool
    {
        if (!Auth::can('leads.view')) {
            return false;
        }
        if ((int) $lead->organization_id !== (int) Auth::organizationId()) {
            return false;
        }
        if (self::seesAllLeads()) {
            return true;
        }

        return (int) ($lead->owner_user_id ?? 0) === (int) Auth::id();
    }

    public static function canViewClient(Client $client): bool
    {
        if (!Auth::can('clients.view')) {
            return false;
        }
        if ((int) $client->organization_id !== (int) Auth::organizationId()) {
            return false;
        }
        if (self::seesAllClients()) {
            return true;
        }

        return (int) ($client->assigned_user_id ?? 0) === (int) Auth::id();
    }
}
