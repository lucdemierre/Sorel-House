import express from "express";
import cookieSession from "cookie-session";
import { randomBytes, timingSafeEqual } from "node:crypto";
import { initDb, rows, one, run, ensureCurrentPayments, month, today } from "./db.js";
import { publicPage, loginPage, landlordPage, pageBody, portalPage } from "./views.js";
import { homeBody, featuresBody, pricingBody, aboutBody } from "./marketing.js";

try { process.loadEnvFile?.(".env"); } catch {}

const app = express();
app.set("trust proxy", 1);
const config = {
  landlordEmail: (process.env.LANDLORD_EMAIL || "landlord@example.com").toLowerCase(),
  landlordPassword: process.env.LANDLORD_PASSWORD || "change-me",
  openRouterKey: process.env.OPENROUTER_API_KEY || "",
  openRouterModel: process.env.OPENROUTER_MODEL || "nvidia/nemotron-nano-12b-v2-vl:free",
  siteUrl: process.env.OPENROUTER_SITE_URL || "http://localhost:8080",
};
const pageMeta = {
  dashboard: ["Stay on top of every tenancy.", "Portfolio command centre", "Track compliance, rent and tenant messages without a letting agent.", '<div class="top-actions"><button class="button secondary" data-open="property-dialog">Add property</button><button class="button primary" data-open="tenant-dialog">Add tenant</button></div>'],
  properties: ["Properties", "Portfolio", "Manage the homes in your portfolio and keep operational details together.", '<button class="button primary" data-open="property-dialog">Add property</button>'],
  maintenance: ["Maintenance", "Repairs and issues", "Track reported problems from first message through to completion.", ""],
  compliance: ["Compliance calendar", "Legal checks", "Record certificate dates and spot upcoming requirements early.", '<button class="button primary" data-open="certificate-dialog">Add certificate</button>'],
  tenants: ["Tenants", "People and tenancies", "Manage tenant details, rent terms and private portal links.", '<button class="button primary" data-open="tenant-dialog">Add tenant</button>'],
  rent: ["Rent tracker", "This month", "See received, pending and late payments.", '<button class="button primary" data-open="tenant-dialog">Add tenant</button>'],
  inbox: ["AI inbox", "Tenant support", "Review tenant messages and approve reply drafts.", ""],
  agreements: ["Agreement generator", "First draft only", "Prepare periodic tenancy first drafts for review.", ""],
  documents: ["Documents", "Property records", "Keep a register of important property documents.", ""],
  reminders: ["Reminders", "Upcoming tasks", "Keep practical follow-ups visible.", ""],
};

app.disable("x-powered-by");
app.use(express.urlencoded({ extended: false }));
app.use(cookieSession({ name: "sorel", keys: [process.env.SESSION_SECRET || "local-development-change-me"], httpOnly: true, sameSite: "lax", secure: process.env.NODE_ENV === "production", maxAge: 7 * 86400000 }));
app.use("/assets", express.static("public/assets", { maxAge: "1d" }));
app.use((req, res, next) => {
  res.set({ "X-Content-Type-Options": "nosniff", "X-Frame-Options": "SAMEORIGIN", "Referrer-Policy": "strict-origin-when-cross-origin" });
  next();
});
const equal = (left, right) => {
  const a = Buffer.from(String(left)); const b = Buffer.from(String(right));
  return a.length === b.length && timingSafeEqual(a, b);
};
const csrf = (req) => req.session.csrf ||= randomBytes(24).toString("hex");
const verifyCsrf = (req) => { if (!equal(req.session.csrf || "", req.body.csrf || "")) throw new Error("Your session expired. Please refresh and try again."); };
const flash = (req, message, type = "success") => { req.session.flash = { message, type }; };
const takeFlash = (req) => { const value = req.session.flash; delete req.session.flash; return value; };
const signedIn = (req) => req.session.landlordSignedIn === true;
const requireLandlord = (req, res, next) => signedIn(req) ? next() : res.redirect("/login");
const text = (body, key, label) => { const value = String(body[key] || "").trim(); if (!value) throw new Error(`${label} is required.`); return value; };
const id = (body, key, label) => { const value = Number.parseInt(body[key], 10); if (!Number.isInteger(value) || value < 1) throw new Error(`${label} is required.`); return value; };
const money = (body, key, label) => { const value = Number(body[key]); if (!Number.isFinite(value) || value < 0) throw new Error(`${label} must be zero or more.`); return value; };
const day = (body, key, label) => { const value = id(body, key, label); if (value > 28) throw new Error(`${label} must be between 1 and 28.`); return value; };
const validDate = (body, key, label) => { const value = text(body,key,label); if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) throw new Error(`${label} must be a valid date.`); return value; };
const statusIn = (value, allowed, fallback) => allowed.includes(value) ? value : fallback;

