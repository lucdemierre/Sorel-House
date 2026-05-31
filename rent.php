<?php

declare(strict_types=1);
require __DIR__ . '/lib/landlord.php';
handleLandlordPost('rent.php');
extract(landlordData());

renderLandlordStart('rent', 'Rent tracker', 'This month', 'See who has paid, what remains pending and which payments need a follow-up.', '<div class="top-actions"><button class="button primary" data-open="tenant-dialog">Add tenant</button></div>');
?>
<section class="metrics">
    <article class="metric"><span>Received</span><strong class="positive">&pound;<?= number_format($receivedRent) ?></strong><small>Recorded this month</small></article>
    <article class="metric"><span>Late payments</span><strong class="warning"><?= $lateCount ?></strong><small>Need a follow-up</small></article>
    <article class="metric"><span>Tenants</span><strong><?= count($tenants) ?></strong><small>Current rent records</small></article>
</section>
<section class="panel table-wrap section">
    <table>
        <thead><tr><th>Tenant</th><th>Property</th><th>Amount</th><th>Due</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($payments as $payment): ?>
            <tr><td><?= e($payment['name']) ?></td><td><?= e($payment['address']) ?></td><td>&pound;<?= number_format((float) $payment['amount'], 2) ?></td><td><?= (int) $payment['rent_due_day'] ?> <?= e(date('M')) ?></td><td>
                <form method="post"><input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="payment_status"><input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>"><select name="status" class="status-select <?= e($payment['status']) ?>" onchange="this.form.submit()"><?php foreach (['received', 'pending', 'late'] as $status): ?><option value="<?= $status ?>" <?= $payment['status'] === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option><?php endforeach; ?></select></form>
            </td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php renderLandlordEnd($properties); ?>
