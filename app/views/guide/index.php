<?php
$platform = !empty($platform);
$go = static function (string $path, string $label) use ($platform): string {
    $platformPath = str_starts_with($path, 'platform');
    if (($platform && !$platformPath) || (!$platform && $platformPath)) {
        return $label === 'Open' ? '' : e($label);
    }

    return '<a href="' . e(base_url($path)) . '">' . e($label) . '</a>';
};
$featureOn = $featureOn ?? null;
$guideOrganization = $guideOrganization ?? null;
$organizations = $organizations ?? [];
$on = static function (?string $feature) use ($featureOn): bool {
    if ($feature === null || $feature === '') {
        return true;
    }
    if ($featureOn === null) {
        return true;
    }

    return in_array($feature, $featureOn, true);
};
$featureLabels = [];
foreach (\App\Services\FeatureService::catalog() as $slug => $feature) {
    if ($on($slug)) {
        $featureLabels[] = $feature['label'];
    }
}
?>
<?php \App\Core\View::component('settings-nav', []); ?>
<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Settings</p>
        <h2>How it works</h2>
        <p class="text-muted mb-0">
            <?php if ($guideOrganization): ?>
                Showing the functions turned on for <?= e($guideOrganization->name) ?><?= $guideOrganization->company_code ? ' · ' . e($guideOrganization->company_code) : '' ?>.
            <?php else: ?>
                Every role, the records they share, and the order work moves through <?= e(app_name()) ?>.
            <?php endif; ?>
        </p>
    </div>
    <?php if ($platform && $organizations !== []): ?>
        <form method="get" action="<?= e(base_url('platform/guide')) ?>" class="d-flex gap-2 align-items-center">
            <label class="form-label mb-0" for="guide-organization">Company</label>
            <select class="form-select" id="guide-organization" name="organization" onchange="this.form.submit()">
                <?php foreach ($organizations as $row): ?>
                    <option value="<?= (int) $row['id'] ?>" <?= selected($guideOrganization->id ?? 0, $row['id']) ?>><?= e($row['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    <?php endif; ?>
</div>
<?php if ($guideOrganization): ?>
    <p class="text-muted small">On: <?= $featureLabels === [] ? 'no functions yet' : e(implode(', ', $featureLabels)) ?>. <?php if ($platform): ?><a href="<?= e(base_url('platform/organizations/' . $guideOrganization->id . '/edit')) ?>">Change these switches</a><?php endif; ?></p>
<?php endif; ?>

<nav class="wf-jump" aria-label="Workflow sections">
    <a href="#signin">Sign in</a>
    <a href="#platform-flow">Platform</a>
    <a href="#setup">Owner setup</a>
    <a href="#relationships">Relationships</a>
    <?php if ($on('clients') || $on('projects') || $on('tasks') || $on('time') || $on('deliverables') || $on('portal')): ?><a href="#delivery">Delivery</a><?php endif; ?>
    <?php if ($on('tasks')): ?><a href="#tasks">Tasks</a><?php endif; ?>
    <?php if ($on('approvals')): ?><a href="#approvals">Approvals</a><?php endif; ?>
    <?php if ($on('time')): ?><a href="#time">Time</a><?php endif; ?>
    <?php if ($on('finance') || $on('invoices') || $on('expenses')): ?><a href="#finance">Finance</a><?php endif; ?>
    <?php if ($on('portal')): ?><a href="#portal">Client portal</a><?php endif; ?>
    <a href="#roles">Roles</a>
    <a href="#process-chart">Process chart</a>
    <?php if ($platform): ?><a href="#diagram">Full diagram</a><?php endif; ?>
</nav>

<section class="wf-card mb-3" id="signin">
    <div class="wf-card-head"><h3>Two kinds of sign-in</h3></div>
    <div class="row g-3">
        <div class="col-md-6">
            <div class="wf-lane">
                <strong><?= icon('buildings') ?> Platform owner</strong>
                <p class="mb-0 text-muted">Signs in and lands on the platform console. This account has no organization. It creates organizations and other platform administrators. It cannot open a company’s clients, projects, or invoices.</p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="wf-lane">
                <strong><?= icon('building') ?> Organization users</strong>
                <p class="mb-0 text-muted">Owner, admin, manager, project manager, team leader, employee, sales, finance, and client all use the same login. The role decides the menu. A client sees only the portal for one company.</p>
            </div>
        </div>
    </div>
</section>

<section class="mb-4" id="platform-flow">
    <h3 class="wf-guide-title">Platform owner</h3>
    <div class="wf-path">
        <article class="wf-path-item">
            <span>1</span>
            <?= icon('box-arrow-in-right') ?>
            <h3>Sign in</h3>
            <p>The console opens. Organization pages stay closed.</p>
        </article>
        <article class="wf-path-item">
            <span>2</span>
            <?= icon('building-add') ?>
            <h3>Create an organization</h3>
            <p><?= $go('platform/organizations/create', 'New organization') ?> sets the name, slug, plan, and status. <?= $go('platform/plans', 'Plans') ?> decide which functions a plan includes. Each organization is a separate tenant.</p>
        </article>
        <article class="wf-path-item">
            <span>3</span>
            <?= icon('person-gear') ?>
            <h3>Add platform users</h3>
            <p><?= $go('platform/users/create', 'Platform users') ?> are other console administrators, not staff inside a company.</p>
        </article>
        <article class="wf-path-item">
            <span>4</span>
            <?= icon('arrow-right') ?>
            <h3>Hand off</h3>
            <p>The organization owner signs in and sets up people, clients, and work. You do not do that work from this console.</p>
        </article>
    </div>
</section>

<section class="mb-4" id="setup">
    <h3 class="wf-guide-title">Organization owner and admin</h3>
    <ol class="wf-steps">
        <li class="wf-step">
            <div class="wf-step-rail"><span class="wf-step-num">1</span><span class="wf-step-line"></span></div>
            <div class="wf-step-body">
                <h3>Confirm the organization</h3>
                <p><?= $go('settings/organization', 'Organization settings') ?> hold the invoice prefix and the next invoice number. Later invoices use that sequence.</p>
            </div>
        </li>
        <li class="wf-step">
            <div class="wf-step-rail"><span class="wf-step-num">2</span><span class="wf-step-line"></span></div>
            <div class="wf-step-body">
                <h3>Check roles</h3>
                <p><?= $go('roles', 'Roles') ?> are fixed. Open one to see what that person can do. You assign a role to a person. You do not invent a new role here.</p>
            </div>
        </li>
        <li class="wf-step">
            <div class="wf-step-rail"><span class="wf-step-num">3</span><span class="wf-step-line"></span></div>
            <div class="wf-step-body">
                <h3>Add people</h3>
                <p><?= $go('users/create', 'New person') ?> creates a login inside this organization. Activate or deactivate an account from the person page. A client login must be linked to one client company.</p>
            </div>
        </li>
        <li class="wf-step">
            <div class="wf-step-rail"><span class="wf-step-num">4</span></div>
            <div class="wf-step-body">
                <h3>Start the work chain</h3>
                <p>Add a client, then a project for that client, then tasks and deliverables. Finance and the portal read those same records. They do not get a second copy.</p>
            </div>
        </li>
    </ol>
</section>

<section class="wf-card mb-4" id="relationships">
    <div class="wf-card-head"><h3>How records connect</h3></div>
    <p class="text-muted">Everything hangs off one organization. A person in another organization cannot see these rows.</p>
    <div class="wf-map">
        <div class="wf-node wf-node-root"><?= icon('building') ?> <div><strong>Organization</strong><span>Tenant boundary</span></div></div>
        <div class="wf-map-branch">
            <div class="wf-node"><?= icon('people') ?> <div><strong>People</strong><span>One role each</span></div></div>
            <?php if ($on('clients')): ?><div class="wf-node"><?= icon('person-vcard') ?> <div><strong>Client</strong><span>Contacts and one portal login</span></div></div><?php endif; ?>
            <?php if ($on('projects')): ?><div class="wf-node"><?= icon('folder') ?> <div><strong>Project</strong><span>Belongs to one client</span></div></div><?php endif; ?>
        </div>
        <div class="wf-map-branch wf-map-branch-sub">
            <?php if ($on('projects')): ?>
                <div class="wf-node"><?= icon('people') ?> <div><strong>Members</strong><span>Who can see the project</span></div></div>
                <div class="wf-node"><?= icon('flag') ?> <div><strong>Milestones</strong><span>Dates and progress</span></div></div>
            <?php endif; ?>
            <?php if ($on('tasks')): ?><div class="wf-node"><?= icon('check2-square') ?> <div><strong>Tasks</strong><span>Comments, files, time</span></div></div><?php endif; ?>
            <?php if ($on('deliverables')): ?><div class="wf-node"><?= icon('box-seam') ?> <div><strong>Deliverables</strong><span>File and approvals</span></div></div><?php endif; ?>
            <?php if ($on('invoices')): ?><div class="wf-node"><?= icon('receipt') ?> <div><strong>Invoices</strong><span>Lines and payments</span></div></div><?php endif; ?>
            <?php if ($on('expenses')): ?><div class="wf-node"><?= icon('cash-stack') ?> <div><strong>Expenses</strong><span>Approved costs only hit profit</span></div></div><?php endif; ?>
        </div>
        <?php if ($on('workflows')): ?><div class="wf-node"><?= icon('diagram-3') ?> <div><strong>Workflows</strong><span>Allowed status moves for tasks and deliverables. The first saved move starts enforcement.</span></div></div><?php endif; ?>
    </div>
</section>

<?php if ($on('clients') || $on('projects') || $on('tasks') || $on('time') || $on('deliverables') || $on('portal')): ?>
<section class="mb-4" id="delivery">
    <h3 class="wf-guide-title">Delivery, from client to portal</h3>
    <div class="wf-path">
        <?php
        $delivery = [
            ['person-vcard', 'Client', 'Company, contacts, and the person who will sign in to the portal.', 'clients/create', 'clients'],
            ['folder', 'Project', 'Tied to that client. Status, dates, members, and milestones live here.', 'projects/create', 'projects'],
            ['check2-square', 'Tasks', 'Assigned work. The assignee updates status. Comments and files stay on the task.', 'tasks/create', 'tasks'],
            ['clock', 'Time', 'Logged on the project, and on a task when there is one.', 'time', 'time'],
            ['box-seam', 'Deliverable', 'The file the client reviews. Status moves can require approval.', 'deliverables/create', 'deliverables'],
            ['bell', 'Portal', 'The client sees this project, the file, the invoice, and a notification.', '', 'portal'],
        ];
        $delivery = array_values(array_filter($delivery, static function (array $step) use ($on): bool {
            return $on($step[4]);
        }));
        foreach ($delivery as $index => $step):
        ?>
            <article class="wf-path-item">
                <span><?= $index + 1 ?></span>
                <?= icon($step[0]) ?>
                <h3><?= e($step[1]) ?></h3>
                <p><?= e($step[2]) ?><?php if ($step[3] !== ''): ?> <?= $go($step[3], 'Open') ?><?php endif; ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($on('tasks')): ?>
<section class="mb-4" id="tasks">
    <h3 class="wf-guide-title">Task workflow</h3>
    <div class="wf-path">
        <article class="wf-path-item"><span>1</span><h3>Create</h3><p>Pick the project, title, assignee, priority, and dates. <?= $go('tasks/create', 'New task') ?></p></article>
        <article class="wf-path-item"><span>2</span><h3>Work</h3><p>Backlog, to do, in progress, review, completed. The board and the circle on the list use the same status update.</p></article>
        <article class="wf-path-item"><span>3</span><h3>Gate</h3><p>Until a workflow exists, any move is allowed. After the first transition, only listed moves work. A required approval waits. The status does not change until someone decides.</p></article>
        <article class="wf-path-item"><span>4</span><h3>Close</h3><p>Completed tasks show a filled circle. If the workflow blocks that jump, the circle returns and a message explains why.</p></article>
    </div>
</section>
<?php endif; ?>

<?php if ($on('approvals')): ?>
<section class="mb-4" id="approvals">
    <h3 class="wf-guide-title">Approvals</h3>
    <div class="wf-path">
        <article class="wf-path-item"><span>1</span><h3>Request</h3><p>A status change marked internal or client creates an approval. <?= $go('workflows', 'Workflows') ?> define which move asks.</p></article>
        <article class="wf-path-item"><span>2</span><h3>Internal</h3><p>A staff member with approval access decides. The requester can decide their own request.</p></article>
        <article class="wf-path-item"><span>3</span><h3>Client</h3><p>Only the client linked to that company can decide a client approval. Staff cannot decide it for them.</p></article>
        <article class="wf-path-item"><span>4</span><h3>Result</h3><p>Approved writes the new status. Changes requested on a deliverable moves it to that status when it exists. The client is notified when a client approval is requested.</p></article>
    </div>
</section>
<?php endif; ?>

<?php if ($on('time')): ?>
<section class="mb-4" id="time">
    <h3 class="wf-guide-title">Time</h3>
    <div class="wf-path">
        <article class="wf-path-item"><span>1</span><h3>Start or log</h3><p>One running timer per person. A manual entry needs at least one minute and cannot exceed 24 hours.</p></article>
        <article class="wf-path-item"><span>2</span><h3>Own hours</h3><p>Everyone with time access sees their own entries. <?= $go('time', 'Timesheet') ?></p></article>
        <article class="wf-path-item"><span>3</span><h3>Team hours</h3><p>People with access to all time also need to be able to see that project. Hidden projects stay hidden.</p></article>
        <article class="wf-path-item"><span>4</span><h3>Stop</h3><p>The top bar shows a running timer. Stopping writes the duration onto the same entry.</p></article>
    </div>
</section>
<?php endif; ?>

<?php if ($on('finance') || $on('invoices') || $on('expenses')): ?>
<section class="mb-4" id="finance">
    <h3 class="wf-guide-title">Finance</h3>
    <div class="wf-path">
        <article class="wf-path-item"><span>1</span><h3>Draft</h3><p><?= $go('invoices/create', 'New invoice') ?> for a client, optionally a project. Drafts stay off the client portal.</p></article>
        <article class="wf-path-item"><span>2</span><h3>Send</h3><p>Sending assigns the next number, such as INV-1001, and notifies the client.</p></article>
        <article class="wf-path-item"><span>3</span><h3>Payment</h3><p>A payment cannot exceed the balance. The invoice becomes partial or paid. Overdue is calculated from the due date. It is not stored as its own status.</p></article>
        <article class="wf-path-item"><span>4</span><h3>Expenses</h3><p>Staff submit expenses. Only an approver can accept them. Profit is sent, partial, and paid invoice totals minus approved expenses. Drafts and cancelled invoices are left out.</p></article>
    </div>
</section>
<?php endif; ?>

<?php if ($on('portal')): ?>
<section class="mb-4" id="portal">
    <h3 class="wf-guide-title">Client portal</h3>
    <div class="wf-path">
        <article class="wf-path-item"><span>1</span><h3>Same login</h3><p>The client uses the normal sign-in page and lands on their dashboard.</p></article>
        <article class="wf-path-item"><span>2</span><h3>Their company only</h3><p>Projects, files, invoices, and payments are limited to the linked client. Another client in the same organization is not found.</p></article>
        <article class="wf-path-item"><span>3</span><h3>What they see</h3><p>Company profile, project progress, milestones, deliverables that are in review, changes requested, approved, or delivered, invoice balances, and recorded payments.</p></article>
        <article class="wf-path-item"><span>4</span><h3>What they never see</h3><p>Staff menus, draft invoices, the payment form, budgets, members, and internal approval notes. They can approve a client request. They cannot record a payment.</p></article>
    </div>
</section>
<?php endif; ?>

<section id="roles">
    <h3 class="wf-guide-title">What each person does</h3>
    <div class="row g-3">
        <div class="col-md-6">
            <article class="wf-lane h-100">
                <strong>Owner and admin</strong>
                <p class="mb-0 text-muted">Full workspace: people, roles, clients, projects, tasks, workflows, time, invoices, payments, and expenses.</p>
            </article>
        </div>
        <div class="col-md-6">
            <article class="wf-lane h-100">
                <strong>Manager and project manager</strong>
                <p class="mb-0 text-muted">Projects they can see, tasks, their team’s time when allowed, invoice viewing, and creating expenses. They do not manage every invoice.</p>
            </article>
        </div>
        <div class="col-md-6">
            <article class="wf-lane h-100">
                <strong>Team leader and employee</strong>
                <p class="mb-0 text-muted">Assigned tasks, own time, and own expenses. An employee does not see other people’s invoices.</p>
            </article>
        </div>
        <div class="col-md-6">
            <article class="wf-lane h-100">
                <strong>Sales</strong>
                <p class="mb-0 text-muted">Clients and contacts, plus read access to invoices and expenses.</p>
            </article>
        </div>
        <div class="col-md-6">
            <article class="wf-lane h-100">
                <strong>Finance</strong>
                <p class="mb-0 text-muted">Invoices, payments, expense approval, and a read-only view of time. Profit on a project uses their invoice and approved-expense totals.</p>
            </article>
        </div>
        <div class="col-md-6">
            <article class="wf-lane h-100">
                <strong>Client</strong>
                <p class="mb-0 text-muted">Portal only: company, projects, deliverables, approvals, files, invoices, payments, and notifications.</p>
            </article>
        </div>
    </div>
</section>

<?php if ($platform): ?>
<section class="mt-4" id="diagram">
    <h3 class="wf-guide-title">Full workflow</h3>
    <p class="text-muted">Every path on one diagram. Arrows across a row happen in order. Boxes in a band can happen together.</p>

    <p class="wf-flow-label">Platform owner</p>
    <div class="wf-flow-row">
        <div class="wf-flow-box"><?= icon('box-arrow-in-right') ?><strong>Sign in</strong><span>Console only. No company records.</span></div>
        <span class="wf-flow-next"><?= icon('arrow-right') ?></span>
        <div class="wf-flow-box"><?= icon('sliders') ?><strong>Plans</strong><span>Check the functions each plan includes.</span></div>
        <span class="wf-flow-next"><?= icon('arrow-right') ?></span>
        <div class="wf-flow-box"><?= icon('building-add') ?><strong>Register company</strong><span>Name, plan, and a company ID such as CMP-0001.</span></div>
        <span class="wf-flow-next"><?= icon('arrow-right') ?></span>
        <div class="wf-flow-box"><?= icon('toggles') ?><strong>Company features</strong><span>Keep the plan, add extras, or clear a function.</span></div>
        <span class="wf-flow-next"><?= icon('arrow-right') ?></span>
        <div class="wf-flow-box"><?= icon('person-gear') ?><strong>Platform users</strong><span>Other console administrators.</span></div>
    </div>
    <div class="wf-flow-down"><?= icon('arrow-down') ?> Hand off to the organization owner</div>

    <p class="wf-flow-label">Organization owner</p>
    <div class="wf-flow-row">
        <div class="wf-flow-box"><?= icon('box-arrow-in-right') ?><strong>Sign in</strong><span>Lands in that company. The sidebar shows the company ID.</span></div>
        <span class="wf-flow-next"><?= icon('arrow-right') ?></span>
        <div class="wf-flow-box"><?= icon('building') ?><strong>Organization</strong><span>Invoice prefix and the next invoice number.</span></div>
        <span class="wf-flow-next"><?= icon('arrow-right') ?></span>
        <div class="wf-flow-box"><?= icon('shield-check') ?><strong>Roles</strong><span>See what each role can do. Roles stay fixed.</span></div>
        <span class="wf-flow-next"><?= icon('arrow-right') ?></span>
        <div class="wf-flow-box"><?= icon('people') ?><strong>People</strong><span>One role each. A client login links to one client.</span></div>
    </div>
    <?php if ($on('clients') || $on('projects')): ?>
        <div class="wf-flow-down"><?= icon('arrow-down') ?> Start the work chain</div>
        <p class="wf-flow-label">Records</p>
        <div class="wf-flow-row">
            <?php if ($on('clients')): ?><div class="wf-flow-box"><?= icon('person-vcard') ?><strong>Client</strong><span>Company and contacts.</span></div><?php endif; ?>
            <?php if ($on('clients') && $on('projects')): ?><span class="wf-flow-next"><?= icon('arrow-right') ?></span><?php endif; ?>
            <?php if ($on('projects')): ?><div class="wf-flow-box"><?= icon('folder') ?><strong>Project</strong><span>One client. Members and milestones live here.</span></div><?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if ($on('projects') || $on('tasks') || $on('time') || $on('deliverables') || $on('invoices') || $on('expenses')): ?>
        <div class="wf-flow-down"><?= icon('arrow-down') ?> On that project</div>
        <div class="wf-flow-row">
            <?php if ($on('projects')): ?><div class="wf-flow-box"><?= icon('people') ?><strong>Members</strong><span>Who can see the project.</span></div><?php endif; ?>
            <?php if ($on('projects')): ?><div class="wf-flow-box"><?= icon('flag') ?><strong>Milestones</strong><span>Dates and progress.</span></div><?php endif; ?>
            <?php if ($on('tasks')): ?><div class="wf-flow-box"><?= icon('check2-square') ?><strong>Tasks</strong><span>Assignee, comments, and files.</span></div><?php endif; ?>
            <?php if ($on('time')): ?><div class="wf-flow-box"><?= icon('clock') ?><strong>Time</strong><span>Timer or a manual entry on the project.</span></div><?php endif; ?>
            <?php if ($on('deliverables')): ?><div class="wf-flow-box"><?= icon('box-seam') ?><strong>Deliverables</strong><span>The file sent for review.</span></div><?php endif; ?>
            <?php if ($on('invoices')): ?><div class="wf-flow-box"><?= icon('receipt') ?><strong>Invoices</strong><span>Lines for the client, and a project when there is one.</span></div><?php endif; ?>
            <?php if ($on('expenses')): ?><div class="wf-flow-box"><?= icon('cash-stack') ?><strong>Expenses</strong><span>Costs waiting for approval.</span></div><?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="row g-3 mt-2">
        <?php if ($on('tasks')): ?>
        <div class="col-lg-4">
            <article class="wf-flow-stack">
                <p class="wf-flow-label">Tasks</p>
                <div class="wf-flow-box"><?= icon('plus-lg') ?><strong>Create</strong><span>Project, title, assignee, priority, dates.</span></div>
                <div class="wf-flow-down"><?= icon('arrow-down') ?></div>
                <div class="wf-flow-box"><?= icon('kanban') ?><strong>Move status</strong><span>Backlog, to do, in progress, review, completed.</span></div>
                <?php if ($on('workflows')): ?>
                    <div class="wf-flow-down"><?= icon('arrow-down') ?></div>
                    <div class="wf-flow-box"><?= icon('diagram-3') ?><strong>Workflow gate</strong><span>Open until the first saved move. After that, only listed moves work.</span></div>
                <?php endif; ?>
                <div class="wf-flow-down"><?= icon('arrow-down') ?></div>
                <div class="wf-flow-box"><?= icon('check2-circle') ?><strong>Completed</strong><span>An illegal jump is rejected and the circle returns.</span></div>
            </article>
        </div>
        <?php endif; ?>
        <?php if ($on('approvals')): ?>
        <div class="col-lg-4">
            <article class="wf-flow-stack">
                <p class="wf-flow-label">Approvals</p>
                <div class="wf-flow-box"><?= icon('signpost-split') ?><strong>Request</strong><span>A move marked internal or client waits. Status stays put.</span></div>
                <div class="wf-flow-down"><?= icon('arrow-down') ?></div>
                <div class="wf-flow-box"><?= icon('person-check') ?><strong>Internal</strong><span>Staff with approval access decide, including the requester.</span></div>
                <div class="wf-flow-box"><?= icon('person-vcard') ?><strong>Client</strong><span>Only the linked client decides. Staff cannot decide it.</span></div>
                <div class="wf-flow-down"><?= icon('arrow-down') ?></div>
                <div class="wf-flow-box"><?= icon('check2-all') ?><strong>Result</strong><span>Approved writes the new status. The client is notified when asked.</span></div>
            </article>
        </div>
        <?php endif; ?>
        <?php if ($on('finance') || $on('invoices') || $on('expenses')): ?>
        <div class="col-lg-4">
            <article class="wf-flow-stack">
                <p class="wf-flow-label">Finance</p>
                <?php if ($on('invoices')): ?>
                    <div class="wf-flow-box"><?= icon('file-earmark') ?><strong>Draft</strong><span>Hidden from the client portal.</span></div>
                    <div class="wf-flow-down"><?= icon('arrow-down') ?></div>
                    <div class="wf-flow-box"><?= icon('send') ?><strong>Send</strong><span>Next number, such as INV-1001. The client is notified.</span></div>
                    <div class="wf-flow-down"><?= icon('arrow-down') ?></div>
                    <div class="wf-flow-box"><?= icon('credit-card') ?><strong>Payment</strong><span>Cannot exceed the balance. Partial or paid. Overdue is calculated.</span></div>
                <?php elseif ($on('expenses')): ?>
                    <div class="wf-flow-box"><?= icon('cash-stack') ?><strong>Expenses</strong><span>Submitted costs wait for approval.</span></div>
                <?php endif; ?>
                <?php if ($on('finance') || ($on('invoices') && $on('expenses'))): ?>
                    <div class="wf-flow-down"><?= icon('arrow-down') ?></div>
                    <div class="wf-flow-box"><?= icon('graph-up') ?><strong>Profit</strong><span>Sent, partial, and paid invoices minus approved expenses.</span></div>
                <?php endif; ?>
            </article>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($on('portal')): ?>
    <div class="wf-flow-down"><?= icon('arrow-down') ?> What the client can open</div>
    <p class="wf-flow-label">Client portal</p>
    <div class="wf-flow-row">
        <div class="wf-flow-box"><?= icon('box-arrow-in-right') ?><strong>Same sign-in</strong><span>Lands on the client dashboard.</span></div>
        <span class="wf-flow-next"><?= icon('arrow-right') ?></span>
        <div class="wf-flow-box"><?= icon('building') ?><strong>Their company</strong><span>Projects, files, invoices, and payments for that client only.</span></div>
        <span class="wf-flow-next"><?= icon('arrow-right') ?></span>
        <div class="wf-flow-box"><?= icon('eye') ?><strong>Visible work</strong><span>Progress, milestones, deliverables in review or later, balances, and payments.</span></div>
        <span class="wf-flow-next"><?= icon('arrow-right') ?></span>
        <div class="wf-flow-box"><?= icon('check2-circle') ?><strong>Decide</strong><span>Approves a client request. Cannot record a payment.</span></div>
        <span class="wf-flow-next"><?= icon('arrow-right') ?></span>
        <div class="wf-flow-box"><?= icon('eye-slash') ?><strong>Closed</strong><span>No staff menu, drafts, budgets, members, or internal notes.</span></div>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php
$chartSteps = [];
foreach ([
    ['clients', 'Clients', 'The company you deliver for, and the people there.'],
    ['projects', 'Projects', 'One client, with members, dates, and milestones.'],
    ['tasks', 'Tasks', 'Assigned work. Completing a task moves the project bar.'],
    ['approvals', 'Approvals', 'A gated change waits until the right person decides.'],
    ['deliverables', 'Files', 'The deliverable sent for review, changes, or delivery.'],
    ['invoices', 'Invoices', 'Draft, send, then record a payment against the balance.'],
    ['time', 'Time', 'A timer or a manual entry on the project.'],
    ['portal', 'Portal', 'The client sees their projects, files, and invoices.'],
] as $step) {
    if ($on($step[0])) {
        $chartSteps[] = ['title' => $step[1], 'text' => $step[2]];
    }
}
foreach ([
    ['Sign in', 'The company workspace opens, with its ID in the sidebar.'],
    ['People', 'Each person has one role. A client login links to one client.'],
    ['Roles', 'What a role can do stays fixed for that role.'],
    ['Record', 'The work stays inside this company.'],
] as $step) {
    if (count($chartSteps) >= 4) {
        break;
    }
    $chartSteps[] = ['title' => $step[0], 'text' => $step[1]];
}
$chartSteps = array_slice($chartSteps, 0, 4);
$chartTones = ['is-c1', 'is-c2', 'is-c3', 'is-c4'];
$closeParts = [];
if ($on('deliverables')) {
    $closeParts[] = 'deliverables';
}
if ($on('invoices')) {
    $closeParts[] = 'invoices';
}
if ($on('portal')) {
    $closeParts[] = 'the client portal';
} elseif ($on('expenses')) {
    $closeParts[] = 'expenses';
} elseif ($on('time')) {
    $closeParts[] = 'logged time';
}
$closeText = $closeParts === []
    ? 'Finished work stays on the company record.'
    : 'Closes with ' . implode(', ', $closeParts) . '.';
?>
<section class="wf-card mt-4" id="process-chart">
    <div class="wf-card-head"><h3>Process flow chart</h3></div>
    <p class="text-muted">The system in one chart. The circle is the company. The two cards split setup from delivery. The numbered cards are the path that is turned on.</p>
    <div class="wf-info-scroll">
    <div class="wf-info">
        <svg class="wf-info-lines" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
            <path d="M23 51 H25.5 V27.5 H28" />
            <path d="M25.5 51 V75 H28" />
            <path d="M58 27.5 H59.5 V16 H61" />
            <path d="M59.5 27.5 V39.5 H61" />
            <path d="M58 75 H59.5 V63.5 H61" />
            <path d="M59.5 75 V87 H61" />
        </svg>
        <div class="wf-info-hub">
            <div class="wf-info-ring">
                <div class="wf-info-circle">
                    <svg class="wf-info-globe" viewBox="0 0 100 100" aria-hidden="true">
                        <ellipse cx="50" cy="50" rx="22" ry="34" fill="none" stroke="currentColor" stroke-width="1.2"/>
                        <ellipse cx="50" cy="50" rx="10" ry="34" fill="none" stroke="currentColor" stroke-width="1.2"/>
                        <path d="M20 50 H80 M24 36 H76 M24 64 H76" fill="none" stroke="currentColor" stroke-width="1.2"/>
                    </svg>
                    <strong>Work<br>flows</strong>
                    <span><?= e($guideOrganization->company_code ?? app_name()) ?></span>
                    <i class="wf-info-pips" aria-hidden="true"></i>
                </div>
            </div>
        </div>
        <div class="wf-info-mids">
            <article class="wf-info-mid is-orange">
                <span class="wf-info-dot is-left"></span>
                <span class="wf-info-dot is-right"></span>
                <span class="wf-info-icon"><?= icon('people') ?></span>
                <div>
                    <strong>Company</strong>
                    <p>Sign in, people, and one role each.</p>
                </div>
            </article>
            <article class="wf-info-mid is-teal">
                <span class="wf-info-dot is-left"></span>
                <span class="wf-info-dot is-right"></span>
                <span class="wf-info-icon"><?= icon('diagram-3') ?></span>
                <div>
                    <strong>Delivery</strong>
                    <p><?= e($closeText) ?></p>
                </div>
            </article>
        </div>
        <ol class="wf-info-steps">
            <?php foreach ($chartSteps as $index => $step): ?>
                <li class="wf-info-step <?= e($chartTones[$index]) ?>">
                    <span class="wf-info-cap"></span>
                    <span class="wf-info-dot is-left"></span>
                    <div>
                        <strong><?= e($step['title']) ?></strong>
                        <p><?= e($step['text']) ?></p>
                    </div>
                    <span class="wf-info-num"><?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
    </div>
</section>
