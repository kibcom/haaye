<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Approval;

final class WorkflowPending extends \RuntimeException
{
    public Approval $approval;

    public function __construct(Approval $approval)
    {
        $this->approval = $approval;
        parent::__construct('This status change is waiting for ' . ($approval->approval_kind === 'client' ? 'client' : 'internal') . ' approval.');
    }
}
