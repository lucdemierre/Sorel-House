<?php

declare(strict_types=1);
require __DIR__ . '/lib/landlord.php';
handleLandlordPost('inbox.php');
extract(landlordData());

renderLandlordStart('inbox', 'AI inbox', 'Tenant support', 'Review tenant messages, prepare careful drafts and approve replies before they appear in the tenant portal.');
?>
<section class="split">
    <div class="panel conversation-list">
        <?php foreach ($messages as $message): ?>
            <article class="message-card <?= e($message['sender']) ?>"><div><strong><?= e($message['name']) ?></strong><span><?= e($message['address']) ?></span></div><p><?= nl2br(e($message['body'])) ?></p><small><?= e(ucfirst($message['sender'])) ?> &middot; <?= e(date('j M, H:i', strtotime($message['created_at']))) ?> &middot; <?= e($message['status']) ?></small></article>
        <?php endforeach; ?>
    </div>
    <aside class="panel compose-panel">
        <p class="eyebrow">Claude-assisted</p><h2>Prepare a reply</h2>
        <form method="post"><input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="tenant_message"><label>Tenant<select name="tenant_id" required><?php foreach ($tenants as $tenant): ?><option value="<?= (int) $tenant['id'] ?>"><?= e($tenant['name']) ?> &middot; <?= e($tenant['address']) ?></option><?php endforeach; ?></select></label><label>Tenant message<textarea name="body" rows="5" required placeholder="Paste or record the tenant's message here"></textarea></label><button class="button primary" type="submit">Create reply draft</button></form>
        <?php if ($latestDraft): ?><div class="draft-box"><small>Latest draft &middot; <?= e($latestDraft['generated_by'] ?: 'Local fallback') ?></small><p><?= nl2br(e($latestDraft['body'])) ?></p><div class="draft-actions"><form method="post"><input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="approve_message"><input type="hidden" name="message_id" value="<?= (int) $latestDraft['id'] ?>"><button class="button primary" type="submit">Approve reply</button></form><button class="button secondary" type="button" data-open="redo-draft-dialog">Redo draft</button><button class="button secondary" type="button" data-open="decline-draft-dialog">Decline</button></div></div><?php endif; ?>
        <div class="portal-links"><small>Tenant portal links</small><?php foreach ($tenants as $tenant): ?><a href="portal.php?token=<?= e($tenant['portal_token']) ?>" target="_blank" rel="noopener"><?= e($tenant['name']) ?> &middot; <?= e($tenant['address']) ?></a><?php endforeach; ?></div>
    </aside>
</section>
<?php if ($latestDraft): ?>
<dialog id="redo-draft-dialog"><form method="post"><button type="button" class="dialog-close" data-close>&times;</button><p class="eyebrow">AI review</p><h2>Redo draft</h2><input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="regenerate_message"><input type="hidden" name="message_id" value="<?= (int) $latestDraft['id'] ?>"><label>Optional guidance<textarea name="review_note" rows="4" placeholder="Example: make it shorter, ask for a photo, do not promise a contractor date"></textarea></label><button class="button primary">Generate revised draft</button></form></dialog>
<dialog id="decline-draft-dialog"><form method="post"><button type="button" class="dialog-close" data-close>&times;</button><p class="eyebrow">AI review</p><h2>Decline draft</h2><input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="decline_message"><input type="hidden" name="message_id" value="<?= (int) $latestDraft['id'] ?>"><label>Reason<textarea name="review_note" rows="4" placeholder="Keep a note for the audit history"></textarea></label><button class="button secondary">Decline and keep record</button></form></dialog>
<?php endif; ?>
<?php renderLandlordEnd($properties); ?>
