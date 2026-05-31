const featureCards = [
  ["01", "Portfolio overview", "Keep full property profiles, local authority details, access notes and tenancy records together."],
  ["02", "Compliance calendar", "Track certificates and see what is expired, due soon or comfortably in date."],
  ["03", "Rent tracker", "See the current month clearly and update received, pending or late payments in seconds."],
  ["04", "Tenant portals", "Give each tenant a private space for messages, rent status and repair reporting."],
  ["05", "AI-assisted inbox", "Prepare careful replies, then approve, redo or decline each draft before it is used."],
  ["06", "Maintenance desk", "Capture issues with a priority, property and tenant, then follow progress through completion."],
  ["07", "Agreement drafts", "Prepare an England periodic-tenancy first draft for review without starting from a blank page."],
  ["08", "Documents and reminders", "Keep practical records and upcoming actions visible before they become urgent."]
];

const cards = (items = featureCards) => `<div class="public-grid">${items.map(([number, title, copy]) => `<article><span>${number}</span><h3>${title}</h3><p>${copy}</p></article>`).join("")}</div>`;
const hero = (eyebrow, title, copy, actions = "") => `<section class="public-page-hero public-animated-hero"><div><p class="eyebrow">${eyebrow}</p><h1>${title}</h1><p>${copy}</p>${actions}</div><p class="public-hero-index" aria-hidden="true">S/H</p></section>`;

export const homeBody = `
  <section class="public-hero public-hero-image">
    <div class="public-hero-copy">
      <p class="eyebrow">For self-managing landlords in England</p>
      <h1>Run your properties with quiet control.</h1>
      <p>Sorel House brings compliance dates, rent records, repair requests and tenant messages into one calm private workspace.</p>
      <div class="public-actions"><a class="button primary" href="/login">Open landlord desk</a><a class="button secondary" href="/features">Explore the platform</a></div>
    </div>
  </section>
  <section class="public-editorial" data-reveal>
    <img src="/assets/images/sorel-house-interior.png" alt="A refined property management desk at dusk">
    <div><p class="eyebrow">Built for the details</p><h2>A calmer way to stay on top of the work.</h2><p>Keep recurring jobs visible: certificate renewals, rent follow-ups, repair requests, tenant messages and the documents that matter.</p><a class="text-button" href="/features">Explore every feature</a></div>
  </section>
  <section class="public-section" data-reveal>
    <p class="eyebrow">One private desk</p><h2>Everything important, kept in view.</h2>
    ${cards(featureCards.slice(0, 4))}
  </section>
  <section class="public-statement" data-reveal><p class="eyebrow">Independent, not improvised</p><h2>Designed around the jobs that come back every month.</h2></section>
  <section class="public-cta" data-reveal><p class="eyebrow">Landlord operations</p><h2>Less chasing. More certainty.</h2><p>Start with one portfolio view and build from there.</p><a class="button primary" href="/login">Sign in to Sorel House</a></section>`;

export const featuresBody = `
  ${hero("Features", "A clearer way to manage the details.", "One composed workspace for the recurring work that otherwise disappears into inboxes, calendars and spreadsheets.")}
  <section class="public-section" data-reveal><p class="eyebrow">The working desk</p><h2>Useful on an ordinary Tuesday.</h2>${cards()}</section>
  <section class="public-editorial public-editorial-reverse" data-reveal><div><p class="eyebrow">Human approval stays central</p><h2>AI drafts without handing over control.</h2><p>Tenant replies are prepared for review. You can approve the response, decline it or regenerate it with clearer guidance while keeping the history visible.</p><a class="text-button" href="/login">Open the landlord desk</a></div><img src="/assets/images/sorel-house-interior.png" alt="A calm interior workspace"></section>
  <section class="public-cta" data-reveal><p class="eyebrow">Start with the essentials</p><h2>Your portfolio, properly organised.</h2><a class="button primary" href="/login">Sign in to Sorel House</a></section>`;

export const pricingBody = `
  ${hero("Pricing", "Simple enough to understand.", "A focused product for landlords who need a reliable operating desk, not another complicated agency platform.")}
  <section class="pricing-grid" data-reveal>
    <article><p class="eyebrow">Starter</p><h2>&pound;12<span>/month</span></h2><p>For a small portfolio.</p><ul><li>Up to 3 properties</li><li>Compliance calendar</li><li>Rent tracker</li><li>Maintenance desk</li></ul><a class="button secondary" href="/login">Start testing</a></article>
    <article class="featured"><p class="eyebrow">Portfolio</p><h2>&pound;24<span>/month</span></h2><p>For landlords managing more moving parts.</p><ul><li>Up to 15 properties</li><li>Tenant portals</li><li>AI-assisted inbox</li><li>Agreement drafts</li><li>Documents and reminders</li></ul><a class="button primary" href="/login">Start testing</a></article>
  </section>
  <section class="public-section" data-reveal><p class="eyebrow">No agency overhead</p><h2>Built for the landlord who still makes the decisions.</h2>${cards([["01","A single workspace","Keep the operational picture together."],["02","Clear monthly cost","Simple launch tiers without hidden complexity."],["03","Room to grow","Add services as the product matures."],["04","Landlord oversight","Keep approval and judgment with the person responsible."]])}</section>`;

export const aboutBody = `
  ${hero("About Sorel House", "Built for landlords who still manage the details themselves.", "Letting-agent software often assumes a large operation. Sorel House starts with a simpler question: what does an independent landlord need to keep under control every month?")}
  <section class="public-editorial" data-reveal><img src="/assets/images/sorel-house-interior.png" alt="A quiet, considered property interior"><div><p class="eyebrow">A focused first version</p><h2>The practical work, brought into one place.</h2><p>The platform supports standard private rentals in England. It organises recurring operational work and prepares careful first drafts while leaving legal judgment and final approval with the landlord.</p></div></section>
  <section class="public-section" data-reveal><p class="eyebrow">Product principles</p><h2>Calm software for real responsibilities.</h2>${cards([["01","Clarity first","Show what needs attention without turning the dashboard into noise."],["02","Approval matters","Make automation useful while keeping the landlord in control."],["03","Operational depth","Store the details needed when a repair, renewal or tenancy question arrives."],["04","Build deliberately","Expand into stronger accounts, delivery and audit records as the service matures."]])}</section>
  <section class="public-cta" data-reveal><p class="eyebrow">Private landlord operations</p><h2>Start with a clearer view.</h2><a class="button primary" href="/features">Explore the platform</a></section>`;
