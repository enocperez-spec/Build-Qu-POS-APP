import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { chromium } from 'playwright';

const settings = {
  quUrl: process.env.QU_ADMIN_URL || 'https://admin.qubeyond.com/n/operations/terminals',
  quUsername: process.env.QU_ADMIN_USER,
  quPassword: process.env.QU_ADMIN_PASS,
  importUrl: process.env.QU_APP_IMPORT_URL || 'https://quposapp.qupostech.com/api/cloud-import.php',
  importToken: process.env.QU_APP_IMPORT_TOKEN,
  headless: String(process.env.QU_EXPORT_HEADLESS ?? 'true').toLowerCase() !== 'false',
  timeoutMs: Number(process.env.QU_EXPORT_TIMEOUT_MS || 90000),
};

function required(value, name) {
  if (!value) throw new Error(`Missing required secret or setting: ${name}`);
  return value;
}

async function clickFirst(page, choices) {
  let lastError;
  for (const choice of choices) {
    try {
      const locator = choice.locator(page).first();
      await locator.waitFor({ state: 'visible', timeout: 10000 });
      await locator.click();
      console.log(`Clicked ${choice.name}.`);
      return;
    } catch (error) {
      lastError = error;
    }
  }
  throw new Error(`Could not click target. Last error: ${lastError?.message || 'none'}`);
}

async function fillFirst(page, choices, value) {
  let lastError;
  for (const choice of choices) {
    try {
      const locator = choice.locator(page).first();
      await locator.waitFor({ state: 'visible', timeout: 10000 });
      await locator.fill(value);
      console.log(`Filled ${choice.name}.`);
      return;
    } catch (error) {
      lastError = error;
    }
  }
  throw new Error(`Could not fill target. Last error: ${lastError?.message || 'none'}`);
}

async function loginIfNeeded(page) {
  const isLoginPage = () => /\/login\b/i.test(new URL(page.url()).pathname);
  if (!isLoginPage()) return;

  console.log('Login page detected.');
  await fillFirst(page, [
    { name: 'username input', locator: p => p.locator('input[name="username"], input[type="text"], input.e-input').nth(0) },
  ], required(settings.quUsername, 'QU_ADMIN_USER'));
  await fillFirst(page, [
    { name: 'password input', locator: p => p.locator('input[name="password"], input[type="password"], input.e-input').nth(1) },
  ], required(settings.quPassword, 'QU_ADMIN_PASS'));
  await clickFirst(page, [
    { name: 'LOGIN button', locator: p => p.locator('button, input[type="submit"], [role="button"]').filter({ hasText: /^LOGIN$/i }) },
    { name: 'submit button', locator: p => p.locator('button[type="submit"], input[type="submit"]') },
  ]);
  await page.waitForLoadState('networkidle', { timeout: settings.timeoutMs }).catch(() => {});
  await page.waitForTimeout(3000);
  if (isLoginPage()) throw new Error('QU Admin login did not complete.');
}

async function exportTerminals() {
  const browser = await chromium.launch({ headless: settings.headless });
  const context = await browser.newContext({ acceptDownloads: true });
  const page = await context.newPage();
  page.setDefaultTimeout(settings.timeoutMs);

  try {
    await page.goto(settings.quUrl, { waitUntil: 'domcontentloaded', timeout: settings.timeoutMs });
    await loginIfNeeded(page);
    if (!page.url().includes('/operations/terminals')) {
      await page.goto(settings.quUrl, { waitUntil: 'networkidle', timeout: settings.timeoutMs });
    }

    await clickFirst(page, [
      { name: 'Actions button', locator: p => p.getByRole('button', { name: /^actions?$/i }) },
      { name: 'Actions-like button', locator: p => p.locator('button, [role="button"]').filter({ hasText: /actions?/i }) },
    ]);

    const downloadPromise = page.waitForEvent('download', { timeout: settings.timeoutMs });
    await clickFirst(page, [
      { name: 'Export Terminals item', locator: p => p.locator('a, button, [role="menuitem"], [role="button"]').filter({ hasText: /export terminals/i }) },
    ]);
    const download = await downloadPromise;
    const filePath = path.join(await fs.mkdtemp(path.join(os.tmpdir(), 'qu-export-')), download.suggestedFilename() || 'terminals.csv');
    await download.saveAs(filePath);
    console.log(`Export saved: ${filePath}`);
    return filePath;
  } finally {
    await context.close();
    await browser.close();
  }
}

async function importIntoWebApp(csvPath) {
  const form = new FormData();
  const bytes = await fs.readFile(csvPath);
  form.append('currentCsv', new Blob([bytes], { type: 'text/csv' }), path.basename(csvPath));

  const response = await fetch(required(settings.importUrl, 'QU_APP_IMPORT_URL'), {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${required(settings.importToken, 'QU_APP_IMPORT_TOKEN')}`,
    },
    body: form,
  });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok || !payload.ok) {
    throw new Error(payload.error || `Import failed with HTTP ${response.status}`);
  }
  console.log(`Web app updated: ${payload.htmlUrl || 'latest report generated'}`);
}

const csvPath = await exportTerminals();
await importIntoWebApp(csvPath);
