<?php

declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
requireLandlordSignIn();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

db();

function landlordRedirect(string $page): never
{
    header('Location: ' . $page);
    exit;
}

function handleLandlordPost(string $fallbackPage): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    try {
        verifyCsrf();
        $action = $_POST['action'] ?? '';

        if ($action === 'add_property') {
            execute('INSERT INTO properties (address, address_line_2, town_city, county, postcode, property_type, bedrooms, bathrooms, local_authority, council_tax_band, ownership_reference, access_notes, emergency_notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                requiredText('address', 'Address'),
                trim((string) ($_POST['address_line_2'] ?? '')),
                trim((string) ($_POST['town_city'] ?? '')),
                trim((string) ($_POST['county'] ?? '')),
                strtoupper(requiredText('postcode', 'Postcode')),
                requiredText('property_type', 'Property type'),
                requiredCount('bedrooms', 'Bedrooms'),
                requiredCount('bathrooms', 'Bathrooms'),
                trim((string) ($_POST['local_authority'] ?? '')),
                strtoupper(trim((string) ($_POST['council_tax_band'] ?? ''))),
                trim((string) ($_POST['ownership_reference'] ?? '')),
                trim((string) ($_POST['access_notes'] ?? '')),
                trim((string) ($_POST['emergency_notes'] ?? '')),
            ]);
            flash('Property added.');
            landlordRedirect('properties.php');
        }

        if ($action === 'edit_property') {
            $propertyId = requiredId('property_id', 'Property');
            if (!row('SELECT id FROM properties WHERE id = ?', [$propertyId])) {
                throw new RuntimeException('Property not found.');
            }
            execute('UPDATE properties SET address = ?, address_line_2 = ?, town_city = ?, county = ?, postcode = ?, property_type = ?, bedrooms = ?, bathrooms = ?, local_authority = ?, council_tax_band = ?, ownership_reference = ?, access_notes = ?, emergency_notes = ? WHERE id = ?', [
                requiredText('address', 'Address'),
                trim((string) ($_POST['address_line_2'] ?? '')),
                trim((string) ($_POST['town_city'] ?? '')),
                trim((string) ($_POST['county'] ?? '')),
                strtoupper(requiredText('postcode', 'Postcode')),
                requiredText('property_type', 'Property type'),
                requiredCount('bedrooms', 'Bedrooms'),
                requiredCount('bathrooms', 'Bathrooms'),
                trim((string) ($_POST['local_authority'] ?? '')),
                strtoupper(trim((string) ($_POST['council_tax_band'] ?? ''))),
                trim((string) ($_POST['ownership_reference'] ?? '')),
                trim((string) ($_POST['access_notes'] ?? '')),
                trim((string) ($_POST['emergency_notes'] ?? '')),
                $propertyId,
            ]);
            flash('Property updated.');
            landlordRedirect('properties.php');
        }

        if ($action === 'delete_property') {
            $propertyId = requiredId('property_id', 'Property');
            $property = row(
                'SELECT p.id, COUNT(DISTINCT t.id) AS tenant_count, COUNT(DISTINCT c.id) AS certificate_count,
                        COUNT(DISTINCT m.id) AS maintenance_count, COUNT(DISTINCT d.id) AS document_count, COUNT(DISTINCT r.id) AS reminder_count
                 FROM properties p
                 LEFT JOIN tenants t ON t.property_id = p.id
                 LEFT JOIN certificates c ON c.property_id = p.id
                 LEFT JOIN maintenance_requests m ON m.property_id = p.id
                 LEFT JOIN documents d ON d.property_id = p.id
                 LEFT JOIN reminders r ON r.property_id = p.id
                 WHERE p.id = ?
                 GROUP BY p.id',
                [$propertyId]
            );
            if (!$property) {
                throw new RuntimeException('Property not found.');
            }
            if (array_sum(array_map('intval', [$property['tenant_count'], $property['certificate_count'], $property['maintenance_count'], $property['document_count'], $property['reminder_count']])) > 0) {
                throw new RuntimeException('Remove linked tenants, certificates, maintenance requests, documents and reminders before deleting this property.');
            }
            execute('DELETE FROM properties WHERE id = ?', [$propertyId]);
            flash('Property deleted.');
            landlordRedirect('properties.php');
        }

        if ($action === 'add_certificate') {
            execute('INSERT INTO certificates (property_id, type, expires_on, notes) VALUES (?, ?, ?, ?)', [
                requiredId('property_id', 'Property'),
                requiredText('type', 'Certificate type'),
                requiredDate('expires_on', 'Expiry date'),
                trim((string) ($_POST['notes'] ?? '')),
            ]);
            flash('Certificate added.');
            landlordRedirect('compliance.php');
        }

        if ($action === 'add_tenant') {
            $email = trim((string) ($_POST['email'] ?? ''));
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Email must be a valid email address.');
            }
            execute('INSERT INTO tenants (property_id, name, email, monthly_rent, rent_due_day, portal_token) VALUES (?, ?, ?, ?, ?, ?)', [
                requiredId('property_id', 'Property'),
                requiredText('name', 'Tenant name'),
                $email,
                requiredMoney('monthly_rent', 'Monthly rent'),
                requiredDay('rent_due_day', 'Rent due day'),
                bin2hex(random_bytes(16)),
            ]);
            ensureCurrentPayments(db());
            flash('Tenant and current rent record added.');
            landlordRedirect('rent.php');
        }

        if ($action === 'edit_tenant') {
            $tenantId = requiredId('tenant_id', 'Tenant');
            $email = trim((string) ($_POST['email'] ?? ''));
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Email must be a valid email address.');
            }
            execute('UPDATE tenants SET name = ?, email = ?, monthly_rent = ?, rent_due_day = ?, status = ? WHERE id = ?', [
                requiredText('name', 'Tenant name'),
                $email,
                requiredMoney('monthly_rent', 'Monthly rent'),
                requiredDay('rent_due_day', 'Rent due day'),
                in_array($_POST['status'] ?? '', ['active', 'archived'], true) ? $_POST['status'] : 'active',
                $tenantId,
            ]);
            flash('Tenant updated.');
            landlordRedirect('tenants.php');
        }

        if ($action === 'regenerate_portal') {
            execute('UPDATE tenants SET portal_token = ? WHERE id = ?', [bin2hex(random_bytes(16)), requiredId('tenant_id', 'Tenant')]);
            flash('Tenant portal link regenerated. The old link no longer works.');
            landlordRedirect('tenants.php');
        }

        if ($action === 'payment_status') {
            $status = in_array($_POST['status'] ?? '', ['received', 'pending', 'late'], true) ? $_POST['status'] : 'pending';
            execute('UPDATE payments SET status = ?, paid_on = ? WHERE id = ?', [
                $status, $status === 'received' ? date('Y-m-d') : null, requiredId('payment_id', 'Payment'),
            ]);
            flash('Rent status updated.');
            landlordRedirect('rent.php');
        }

        if ($action === 'tenant_message') {
            $tenant = row('SELECT * FROM tenants WHERE id = ?', [requiredId('tenant_id', 'Tenant')]);
            if (!$tenant) {
                throw new RuntimeException('Tenant not found.');
            }
            $body = requiredText('body', 'Tenant message');
            execute('INSERT INTO messages (tenant_id, sender, body, status) VALUES (?, ?, ?, ?)', [$tenant['id'], 'tenant', $body, 'received']);
            $sourceMessageId = (int) db()->lastInsertId();
            execute('INSERT INTO messages (tenant_id, sender, body, status, source_message_id, generated_by) VALUES (?, ?, ?, ?, ?, ?)', [$tenant['id'], 'assistant', draftTenantReply($tenant, $body), 'draft', $sourceMessageId, aiProviderLabel()]);
            flash('Message received and a reply draft was prepared.');
            landlordRedirect('inbox.php');
        }

        if ($action === 'approve_message') {
            execute("UPDATE messages SET status = 'approved' WHERE id = ? AND sender = 'assistant' AND status = 'draft'", [requiredId('message_id', 'Message')]);
            flash('Reply approved. Connect an email or messaging provider before launch to deliver approved replies.');
            landlordRedirect('inbox.php');
        }

        if ($action === 'decline_message') {
            execute("UPDATE messages SET status = 'declined', review_note = ? WHERE id = ? AND sender = 'assistant' AND status = 'draft'", [
                trim((string) ($_POST['review_note'] ?? '')),
                requiredId('message_id', 'Message'),
            ]);
            flash('Draft declined and kept in the audit history.');
            landlordRedirect('inbox.php');
        }

        if ($action === 'regenerate_message') {
            $draft = row("SELECT m.*, t.name FROM messages m JOIN tenants t ON t.id = m.tenant_id WHERE m.id = ? AND m.sender = 'assistant' AND m.status = 'draft'", [requiredId('message_id', 'Message')]);
            if (!$draft || !(int) $draft['source_message_id']) {
                throw new RuntimeException('The original tenant message could not be found for this draft.');
            }
            $source = row("SELECT body FROM messages WHERE id = ? AND sender = 'tenant'", [(int) $draft['source_message_id']]);
            if (!$source) {
                throw new RuntimeException('The original tenant message could not be found.');
            }
            $guidance = trim((string) ($_POST['review_note'] ?? ''));
            execute("UPDATE messages SET status = 'superseded', review_note = ? WHERE id = ?", [$guidance, $draft['id']]);
            execute('INSERT INTO messages (tenant_id, sender, body, status, source_message_id, generated_by) VALUES (?, ?, ?, ?, ?, ?)', [
                $draft['tenant_id'], 'assistant', draftTenantReply(['name' => $draft['name']], $source['body'], $guidance), 'draft', $draft['source_message_id'], aiProviderLabel(),
            ]);
            flash('A revised AI draft was prepared. The previous version remains in the audit history.');
            landlordRedirect('inbox.php');
        }

        if ($action === 'add_maintenance') {
            execute('INSERT INTO maintenance_requests (property_id, tenant_id, title, description, priority, status) VALUES (?, ?, ?, ?, ?, ?)', [
                requiredId('property_id', 'Property'),
                ($_POST['tenant_id'] ?? '') !== '' ? requiredId('tenant_id', 'Tenant') : null,
                requiredText('title', 'Issue title'),
                requiredText('description', 'Description'),
                in_array($_POST['priority'] ?? '', ['low', 'normal', 'urgent'], true) ? $_POST['priority'] : 'normal',
                'reported',
            ]);
            flash('Maintenance request added.');
            landlordRedirect('maintenance.php');
        }

        if ($action === 'maintenance_status') {
            $status = in_array($_POST['status'] ?? '', ['reported', 'scheduled', 'completed'], true) ? $_POST['status'] : 'reported';
            execute('UPDATE maintenance_requests SET status = ? WHERE id = ?', [$status, requiredId('maintenance_id', 'Maintenance request')]);
            flash('Maintenance status updated.');
            landlordRedirect('maintenance.php');
        }

        if ($action === 'add_document') {
            execute('INSERT INTO documents (property_id, name, category, notes) VALUES (?, ?, ?, ?)', [
                requiredId('property_id', 'Property'),
                requiredText('name', 'Document name'),
                requiredText('category', 'Category'),
                trim((string) ($_POST['notes'] ?? '')),
            ]);
            flash('Document record added.');
            landlordRedirect('documents.php');
        }

        if ($action === 'add_reminder') {
            execute('INSERT INTO reminders (property_id, title, due_on) VALUES (?, ?, ?)', [
                ($_POST['property_id'] ?? '') !== '' ? requiredId('property_id', 'Property') : null,
                requiredText('title', 'Reminder title'),
                requiredDate('due_on', 'Due date'),
            ]);
            flash('Reminder added.');
            landlordRedirect('reminders.php');
        }

        if ($action === 'reminder_status') {
            $status = ($_POST['status'] ?? '') === 'done' ? 'done' : 'open';
            execute('UPDATE reminders SET status = ? WHERE id = ?', [$status, requiredId('reminder_id', 'Reminder')]);
            flash('Reminder updated.');
            landlordRedirect('reminders.php');
        }

        if ($action === 'generate_agreement') {
            $input = [
                'landlord_name' => requiredText('landlord_name', 'Landlord name'),
                'tenant_name' => requiredText('tenant_name', 'Tenant name'),
                'property_address' => requiredText('property_address', 'Property address'),
                'rent_amount' => requiredMoney('rent_amount', 'Monthly rent'),
                'rent_due_day' => requiredDay('rent_due_day', 'Rent due day'),
                'start_date' => requiredDate('start_date', 'Start date'),
            ];
            $prompt = "Draft an assured periodic tenancy agreement for England. It must be a rolling monthly tenancy, not a fixed-term AST. Add a heading saying DRAFT FOR REVIEW. Include parties, property, start date, rent, deposit placeholder, landlord and tenant obligations, repairs, access, notices and signatures. State that the draft needs legal review.\n\nDetails:\n" . json_encode($input, JSON_PRETTY_PRINT);
            $draft = callClaude('You prepare careful first drafts of England residential tenancy agreements. Do not present the draft as legal advice.', $prompt, 1800) ?? fallbackAgreement($input);
            execute('INSERT INTO agreements (tenant_name, property_address, rent_amount, rent_due_day, start_date, draft) VALUES (?, ?, ?, ?, ?, ?)', [
                $input['tenant_name'], $input['property_address'], $input['rent_amount'], $input['rent_due_day'], $input['start_date'], $draft,
            ]);
            flash('Agreement draft generated.');
            landlordRedirect('agreements.php');
        }

        throw new RuntimeException('Unknown action.');
    } catch (Throwable $error) {
        flash($error->getMessage(), 'error');
        landlordRedirect($fallbackPage);
    }
}