function certificateStatus(expires) {
  const days = Math.floor((new Date(`${expires}T12:00:00`) - new Date(`${today()}T12:00:00`)) / 86400000);
  return days < 0 ? { status: "expired", label: "Expired", days } : days <= 30 ? { status: "due", label: "Due soon", days } : { status: "ok", label: "Compliant", days };
}
async function landlordData() {
  const properties = await rows("SELECT p.*, COUNT(DISTINCT t.id) tenant_count, COUNT(DISTINCT c.id) certificate_count FROM properties p LEFT JOIN tenants t ON t.property_id=p.id LEFT JOIN certificates c ON c.property_id=p.id GROUP BY p.id ORDER BY p.address");
  const certificates = (await rows("SELECT c.*,p.address FROM certificates c JOIN properties p ON p.id=c.property_id ORDER BY c.expires_on")).map(x => ({ ...x, compliance: certificateStatus(x.expires_on) }));
  const payments = await rows("SELECT pay.*,t.name,t.rent_due_day,p.address FROM payments pay JOIN tenants t ON t.id=pay.tenant_id JOIN properties p ON p.id=t.property_id WHERE pay.rent_month=? ORDER BY t.name", [month()]);
  const tenants = await rows("SELECT t.*,p.address FROM tenants t JOIN properties p ON p.id=t.property_id ORDER BY t.name");
  const messages = await rows("SELECT m.*,t.name,p.address FROM messages m JOIN tenants t ON t.id=m.tenant_id JOIN properties p ON p.id=t.property_id ORDER BY m.id DESC LIMIT 20");
  const agreements = await rows("SELECT * FROM agreements ORDER BY id DESC LIMIT 12");
  const maintenance = await rows("SELECT m.*,p.address,t.name tenant_name FROM maintenance_requests m JOIN properties p ON p.id=m.property_id LEFT JOIN tenants t ON t.id=m.tenant_id ORDER BY CASE m.priority WHEN 'urgent' THEN 0 WHEN 'normal' THEN 1 ELSE 2 END,m.id DESC");
  const documents = await rows("SELECT d.*,p.address FROM documents d JOIN properties p ON p.id=d.property_id ORDER BY d.id DESC");
  const reminders = await rows("SELECT r.*,p.address FROM reminders r LEFT JOIN properties p ON p.id=r.property_id ORDER BY r.status,r.due_on");
  return { properties, certificates, payments, tenants, messages, agreements, maintenance, documents, reminders,
    receivedRent: payments.filter(x=>x.status==="received").reduce((sum,x)=>sum+Number(x.amount),0),
    dueCertificates: certificates.filter(x=>x.compliance.status!=="ok").length,
    draftCount: messages.filter(x=>x.status==="draft").length,
    latestDraft: messages.find(x=>x.status==="draft"),
    openMaintenance: maintenance.filter(x=>x.status!=="completed").length,
    openReminders: reminders.filter(x=>x.status!=="done").length };
}
async function askOpenRouter(system, prompt, maxTokens = 900) {
  if (!config.openRouterKey) return null;
  const response = await fetch("https://openrouter.ai/api/v1/chat/completions", { method: "POST", headers: { Authorization: `Bearer ${config.openRouterKey}`, "Content-Type": "application/json", "HTTP-Referer": config.siteUrl, "X-OpenRouter-Title": "Sorel House" }, body: JSON.stringify({ model: config.openRouterModel, max_tokens: maxTokens, messages: [{ role: "system", content: system }, { role: "user", content: prompt }] }) });
  if (!response.ok) return null;
  return (await response.json()).choices?.[0]?.message?.content || null;
}
async function draftReply(tenant, message, guidance = "") {
  return await askOpenRouter("Draft concise professional replies for an England residential landlord. Do not invent bookings. Return only the reply.", `Tenant: ${tenant.name}\nMessage: ${message}${guidance ? `\nLandlord revision guidance: ${guidance}` : ""}`) || `Hi ${tenant.name},\n\nThank you for your message. I have received it and will review the details before confirming the next step.\n\nKind regards`;
}
const fallbackAgreement = (input) => `DRAFT FOR REVIEW

ASSURED PERIODIC TENANCY AGREEMENT

Landlord: ${input.landlord_name}
Tenant: ${input.tenant_name}
Property: ${input.property_address}
Start date: ${input.start_date}
Monthly rent: GBP ${input.rent_amount.toFixed(2)}, due on day ${input.rent_due_day}

This is a rolling monthly tenancy draft. The parties must review deposit protection, repairs, access, notices, obligations and signature requirements before use.

This draft requires legal review.`;

