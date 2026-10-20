import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright-core';

// Review HTML rendered by real Laravel feature requests against an in-memory test database.
// This test never creates an administrator or changes accounts in MySQL.
const root = new URL('../', import.meta.url);
const artifacts = new URL('.artifacts/', root);
const manifest = JSON.parse(await readFile(new URL('public/build/manifest.json', root), 'utf8'));
const css = fileURLToPath(new URL(`public/build/${manifest['resources/css/app.css'].file}`, root));
const js = fileURLToPath(new URL(`public/build/${manifest['resources/js/app.js'].file}`, root));
const browser = await chromium.launch({
    ...(process.env.BROWSER_EXECUTABLE_PATH ? { executablePath: process.env.BROWSER_EXECUTABLE_PATH } : { channel: 'msedge' }),
    headless: true,
});
const errors = [];
try {
    const page = await browser.newPage();
    page.on('pageerror', (error) => errors.push(error.message));
    await page.route('**/images/cgpark-logo.png', (route) => route.fulfill({
        path: fileURLToPath(new URL('public/images/cgpark-logo.png', root)), contentType: 'image/png',
    }));

    for (const view of ['admin-list', 'admin-create', 'admin-edit', 'admin-password', 'admin-deactivate']) {
        const html = await readFile(new URL(`${view}.html`, artifacts), 'utf8');
        for (const [width, height, device] of [[1440, 1000, 'desktop'], [768, 1024, 'tablet'], [390, 844, 'mobile'], [320, 740, 'small-mobile']]) {
            await page.goto('about:blank');
            await page.setViewportSize({ width, height });
            await page.setContent(html);
            await page.addStyleTag({ path: css });
            await page.addScriptTag({ path: js });
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `${view}/${device}: horizontal overflow`);
            assert.ok(await page.locator('h1').textContent());
            if (width < 992) {
                await page.getByRole('button', { name: 'Ouvrir la navigation' }).click();
                assert.equal(await page.locator('[data-menu-toggle]').getAttribute('aria-expanded'), 'true');
                await page.keyboard.press('Escape');
                assert.equal(await page.locator('[data-menu-toggle]').getAttribute('aria-expanded'), 'false');
            }
            if (view === 'admin-create' || view === 'admin-password') {
                for (const button of await page.locator('[data-password-toggle]').all()) {
                    const id = await button.getAttribute('aria-controls');
                    await button.click();
                    assert.equal(await page.locator(`#${id}`).getAttribute('type'), 'text');
                    await button.click();
                    assert.equal(await page.locator(`#${id}`).getAttribute('type'), 'password');
                }
            }
            if (view === 'admin-edit') {
                await page.locator('[data-account-status]').selectOption('0');
                assert.equal(await page.locator('[data-deactivation-confirm]').isVisible(), true);
                assert.equal(await page.locator('[name=confirm_deactivation]').getAttribute('required'), '');
                await page.locator('[data-account-status]').selectOption('1');
                assert.equal(await page.locator('[data-deactivation-confirm]').isVisible(), false);
            }
            await page.mouse.move(0, 0);
            await page.evaluate(() => { document.activeElement?.blur(); window.scrollTo(0, 0); });
            await page.screenshot({ path: fileURLToPath(new URL(`${view}-${device}.png`, artifacts)), fullPage: true, animations: 'disabled' });
        }
        console.log(`PASS ${view}: desktop, tablet, mobile, small mobile`);
    }
    assert.deepEqual(errors, []);
    console.log('PASS navigation drawer, password visibility, deactivation confirmation; no browser errors');
} finally {
    await browser.close();
}