function landlordData(): array
{
    $properties = rows(
        'SELECT p.*, COUNT(DISTINCT t.id) AS tenant_count, COUNT(DISTINCT c.id) AS certificate_count
         FROM properties p
         LEFT JOIN tenants t ON t.property_id = p.id
         LEFT JOIN certificates c ON c.property_id = p.id
         GROUP BY p.id, p.address, p.address_line_2, p.town_city, p.county, p.postcode, p.property_type, p.bedrooms, p.bathrooms, p.local_authority, p.council_tax_band, p.ownership_reference, p.access_notes, p.emergency_notes, p.created_at
         ORDER BY p.address'
    );
    $certificates = rows('SELECT c.*, p.address FROM certificates c JOIN properties p ON p.id = c.property_id ORDER BY c.expires_on');
    $payments = rows('SELECT pay.*, t.name, t.rent_due_day, p.address FROM payments pay JOIN tenants t ON t.id = pay.tenant_id JOIN properties p ON p.id = t.property_id WHERE pay.rent_month = ? ORDER BY t.name', [date('Y-m-01')]);
    $tenants = rows('SELECT t.*, p.address FROM tenants t JOIN properties p ON p.id = t.property_id ORDER BY t.name');
    $messages = rows('SELECT m.*, t.name, p.address FROM messages m JOIN tenants t ON t.id = m.tenant_id JOIN properties p ON p.id = t.property_id ORDER BY m.id DESC LIMIT 20');
    $agreements = rows('SELECT * FROM agreements ORDER BY id DESC LIMIT 12');
    $maintenance = rows('SELECT m.*, p.address, t.name AS tenant_name FROM maintenance_requests m JOIN properties p ON p.id = m.property_id LEFT JOIN tenants t ON t.id = m.tenant_id ORDER BY CASE m.priority WHEN ? THEN 0 WHEN ? THEN 1 ELSE 2 END, m.id DESC', ['urgent', 'normal']);
    $documents = rows('SELECT d.*, p.address FROM documents d JOIN properties p ON p.id = d.property_id ORDER BY d.id DESC');
    $reminders = rows('SELECT r.*, p.address FROM reminders r LEFT JOIN properties p ON p.id = r.property_id ORDER BY r.status, r.due_on');

    return compact('properties', 'certificates', 'payments', 'tenants', 'messages', 'agreements', 'maintenance', 'documents', 'reminders') + [
        'receivedRent' => array_sum(array_map(fn(array $payment): float => $payment['status'] === 'received' ? (float) $payment['amount'] : 0, $payments)),
        'lateCount' => count(array_filter($payments, fn(array $payment): bool => $payment['status'] === 'late')),
        'dueCertificates' => count(array_filter($certificates, fn(array $certificate): bool => certificateStatus($certificate['expires_on'])[0] !== 'ok')),
        'draftCount' => count(array_filter($messages, fn(array $message): bool => $message['status'] === 'draft')),
        'latestDraft' => current(array_filter($messages, fn(array $message): bool => $message['status'] === 'draft')) ?: null,
        'openMaintenance' => count(array_filter($maintenance, fn(array $item): bool => $item['status'] !== 'completed')),
        'openReminders' => count(array_filter($reminders, fn(array $item): bool => $item['status'] !== 'done')),
    ];
}

