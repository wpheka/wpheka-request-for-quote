// The shop owner's side: the WPHEKA > Request For Quote page (1.9.0: Settings saved by a
// form post, the Requests tab, the stats) and the review request's buttons (Maybe later, X,
// I don't want to), which behave per user.
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const STATE = process.env.RFQ_STATE;
const ids = JSON.parse(fs.readFileSync(path.join(STATE, 'ids.json'), 'utf8'));
// WP_CLI overrides how WP-CLI is called (words split on spaces) on a machine
// where `wp` is a script node cannot start: WP_CLI="php /path/to/wp-cli.phar".
const WP_CLI = (process.env.WP_CLI || 'wp').split(' ');
const php = (file, ...args) => execFileSync(WP_CLI[0], [...WP_CLI.slice(1), '--path=' + process.env.WP_PATH, 'eval-file', path.join(__dirname, file), ...args], { encoding: 'utf8', env: process.env, stdio: ['ignore', 'pipe', 'ignore'] }).trim().split('\n').filter(l => !l.startsWith('Deprecated')).pop();
const record = (ok, name, detail) => { const line = `${ok ? 'PASS' : 'FAIL'}|${name}${ok ? '' : ' -- ' + detail}`; fs.appendFileSync(path.join(STATE, 'results'), line + '\n'); console.log('  ' + line); };
// A crash anywhere in this script is a FAIL, never a silent stop: run.sh also
// fails a non-zero exit and a missing "ran to the end" case.
const crashed = kind => e => {
  const lines = String((e && e.stack) || e).split('\n');
  try { record(false, `admin.js: ${kind}`, [lines[0], (lines.find(l => /^\s+at /.test(l)) || '').trim()].filter(Boolean).join(' ')); } catch (ignored) {}
  process.exit(1);
};
process.on('unhandledRejection', crashed('no unhandled promise rejection'));
process.on('uncaughtException', crashed('no uncaught exception'));
const A = ids.admin_url;
async function check(name, fn) {
  try { const [ok, detail] = await fn(); record(ok, name, detail); } catch (e) { record(false, name, e.message.split('\n')[0]); }
}

