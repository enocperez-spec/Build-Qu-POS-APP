import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { chromium } from 'playwright';

const settings = {
  exportMode: (process.env.QU_EXPORT_MODE || 'terminals').toLowerCase(),
  quUrl: process.env.QU_ADMIN_URL || '',
  quUsername: process.env.QU_ADMIN_USER,
  quPassword: process.env.QU_ADMIN_PASS,
  importUrl: process.env.QU_APP_IMPORT_URL || '',
  importToken: process.env.QU_APP_IMPORT_TOKEN,
  triggerType: process.env.QU_TRIGGER_TYPE || 'Scheduled',
  headless: String(process.env.QU_EXPORT_HEADLESS ?? 'true').toLowerCase() !== 'false',
  timeoutMs: Number(process.env.QU_EXPORT_TIMEOUT_MS || 90000),
  artifactDir: process.env.QU_EXPORT_ARTIFACT_DIR || path.resolve('artifacts'),
};

const exportTargets = {
  terminals: {
    quUrl: 'https://admin.qubeyond.com/n/operations/terminals',
    importUrl: 'https://quposapp.qupostech.com/api/cloud-import.php',
    exportText: /export terminals/i,
    exportTestId: '',
    formField: 'currentCsv',
    label: 'terminals',
  },
  stores: {
    quUrl: 'https://admin.qubeyond.com/configuration/stores/',
    importUrl: 'https://quposapp.qupostech.com/api/cloud-store-import.php',
    exportText: /export stores? info|export store information/i,
    exportTestId: 'exportStoresInfoBtn',
    formField: 'storeCsv',
    label: 'stores',
  },
};

const target = exportTargets[settings.exportMode] || exportTargets.terminals;
settings.quUrl ||= target.quUrl;
settings.importUrl ||= target.importUrl;

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

function actionsButton(page) {
  return page.locator('button, [role="button"]').filter({ hasText: /actions?/i }).first();
}

function usernameInput(page) {
  return page.locator('input[name="username"], input[type="text"]:visible').first();
}

function loginForm(page) {
  return page.locator('input[name="password"], input[type="password"], button, input[type="submit"], [role="button"]').filter({ hasText: /^LOGIN$/i }).first();
}

async function isActionsReady(page) {
  return actionsButton(page).isVisible().catch(() => false);
}

async function isLoginReady(page) {
  const passwordVisible = await page.locator('input[name="password"], input[type="password"]').first().isVisible().catch(() => false);
  const loginButtonVisible = await page.locator('button, input[type="submit"], [role="button"]').filter({ hasText: /^LOGIN$/i }).first().isVisible().catch(() => false);
  return passwordVisible || loginButtonVisible;
}

