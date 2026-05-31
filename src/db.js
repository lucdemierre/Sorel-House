import { createClient } from "@libsql/client";
import { randomBytes } from "node:crypto";

let client;
let ready;
const isProduction = process.env.NODE_ENV === "production" || Boolean(process.env.VERCEL);
const databaseConfig = () => {
  const url = process.env.DATABASE_URL || (isProduction ? "" : "file:storage/sorel-house.db");
  const authToken = process.env.DATABASE_AUTH_TOKEN || undefined;
  if (!url) throw new Error("DATABASE_URL is required in production. Configure a hosted LibSQL database in Vercel.");
  if (isProduction && url.startsWith("file:")) throw new Error("DATABASE_URL must use a hosted LibSQL database in production. file: databases are not supported on Vercel.");
  if (isProduction && url.startsWith("libsql://") && !authToken) throw new Error("DATABASE_AUTH_TOKEN is required for a hosted LibSQL database in production.");
  return { url, authToken };
};
const db = () => client ||= createClient({
  ...databaseConfig(),
});

const sql = (statement, args = []) => ({ sql: statement, args });
const today = () => new Date().toISOString().slice(0, 10);
const month = () => `${today().slice(0, 7)}-01`;
const future = (days) => {
  const date = new Date();
  date.setDate(date.getDate() + days);
  return date.toISOString().slice(0, 10);
};

async function migrate() {
  await db().batch([
    sql("CREATE TABLE IF NOT EXISTS properties (id INTEGER PRIMARY KEY AUTOINCREMENT, address TEXT NOT NULL, address_line_2 TEXT NOT NULL DEFAULT '', town_city TEXT NOT NULL DEFAULT '', county TEXT NOT NULL DEFAULT '', postcode TEXT NOT NULL, property_type TEXT NOT NULL DEFAULT 'House', bedrooms INTEGER NOT NULL DEFAULT 1, bathrooms INTEGER NOT NULL DEFAULT 1, local_authority TEXT NOT NULL DEFAULT '', council_tax_band TEXT NOT NULL DEFAULT '', ownership_reference TEXT NOT NULL DEFAULT '', access_notes TEXT NOT NULL DEFAULT '', emergency_notes TEXT NOT NULL DEFAULT '', created_at TEXT DEFAULT CURRENT_TIMESTAMP)"),
    sql("CREATE TABLE IF NOT EXISTS certificates (id INTEGER PRIMARY KEY AUTOINCREMENT, property_id INTEGER NOT NULL, type TEXT NOT NULL, expires_on TEXT NOT NULL, notes TEXT NOT NULL DEFAULT '', created_at TEXT DEFAULT CURRENT_TIMESTAMP)"),
    sql("CREATE TABLE IF NOT EXISTS tenants (id INTEGER PRIMARY KEY AUTOINCREMENT, property_id INTEGER NOT NULL, name TEXT NOT NULL, email TEXT NOT NULL DEFAULT '', monthly_rent REAL NOT NULL, rent_due_day INTEGER NOT NULL DEFAULT 1, portal_token TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT 'active', created_at TEXT DEFAULT CURRENT_TIMESTAMP)"),
    sql("CREATE TABLE IF NOT EXISTS payments (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, rent_month TEXT NOT NULL, amount REAL NOT NULL, status TEXT NOT NULL DEFAULT 'pending', paid_on TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)"),
    sql("CREATE TABLE IF NOT EXISTS messages (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, sender TEXT NOT NULL, body TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'received', source_message_id INTEGER, review_note TEXT NOT NULL DEFAULT '', generated_by TEXT NOT NULL DEFAULT '', created_at TEXT DEFAULT CURRENT_TIMESTAMP)"),
    sql("CREATE TABLE IF NOT EXISTS agreements (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_name TEXT NOT NULL, property_address TEXT NOT NULL, rent_amount REAL NOT NULL, rent_due_day INTEGER NOT NULL, start_date TEXT NOT NULL, draft TEXT NOT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP)"),
    sql("CREATE TABLE IF NOT EXISTS maintenance_requests (id INTEGER PRIMARY KEY AUTOINCREMENT, property_id INTEGER NOT NULL, tenant_id INTEGER, title TEXT NOT NULL, description TEXT NOT NULL, priority TEXT NOT NULL DEFAULT 'normal', status TEXT NOT NULL DEFAULT 'reported', created_at TEXT DEFAULT CURRENT_TIMESTAMP)"),
    sql("CREATE TABLE IF NOT EXISTS documents (id INTEGER PRIMARY KEY AUTOINCREMENT, property_id INTEGER NOT NULL, name TEXT NOT NULL, category TEXT NOT NULL DEFAULT 'General', notes TEXT NOT NULL DEFAULT '', created_at TEXT DEFAULT CURRENT_TIMESTAMP)"),
    sql("CREATE TABLE IF NOT EXISTS reminders (id INTEGER PRIMARY KEY AUTOINCREMENT, property_id INTEGER, title TEXT NOT NULL, due_on TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'open', created_at TEXT DEFAULT CURRENT_TIMESTAMP)"),
    sql("CREATE UNIQUE INDEX IF NOT EXISTS payments_tenant_month_unique ON payments (tenant_id, rent_month)"),
    sql("CREATE UNIQUE INDEX IF NOT EXISTS tenants_portal_token_unique ON tenants (portal_token)")
  ], "write");
  if ((await one("SELECT COUNT(*) AS count FROM properties")).count === 0 && process.env.SEED_DEMO_DATA !== "false") await seed();
  await ensureCurrentPayments();
}

