<?php

declare(strict_types=1);
require __DIR__ . '/lib/landlord.php';
handleLandlordPost('properties.php');
extract(landlordData());

renderLandlordStart('properties', 'Properties', 'Portfolio', 'Manage the homes in your portfolio and add tenants as they move in.', '<div class="top-actions"><button class="button secondary" data-open="property-dialog">Add property</button><button class="button primary" data-open="tenant-dialog">Add tenant</button></div>');
?>
<section class="portfolio-toolbar panel">
    <label>Search properties<input type="search" placeholder="Search by address or postcode" data-property-search></label>
    <p><strong><?= count($properties) ?></strong> properties in your portfolio</p>
</section>
<section class="property-grid">
<?php foreach ($properties as $property): ?>
    <article class="panel property-card" data-property-card data-search="<?= e(strtolower(implode(' ', [$property['address'], $property['address_line_2'], $property['town_city'], $property['county'], $property['postcode'], $property['property_type']]))) ?>">
        <div class="property-card-main"><span class="property-icon">&#8962;</span><div><h3><?= e($property['address']) ?></h3><p><?= e(implode(', ', array_filter([$property['address_line_2'], $property['town_city'], $property['postcode']]))) ?></p></div></div>
        <div class="property-meta"><span><?= e($property['property_type']) ?></span><span><?= (int) $property['bedrooms'] ?> bed</span><span><?= (int) $property['bathrooms'] ?> bath</span><span><?= (int) $property['tenant_count'] ?> tenant<?= (int) $property['tenant_count'] === 1 ? '' : 's' ?></span><span><?= (int) $property['certificate_count'] ?> certificate<?= (int) $property['certificate_count'] === 1 ? '' : 's' ?></span></div>
        <details class="property-profile"><summary>Operational profile</summary><dl><div><dt>Local authority</dt><dd><?= e($property['local_authority'] ?: 'Not recorded') ?></dd></div><div><dt>Council tax</dt><dd><?= e($property['council_tax_band'] ?: 'Not recorded') ?></dd></div><div><dt>Reference</dt><dd><?= e($property['ownership_reference'] ?: 'Not recorded') ?></dd></div><div><dt>Access notes</dt><dd><?= e($property['access_notes'] ?: 'Not recorded') ?></dd></div><div><dt>Emergency notes</dt><dd><?= e($property['emergency_notes'] ?: 'Not recorded') ?></dd></div></dl></details>
        <div class="card-actions">
            <button class="text-button" type="button" data-edit-property data-id="<?= (int) $property['id'] ?>" data-address="<?= e($property['address']) ?>" data-address-line-2="<?= e($property['address_line_2']) ?>" data-town-city="<?= e($property['town_city']) ?>" data-county="<?= e($property['county']) ?>" data-postcode="<?= e($property['postcode']) ?>" data-property-type="<?= e($property['property_type']) ?>" data-bedrooms="<?= (int) $property['bedrooms'] ?>" data-bathrooms="<?= (int) $property['bathrooms'] ?>" data-local-authority="<?= e($property['local_authority']) ?>" data-council-tax-band="<?= e($property['council_tax_band']) ?>" data-ownership-reference="<?= e($property['ownership_reference']) ?>" data-access-notes="<?= e($property['access_notes']) ?>" data-emergency-notes="<?= e($property['emergency_notes']) ?>">Edit</button>
            <form method="post"><input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="delete_property"><input type="hidden" name="property_id" value="<?= (int) $property['id'] ?>"><button class="text-button danger" type="submit" data-confirm="Delete this empty property?">Delete</button></form>
        </div>
    </article>
<?php endforeach; ?>
</section>
<p class="empty property-search-empty" data-property-search-empty hidden>No properties match that search.</p>
<dialog id="edit-property-dialog" class="wide-dialog"><form method="post"><button type="button" class="dialog-close" data-close>&times;</button><p class="eyebrow">Portfolio</p><h2>Edit property profile</h2><input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="edit_property"><input type="hidden" name="property_id"><?= propertyProfileFields() ?><button class="button primary">Save changes</button></form></dialog>
<?php renderLandlordEnd($properties); ?>
