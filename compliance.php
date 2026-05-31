<?php

declare(strict_types=1);
require __DIR__ . '/lib/landlord.php';
handleLandlordPost('compliance.php');
extract(landlordData());

renderLandlordStart('compliance', 'Compliance calendar', 'Legal checks', 'Record certificate dates and spot expired or upcoming requirements early.', '<div class="top-actions"><button class="button primary" data-open="certificate-dialog">Add certificate</button></div>');
?>
<section class="panel table-wrap">
    <table>
        <thead><tr><th>Property</th><th>Certificate</th><th>Expiry</th><th>Status</th><th>Notes</th></tr></thead>
        <tbody>
        <?php foreach ($certificates as $certificate): [$status, $label] = certificateStatus($certificate['expires_on']); ?>
            <tr><td><?= e($certificate['address']) ?></td><td><?= e($certificate['type']) ?></td><td><?= e(date('j M Y', strtotime($certificate['expires_on']))) ?></td><td><span class="badge <?= e($status) ?>"><?= e($label) ?></span></td><td><?= e($certificate['notes']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<p class="legal-note">Built for organisation, not legal advice. Rules differ across the UK and by property type. This MVP is scoped to standard private rentals in England.</p>
<?php renderLandlordEnd($properties); ?>
