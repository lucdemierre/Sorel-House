# Sorel House deployment

## Vercel deployment

Sorel House now runs as a Node.js Express application. Vercel routes requests through `api/index.js`.

The production database must be a hosted LibSQL-compatible database such as Turso. Do not use the local `file:` database URL on Vercel because Vercel Functions do not provide persistent writable storage.

1. Create a hosted LibSQL database.
2. Import this repository into Vercel.
3. Add the environment variables below in Vercel Project Settings.
4. Deploy.
5. Open `/login` and test a write operation.

Required production variables:

```text
DATABASE_URL=libsql://your-database-host
DATABASE_AUTH_TOKEN=your-database-token
SESSION_SECRET=replace-with-a-long-random-value
LANDLORD_EMAIL=your-login-email
LANDLORD_PASSWORD=your-unique-password
SEED_DEMO_DATA=false
OPENROUTER_API_KEY=your-openrouter-key
OPENROUTER_MODEL=nvidia/nemotron-nano-12b-v2-vl:free
OPENROUTER_SITE_URL=https://your-domain.example
```

Keep tokens and passwords in Vercel environment variables. Do not commit `.env`, `config.php` or database files.

## Local development

Copy `.env.example` to `.env`, then run:

```powershell
npm.cmd install
npm.cmd run dev
```

Open `http://localhost:8080`.

The default local database is `storage/sorel-house.db`. It is created automatically and ignored by Git.
