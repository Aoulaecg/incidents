import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright-core';

const root = new URL('../', import.meta.url);
const artifacts = new URL('.artifacts/', root);
const manifest = JSON.parse(await readFile(new URL('public/build/manifest.json', root), 'utf8'));
const browser = await chromium.launch({ ...(process.env.BROWSER_EXECUTABLE_PATH ? { executablePath: process.env.BROWSER_EXECUTABLE_PATH } : { channel: 'msedge' }), headless: true });
const errors = [];
try {
    const page = await browser.newPage();
    page.on('pageerror', (e) => errors.push(e.message));
    await page.route('**/images/cgpark-logo.png', (route) => route.fulfill({ path: fileURLToPath(new URL('public/images/cgpark-logo.png', root)), contentType: 'image/png' }));
    for (const view of ['supervisor-workspace', 'director-workspace', 'parking-assignments']) {
        const html = await readFile(new URL(`${view}.html`, artifacts), 'utf8');
        for (const [width, height, device] of [[1440, 1000, 'desktop'], [768, 1024, 'tablet'], [390, 844, 'mobile'], [320, 740, 'small-mobile']]) {
            await page.goto('about:blank');
            await page.setViewportSize({ width, height });
            await page.setContent(html);
            await page.addStyleTag({ path: fileURLToPath(new URL(`public/build/${manifest['resources/css/app.css'].file}`, root)) });
            await page.addScriptTag({ path: fileURLToPath(new URL(`public/build/${manifest['resources/js/app.js'].file}`, root)) });
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `${view}/${device}: overflow`);
            if (view === 'supervisor-workspace') {
                assert.equal(await page.locator('#description').getAttribute('required'), '');
                await page.getByRole('button', { name: 'Rien à signaler aujourd’hui' }).click();
                assert.equal(await page.locator('dialog').isVisible(), true);
                assert.equal(await page.locator('[data-ras-parking-name]').textContent(), 'Taghazout');
                await page.getByRole('button', { name: 'Annuler', exact: true }).click();
                assert.equal(await page.locator('dialog').isVisible(), false);
            } else if (width < 992) {
                await page.getByRole('button', { name: 'Ouvrir la navigation' }).click();
                assert.equal(await page.locator('[data-menu-toggle]').getAttribute('aria-expanded'), 'true');
                await page.keyboard.press('Escape');
            }
            await page.mouse.move(0, 0);
            await page.evaluate(() => { document.activeElement?.blur(); window.scrollTo(0, 0); });
            await page.screenshot({ path: fileURLToPath(new URL(`${view}-${device}.png`, artifacts)), fullPage: true, animations: 'disabled' });
        }
        console.log(`PASS ${view}: desktop, tablet, mobile, small mobile`);
    }
    assert.deepEqual(errors, []);
    console.log('PASS RAS confirmation and cancellation, scoped parking label, navigation, no browser errors');
} finally { await browser.close(); }
