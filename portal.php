<?php

declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

db();

$token = trim((string) ($_GET['token'] ?? ''));
$tenant = preg_match('/^[a-f0-9]{32}$/', $token)
    ? row('SELECT t.*, p.address FROM tenants t JOIN properties p ON p.id = t.property_id WHERE t.portal_token = ?', [$token])
    : null;

if (!$tenant) {
    http_response_code(404);
    exit('Tenant portal link not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrf();
        if (($_POST['action'] ?? '') === 'report_maintenance') {
            execute('INSERT INTO maintenance_requests (property_id, tenant_id, title, description, priority, status) VALUES (?, ?, ?, ?, ?, ?)', [
                $tenant['property_id'], $tenant['id'], requiredText('title', 'Issue title'), requiredText('description', 'Description'),
                in_array($_POST['priority'] ?? '', ['low', 'normal', 'urgent'], true) ? $_POST['priority'] : 'normal', 'reported',
            ]);
            flash('Maintenance request sent to your landlord.');
        } else {
            $body = requiredText('body', 'Message');
            execute('INSERT INTO messages (tenant_id, sender, body, status) VALUES (?, ?, ?, ?)', [$tenant['id'], 'tenant', $body, 'received']);
            $sourceMessageId = (int) db()->lastInsertId();
            execute('INSERT INTO messages (tenant_id, sender, body, status, source_message_id, generated_by) VALUES (?, ?, ?, ?, ?, ?)', [$tenant['id'], 'assistant', draftTenantReply($tenant, $body), 'draft', $sourceMessageId, aiProviderLabel()]);
            flash('Message sent. Your landlord will review the reply shortly.');
        }
    } catch (Throwable $error) {
        flash($error->getMessage(), 'error');
    }
    header('Location: portal.php?token=' . urlencode($token));
    exit;
}

$messages = rows(
    "SELECT * FROM messages WHERE tenant_id = ? AND (sender = 'tenant' OR status = 'approved') ORDER BY id",
    [$tenant['id']]
);
$maintenance = rows('SELECT * FROM maintenance_requests WHERE tenant_id = ? ORDER BY id DESC', [$tenant['id']]);
$payment = row('SELECT * FROM payments WHERE tenant_id = ? AND rent_month = ?', [$tenant['id'], date('Y-m-01')]);
$flash = takeFlash();
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tenant messages | Sorel House</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
    <script defer src="assets/app.js"></script>
</head>
<body class="portal-body">
<main class="portal-shell">
    <header class="portal-header">
        <a class="brand" href="portal.php?token=<?= e($token) ?>"><span class="brand-mark">S</span><span><strong>Sorel House</strong><small>Tenant portal</small></span></a>
        <button class="theme-button" type="button" data-theme-toggle>Switch theme</button>
    </header>
    <section>
        <p class="eyebrow"><?= e($tenant['address']) ?></p>
        <h1>Hello, <?= e($tenant['name']) ?>.</h1>
        <p class="lede">Message your landlord, report repairs and keep track of your current rent status. For emergencies, use the emergency contact details provided by your landlord.</p>
    </section>
    <?php if ($flash): ?><div class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
    <nav class="portal-nav"><a href="#overview">Overview</a><a href="#messages">Messages</a><a href="#maintenance">Maintenance</a></nav>
    <section class="portal-overview" id="overview">
        <article class="metric"><span>Monthly rent</span><strong>&pound;<?= number_format((float)$tenant['monthly_rent'],2) ?></strong><small>Due on day <?= (int)$tenant['rent_due_day'] ?></small></article>
        <article class="metric"><span>This month</span><strong class="<?= ($payment['status'] ?? 'pending') === 'received' ? 'positive' : 'warning' ?>"><?= e(ucfirst($payment['status'] ?? 'pending')) ?></strong><small>Rent tracker status</small></article>
        <article class="metric"><span>Open repairs</span><strong><?= count(array_filter($maintenance, fn(array $item): bool => $item['status'] !== 'completed')) ?></strong><small>Reported maintenance issues</small></article>
    </section>
    <h2 id="messages" class="portal-section-title">Messages</h2>
    <section class="portal-thread panel" aria-label="Conversation">
        <?php if (!$messages): ?><p class="empty">No messages yet.</p><?php endif; ?>
        <?php foreach ($messages as $message): ?>
            <article class="portal-message <?= e($message['sender']) ?>">
                <strong><?= $message['sender'] === 'tenant' ? 'You' : 'Landlord' ?></strong>
                <p><?= nl2br(e($message['body'])) ?></p>
                <small><?= e(date('j M, H:i', strtotime($message['created_at']))) ?></small>
            </article>
        <?php endforeach; ?>
    </section>
    <form class="panel portal-compose" method="post">
        <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" value="send_message">
        <label>New message<textarea name="body" rows="4" required placeholder="How can your landlord help?"></textarea></label>
        <button class="button primary" type="submit">Send message</button>
    </form>
    <h2 id="maintenance" class="portal-section-title">Maintenance</h2>
    <section class="portal-repairs">
        <?php if (!$maintenance): ?><p class="empty">No maintenance requests yet.</p><?php endif; ?>
        <?php foreach($maintenance as $item): ?><article class="panel repair-card"><p class="eyebrow"><?= e($item['status']) ?></p><h3><?= e($item['title']) ?></h3><p><?= e($item['description']) ?></p><span class="badge <?= $item['priority']==='urgent'?'expired':'due' ?>"><?= e(ucfirst($item['priority'])) ?></span></article><?php endforeach; ?>
    </section>
    <form class="panel portal-compose" method="post"><input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="report_maintenance"><label>Issue title<input name="title" required placeholder="e.g. Boiler not heating"></label><label>Description<textarea name="description" rows="4" required placeholder="Tell your landlord what is happening"></textarea></label><label>Priority<select name="priority"><option value="normal">Normal</option><option value="low">Low</option><option value="urgent">Urgent</option></select></label><button class="button primary">Report maintenance issue</button></form>
    <p class="legal-note">This private link gives access to your messages. Do not share it.</p>
</main>
</body>
</html>
