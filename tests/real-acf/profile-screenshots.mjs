/** Save the actual disposable-site profile, bylines, directory and editorial form. */
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const targets = JSON.parse(fs.readFileSync(process.env.SALT_HARNESS_STATE + '.profiles', 'utf8'));
const browser = await chromium.launch();
try {
	const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
	for (const [name, url] of Object.entries(targets)) {
		if (name === 'editor') {
			await page.goto(process.env.SALT_HARNESS_URL + '/wp-login.php');
			await page.fill('#user_login', 'harness-profile-editor');
			await page.fill('#user_pass', process.env.SALT_HARNESS_EDITOR_PASS);
			await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);
		}
		const response = await page.goto(url);
		if (response.status() !== 200) throw new Error(`${name}: HTTP ${response.status()}`);
		if (process.env.SALT_HARNESS_SCREENSHOTS) {
			await page.screenshot({ path: path.join(process.env.SALT_HARNESS_SCREENSHOTS, `team-${name}.png`), fullPage: true });
		}
		if (name === 'editor') {
			const role = page.locator('[data-key="field_salt_collection_team_role"] input');
			await role.fill('Community gardener');
			await Promise.all([page.waitForNavigation(), page.click('#submit')]);
			if (await role.inputValue() !== 'Community gardener') throw new Error('Profile form did not retain the editorial change');
			await page.goto(targets.profile);
			await page.getByText('Community gardener', { exact: true }).waitFor();
			console.log('PASS an Editor submits the public profile form and its updated role appears at the same public URL');
		}
	}
} finally {
	await browser.close();
}
