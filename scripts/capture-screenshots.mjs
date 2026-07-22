// Captures the README screenshots from a locally running instance seeded with
// DemoSeeder. Nothing here touches application code; it only drives a browser.
//
//   php artisan migrate:fresh --force
//   php artisan db:seed --class=DemoSeeder --force
//   php artisan serve &                       # http://127.0.0.1:8000
//   node scripts/capture-screenshots.mjs
//
// Requires Playwright, which is not a dependency of this repo (there is no npm
// build). Install it globally (`npm i -g playwright && npx playwright install
// chromium`) and run with NODE_PATH=$(npm root -g).
//
// Override the target with BASE_URL, e.g. when serving via a raw built-in server
// that routes static assets: BASE_URL=http://127.0.0.1:8803 node scripts/...

import playwright from 'playwright';
import { mkdir } from 'node:fs/promises';

const { chromium } = playwright;

const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const outDir = 'docs/images';

const browser = await chromium.launch();
const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
const page = await context.newPage();

async function go(path, height) {
  await page.setViewportSize({ width: 1280, height });
  await page.goto(`${base}${path}`, { waitUntil: 'domcontentloaded' });
  await page.waitForLoadState('networkidle');
}

async function shot(file) {
  await page.screenshot({ path: `${outDir}/${file}` });
  console.log(`captured ${outDir}/${file}`);
}

await page.goto(`${base}/login`, { waitUntil: 'domcontentloaded' });
await page.fill('#email', 'demo@example.com');
await page.fill('#password', 'password');
await Promise.all([
  page.waitForURL(`${base}/`, { waitUntil: 'domcontentloaded' }),
  page.click('button[type=submit]'),
]);

await mkdir(outDir, { recursive: true });

await go('/events', 760);
await shot('events-browse.png');

await go('/deliveries', 860);
await shot('deliveries-browse.png');

await go('/dlq', 620);
await shot('dead-letter-queue.png');

// Open the first (most recent) dead delivery to show its full attempt trail.
await page.click('table tbody tr td.mono a');
await page.waitForLoadState('networkidle');
await page.setViewportSize({ width: 1280, height: 980 });
await shot('delivery-detail.png');

await browser.close();