app.get("/", (req,res) => res.send(publicPage("home", homeBody, signedIn(req))));
app.get("/features", (req,res) => res.send(publicPage("features", featuresBody, signedIn(req))));
app.get("/pricing", (req,res) => res.send(publicPage("pricing", pricingBody, signedIn(req))));
app.get("/about", (req,res) => res.send(publicPage("about", aboutBody, signedIn(req))));
app.get("/login", (req,res) => signedIn(req) ? res.redirect("/dashboard") : res.send(loginPage(csrf(req))));
app.post("/login", (req,res) => { try { verifyCsrf(req); if (!equal(String(req.body.email||"").toLowerCase(), config.landlordEmail) || !equal(req.body.password||"", config.landlordPassword)) throw new Error("Email or password is incorrect."); req.session.landlordSignedIn=true; res.redirect("/dashboard"); } catch(error) { res.status(401).send(loginPage(csrf(req),error.message)); } });
app.get("/logout", (req,res) => { req.session=null; res.redirect("/"); });
app.use(async (req, res, next) => {
  try { await initDb(); next(); } catch (error) { next(error); }
});

for (const page of Object.keys(pageMeta)) {
  app.get(`/${page}`, requireLandlord, async (req,res,next) => { try { const data=await landlordData(); const [title,eyebrow,lede,actions]=pageMeta[page]; res.send(landlordPage(page,title,eyebrow,lede,actions,pageBody(page,data,csrf(req)),data,csrf(req),takeFlash(req))); } catch(error){ next(error); } });
  app.post(`/${page}`, requireLandlord, async (req,res) => handleAction(req,res,page));
}

