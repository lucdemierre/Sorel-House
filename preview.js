import http from "node:http";
import { createReadStream, existsSync } from "node:fs";
import { extname, join } from "node:path";
import { publicPage } from "./src/views.js";
import { homeBody, featuresBody, pricingBody, aboutBody } from "./src/marketing.js";

const port = Number(process.env.PREVIEW_PORT || 8091);
const pages = {
  "/": publicPage("home", homeBody, false),
  "/features": publicPage("features", featuresBody, false),
  "/pricing": publicPage("pricing", pricingBody, false),
  "/about": publicPage("about", aboutBody, false),
};
const types = { ".css": "text/css", ".js": "text/javascript", ".png": "image/png" };

http.createServer((req, res) => {
  const pathname = new URL(req.url, "http://127.0.0.1").pathname;
  if (pages[pathname]) {
    res.setHeader("Content-Type", "text/html; charset=utf-8");
    return res.end(pages[pathname]);
  }
  if (pathname.startsWith("/assets/")) {
    const file = join("public", pathname);
    if (existsSync(file)) {
      res.setHeader("Content-Type", types[extname(file)] || "application/octet-stream");
      return createReadStream(file).pipe(res);
    }
  }
  res.statusCode = 404;
  res.end("Not found");
}).listen(port, "127.0.0.1", () => console.log(`Marketing preview running at http://127.0.0.1:${port}`));
