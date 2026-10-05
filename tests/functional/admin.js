// The shop owner's side: the Settings page saves through its AJAX handler, and
// the review request's buttons (Maybe later, X, I don't want to) behave per user.
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const STATE = process.env.RFQ_STATE;
const ids = JSON.parse(fs.readFileSync(path.join(STATE, 'ids.json'), 'utf8'));
const php = (file, ...args) => execFileSync('wp', ['--path=' + process.env.WP_PATH, 'eval-file', path.join(__dirname, file), ...args], { encoding: 'utf8', env: process.env, stdio: ['ignore', 'pipe', 'ignore'] }).trim().split('\n').filter(l => !l.startsWith('Deprecated')).pop();
const record = (ok, name, detail) => { const line = `${ok ? 'PASS' : 'FAIL'}|${name}${ok ? '' : ' -- ' + detail}`; fs.appendFileSync(path.join(STATE, 'results'), line + '\n'); console.log('  ' + line); };
const A = ids.admin_url;
async function check(name, fn) {
  try { const [ok, detail] = await fn(); record(ok, name, detail); } catch (e) { record(false, name, e.message.split('\n')[0]); }
}

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1400, height: 1400 } });
  await ctx.addCookies(ids.cookies);
  const errors = [];
  const newPage = async () => { const p = await ctx.newPage(); p.setDefaultTimeout(120000); p.on('pageerror', e => errors.push(`${p.url()}: ${e && (e.stack || e.message)}`)); return p; };

  await check('settings page: changing the button text saves and shows on the product', async () => {
    const page = await newPage();
    await page.goto(`${A}admin.php?page=wpheka_request_for_quote`, { waitUntil: 'load' });
    const field = page.locator('#button_link_text');
    await field.fill('Ask for a price');
    const resp = page.waitForResponse(r => r.url().includes('admin-ajax.php') && (r.request().postData() || '').includes('save_wpheka_rfq_plugin_data'));
    await page.locator('.wpheka-save-changes').click();
    const r = await resp;
    const body = await r.text().catch(() => '(reloaded)');
    const stored = JSON.parse(php('settings-read.php', 'button_link_text'));
    const g = await browser.newContext(); const gp = await g.newPage(); gp.setDefaultTimeout(90000);
    await gp.goto(ids.in_url, { waitUntil: 'load' });
    const shown = await gp.locator('.wpheka-add-to-quote-button', { hasText: 'Ask for a price' }).count();
    await g.close(); await page.close();
    return [r.status() === 200 && stored === 'Ask for a price' && shown === 1, `save ${r.status()} ${body.slice(0, 60)}, stored ${stored}, on product ${shown}`];
  });
  php('settings.php', 'hide_price=yes');

  // Review request. The quote sent by frontend.js brings the count to the threshold.
  const clear = () => { php('usermeta.php', 'clear', 'wpheka_rfq_review_snoozed_until'); php('usermeta.php', 'clear', 'wpheka_rfq_review_dismissed'); };
  clear();
  php('set-option.php', 'wpheka_rfq_quote_count', '3');
  await check('review request: shows on the Dashboard after three quotes', async () => {
    const page = await newPage(); await page.goto(`${A}index.php`, { waitUntil: 'load' });
    const n = await page.locator('#wpheka-rfq-review-notice').count(); await page.close();
    return [n === 1, 'not shown'];
  });
  for (const [label, click, metaKey, expectDays] of [
    ['"Maybe later" snoozes it for 14 days', '[data-wpheka-rfq-review="later"]', 'wpheka_rfq_review_snoozed_until', 14],
    ['X snoozes it for 14 days', '.notice-dismiss', 'wpheka_rfq_review_snoozed_until', 14],
    ['"I don\'t want to leave a review" hides it for good', '[data-wpheka-rfq-review="never"]', 'wpheka_rfq_review_dismissed', 0],
  ]) {
    clear();
    await check(`review request: ${label}`, async () => {
      const page = await newPage(); await page.goto(`${A}index.php`, { waitUntil: 'load' });
      const resp = page.waitForResponse(r => r.url().includes('admin-ajax.php') && /wpheka_rfq_(snooze|dismiss)_review/.test(r.request().postData() || ''));
      await page.locator(`#wpheka-rfq-review-notice ${click}`).first().click();
      const r = await resp;
      await page.goto(`${A}index.php`, { waitUntil: 'load' });
      const gone = (await page.locator('#wpheka-rfq-review-notice').count()) === 0;
      const v = JSON.parse(php('usermeta.php', 'get', metaKey));
      const ok = expectDays ? Math.abs((parseInt(v, 10) - Date.now() / 1000) / 86400 - 14) < 0.05 : String(v) === '1';
      await page.close();
      return [r.status() === 200 && gone && ok, `ajax ${r.status()}, hidden ${gone}, ${metaKey}=${v}`];
    });
  }
  await check('review request: the snooze is per user, not site-wide', async () => {
    const t = php('option.php', '_transient_wpheka_rfq_review_snoozed');
    return [!t, `site-wide transient set: ${t}`];
  });

  const real = errors.filter(e => !/Transition was skipped/.test(e));
  record(real.length === 0, 'no JavaScript errors on the admin screens', JSON.stringify(real).slice(0, 400));
  await browser.close();
})();
