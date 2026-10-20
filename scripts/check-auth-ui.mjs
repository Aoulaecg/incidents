import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright-core';

// Use an existing browser; no browser download is needed on Windows/XAMPP.
const browserOptions = process.env.BROWSER_EXECUTABLE_PATH
    ? { executablePath: process.env.BROWSER_EXECUTABLE_PATH }
    : { channel: 'msedge' };
const baseUrl = process.env.TEST_APP_URL || 'http://127.0.0.1:8000';
const artifacts = new URL('../.artifacts/', import.meta.url);
await mkdir(artifacts, { recursive: true });
const browser = await chromium.launch({ ...browserOptions, headless: true });
const errors = [];

try {
    const context = await browser.newContext();
    const page = await context.newPage();
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('response', (response) => {
        if (response.status() >= 500) errors.push(`${response.status()}: ${response.url()}`);
    });

    for (const [portal, prefix, title] of [
        ['/connexion', 'login', 'Journal d’Incidents'],
        ['/administration/connexion', 'admin-login', 'Espace Administrateur'],
        ['/direction/connexion', 'director-login', 'Espace Direction'],
    ]) {
    for (const [width, height, name] of [
        [1440, 1000, 'desktop'], [768, 1024, 'tablet'],
        [390, 844, 'mobile'], [320, 740, 'small-mobile'],
    ]) {
        await page.setViewportSize({ width, height });
        const response = await page.goto(`${baseUrl}${portal}`);
        assert.equal(response.status(), 200);
        await page.locator('[data-password-toggle]:not([hidden])').waitFor();
        await page.evaluate(() => document.fonts.ready);
        assert.equal(await page.locator('h1').textContent(), title);
        assert.equal(await page.locator('[data-submit-button]').isDisabled(), false, 'Submit must remain usable after pageshow');
        if (portal === '/connexion') {
            assert.equal(await page.locator('#user_id').count(), 1);
            assert.equal(await page.locator('a[href*="administration"], a[href*="direction"]').count(), 0);
        } else {
            assert.equal(await page.locator('select').count(), 0);
            assert.equal(await page.locator('#email').getAttribute('autocomplete'), 'username');
        }
        await page.getByRole('button', { name: 'Afficher le mot de passe' }).click();
        assert.equal(await page.locator('#password').getAttribute('type'), 'text');
        await page.getByRole('button', { name: 'Masquer le mot de passe' }).click();
        assert.equal(await page.locator('#password').getAttribute('type'), 'password');
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `${name}: horizontal overflow`);
        const card = await page.locator('.auth-card').boundingBox();
        assert.ok(card.width <= Math.min(width, 456), `${name}: card width`);
        assert.ok(await page.locator('.company-logo').evaluate((logo) => logo.complete && logo.naturalWidth > 0), 'Logo must load');
        await page.mouse.move(0, 0);
        await page.evaluate(() => { document.activeElement?.blur(); window.scrollTo(0, 0); });
        await page.screenshot({ path: fileURLToPath(new URL(`${prefix}-${name}.png`, artifacts)), fullPage: true, animations: 'disabled' });
        console.log(`PASS ${portal} ${name} (${width} × ${height})`);
    }
    }
    await page.goto(`${baseUrl}/connexion`);

    await page.getByRole('button', { name: 'Afficher le mot de passe' }).click();
    assert.equal(await page.locator('#password').getAttribute('type'), 'text');
    await page.getByRole('button', { name: 'Masquer le mot de passe' }).click();
    assert.equal(await page.locator('#password').getAttribute('type'), 'password');

    // Credentials are optional and supplied by the operator; never stored in source.
    const supervisorId = process.env.TEST_SUPERVISOR_ID;
    const supervisorPassword = process.env.TEST_SUPERVISOR_PASSWORD;
    if (!supervisorId || !supervisorPassword) {
        assert.deepEqual(errors, [], 'Browser errors');
        console.log('PASS login layout and password visibility; authenticated flow skipped (no test credentials supplied)');
    } else {
    await page.locator('#user_id').selectOption(supervisorId);
    const selectedLabel = await page.locator('#user_id option:checked').textContent();
    await page.locator('#password').fill('wrong-password');
    await Promise.all([
        page.waitForURL(`${baseUrl}/connexion`, { waitUntil: 'load' }),
        page.getByRole('button', { name: 'Se connecter' }).click(),
    ]);
    await page.getByRole('alert').waitFor();
    assert.ok((await page.getByRole('alert').textContent()).includes('Connexion impossible'));
    assert.equal(await page.locator('#password').inputValue(), '');
    assert.equal(await page.locator('#user_id option:checked').textContent(), selectedLabel);
    await page.screenshot({ path: fileURLToPath(new URL('login-error-mobile.png', artifacts)), fullPage: true, animations: 'disabled' });

    const cookieBefore = (await context.cookies()).find((cookie) => cookie.httpOnly);
    await page.locator('#password').fill(supervisorPassword);
    await Promise.all([
        page.waitForURL(`${baseUrl}/tableau-de-bord`),
        page.getByRole('button', { name: 'Se connecter' }).click(),
    ]);
    assert.ok((await page.locator('h1').textContent()).includes('Déclaration quotidienne'));
    const cookieAfter = (await context.cookies()).find((cookie) => cookie.httpOnly);
    assert.ok(cookieAfter);
    assert.notEqual(cookieBefore.value, cookieAfter.value, 'Session cookie must rotate');
    await page.screenshot({ path: fileURLToPath(new URL('connected-mobile.png', artifacts)), fullPage: true, animations: 'disabled' });
    await Promise.all([
        page.waitForURL(`${baseUrl}/connexion`),
        page.getByRole('button', { name: 'Se déconnecter' }).click(),
    ]);
    await page.getByRole('status').waitFor();
    await page.goto(`${baseUrl}/tableau-de-bord`);
    assert.equal(new URL(page.url()).pathname, '/connexion');
    assert.deepEqual(errors, [], 'Browser errors');
    console.log('PASS password toggle, invalid credentials, login, session rotation, logout, protected page');
    }
} finally {
    await browser.close();
}
