/** Native-radio keyboard behaviour and real-ACF section rendering, without JavaScript. */
/* global document, window */
import assert from 'node:assert/strict';
import path from 'node:path';
import { chromium } from 'playwright';

const browser = await chromium.launch();
try {
	const page = await browser.newPage({ javaScriptEnabled: false, viewport: { width: 1280, height: 900 } });
	const open = async (name) => {
		const response = await page.goto(`${process.env.SALT_HARNESS_URL}/section-cases/${name}.html`);
		assert.equal(response.status(), 200);
		await page.locator('link[rel="stylesheet"]').first().waitFor({ state: 'attached' });
	};
	const capture = async (name, selector) => {
		if (process.env.SALT_HARNESS_SCREENSHOTS) {
			await page.locator(selector).screenshot({ path: path.join(process.env.SALT_HARNESS_SCREENSHOTS, `${name}.png`) });
		}
	};

	await open('tabs--many-tabbed');
	const radios = page.getByRole('radio');
	const panels = page.locator('.salt-tabs__panel');
	assert.equal(await radios.count(), 3);
	assert.equal(await radios.nth(0).isChecked(), true);
	assert.equal(await panels.nth(0).isVisible(), true);
	assert.equal(await panels.nth(1).isVisible(), false);
	assert.equal(await page.getByRole('radiogroup').getAttribute('aria-label'), 'Questions about planting');
	assert.equal(await page.locator('.salt-tabs').getAttribute('aria-label'), null);
	await capture('tabs-default', '.salt-section');
	await radios.nth(0).focus();
	await page.keyboard.press('ArrowRight');
	assert.equal(await radios.nth(1).isChecked(), true);
	assert.equal(await panels.nth(0).isVisible(), false);
	assert.equal(await panels.nth(1).isVisible(), true);
	assert.equal(await radios.nth(1).evaluate((element) => element === document.activeElement), true);
	await capture('tabs-keyboard-selected', '.salt-section');
	await page.keyboard.press('Tab');
	assert.equal(await panels.nth(1).evaluate((element) => element === document.activeElement), true);
	await page.keyboard.press('Shift+Tab');
	assert.equal(await radios.nth(1).evaluate((element) => element === document.activeElement), true);
	await page.keyboard.press('ArrowLeft');
	await page.keyboard.press('ArrowLeft');
	assert.equal(await radios.nth(2).isChecked(), true, 'ArrowLeft wraps from the first to the last radio');
	await page.keyboard.press('ArrowRight');
	assert.equal(await radios.nth(0).isChecked(), true, 'ArrowRight wraps from the last to the first radio');
	await page.setViewportSize({ width: 390, height: 844 });
	await capture('tabs-mobile', '.salt-section');
	assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);
	console.log('PASS tabs: native arrows, wraparound, checked state, visible panel, Tab/Shift+Tab focus and mobile overflow, all without script');

	await page.setViewportSize({ width: 1280, height: 900 });
	for (const name of ['no-name-stacked', 'one-panel-stacked']) {
		await open(`tabs--${name}`);
		assert.equal(await page.getByRole('radiogroup').count(), 0);
		for (const panel of await page.locator('.salt-tabs__panel').all()) {
			assert.equal(await panel.isVisible(), true);
			assert.equal(await panel.locator('.salt-tabs__label').isVisible(), true);
		}
		await capture(`tabs-${name}`, '.salt-section');
	}
	await open('tabs--label-no-heading-tabbed');
	assert.equal(await page.getByRole('radiogroup').getAttribute('aria-label'), 'Opening times and contact');
	await page.getByText('Contact', { exact: true }).first().click();
	assert.equal(await page.getByRole('link', { name: '0117 234 5678' }).isVisible(), true);
	await capture('tabs-label-inverse', '.salt-section');
	await open('tabs--empty');
	assert.equal(await page.locator('[data-block="tabs"]').count(), 0);
	console.log('PASS tabs: single and unnamed sets stacked; label-only inverse set works; empty set omitted');

	for (const [name, current, total] of [['cards-with-pagination', 2, 3], ['cards-pagination-gaps', 5, 9]]) {
		await open(`listing--${name}`);
		assert.equal(await page.locator('.salt-card').count(), 3);
		assert.equal(await page.locator('.salt-pagination [aria-current="page"]').textContent(), String(current));
		assert.equal(await page.locator('.salt-pagination__gap').count(), total === 9 ? 2 : 0);
		assert.equal(await page.locator('.salt-card img').first().getAttribute('loading'), current === 2 ? null : 'lazy');
		assert.equal(await page.locator('.salt-card img').first().getAttribute('fetchpriority'), current === 2 ? 'high' : null);
		assert.equal(await page.locator('.salt-card img').nth(1).getAttribute('loading'), 'lazy');
		await capture(`listing-${name}`, '.salt-section');
	}
	await page.setViewportSize({ width: 390, height: 844 });
	assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);
	await capture('listing-mobile', '.salt-section');
	await page.setViewportSize({ width: 1280, height: 900 });
	await open('listing--empty-page-message');
	assert.equal(await page.locator('.salt-listing__empty').textContent(), 'There are no posts to show yet.');
	assert.equal(await page.locator('.salt-pagination').count(), 0);
	await capture('listing-empty', '.salt-section');
	await open('listing--empty');
	assert.equal(await page.locator('[data-block="listing"]').count(), 0);
	console.log('PASS listing: real paged cards, priority/lazy images, pagination gaps, mobile overflow, empty route message and absent route');
} finally {
	await browser.close();
}