async function dismissBlockingModals(page) {
  const modal = page.locator('.modal, [role="dialog"], .v-dialog, .e-dialog').filter({ hasText: /what'?s new|version|released/i }).first();
  if (!(await modal.isVisible().catch(() => false))) return false;

  console.log('Blocking release notes modal detected.');
  const closeTargets = [
    page.getByRole('button', { name: /^close$/i }),
    page.locator('button').filter({ hasText: /^close$/i }),
    page.locator('.modal button, [role="dialog"] button, .v-dialog button, .e-dialog button').filter({ hasText: /close/i }),
    page.locator('button[aria-label*="close" i], [role="button"][aria-label*="close" i], .close, .delete').first(),
  ];

  for (const locator of closeTargets) {
    try {
      await locator.first().click({ timeout: 2500 });
      await page.waitForTimeout(1000);
      if (!(await modal.isVisible().catch(() => false))) {
        console.log('Dismissed release notes modal.');
        return true;
      }
    } catch {
      // Try the next close strategy.
    }
  }

  await page.keyboard.press('Escape').catch(() => {});
  await page.waitForTimeout(1000);
  if (!(await modal.isVisible().catch(() => false))) {
    console.log('Dismissed release notes modal with Escape.');
    return true;
  }

  const box = await modal.boundingBox().catch(() => null);
  if (box) {
    await page.mouse.click(box.x + box.width - 18, box.y + 18).catch(() => {});
    await page.waitForTimeout(1000);
    if (!(await modal.isVisible().catch(() => false))) {
      console.log('Dismissed release notes modal with X fallback.');
      return true;
    }
  }

  console.log('Release notes modal remained visible after dismissal attempts.');
  return false;
}

async function waitForLoginOrActions(page) {
  await Promise.race([
    loginForm(page).waitFor({ state: 'visible', timeout: settings.timeoutMs }).catch(() => {}),
    actionsButton(page).waitFor({ state: 'visible', timeout: settings.timeoutMs }).catch(() => {}),
  ]);
}

async function loginIfNeeded(page) {
  await waitForLoginOrActions(page);
  if (await isActionsReady(page)) return;
  if (!(await isLoginReady(page))) return;

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
  await page.waitForTimeout(2500);
  await dismissBlockingModals(page);
  if (await isLoginReady(page)) throw new Error('QU Admin login did not complete.');
}

async function ensureTerminalPage(page) {
  for (let attempt = 1; attempt <= 2; attempt++) {
    await loginIfNeeded(page);
    await dismissBlockingModals(page);
    if (await isActionsReady(page)) return;
    await page.goto(settings.quUrl, { waitUntil: 'domcontentloaded', timeout: settings.timeoutMs });
  }
  await waitForLoginOrActions(page);
  await dismissBlockingModals(page);
  if (!(await isActionsReady(page))) {
    throw new Error(`QU Admin terminals page did not become ready. Current URL: ${page.url()}`);
  }
}

async function exportCsv() {
  const browser = await chromium.launch({ headless: settings.headless });
  const context = await browser.newContext({ acceptDownloads: true });
  const page = await context.newPage();
  page.setDefaultTimeout(settings.timeoutMs);

  try {
    await page.goto(settings.quUrl, { waitUntil: 'domcontentloaded', timeout: settings.timeoutMs });
    await ensureTerminalPage(page);

    await clickFirst(page, [
      { name: 'Actions button', locator: p => p.getByRole('button', { name: /^actions?$/i }) },
      { name: 'Actions-like button', locator: p => actionsButton(p) },
    ]);

    const [download] = await Promise.all([
      page.waitForEvent('download', { timeout: settings.timeoutMs }),
      clickFirst(page, [
        ...(target.exportTestId ? [{ name: `Export ${target.label} item by test id`, locator: p => p.locator(`[data-test-id="${target.exportTestId}"]`) }] : []),
        { name: `Export ${target.label} item`, locator: p => p.locator('a, button, [role="menuitem"], [role="button"], .o-drop__item').filter({ hasText: target.exportText }) },
      ]),
    ]);
    const filePath = path.join(await fs.mkdtemp(path.join(os.tmpdir(), 'qu-export-')), download.suggestedFilename() || 'terminals.csv');
    await download.saveAs(filePath);
    console.log(`${target.label} export saved: ${filePath}`);
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
  form.append(target.formField, new Blob([bytes], { type: 'text/csv' }), path.basename(csvPath));

  const response = await fetch(required(settings.importUrl, 'QU_APP_IMPORT_URL'), {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${required(settings.importToken, 'QU_APP_IMPORT_TOKEN')}`,
      'X-QU-Import-Token': required(settings.importToken, 'QU_APP_IMPORT_TOKEN'),
      'X-QU-Trigger-Type': settings.triggerType,
      'X-QU-Attempts': '1',
    },
    body: form,
  });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok || !payload.ok) {
    throw new Error(payload.error || `Import failed with HTTP ${response.status}`);
  }
  console.log(`Web app updated: ${payload.htmlUrl || 'latest report generated'}`);
}

const csvPath = await exportCsv();
await importIntoWebApp(csvPath);
