<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Booking;
use App\Models\InventoryItem;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkFlowRole;
use App\Services\BookingChecklist;
use App\Services\BookingService;
use App\Services\Phase12Setup;
use App\Services\Phase13Setup;
use App\Services\ProjectAccess;

final class BookingController extends Controller
{
    public function index(): void
    {
        $this->authorize('bookings.view');
        Phase12Setup::applySchema();
        Phase13Setup::applySchema();
        Phase13Setup::seedPermissions();
        $orgId = $this->tenantId();
        $status = (string) Request::input('status', '');
        if (!in_array($status, BookingService::STATUSES, true)) {
            $status = '';
        }
        $projectId = (int) Request::input('project', 0);

        $this->view('bookings/index', [
            'title' => 'Bookings',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Bookings'],
            ],
            'bookings' => Booking::listFor(
                $orgId,
                $projectId > 0 ? $projectId : null,
                $status !== '' ? $status : null,
                ProjectAccess::restrictToUserId()
            ),
            'projects' => Project::optionsVisible($orgId, ProjectAccess::restrictToUserId()),
            'status' => $status,
            'projectId' => $projectId,
        ]);
    }

    public function create(): void
    {
        $this->authorize('bookings.create');
        $this->form(null);
    }

    public function store(): void
    {
        $this->authorize('bookings.create');
        $data = $this->formData();
        if (!$this->validateBooking($data, 'bookings/create')) {
            return;
        }

        try {
            $booking = (new BookingService())->create(
                $this->tenantId(),
                $data,
                (int) $this->actor()->id,
                ProjectAccess::restrictToUserId()
            );
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->withErrors([]);
            $this->redirect('bookings/create');
        }

        $this->clearOld();
        flash('success', 'Booking has been added.');
        $this->redirect('bookings/' . $booking->id);
    }

    public function show(string $id): void
    {
        $booking = $this->visible($id);
        Phase13Setup::applySchema();
        $selected = BookingChecklist::selectedFromBooking($booking);
        $ownedMap = [];
        foreach (InventoryItem::listFor($this->tenantId(), 'owned', false) as $item) {
            $ownedMap[(int) $item->id] = $item;
        }
        $rentalMap = [];
        foreach (InventoryItem::listFor($this->tenantId(), 'rental', false) as $item) {
            $rentalMap[(int) $item->id] = $item;
        }
        $this->view('bookings/show', [
            'title' => $booking->title,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Bookings', 'url' => 'bookings'],
                ['label' => $booking->title],
            ],
            'booking' => $booking,
            'canEdit' => Auth::can('bookings.update'),
            'canSubmit' => Auth::can('bookings.submit') || Auth::can('bookings.create') || Auth::can('bookings.update'),
            'canApprove' => Auth::can('bookings.approve') || (int) ($booking->submitted_to_user_id ?? 0) === (int) Auth::id(),
            'selectedEquipment' => $selected,
            'ownedMap' => $ownedMap,
            'rentalMap' => $rentalMap,
            'approvers' => array_values(array_filter(
                User::listAssignable($this->tenantId()),
                static fn (User $user): bool => in_array((string) $user->role_slug, [
                    'general_manager', 'operations_manager', 'owner', 'admin', 'manager',
                ], true)
            )),
        ]);
    }

    public function edit(string $id): void
    {
        $this->authorize('bookings.update');
        $this->form($this->visible($id));
    }

    public function update(string $id): void
    {
        $this->authorize('bookings.update');
        $booking = $this->visible($id);
        $data = $this->formData();
        if (!$this->validateBooking($data, 'bookings/' . $booking->id . '/edit')) {
            return;
        }

        try {
            (new BookingService())->update($booking, $data, (int) $this->actor()->id, ProjectAccess::restrictToUserId());
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('bookings/' . $booking->id . '/edit');
        }

        $this->clearOld();
        flash('success', 'Booking has been updated.');
        $this->redirect('bookings/' . $booking->id);
    }

    public function status(string $id): void
    {
        $this->authorize('bookings.update');
        $booking = $this->visible($id);
        try {
            (new BookingService())->setStatus($booking, (string) Request::input('status', ''), (int) $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('bookings/' . $booking->id);
        }

        flash('success', 'Booking status has been updated.');
        $this->redirect('bookings/' . $booking->id);
    }

    public function destroy(string $id): void
    {
        $this->authorize('bookings.update');
        $booking = $this->visible($id);
        (new BookingService())->delete($booking, (int) $this->actor()->id);
        flash('success', 'Booking has been removed.');
        $this->redirect('bookings');
    }

    public function submit(string $id): void
    {
        if (!Auth::can('bookings.submit') && !Auth::can('bookings.create') && !Auth::can('bookings.update')) {
            Response::forbidden();
        }
        $booking = $this->visible($id);
        try {
            (new BookingService())->submitForApproval(
                $booking,
                (int) Request::input('submit_to_user_id', 0),
                (int) $this->actor()->id
            );
            flash('success', 'Checklist sent for approval.');
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
        }
        $this->redirect('bookings/' . $booking->id);
    }

    public function review(string $id): void
    {
        $booking = $this->visible($id);
        $canApprove = Auth::can('bookings.approve')
            || (int) ($booking->submitted_to_user_id ?? 0) === (int) Auth::id();
        if (!$canApprove) {
            Response::forbidden();
        }
        try {
            (new BookingService())->review(
                $booking,
                (string) Request::input('decision', ''),
                (string) Request::input('review_note', ''),
                (int) $this->actor()->id
            );
            flash('success', 'Checklist review saved.');
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
        }
        $this->redirect('bookings/' . $booking->id);
    }

    private function form(?Booking $booking): void
    {
        Phase12Setup::applySchema();
        Phase13Setup::applySchema();
        Phase13Setup::seedPermissions();
        $orgId = $this->tenantId();
        $projectId = $booking instanceof Booking ? (int) $booking->project_id : (int) Request::input('project', 0);
        $people = User::listAssignable($orgId);
        $approvers = array_values(array_filter(
            $people,
            static fn (User $user): bool => in_array((string) $user->role_slug, [
                'general_manager', 'operations_manager', 'owner', 'admin', 'manager',
            ], true)
        ));
        $this->view('bookings/form', [
            'title' => $booking instanceof Booking ? 'Edit booking' : 'New booking',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Bookings', 'url' => 'bookings'],
                ['label' => $booking instanceof Booking ? 'Edit' : 'New booking'],
            ],
            'booking' => $booking,
            'projects' => Project::optionsVisible($orgId, ProjectAccess::restrictToUserId()),
            'projectId' => $projectId,
            'checklist' => BookingChecklist::fromBooking($booking),
            'selectedEquipment' => BookingChecklist::selectedFromBooking($booking),
            'ownedItems' => InventoryItem::listFor($orgId, 'owned'),
            'rentalItems' => InventoryItem::listFor($orgId, 'rental'),
            'workflowRoles' => WorkFlowRole::listFor($orgId, true),
            'people' => $people,
            'approvers' => $approvers,
        ]);
    }

    private function formData(): array
    {
        return Request::all();
    }

    private function validateBooking(array $data, string $back): bool
    {
        $validator = new Validator($data);
        if ($validator->validate([
            'project_id' => 'required',
            'title' => 'required|max:180',
            'contact_name' => 'max:160',
            'starts_at' => 'required',
            'ends_at' => 'required',
            'status' => 'required|in:' . implode(',', BookingService::STATUSES),
            'location' => 'max:180',
            'notes' => 'max:4000',
        ])) {
            return true;
        }

        $this->withErrors($validator->errors());
        $this->redirect($back);

        return false;
    }

    private function visible(string $id): Booking
    {
        $this->authorize('bookings.view');
        Phase12Setup::applySchema();
        if (!ctype_digit($id)) {
            Response::notFound();
        }
        $booking = Booking::findInOrganization((int) $id, $this->tenantId());
        if (!$booking instanceof Booking) {
            Response::notFound();
        }
        $project = Project::findInOrganization((int) $booking->project_id, $this->tenantId());
        if (!$project instanceof Project || !ProjectAccess::canView($project)) {
            Response::forbidden();
        }

        return $booking;
    }
}
