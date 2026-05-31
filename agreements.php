<?php

declare(strict_types=1);
require __DIR__ . '/lib/landlord.php';
handleLandlordPost('agreements.php');
extract(landlordData());

renderLandlordStart('agreements', 'Agreement generator', 'First draft only', 'Create a periodic tenancy first draft, then review it carefully before use.', '<div class="top-actions"><button class="button primary" data-open="agreement-dialog">Generate agreement</button></div>');
?>
<div class="notice"><strong>England tenancy update:</strong> GOV.UK states that ASTs cannot be created from 1 May 2026 and assured tenancies run on a rolling basis. Generated drafts follow that structure and still need review before use. <a href="https://www.gov.uk/guidance/renting-out-your-property-guidance-for-landlords-and-letting-agents" target="_blank" rel="noopener">Read the official guidance.</a></div>
<section class="agreement-grid">
<?php if (!$agreements): ?><div class="empty">No drafts yet. Generate your first agreement.</div><?php endif; ?>
<?php foreach ($agreements as $agreement): ?>
    <article class="panel agreement-card"><p class="eyebrow"><?= e(date('j M Y', strtotime($agreement['created_at']))) ?></p><h3><?= e($agreement['tenant_name']) ?></h3><p><?= e($agreement['property_address']) ?></p><details><summary>Read draft</summary><pre><?= e($agreement['draft']) ?></pre></details></article>
<?php endforeach; ?>
</section>
<?php renderLandlordEnd($properties); ?>
