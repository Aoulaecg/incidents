import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright-core';

// Laravel-rendered fixtures use SQLite in memory; no fake accounts are inserted into MySQL.
const root = new URL('../', import.meta.url);
const artifacts = new URL('.artifacts/', root);
const manifest = JSON.parse(await readFile(new URL('public/build/manifest.json', root), 'utf8'));
const browser = await chromium.launch({ ...(process.env.BROWSER_EXECUTABLE_PATH ? { executablePath: process.env.BROWSER_EXECUTABLE_PATH } : { channel: 'msedge' }), headless: true });
const errors = [];
try {
    const page = await browser.newPage();
    page.on('pageerror', (error) => errors.push(error.message));
    await page.route('**/images/cgpark-logo.png', (route) => route.fulfill({ path: fileURLToPath(new URL('public/images/cgpark-logo.png', root)), contentType: 'image/png' }));
    const views = ['parking-list', 'parking-create', 'parking-edit', 'parking-supervisor-create', 'parking-supervisor-edit', 'parking-assignment-edit', 'parking-supervisor-list'];
    for (const view of views) {
        const html = await readFile(new URL(`${view}.html`, artifacts), 'utf8');
        for (const [width, height, device] of [[1440, 1000, 'desktop'], [768, 1024, 'tablet'], [390, 844, 'mobile'], [320, 740, 'small-mobile']]) {
            await page.goto('about:blank');
            await page.setViewportSize({ width, height });
            await page.setContent(html);
            await page.addStyleTag({ path: fileURLToPath(new URL(`public/build/${manifest['resources/css/app.css'].file}`, root)) });
            await page.addScriptTag({ path: fileURLToPath(new URL(`public/build/${manifest['resources/js/app.js'].file}`, root)) });
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `${view}/${device}: horizontal overflow`);
            if (width < 992) {
                await page.getByRole('button', { name: 'Ouvrir la navigation' }).click();
                assert.equal(await page.getByRole('link', { name: 'Gestion des parkings', exact: true }).isVisible(), true);
                await page.keyboard.press('Escape');
            }
            if (view === 'parking-create') assert.equal(await page.locator('#active').inputValue(), '1');
            if (view === 'parking-list') {
                assert.equal(await page.locator('table thead').isVisible(), width >= 768);
                assert.equal(new URL(await page.locator('.sidebar-link.is-current').getAttribute('href')).pathname, '/administration/parkings');
            }
            if (await page.locator('[data-parking-selector]').count()) {
                const selector = page.locator('[data-parking-selector]');
                const boxes = selector.locator('input[type=checkbox]');
                assert.equal(await boxes.count(), 14);
                const initiallyChecked = await selector.locator('input[type=checkbox]:checked').count();
                assert.equal(initiallyChecked, view === 'parking-supervisor-create' ? 0 : 2);
                assert.equal(await selector.locator('[data-parking-count]').textContent(), `${initiallyChecked} sélectionné${initiallyChecked > 1 ? 's' : ''}`);
                if (view !== 'parking-supervisor-create') assert.equal(await selector.getByText('Parking historique désactivé', { exact: true }).isVisible(), true);
                await selector.locator('[data-parking-search]').fill('aeroport');
                assert.equal(await selector.locator('[data-parking-option]:visible').count(), 1);
                assert.equal(await selector.locator('[data-parking-option]:visible strong').textContent(), 'Aéroport Marrakech');
                await selector.locator('[data-parking-search]').fill('parking introuvable');
                assert.equal(await selector.locator('[data-parking-no-match]').isVisible(), true);
                await selector.locator('[data-parking-search]').fill('');
                await selector.getByRole('button', { name: 'Tout sélectionner', exact: true }).click();
                assert.equal(await selector.locator('input[type=checkbox]:checked').count(), 14);
                assert.equal(await selector.locator('[data-parking-count]').textContent(), '14 sélectionnés');
                await selector.getByRole('button', { name: 'Désélectionner tout', exact: true }).click();
                assert.equal(await selector.locator('input[type=checkbox]:checked').count(), 0);
                await boxes.first().check();
                assert.equal(await selector.locator('[data-parking-count]').textContent(), '1 sélectionné');
                const grid = await selector.locator('.parking-assignment-grid').evaluate((node) => getComputedStyle(node).gridTemplateColumns.split(' ').length);
                assert.equal(grid, width >= 768 ? 2 : 1);
                if (await page.locator('#account_role').count()) {
                    for (const role of ['admin', 'directeur']) {
                        await page.locator('#account_role').selectOption(role);
                        assert.equal(await selector.isVisible(), false);
                        assert.equal(await boxes.first().isDisabled(), true);
                        assert.equal(await page.evaluate(() => new FormData(document.querySelector('main form')).has('parking_ids[]')), false);
                    }
                    await page.locator('#account_role').selectOption('supervisor');
                    assert.equal(await selector.isVisible(), true);
                    assert.equal(await boxes.first().isChecked(), true);
                }
            }
            if (view === 'parking-supervisor-list') {
                const details = page.locator('.assignment-names details');
                assert.equal(await details.locator('summary').textContent(), '10 parkings');
                await details.locator('summary').click();
                assert.equal(await details.locator('span:visible').count(), 10);
                await details.locator('summary').click();
            }
            await page.evaluate(() => { document.activeElement?.blur(); window.scrollTo(0, 0); });
            await page.screenshot({ path: fileURLToPath(new URL(`${view}-${device}.png`, artifacts)), fullPage: true, animations: 'disabled' });
        }
        console.log(`PASS ${view}: desktop, tablet, mobile, small mobile`);
    }
    assert.deepEqual(errors, []);
    console.log('PASS active selection, prechecked assignments, search, select/clear, live count, role changes, inactive history, expandable lists; no browser errors');
} finally { await browser.close(); }
