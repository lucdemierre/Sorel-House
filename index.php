<?php

declare(strict_types=1);
require __DIR__ . '/lib/public.php';
renderPublicStart('home');
?>
<main class="public-main">
    <section class="public-hero public-hero-image">
        <div class="public-hero-copy">
            <p class="eyebrow">For self-managing landlords in England</p>
            <h1>Run your properties with quiet control.</h1>
            <p>Sorel House brings compliance dates, rent records, tenant messages and agreement drafts into one calm private workspace.</p>
            <div class="public-actions"><a class="button primary" href="login.php">Open landlord desk</a><a class="button secondary" href="features.php">Explore the platform</a></div>
        </div>
    </section>
    <section class="public-editorial" data-reveal>
        <img src="assets/images/sorel-house-interior.png" alt="A refined property management desk at dusk">
        <div><p class="eyebrow">Built for the details</p><h2>A calmer way to stay on top of the work.</h2><p>Keep the recurring jobs visible: certificate renewals, rent follow-ups, repair requests, tenant messages and the documents that matter.</p><a class="text-button" href="features.php">Explore every feature</a></div>
    </section>
    <section class="public-section" data-reveal>
        <p class="eyebrow">One private desk</p><h2>Everything important, kept in view.</h2>
        <div class="public-grid">
            <article><span>01</span><h3>Compliance</h3><p>Record certificates and see what is expired or approaching renewal.</p></article>
            <article><span>02</span><h3>Rent</h3><p>Track received, pending and late payments without spreadsheets.</p></article>
            <article><span>03</span><h3>Tenant inbox</h3><p>Review messages and approve careful AI-assisted reply drafts.</p></article>
            <article><span>04</span><h3>Agreements</h3><p>Prepare first drafts for England periodic tenancies and legal review.</p></article>
        </div>
    </section>
    <section class="public-cta" data-reveal><p class="eyebrow">Landlord operations</p><h2>Less chasing. More certainty.</h2><p>Start with one portfolio view and build from there.</p><a class="button primary" href="login.php">Sign in to Sorel House</a></section>
</main>
<?php renderPublicEnd(); ?>