async function handleAction(req,res,page) {
  try {
    verifyCsrf(req); const b=req.body; const action=b.action;
    if(action==="add_property") await run("INSERT INTO properties (address,address_line_2,town_city,county,postcode,property_type,bedrooms,bathrooms,local_authority,council_tax_band,ownership_reference,access_notes,emergency_notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",[text(b,"address","Address"),b.address_line_2||"",b.town_city||"",b.county||"",text(b,"postcode","Postcode").toUpperCase(),text(b,"property_type","Property type"),Number(b.bedrooms||1),Number(b.bathrooms||1),b.local_authority||"",String(b.council_tax_band||"").toUpperCase(),b.ownership_reference||"",b.access_notes||"",b.emergency_notes||""]);
    else if(action==="edit_property") await run("UPDATE properties SET address=?,address_line_2=?,town_city=?,county=?,postcode=?,property_type=?,bedrooms=?,bathrooms=?,local_authority=?,council_tax_band=?,ownership_reference=?,access_notes=?,emergency_notes=? WHERE id=?",[text(b,"address","Address"),b.address_line_2||"",b.town_city||"",b.county||"",text(b,"postcode","Postcode").toUpperCase(),text(b,"property_type","Property type"),Number(b.bedrooms||1),Number(b.bathrooms||1),b.local_authority||"",String(b.council_tax_band||"").toUpperCase(),b.ownership_reference||"",b.access_notes||"",b.emergency_notes||"",id(b,"property_id","Property")]);
    else if(action==="delete_property") { const propertyId=id(b,"property_id","Property"); const linked=await one("SELECT (SELECT COUNT(*) FROM tenants WHERE property_id=?)+(SELECT COUNT(*) FROM certificates WHERE property_id=?)+(SELECT COUNT(*) FROM maintenance_requests WHERE property_id=?)+(SELECT COUNT(*) FROM documents WHERE property_id=?)+(SELECT COUNT(*) FROM reminders WHERE property_id=?) count",[propertyId,propertyId,propertyId,propertyId,propertyId]); if(Number(linked.count)>0) throw new Error("Remove linked records before deleting this property."); await run("DELETE FROM properties WHERE id=?",[propertyId]); }
    else if(action==="add_certificate") await run("INSERT INTO certificates (property_id,type,expires_on,notes) VALUES (?,?,?,?)",[id(b,"property_id","Property"),text(b,"type","Certificate type"),validDate(b,"expires_on","Expiry date"),b.notes||""]);
    else if(action==="add_tenant") { await run("INSERT INTO tenants (property_id,name,email,monthly_rent,rent_due_day,portal_token) VALUES (?,?,?,?,?,?)",[id(b,"property_id","Property"),text(b,"name","Tenant"),b.email||"",money(b,"monthly_rent","Monthly rent"),day(b,"rent_due_day","Rent due day"),randomBytes(16).toString("hex")]); await ensureCurrentPayments(); }
    else if(action==="edit_tenant") await run("UPDATE tenants SET name=?,email=?,monthly_rent=?,rent_due_day=?,status=? WHERE id=?",[text(b,"name","Tenant"),b.email||"",money(b,"monthly_rent","Monthly rent"),day(b,"rent_due_day","Rent due day"),statusIn(b.status,["active","archived"],"active"),id(b,"tenant_id","Tenant")]);
    else if(action==="regenerate_portal") await run("UPDATE tenants SET portal_token=? WHERE id=?",[randomBytes(16).toString("hex"),id(b,"tenant_id","Tenant")]);
    else if(action==="payment_status") await run("UPDATE payments SET status=?,paid_on=? WHERE id=?",[statusIn(b.status,["received","pending","late"],"pending"),b.status==="received"?today():null,id(b,"payment_id","Payment")]);
    else if(action==="maintenance_status") await run("UPDATE maintenance_requests SET status=? WHERE id=?",[statusIn(b.status,["reported","scheduled","completed"],"reported"),id(b,"maintenance_id","Maintenance")]);
    else if(action==="add_maintenance") await run("INSERT INTO maintenance_requests (property_id,tenant_id,title,description,priority,status) VALUES (?,?,?,?,?,'reported')",[id(b,"property_id","Property"),b.tenant_id?id(b,"tenant_id","Tenant"):null,text(b,"title","Issue title"),text(b,"description","Description"),statusIn(b.priority,["low","normal","urgent"],"normal")]);
    else if(action==="add_document") await run("INSERT INTO documents (property_id,name,category,notes) VALUES (?,?,?,?)",[id(b,"property_id","Property"),text(b,"name","Document name"),text(b,"category","Category"),b.notes||""]);
    else if(action==="add_reminder") await run("INSERT INTO reminders (property_id,title,due_on) VALUES (?,?,?)",[b.property_id?id(b,"property_id","Property"):null,text(b,"title","Title"),validDate(b,"due_on","Due date")]);
    else if(action==="reminder_status") await run("UPDATE reminders SET status=? WHERE id=?",[b.status==="done"?"done":"open",id(b,"reminder_id","Reminder")]);
    else if(action==="tenant_message") { const tenant=await one("SELECT * FROM tenants WHERE id=?",[id(b,"tenant_id","Tenant")]); const body=text(b,"body","Message"); const inserted=await run("INSERT INTO messages (tenant_id,sender,body,status) VALUES (?,'tenant',?,'received')",[tenant.id,body]); await run("INSERT INTO messages (tenant_id,sender,body,status,source_message_id,generated_by) VALUES (?,'assistant',?,'draft',?,?)",[tenant.id,await draftReply(tenant,body),Number(inserted.lastInsertRowid),config.openRouterKey?`OpenRouter: ${config.openRouterModel}`:"Local fallback"]); }
    else if(action==="approve_message") await run("UPDATE messages SET status='approved' WHERE id=? AND sender='assistant' AND status='draft'",[id(b,"message_id","Message")]);
    else if(action==="decline_message") await run("UPDATE messages SET status='declined',review_note=? WHERE id=? AND sender='assistant' AND status='draft'",[b.review_note||"",id(b,"message_id","Message")]);
    else if(action==="regenerate_message") { const draft=await one("SELECT m.*,t.name FROM messages m JOIN tenants t ON t.id=m.tenant_id WHERE m.id=? AND m.sender='assistant' AND m.status='draft'",[id(b,"message_id","Message")]); const source=draft&&await one("SELECT body FROM messages WHERE id=? AND sender='tenant'",[draft.source_message_id]); if(!source) throw new Error("Original tenant message not found."); await run("UPDATE messages SET status='superseded',review_note=? WHERE id=?",[b.review_note||"",draft.id]); await run("INSERT INTO messages (tenant_id,sender,body,status,source_message_id,generated_by) VALUES (?,'assistant',?,'draft',?,?)",[draft.tenant_id,await draftReply(draft,source.body,b.review_note||""),draft.source_message_id,config.openRouterKey?`OpenRouter: ${config.openRouterModel}`:"Local fallback"]); }
    else if(action==="generate_agreement") { const input={landlord_name:text(b,"landlord_name","Landlord name"),tenant_name:text(b,"tenant_name","Tenant name"),property_address:text(b,"property_address","Property address"),start_date:validDate(b,"start_date","Start date"),rent_amount:money(b,"rent_amount","Monthly rent"),rent_due_day:day(b,"rent_due_day","Rent due day")}; const draft=await askOpenRouter("Prepare a careful first draft of an England assured periodic tenancy agreement. Return only the draft.",JSON.stringify(input),1800)||fallbackAgreement(input); await run("INSERT INTO agreements (tenant_name,property_address,rent_amount,rent_due_day,start_date,draft) VALUES (?,?,?,?,?,?)",[input.tenant_name,input.property_address,input.rent_amount,input.rent_due_day,input.start_date,draft]); }
    else throw new Error("Unknown action.");
    flash(req,"Saved."); res.redirect(`/${page}`);
  } catch(error) { flash(req,error.message,"error"); res.redirect(`/${page}`); }
}

