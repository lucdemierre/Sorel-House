import app from "./src/app.js";

const port = Number(process.env.PORT || 8080);
app.listen(port, () => console.log(`Sorel House running at http://localhost:${port}`));
