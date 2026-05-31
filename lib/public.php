<?php

declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

function renderPublicStart(string $active): void
{
    $links = [
        'home' => ['index.php', 'Home'],
        'features' => ['features.php', 'Features'],
        'pricing' => ['pricing.php', 'Pricing'],
        'about' => ['about.php', 'About'],
    ];
    ?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sorel House | Landlord operations</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css"><script defer src="assets/app.js"></script>
</head>
<body class="public-body">
<header class="public-header">
    <span class="scroll-progress" aria-hidden="true"></span>
    <a class="brand" href="index.php"><span class="brand-mark">S</span><span><strong>Sorel House</strong><small>Landlord operations</small></span></a>
    <nav class="public-nav"><?php foreach ($links as $key => [$href, $label]): ?><a href="<?= e($href) ?>" <?= $key === $active ? 'class="active"' : '' ?>><?= e($label) ?></a><?php endforeach; ?></nav>
    <a class="button primary" href="<?= isLandlordSignedIn() ? 'dashboard.php' : 'login.php' ?>"><?= isLandlordSignedIn() ? 'Open dashboard' : 'Sign in' ?></a>
</header>
<?php
}

function renderPublicEnd(): void
{
    ?>
<footer class="public-footer"><a class="brand" href="index.php"><span class="brand-mark">S</span><span><strong>Sorel House</strong><small>Landlord operations</small></span></a><p>Built for self-managing landlords in England. Organisational support, not legal advice.</p></footer>
</body>
</html>
<?php
}
