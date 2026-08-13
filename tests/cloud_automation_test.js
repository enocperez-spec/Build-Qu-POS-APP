const fs = require("node:fs");
const path = require("node:path");
const assert = require("node:assert/strict");

const automationPath = path.join(
    __dirname,
    "..",
    "AppData",
    "CloudAutomation",
    "qu-cloud-export-and-import.mjs"
);
const source = fs.readFileSync(automationPath, "utf8");

assert.match(
    source,
    /Authorization: `Bearer \$\{required\(settings\.importToken, 'QU_APP_IMPORT_TOKEN'\)\}`/,
    "Automation should retain the standard bearer token header."
);
assert.match(
    source,
    /'X-QU-Import-Token': required\(settings\.importToken, 'QU_APP_IMPORT_TOKEN'\)/,
    "Automation must send the IONOS-compatible import-token header."
);
assert.match(source, /'X-QU-Trigger-Type': settings\.triggerType/, "Automation must preserve trigger attribution.");

console.log("Cloud automation authentication tests passed.");
