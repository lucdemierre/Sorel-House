# Sorel House

Shared-hosting PHP MVP for self-managing private landlords in England.

## Included

- Compliance calendar with expired and due-soon checks
- Maintenance queue with tenant repair reporting
- Monthly rent tracker
- Tenant directory with editable tenancy details and resettable portal links
- Tenant portal with overview, messages, rent status and maintenance reporting
- Landlord inbox with server-side Claude reply drafts
- Assured periodic tenancy first-draft generator
- Document register and portfolio reminders
- Responsive light and dark interface
- Public homepage, features, pricing and about pages
- Session sign-in for the private landlord workspace
- Separate landlord pages for overview, properties, compliance, rent, inbox and agreements
- SQLite by default, with MySQL or MariaDB support through PDO

## Local run

Requires PHP 8.1+ with `pdo_sqlite`. From this folder:

```powershell
C:\xampp\php\php.exe -S localhost:8080
```

Then open `http://localhost:8080`.

The public website starts at `http://localhost:8080/index.php`. Sign in to open `dashboard.php`; each sidebar item opens a separate private page.

## Shared-hosting deployment

1. Upload the project files into the website directory.
2. Ensure `storage/` is writable by PHP.
3. Copy `config.example.php` to `config.php`.
4. Set `seed_demo_data` to `false` in `config.php` when you no longer want the starter records.
5. Change `landlord_email` and `landlord_password` in `config.php`.
6. Add your OpenRouter API key to `config.php` to enable AI-generated drafts.
7. Point the domain at `index.php` and enable HTTPS.

The default SQLite database is created automatically. To use MySQL or MariaDB, change the DSN in `config.php`:

```php
'dsn' => 'mysql:host=localhost;dbname=sorel_house;charset=utf8mb4',
'db_user' => 'your_database_user',
'db_password' => 'your_database_password',
```

Tables are created automatically on the first visit. Starter records are added while `seed_demo_data` is enabled.

Each tenant receives a private portal link from the landlord dashboard. Tenants can send messages there and see replies after the landlord approves an AI-assisted draft.

## Before public launch

This is an MVP, not a production tenancy-management service. Add landlord login, stronger tenant identity verification than private portal links, delivery through an email or messaging provider, database backups, audit logs, privacy documents and a solicitor-reviewed agreement template before handling real tenant data.

The compliance checker is an organiser, not legal advice. The current scope is standard private rentals in England; Scotland, Wales, Northern Ireland, HMOs and unusual tenancies need separate rule sets.

## Legal source

The agreement flow follows the current GOV.UK England guidance that assured shorthold tenancies cannot be created and assured periodic tenancies run on a rolling basis from 1 May 2026:

https://www.gov.uk/guidance/renting-out-your-property-guidance-for-landlords-and-letting-agents

## OpenRouter API

OpenRouter calls are made only from PHP. The browser never sees the API key. Copy `config.example.php` to `config.php`, then set:

```php
'ai_provider' => 'openrouter',
'openrouter_api_key' => 'your_key_here',
'openrouter_model' => 'nvidia/nemotron-nano-12b-v2-vl:free',
```

The direct Anthropic integration remains available by changing `ai_provider` to `anthropic`.

OpenRouter API documentation:

https://openrouter.ai/docs/api-reference/overview
