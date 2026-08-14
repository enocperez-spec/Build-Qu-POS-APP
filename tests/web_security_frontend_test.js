const fs = require("node:fs");
const path = require("node:path");
const assert = require("node:assert/strict");

const appPath = path.join(__dirname, "..", "WebApp", "assets", "app.js");
const source = fs.readFileSync(appPath, "utf8");

assert.doesNotMatch(source, /<td>\$\{cell \?\? ""\}<\/td>/, "raw simpleTable sink must stay removed");
assert.match(source, /<td>\$\{tableCellHtml\(cell\)\}<\/td>/, "simpleTable must use the safe cell renderer");
assert.match(source, /function trustedTableHtml\(/, "trusted app-generated HTML must be explicit");

const formulaHelper = source.match(/function spreadsheetSafeText\(value\) \{[\s\S]*?\n\}/)?.[0];
assert.ok(formulaHelper, "spreadsheetSafeText helper was not found");
const spreadsheetSafeText = new Function(`${formulaHelper}; return spreadsheetSafeText;`)();

for (const value of ["=2+2", "+SUM(A1:A2)", "-1+2", "@cmd", "  =HYPERLINK(\"bad\")"]) {
    assert.equal(spreadsheetSafeText(value), `'${value}`, `formula was not neutralized: ${value}`);
}
for (const value of ["3.5.231.6408", "Store 7037", "Normal text"]) {
    assert.equal(spreadsheetSafeText(value), value, `normal value was changed: ${value}`);
}
assert.match(source, /function csvCell\(value\) \{\s*const text = spreadsheetSafeText\(value\);/, "CSV export must use formula protection");
assert.match(source, /escapeHtml\(spreadsheetSafeText\(cell\)\)/, "Excel export must use formula protection");
assert.match(source, /healthMetricCard\("POS Terminals Offline", formatNumber\(store\.posOffline\)/, "Store Health must render the POS offline scorecard metric");

const cssPath = path.join(__dirname, "..", "WebApp", "assets", "app.css");
const css = fs.readFileSync(cssPath, "utf8");
assert.match(css, /\.health-store-metrics\s*\{[^}]*grid-template-columns:\s*repeat\(7,/s, "Store Health must keep all seven scorecard metrics in one wide-screen row");

console.log("Web frontend security tests passed.");