app.get("/portal", async (req,res) => {
  const tenant=/^[a-f0-9]{32}$/.test(req.query.token||"") ? await one("SELECT t.*,p.address FROM tenants t JOIN properties p ON p.id=t.property_id WHERE t.portal_token=?",[req.query.token]) : null;
  if(!tenant) return res.status(404).send("Tenant portal link not found.");
  res.send(portalPage({ tenant, payment:await one("SELECT * FROM payments WHERE tenant_id=? AND rent_month=?",[tenant.id,month()]), maintenance:await rows("SELECT * FROM maintenance_requests WHERE tenant_id=? ORDER BY id DESC",[tenant.id]), messages:await rows("SELECT * FROM messages WHERE tenant_id=? AND (sender='tenant' OR status='approved') ORDER BY id",[tenant.id]), token:req.query.token, csrfToken:csrf(req), itemFlash:takeFlash(req) }));
});
app.post("/portal", async (req,res) => {
  const token=req.query.token||""; const tenant=/^[a-f0-9]{32}$/.test(token) ? await one("SELECT * FROM tenants WHERE portal_token=?",[token]) : null;
  if(!tenant) return res.status(404).send("Tenant portal link not found.");
  try { verifyCsrf(req); if(req.body.action==="report_maintenance") await run("INSERT INTO maintenance_requests (property_id,tenant_id,title,description,priority,status) VALUES (?,?,?,?,?,'reported')",[tenant.property_id,tenant.id,text(req.body,"title","Issue title"),text(req.body,"description","Description"),statusIn(req.body.priority,["low","normal","urgent"],"normal")]); else { const body=text(req.body,"body","Message"); const inserted=await run("INSERT INTO messages (tenant_id,sender,body,status) VALUES (?,'tenant',?,'received')",[tenant.id,body]); await run("INSERT INTO messages (tenant_id,sender,body,status,source_message_id,generated_by) VALUES (?,'assistant',?,'draft',?,?)",[tenant.id,await draftReply(tenant,body),Number(inserted.lastInsertRowid),config.openRouterKey?`OpenRouter: ${config.openRouterModel}`:"Local fallback"]); } flash(req,"Sent."); } catch(error){ flash(req,error.message,"error"); }
  res.redirect(`/portal?token=${encodeURIComponent(token)}`);
});

for (const legacy of ["index","features","pricing","about","login","logout","dashboard","properties","maintenance","compliance","tenants","rent","inbox","agreements","documents","reminders","portal"]) app.get(`/${legacy}.php`, (req,res) => res.redirect(301, `/${legacy==="index"?"":legacy}${req.url.includes("?")?"?"+req.url.split("?")[1]:""}`));
app.use((error,req,res,next) => { console.error(error); res.status(500).send("Sorel House could not load. Check the database environment variables."); });
export default app;
