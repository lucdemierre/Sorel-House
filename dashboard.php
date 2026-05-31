<?php

declare(strict_types=1);
require __DIR__ . '/lib/landlord.php';
handleLandlordPost('dashboard.php');
extract(landlordData());

renderLandlordStart('overview', 'Stay on top of every tenancy.', 'Portfolio command centre', 'Track compliance, rent and tenant messages without a letting agent.', '<div class="top-actions"><button class="button secondary" data-open="property-dialog">Add property</button><button class="button primary" data-open="tenant-dialog">Add tenant</button></div>');
?>
<section class="metrics" aria-label="Portfolio overview">
    <article class="metric"><span>Properties</span><strong><?= count($properties) ?></strong><small>Across your portfolio</small></article>
    <article class="metric"><span>Rent received</span><strong class="positive">&pound;<?= number_format($receivedRent) ?></strong><small><?= $lateCount ?> late payment<?= $lateCount === 1 ? '' : 's' ?></small></article>
    <article class="metric"><span>Needs attention</span><strong class="warning"><?= $dueCertificates ?></strong><small>Expired or due within 30 days</small></article>
    <article class="metric"><span>Reply drafts</span><strong class="accent"><?= $draftCount ?></strong><small>Waiting for your approval</small></article>
    <article class="metric"><span>Open repairs</span><strong class="warning"><?= $openMaintenance ?></strong><small>Reported or scheduled</small></article>
    <article class="metric"><span>Reminders</span><strong><?= $openReminders ?></strong><small>Open portfolio tasks</small></article>
</section>
<section class="section">
    <div class="section-heading"><div><p class="eyebrow">Your workspace</p><h2>Choose what to manage</h2></div></div>
    <div class="feature-grid">
        <a class="panel feature-card" href="properties.php"><span>Portfolio</span><h3>Properties</h3><p>Keep every address and tenant portal organised.</p></a>
        <a class="panel feature-card" href="maintenance.php"><span>Repairs</span><h3>Maintenance</h3><p>Track issues from report through to completion.</p></a>
        <a class="panel feature-card" href="compliance.php"><span>Legal checks</span><h3>Compliance</h3><p>See expired certificates and upcoming deadlines.</p></a>
        <a class="panel feature-card" href="tenants.php"><span>People</span><h3>Tenants</h3><p>Manage tenant records and private portal links.</p></a>
        <a class="panel feature-card" href="rent.php"><span>This month</span><h3>Rent tracker</h3><p>Mark payments as received, pending or late.</p></a>
        <a class="panel feature-card" href="inbox.php"><span>Tenant support</span><h3>AI inbox</h3><p>Review tenant messages and approve reply drafts.</p></a>
        <a class="panel feature-card" href="agreements.php"><span>First draft</span><h3>Agreements</h3><p>Generate periodic tenancy drafts for review.</p></a>
        <a class="panel feature-card" href="documents.php"><span>Records</span><h3>Documents</h3><p>Keep a register of important property documents.</p></a>
        <a class="panel feature-card" href="reminders.php"><span>Tasks</span><h3>Reminders</h3><p>Keep upcoming jobs visible before they become urgent.</p></a>
    </div>
</section>
<?php renderLandlordEnd($properties); ?>