(async () => {
  // PW_CHANNEL=chrome runs the installed Google Chrome instead of Playwright's
  // bundled Chromium (for a machine without the matching browser download).
  const browser = await chromium.launch(process.env.PW_CHANNEL ? { channel: process.env.PW_CHANNEL } : {});
  const ctx = await browser.newContext({ viewport: { width: 1400, height: 1400 } });
  await ctx.addCookies(ids.cookies);
  const errors = [];
  const newPage = async () => { const p = await ctx.newPage(); p.setDefaultTimeout(120000); p.on('pageerror', e => errors.push(`${p.url()}: ${e && (e.stack || e.message)}`)); return p; };

  // ---- 1.9.0 page: WPHEKA > Request For Quote (Settings, Requests, Help), saved by a form post.
  const PAGE = `${A}admin.php?page=wpheka_request_for_quote`;
  const F = k => `[name="wpheka_rfq_general_settings[${k}]"]`;
  const setRadio = (page, k, v) => page.locator(`${F(k)}[value="${v}"]`).evaluate(el => { el.checked = true; el.dispatchEvent(new Event('change', { bubbles: true })); });
  const setSwitch = (page, name, on) => page.locator(`input[role="switch"][name="${name}"]`).evaluate((el, on) => { el.checked = on; el.dispatchEvent(new Event('change', { bubbles: true })); }, on);
  const submit = async page => { await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.locator('#rfq-settings').evaluate(f => f.requestSubmit())]); };
  const toast = async page => ({ saved: await page.locator('.w-toast.is-shown[role="status"]', { hasText: 'Settings saved' }).count(), error: await page.locator('.w-toast--error.is-shown[role="alert"]').count(), errorText: (await page.locator('.w-toast--error').innerText().catch(() => '')).trim() });
  const stored = () => JSON.parse(php('settings-read.php', 'all'));
  const asUser = async who => { const c = await browser.newContext({ viewport: { width: 1400, height: 1400 } }); await c.addCookies(ids[who + '_cookies']); const p = await c.newPage(); p.setDefaultTimeout(120000); return { c, p }; };

  await check('settings page: every field saves and shows again after the reload', async () => {
    const page = await newPage();
    await page.goto(`${PAGE}&tab=settings`, { waitUntil: 'load' });
    await setRadio(page, 'user_type', 'logged');
    await setRadio(page, 'out_of_stock_option', 'only_out_of_stock');
    await setRadio(page, 'button_position', 'before');
    await setRadio(page, 'button_type', 'link');
    await page.fill(F('button_link_text'), 'Price please');
    await setSwitch(page, 'wpheka_rfq_general_settings[button_in_other_pages]', false);
    await setSwitch(page, 'wpheka_rfq_general_settings[hide_price]', true);
    await setSwitch(page, 'wpheka_rfq_general_settings[hide_add_to_cart]', false);
    await page.selectOption('select[name="wpheka_request_for_quote_page_id"]', String(ids.quote_page_id));
    await setRadio(page, 'after_click_action', 'redirect');
    await setSwitch(page, 'wpheka_rfq_general_settings[show_phone_field]', true);
    await setSwitch(page, 'wpheka_rfq_general_settings[phone_required]', true);
    await setSwitch(page, 'wpheka_rfq_general_settings[show_company_field]', true);
    await setSwitch(page, 'wpheka_rfq_delete_data', true);
    await submit(page);
    const t = await toast(page);
    const s = stored();
    const want = { user_type: 'logged', out_of_stock_option: 'only_out_of_stock', button_position: 'before', button_type: 'link', button_link_text: 'Price please', button_in_other_pages: 'no', hide_price: 'yes', hide_add_to_cart: 'no', after_click_action: 'redirect', show_phone_field: 'yes', phone_required: 'yes', show_company_field: 'yes', wpheka_request_for_quote_page_id: String(ids.quote_page_id) };
    const wrong = Object.keys(want).filter(k => String(s[k]) !== want[k]);
    const shown = { user: await page.locator(`${F('user_type')}[value="logged"]`).isChecked(), text: await page.locator(F('button_link_text')).inputValue(), del: await page.locator('input[role="switch"][name="wpheka_rfq_delete_data"]').isChecked(), page: await page.locator('select[name="wpheka_request_for_quote_page_id"]').inputValue() };
    const del = php('option.php', 'wpheka_rfq_delete_data');
    await page.close();
    return [t.saved === 1 && t.error === 0 && wrong.length === 0 && del === 'yes' && shown.user && shown.text === 'Price please' && shown.del && shown.page === String(ids.quote_page_id),
      `toast ${JSON.stringify(t)}, wrong ${JSON.stringify(wrong.map(k => [k, s[k]]))}, delete_data ${del}, shown ${JSON.stringify(shown)}`];
  });
  php('settings.php', 'hide_price=yes');
  php('set-option.php', 'wpheka_rfq_delete_data', 'no');

  await check('settings page: a left nav link glides to its section, marks it and keeps the #hash', async () => {
    const page = await newPage();
    await page.goto(`${PAGE}&tab=settings`, { waitUntil: 'load' });
    const link = page.locator('.w-nav-link[href^="#"]').last();
    const hash = await link.getAttribute('href');
    await link.click();
    // Sample the scroll position while it moves: a jump has no in-between values.
    const ys = await page.evaluate(() => new Promise(done => { const out = []; const t = setInterval(() => out.push(Math.round(window.scrollY)), 16); setTimeout(() => { clearInterval(t); done(out); }, 1200); }));
    const end = ys[ys.length - 1];
    const between = ys.filter(y => y > 0 && y < end).length;
    const top = await page.evaluate(h => Math.round(document.querySelector(h).getBoundingClientRect().top), hash);
    const atBottom = await page.evaluate(() => Math.ceil(window.scrollY + window.innerHeight) >= document.documentElement.scrollHeight - 1);
    const active = await page.locator('.w-nav-link.is-active').getAttribute('href');
    const url = new URL(page.url()).hash;
    await page.close();
    const ok = between >= 3 && (top < 200 || atBottom) && active === hash && url === hash;
    return [ok, `in-between positions ${between}, section top ${top}, active ${active}, url hash ${url}`];
  });

  await check('settings page: with reduced motion, a left nav link jumps straight to its section', async () => {
    const page = await newPage();
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto(`${PAGE}&tab=settings`, { waitUntil: 'load' });
    const link = page.locator('.w-nav-link[href^="#"]').last();
    await link.click();
    const y = await page.evaluate(() => new Promise(done => requestAnimationFrame(() => done(Math.round(window.scrollY)))));
    await page.waitForTimeout(400);
    const later = await page.evaluate(() => Math.round(window.scrollY));
    await page.close();
    return [y > 0 && y === later, `first frame ${y}, settled ${later}`];
  });

  await check('settings page: the button text saved with the Save button shows on the product', async () => {
    const page = await newPage();
    await page.goto(`${PAGE}&tab=settings`, { waitUntil: 'load' });
    await page.fill(F('button_link_text'), 'Ask for a price');
    await page.locator('.w-savebar button[type="submit"]').waitFor({ state: 'visible' });
    await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.locator('.w-savebar button[type="submit"]').click()]);
    const t = await toast(page);
    const g = await browser.newContext(); const gp = await g.newPage(); gp.setDefaultTimeout(90000);
    await gp.goto(ids.in_url, { waitUntil: 'load' });
    const shown = await gp.locator('.wpheka-add-to-quote-button', { hasText: 'Ask for a price' }).count();
    await g.close(); await page.close();
    return [t.saved === 1 && stored().button_link_text === 'Ask for a price' && shown === 1, `toast ${JSON.stringify(t)}, on product ${shown}`];
  });

  await check('settings page: an empty button text is saved as "Add to quote" and the product shows it', async () => {
    const page = await newPage();
    await page.goto(`${PAGE}&tab=settings`, { waitUntil: 'load' });
    await page.fill(F('button_link_text'), '');
    await submit(page);
    const s = stored().button_link_text;
    const g = await browser.newContext(); const gp = await g.newPage(); gp.setDefaultTimeout(90000);
    await gp.goto(ids.in_url, { waitUntil: 'load' });
    const shown = await gp.locator('.wpheka-add-to-quote-button', { hasText: 'Add to quote' }).count();
    await g.close(); await page.close();
    return [s === 'Add to quote' && shown === 1, `stored "${s}", on product ${shown}`];
  });
  php('settings.php', 'hide_price=yes', 'button_link_text=');
  await check('settings page: a stored empty button text still prints "Add to quote" on the product', async () => {
    const g = await browser.newContext(); const gp = await g.newPage(); gp.setDefaultTimeout(90000);
    await gp.goto(ids.in_url, { waitUntil: 'load' });
    const shown = await gp.locator('.wpheka-add-to-quote-button', { hasText: 'Add to quote' }).count();
    await g.close();
    return [shown === 1, `on product ${shown}`];
  });
  php('settings.php', 'hide_price=yes');

  await check('settings page: with no quote page chosen, a red error and never "Settings saved"; nothing is stored', async () => {
    const before = php('settings-read.php', 'all');
    const pageBefore = php('option.php', 'wpheka_request_for_quote_page_id');
    const page = await newPage();
    await page.goto(`${PAGE}&tab=settings`, { waitUntil: 'load' });
    await page.fill(F('button_link_text'), 'Must not be saved');
    await page.selectOption('select[name="wpheka_request_for_quote_page_id"]', '');
    await submit(page);
    const t = await toast(page);
    await page.waitForTimeout(3500); // a saved toast would have shown and faded by now
    const later = await toast(page);
    const after = php('settings-read.php', 'all');
    const pageAfter = php('option.php', 'wpheka_request_for_quote_page_id');
    await page.close();
    return [t.error === 1 && /quote page is not set/i.test(t.errorText) && t.saved === 0 && later.saved === 0 && later.error === 1 && after === before && pageAfter === pageBefore,
      `toast ${JSON.stringify(t)}, later ${JSON.stringify(later)}, settings unchanged ${after === before}, page ${pageBefore}->${pageAfter}`];
  });

  await check('settings page: a shop manager sees it in the WPHEKA menu, opens it and saves', async () => {
    const { c, p } = await asUser('manager');
    const r = await p.goto(`${PAGE}&tab=settings`, { waitUntil: 'load' });
    const menu = await p.locator('#toplevel_page_wpheka_plugin_panel .wp-submenu a', { hasText: 'Request For Quote' }).count();
    await p.fill(F('button_link_text'), 'Manager text');
    await submit(p);
    const t = await toast(p);
    await c.close();
    const s = stored().button_link_text;
    return [r.status() === 200 && menu === 1 && t.saved === 1 && s === 'Manager text', `page ${r.status()}, menu ${menu}, toast ${JSON.stringify(t)}, stored ${s}`];
  });
  php('settings.php', 'hide_price=yes');

  await check('settings page: a subscriber cannot open it', async () => {
    const { c, p } = await asUser('subscriber');
    const r = await p.goto(`${PAGE}&tab=settings`, { waitUntil: 'load' });
    const form = await p.locator('#rfq-settings').count();
    await c.close();
    return [form === 0 && r.status() >= 400, `status ${r.status()}, form ${form}`];
  });

  // ---- Requests tab and stats.
  const others = parseInt(php('requests-read.php', 'others') || '0', 10);
  php('requests-read.php', 'clear');
  if (others === 0) {
    await check('requests: with nothing stored, the stats say so and the tab shows the empty state', async () => {
      const page = await newPage();
      await page.goto(`${PAGE}&tab=requests`, { waitUntil: 'load' });
      const stats = await page.locator('.w-stat').allInnerTexts();
      const empty = await page.locator('.w-empty', { hasText: 'Requests are stored from version 1.9.0' }).count();
      const count = await page.locator('.w-tab', { hasText: 'Requests' }).locator('.w-count').count();
      await page.close();
      const flat = stats.join(' | ');
      return [empty === 1 && count === 0 && (flat.match(/Counted from version 1\.9\.0/g) || []).length === 3 && /None yet/.test(flat), `empty ${empty}, count ${count}, stats ${flat.replace(/\s+/g, ' ')}`];
    });
  } else {
    record(true, 'requests: with nothing stored (skipped: this store already has requests)');
  }
  php('requests-read.php', 'seed', '3');
  await check('requests: the tab lists them newest first and its label counts the New ones', async () => {
    const page = await newPage();
    await page.goto(`${PAGE}&tab=requests`, { waitUntil: 'load' });
    const names = await page.locator('table.rfq-requests-table tbody tr').evaluateAll(trs => trs.map(tr => tr.cells[1].innerText.split('\n')[0]));
    const count = (await page.locator('.w-tab', { hasText: 'Requests' }).locator('.w-count').innerText().catch(() => '')).trim();
    const heads = await page.locator('table.rfq-requests-table thead th').allInnerTexts();
    await page.close();
    const mine = names.filter(n => /Zz Seed/.test(n));
    return [JSON.stringify(mine) === JSON.stringify(['Zz Seed 3', 'Zz Seed 2', 'Zz Seed 1']) && parseInt(count, 10) >= 3 && heads.slice(0, 5).join('|').toLowerCase() === 'request|customer|products|status|received', `names ${JSON.stringify(names)}, count ${count}, heads ${heads.join('|')}`];
  });
  await check('requests: the stats show the stored numbers', async () => {
    const page = await newPage();
    await page.goto(`${PAGE}&tab=requests`, { waitUntil: 'load' });
    const card = async label => (await page.locator('.w-stat', { hasText: label }).locator('.w-stat-value').innerText()).trim();
    const products = await card('Products requested');
    const top = await card('Most requested');
    const avg = await card('Average request');
    await page.close();
    return [others > 0 || (products === '6' && /ZZ RFQ In Stock/.test(top) && /\d/.test(avg)), `products ${products}, top ${top}, average ${avg}`];
  });
  await check('requests: changing a status from the tab saves it and says so', async () => {
    const rows = JSON.parse(php('requests-read.php'));
    const target = rows.find(r => r.customer_name === 'Zz Seed 2');
    const page = await newPage();
    await page.goto(`${PAGE}&tab=requests`, { waitUntil: 'load' });
    const tr = page.locator(`tr[data-request="${target.id}"]`);
    await tr.locator('select[name="status"]').selectOption('replied');
    await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), tr.locator('.rfq-status-form button').click()]);
    const notice = await page.locator('.w-notice--success[role="status"]', { hasText: 'Status changed.' }).count();
    const pill = (await page.locator(`tr[data-request="${target.id}"] .w-pill`).innerText()).trim();
    await page.close();
    const now = JSON.parse(php('requests-read.php')).find(r => r.id === target.id);
    return [notice === 1 && pill === 'Replied' && now.status === 'replied', `notice ${notice}, pill ${pill}, stored ${now.status}`];
  });
  await check('requests: deleting from the tab asks first and removes it', async () => {
    const rows = JSON.parse(php('requests-read.php'));
    const target = rows.find(r => r.customer_name === 'Zz Seed 1');
    const page = await newPage();
    let asked = '';
    page.on('dialog', d => { asked = d.message(); d.accept(); });
    await page.goto(`${PAGE}&tab=requests`, { waitUntil: 'load' });
    await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.locator(`tr[data-request="${target.id}"] .rfq-delete-form button`).click()]);
    const notice = await page.locator('.w-notice', { hasText: 'Request deleted.' }).count();
    const still = await page.locator(`tr[data-request="${target.id}"]`).count();
    await page.close();
    const gone = !JSON.parse(php('requests-read.php')).some(r => r.id === target.id);
    return [/cannot be undone/.test(asked) && notice === 1 && still === 0 && gone, `asked "${asked}", notice ${notice}, row ${still}, deleted ${gone}`];
  });
  await check('notices: other plugins\' notices stay off the plugin page, and show on the Dashboard', async () => {
    const page = await newPage();
    await page.goto(`${A}index.php`, { waitUntil: 'load' });
    const elsewhere = await page.locator('.zz-rfq-foreign').count();
    let here = 0, stray = false;
    for (const tab of ['settings', 'requests']) {
      await page.goto(`${PAGE}&tab=${tab}`, { waitUntil: 'load' });
      here += await page.locator('.zz-rfq-foreign').count();
      stray = stray || await page.locator('.zz-rfq-stray').isVisible();
    }
    await page.close();
    return [elsewhere === 3 && here === 0 && !stray, `dashboard ${elsewhere} (want 3), plugin page ${here}, stray visible ${stray}`];
  });
  await check('requests: a failed change shows a red notice that screen readers announce', async () => {
    const page = await newPage();
    await page.goto(`${PAGE}&tab=requests&rfq_notice=error`, { waitUntil: 'load' });
    const n = await page.locator('.w-notice--error[role="alert"]', { hasText: 'could not be changed' }).count();
    await page.close();
    return [n === 1, `error notices ${n}`];
  });
  await check('requests: a status change without a valid nonce is refused', async () => {
    const rows = JSON.parse(php('requests-read.php'));
    const target = rows.find(r => r.customer_name === 'Zz Seed 3');
    const page = await newPage();
    await page.goto(`${PAGE}&tab=requests`, { waitUntil: 'load' });
    const res = await page.request.post(`${A}admin-post.php`, { form: { action: 'wpheka_rfq_request_status', request_id: String(target.id), status: 'closed', _wpnonce: 'bogus' }, maxRedirects: 0 });
    await page.close();
    const now = JSON.parse(php('requests-read.php')).find(r => r.id === target.id);
    return [res.status() >= 400 && now.status === 'new', `HTTP ${res.status()}, status ${now.status}`];
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
      resp.catch(() => {}); // a failed click must not leave this wait to crash the run
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
  record(true, 'admin.js: ran to the end');
})();
