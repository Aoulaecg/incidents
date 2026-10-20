import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright-core';

const root = new URL('../', import.meta.url);
const artifacts = new URL('.artifacts/', root);
const manifest = JSON.parse(await readFile(new URL('public/build/manifest.json', root), 'utf8'));
const browser = await chromium.launch({ ...(process.env.BROWSER_EXECUTABLE_PATH ? { executablePath: process.env.BROWSER_EXECUTABLE_PATH } : { channel: 'msedge' }), headless: true });
try {
    const page = await browser.newPage();
    await page.route('**/images/cgpark-logo.png', (route) => route.fulfill({ path: fileURLToPath(new URL('public/images/cgpark-logo.png', root)), contentType: 'image/png' }));
    for (const view of ['email-incident', 'email-ras', 'notification-history']) {
        const html = await readFile(new URL(`${view}.html`, artifacts), 'utf8');
        for (const width of [1440, 768, 390, 320]) {
            await page.goto('about:blank');
            await page.setViewportSize({ width, height: 900 });
            await page.setContent(html);
            if (view === 'notification-history') {
                await page.addStyleTag({ path: fileURLToPath(new URL(`public/build/${manifest['resources/css/app.css'].file}`, root)) });
            } else {
                const logo = page.getByRole('img', { name: /CGPark/ });
                assert.equal(await logo.evaluate((img) => img.complete && img.naturalWidth > 0), true, `${view}: logo loaded`);
                assert.equal(await page.getByRole('link', { name: 'Consulter le journal' }).count(), 1);
            }
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `${view}/${width}: overflow`);
            await page.screenshot({ path: fileURLToPath(new URL(`${view}-${width}.png`, artifacts)), fullPage: true });
        }
        console.log(`PASS ${view}: 1440, 768, 390, 320 px`);
    }
} finally {
    await browser.close();
}
