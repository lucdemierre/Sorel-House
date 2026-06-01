# Sorel House

Vercel-ready Node.js MVP for self-managing private landlords in England.

## Included

- Compliance calendar with expired and due-soon checks
- Maintenance queue with tenant repair reporting
- Monthly rent tracker
- Tenant directory with editable tenancy details and resettable portal links
- Tenant portal with overview, messages, rent status and maintenance reporting
- Homeowner portal for portfolio administration
- Service desk portal for customer support, repair triage and reply approvals
- Landlord inbox with server-side Claude reply drafts
- Assured periodic tenancy first-draft generator
- Document register and portfolio reminders
- Responsive light and dark interface
- Public homepage, features, pricing and about pages
- Session sign-in for the private landlord workspace
- Separate landlord pages for overview, properties, compliance, rent, inbox and agreements
- Local LibSQL database by default, with hosted LibSQL for Vercel

## Local run

Requires Node.js. From this folder:

```powershell
npm.cmd install
npm.cmd run dev
```

Then open `http://localhost:8080`.

The public website starts at `http://localhost:8080`. Sign in to open `/dashboard`; each sidebar item opens a separate private page.

Portal entry points:

- Homeowner portal: `/login`
- Service desk: `/admin/login`
- Tenant portal: private `/portal?token=...` links issued from the homeowner workspace

## Deployment

The application is configured for Vercel. Use a hosted LibSQL-compatible database in production and add secrets through Vercel environment variables. See `DEPLOYMENT.md`.

Each tenant receives a private portal link from the landlord dashboard. Tenants can send messages there and see replies after the landlord approves an AI-assisted draft.

## Before public launch

This is an MVP, not a production tenancy-management service. Add a proper multi-account authentication system, stronger tenant identity verification than private portal links, delivery through an email or messaging provider, database backups, audit logs, privacy documents and a solicitor-reviewed agreement template before handling real tenant data.

The compliance checker is an organiser, not legal advice. The current scope is standard private rentals in England; Scotland, Wales, Northern Ireland, HMOs and unusual tenancies need separate rule sets.

## Legal source

The agreement flow follows the current GOV.UK England guidance that assured shorthold tenancies cannot be created and assured periodic tenancies run on a rolling basis from 1 May 2026:

https://www.gov.uk/guidance/renting-out-your-property-guidance-for-landlords-and-letting-agents

## OpenRouter API

OpenRouter calls are made only from the Node server. The browser never sees the API key. Set `OPENROUTER_API_KEY`, `OPENROUTER_MODEL` and `OPENROUTER_SITE_URL` in `.env` locally or in Vercel Project Settings.

OpenRouter API documentation:

https://openrouter.ai/docs/api-reference/overview
