<?php

declare(strict_types=1);
require __DIR__ . '/lib/public.php';

if (isLandlordSignedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrf();
        $email = strtolower(requiredText('email', 'Email'));
        $password = requiredText('password', 'Password');
        if (!hash_equals(strtolower((string) $config['landlord_email']), $email) || !hash_equals((string) $config['landlord_password'], $password)) {
            throw new RuntimeException('Email or password is incorrect.');
        }
        session_regenerate_id(true);
        $_SESSION['landlord_signed_in'] = true;
        header('Location: dashboard.php');
        exit;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

renderPublicStart('');
?>
<main class="public-main"><section class="login-panel"><p class="eyebrow">Private landlord desk</p><h1>Sign in.</h1><p>Use the MVP account configured on the server.</p><?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>"><label>Email<input type="email" name="email" required autocomplete="email"></label><label>Password<input type="password" name="password" required autocomplete="current-password"></label><button class="button primary">Open dashboard</button></form><small>Local demo: landlord@example.com / change-me</small></section></main>
<?php renderPublicEnd(); ?>