async function seed() {
  await db().batch([
    sql("INSERT INTO properties (address, postcode) VALUES (?, ?)", ["12 Albert Road", "N7 8QJ"]),
    sql("INSERT INTO properties (address, postcode) VALUES (?, ?)", ["4 Camden Mews", "NW1 9BY"]),
    sql("INSERT INTO properties (address, postcode) VALUES (?, ?)", ["77 Holloway Street", "N7 6JP"]),
    sql("INSERT INTO properties (address, postcode) VALUES (?, ?)", ["9 Oak House", "E8 3RT"]),
    sql("INSERT INTO certificates (property_id,type,expires_on) VALUES (?,?,?)", [1, "Gas safety", future(13)]),
    sql("INSERT INTO certificates (property_id,type,expires_on) VALUES (?,?,?)", [2, "EICR", future(548)]),
    sql("INSERT INTO certificates (property_id,type,expires_on) VALUES (?,?,?)", [3, "EPC", future(-6)]),
    sql("INSERT INTO certificates (property_id,type,expires_on) VALUES (?,?,?)", [4, "Smoke alarm check", future(6)]),
    ...[
      [2, "Sarah Lee", "sarah@example.com", 1450, 1],
      [1, "Daniel Ross", "daniel@example.com", 1125, 1],
      [4, "Maya Khan", "maya@example.com", 1600, 28],
      [3, "Emily Carter", "emily@example.com", 1325, 1]
    ].map(([property, name, email, rent, day]) => sql("INSERT INTO tenants (property_id,name,email,monthly_rent,rent_due_day,portal_token) VALUES (?,?,?,?,?,?)", [property, name, email, rent, day, randomBytes(16).toString("hex")])),
    sql("INSERT INTO maintenance_requests (property_id,tenant_id,title,description,priority,status) VALUES (3,4,'Boiler fault','Tenant reports the boiler has stopped working again.','urgent','reported')"),
    sql("INSERT INTO maintenance_requests (property_id,tenant_id,title,description,priority,status) VALUES (1,2,'Hallway light','Replace the hallway fitting and check the switch.','normal','scheduled')"),
    sql("INSERT INTO documents (property_id,name,category,notes) VALUES (1,'Gas safety certificate','Safety','Latest certificate copy held offline.')"),
    sql("INSERT INTO reminders (property_id,title,due_on) VALUES (1,'Book gas engineer',?)", [future(5)])
  ], "write");
}

export async function initDb() {
  ready ||= migrate();
  await ready;
}
export async function rows(statement, args = []) {
  const result = await db().execute(sql(statement, args));
  return result.rows.map((row) => ({ ...row }));
}
export async function one(statement, args = []) {
  return (await rows(statement, args))[0] || null;
}
export async function run(statement, args = []) {
  return db().execute(sql(statement, args));
}
export async function ensureCurrentPayments() {
  await run("INSERT OR IGNORE INTO payments (tenant_id,rent_month,amount,status) SELECT id,?,monthly_rent,'pending' FROM tenants", [month()]);
}
export { month, today };