function renderLandlordStart(string $active, string $title, string $eyebrow, string $lede, string $actions = ''): void
{
    global $config;
    $flash = takeFlash();
    $links = [
        'overview' => ['dashboard.php', 'Overview'],
        'properties' => ['properties.php', 'Properties'],
        'maintenance' => ['maintenance.php', 'Maintenance'],
        'compliance' => ['compliance.php', 'Compliance'],
        'tenants' => ['tenants.php', 'Tenants'],
        'rent' => ['rent.php', 'Rent tracker'],
        'inbox' => ['inbox.php', 'AI inbox'],
        'agreements' => ['agreements.php', 'Agreements'],
        'documents' => ['documents.php', 'Documents'],
        'reminders' => ['reminders.php', 'Reminders'],
    ];
    ?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> | <?= e($config['app_name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
    <script defer src="assets/app.js"></script>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="dashboard.php"><span class="brand-mark">S</span><span><strong>Sorel House</strong><small>Landlord operations</small></span></a>
        <nav class="nav" aria-label="Primary navigation">
            <?php foreach ($links as $key => [$href, $label]): ?><a href="<?= e($href) ?>" <?= $key === $active ? 'class="active"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
        </nav>
        <div class="sidebar-foot"><button class="theme-button" type="button" data-theme-toggle>Switch theme</button><a class="sign-out-link" href="logout.php">Sign out</a><small>England MVP &middot; Private landlords</small></div>
    </aside>
    <main>
        <header class="topbar"><div><p class="eyebrow"><?= e($eyebrow) ?></p><h1><?= e($title) ?></h1><p class="lede"><?= e($lede) ?></p></div><?= $actions ?></header>
        <?php if ($flash): ?><div class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
<?php
}

function renderLandlordEnd(array $properties): void
{
    ?>
        <footer><p>Sorel House MVP &middot; Use HTTPS and add landlord login before a public launch.</p></footer>
    </main>
</div>
<?= propertyDialog() ?>
<?= certificateDialog($properties) ?>
<?= tenantDialog($properties) ?>
<?= agreementDialog() ?>
</body>
</html>
<?php
}

function propertyDialog(): string
{
    return '<dialog id="property-dialog" class="wide-dialog"><form method="post"><button type="button" class="dialog-close" data-close>&times;</button><p class="eyebrow">Portfolio</p><h2>Add property profile</h2><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="action" value="add_property">' . propertyProfileFields() . '<button class="button primary">Save property</button></form></dialog>';
}

function propertyProfileFields(): string
{
    return '<div class="form-grid"><label>Address line 1<input name="address" required></label><label>Address line 2<input name="address_line_2"></label><label>Town or city<input name="town_city"></label><label>County<input name="county"></label><label>Postcode<input name="postcode" required></label><label>Property type<select name="property_type"><option>House</option><option>Flat</option><option>Maisonette</option><option>Bungalow</option><option>HMO</option><option>Other</option></select></label><label>Bedrooms<input name="bedrooms" type="number" min="0" max="99" value="1" required></label><label>Bathrooms<input name="bathrooms" type="number" min="0" max="99" value="1" required></label><label>Local authority<input name="local_authority"></label><label>Council tax band<input name="council_tax_band" maxlength="2"></label><label>Internal reference<input name="ownership_reference"></label></div><label>Access notes<textarea name="access_notes" rows="3" placeholder="Keys, alarm, entry instructions or contractor notes"></textarea></label><label>Emergency notes<textarea name="emergency_notes" rows="3" placeholder="Stopcock, fuse box, emergency contractor or important risks"></textarea></label>';
}

function certificateDialog(array $properties): string
{
    $options = '';
    foreach ($properties as $property) {
        $options .= '<option value="' . (int) $property['id'] . '">' . e($property['address']) . '</option>';
    }
    return '<dialog id="certificate-dialog"><form method="post"><button type="button" class="dialog-close" data-close>&times;</button><p class="eyebrow">Compliance</p><h2>Add certificate</h2><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="action" value="add_certificate"><label>Property<select name="property_id">' . $options . '</select></label><label>Certificate type<select name="type"><option>Gas safety</option><option>EICR</option><option>EPC</option><option>Smoke alarm check</option><option>Carbon monoxide alarm check</option><option>Other</option></select></label><label>Expires on<input type="date" name="expires_on" required></label><label>Notes<textarea name="notes" rows="3"></textarea></label><button class="button primary">Save certificate</button></form></dialog>';
}

function tenantDialog(array $properties): string
{
    $options = '';
    foreach ($properties as $property) {
        $options .= '<option value="' . (int) $property['id'] . '">' . e($property['address']) . '</option>';
    }
    return '<dialog id="tenant-dialog"><form method="post"><button type="button" class="dialog-close" data-close>&times;</button><p class="eyebrow">Rent tracker</p><h2>Add tenant</h2><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="action" value="add_tenant"><label>Property<select name="property_id">' . $options . '</select></label><label>Tenant name<input name="name" required></label><label>Email<input name="email" type="email"></label><label>Monthly rent<input name="monthly_rent" type="number" min="0" step=".01" required></label><label>Rent due day<input name="rent_due_day" type="number" min="1" max="28" value="1" required></label><button class="button primary">Save tenant</button></form></dialog>';
}

function agreementDialog(): string
{
    return '<dialog id="agreement-dialog"><form method="post"><button type="button" class="dialog-close" data-close>&times;</button><p class="eyebrow">England periodic tenancy</p><h2>Generate agreement draft</h2><input type="hidden" name="csrf" value="' . e(csrfToken()) . '"><input type="hidden" name="action" value="generate_agreement"><label>Landlord name<input name="landlord_name" required></label><label>Tenant name<input name="tenant_name" required></label><label>Property address<input name="property_address" required></label><label>Start date<input name="start_date" type="date" required></label><label>Monthly rent<input name="rent_amount" type="number" min="0" step=".01" required></label><label>Rent due day<input name="rent_due_day" type="number" min="1" max="28" value="1" required></label><button class="button primary">Generate draft</button></form></dialog>';
}
