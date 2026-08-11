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
  artifactDir: process.env.QU_EXPORT_ARTIFACT_DIR || path.resolve('artifacts'),
};

function required(value, name) {
  if (!value) throw new Error(`Missing required secret or setting: ${name}`);
  return value;
}

function safeStamp() {
  return new Date().toISOString().replace(/[:.]/g, '-');
}

async function saveDiagnostics(page, label, error) {
  await fs.mkdir(settings.artifactDir, { recursive: true });
  const stamp = safeStamp();
  const baseName = `${stamp}-${label}`;
  const screenshotPath = path.join(settings.artifactDir, `${baseName}.png`);
  const htmlPath = path.join(settings.artifactDir, `${baseName}.html`);
  const summaryPath = path.join(settings.artifactDir, `${baseName}.txt`);

  await page.screenshot({ path: screenshotPath, fullPage: true }).catch(() => {});
  const html = await page.content().catch(reason => `Unable to capture HTML: ${reason?.message || reason}`);
  await fs.writeFile(htmlPath, html, 'utf8');
  await fs.writeFile(summaryPath, [
    `URL: ${page.url()}`,
    `Title: ${await page.title().catch(() => '')}`,
    `Error: ${error?.stack || error?.message || error}`,
  ].join('\n'), 'utf8');
  console.log(`Diagnostics saved to ${settings.artifactDir}`);
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

async function waitForLoginOrTarget(page) {
  await Promise.race([
    page.waitForURL(/\/login\b/i, { timeout: settings.timeoutMs }).catch(() => {}),
    page.locator('input[name="username"], input[type="text"], input.e-input').first().waitFor({ state: 'visible', timeout: settings.timeoutMs }).catch(() => {}),
    page.waitForURL(/\/operations\/terminals/i, { timeout: settings.timeoutMs }).catch(() => {}),
  ]);
}

async function loginIfNeeded(page) {
  const isLoginPage = () => /\/login\b/i.test(new URL(page.url()).pathname);
  await waitForLoginOrTarget(page);
  const hasLoginForm = await page.locator('input[name="username"], input[type="text"], input.e-input').first().isVisible().catch(() => false);
  if (!isLoginPage() && !hasLoginForm) return;

  console.log('Login page detected.');
  await fillFirst(page, [
    { name: 'username input by name', locator: p => p.locator('input[name="username"]') },
    { name: 'username text input', locator: p => p.locator('input[type="text"]:visible') },
    { name: 'first visible input', locator: p => p.locator('input:visible').nth(0) },
  ], required(settings.quUsername, 'QU_ADMIN_USER'));
  await fillFirst(page, [
    { name: 'password input by name', locator: p => p.locator('input[name="password"]') },
    { name: 'password input by type', locator: p => p.locator('input[type="password"]') },
    { name: 'second visible input', locator: p => p.locator('input:visible').nth(1) },
  ], required(settings.quPassword, 'QU_ADMIN_PASS'));
  await clickFirst(page, [
    { name: 'LOGIN button', locator: p => p.locator('button, input[type="submit"], [role="button"]').filter({ hasText: /^LOGIN$/i }) },
    { name: 'submit button', locator: p => p.locator('button[type="submit"], input[type="submit"]') },
  ]);
  await page.waitForLoadState('networkidle', { timeout: settings.timeoutMs }).catch(() => {});
  await page.waitForURL(url => !/\/login\b/i.test(url.pathname), { timeout: 30000 }).catch(() => {});
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
      await loginIfNeeded(page);
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
  } catch (error) {
    await saveDiagnostics(page, 'export-failure', error);
    throw error;
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
