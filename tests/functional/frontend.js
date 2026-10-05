// What a shopper sees and does, in headless Chromium as a guest: the Add to
// quote button and hidden price/cart on product and shop pages, adding, the
// quote list (update, remove), sending the request and the email it produces,
// and each display setting. Results go to $RFQ_STATE/results.
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const STATE = process.env.RFQ_STATE;
const ids = JSON.parse(fs.readFileSync(path.join(STATE, 'ids.json'), 'utf8'));
const php = (file, ...args) => execFileSync('wp', ['--path=' + process.env.WP_PATH, 'eval-file', path.join(__dirname, file), ...args], { encoding: 'utf8', env: process.env, stdio: ['ignore', 'pipe', 'ignore'] }).trim().split('\n').filter(l => !l.startsWith('Deprecated')).pop();
const record = (ok, name, detail) => { const line = `${ok ? 'PASS' : 'FAIL'}|${name}${ok ? '' : ' -- ' + detail}`; fs.appendFileSync(path.join(STATE, 'results'), line + '\n'); console.log('  ' + line); };
const mail = () => { try { return fs.readFileSync(path.join(STATE, 'mail.log'), 'utf8').trim().split('\n').filter(Boolean).map(JSON.parse); } catch (e) { return []; } };
const errors = [];

async function guest(browser, theme) {
  const ctx = await browser.newContext({ viewport: { width: 1300, height: 1400 } });
  if (theme === 'storefront') await ctx.addCookies([{ name: 'zz_rfq_theme', value: 'storefront', domain: 'localhost', path: '/' }]);
  const page = await ctx.newPage();
  page.setDefaultTimeout(90000);
  page.on('pageerror', e => errors.push(`${page.url()}: ${e && (e.stack || e.message)}`));
  return { ctx, page };
}
async function check(name, fn) {
  try { const [ok, detail] = await fn(); record(ok, name, detail); } catch (e) { record(false, name, e.message.split('\n')[0]); }
}
const quoteButton = '.wpheka-add-to-quote-button';
async function productState(page, url) {
  await page.goto(url, { waitUntil: 'load' });
  const summary = page.locator('.summary, .product').first();
  // Counted as a shopper sees them: the plugin hides Add to cart with CSS, so an
  // element in the page but not visible is hidden.
  const visible = async sel => { let n = 0; for (const el of await page.locator(sel).all()) if (await el.isVisible()) n++; return n; };
  return {
    button: await visible(quoteButton),
    link: await visible(`a${quoteButton}`),
    price: await visible('.summary .price .amount, .product .price .amount'),
    cart: await visible('form.cart button[name="add-to-cart"], form.cart .single_add_to_cart_button'),
  };
}

(async () => {
  const browser = await chromium.launch();

  // Baseline: button, price and add to cart hidden, shown on shop pages, phone required.
  php('settings.php', 'hide_price=yes');
  for (const theme of ids.themes) {
    const g = await guest(browser, theme);
    await check(`[${theme}] product page in stock: Add to quote shown, price and Add to cart hidden`, async () => {
      const s = await productState(g.page, ids.in_url);
      return [s.button === 1 && s.price === 0 && s.cart === 0, JSON.stringify(s)];
    });
    await check(`[${theme}] product page out of stock: Add to quote shown`, async () => {
      const s = await productState(g.page, ids.out_url);
      return [s.button === 1, JSON.stringify(s)];
    });
    await g.ctx.close();
  }
  let { ctx, page } = await guest(browser);
  await check('product page: clicking Add to quote adds it and offers the list', async () => {
    await page.goto(ids.in_url, { waitUntil: 'load' });
    await page.locator(quoteButton).click();
    await page.waitForSelector('text=Product added to quote list', { timeout: 30000 });
    return [(await page.locator(`a[href="${ids.quote_page}"], a[href*="request-quote"]`).count()) > 0, 'no link to the quote list'];
  });
  await check('product page: after a reload it says the product is already in the list', async () => {
    await page.goto(ids.in_url, { waitUntil: 'load' });
    return [(await page.locator('text=Product already in the quote list').count()) === 1 && (await page.locator(quoteButton).count()) === 0, 'still offers the button'];
  });
  await check('shop page: Add to quote shown in the product loop, Add to cart hidden', async () => {
    await page.goto(ids.shop_url, { waitUntil: 'load' });
    const out = await page.locator(`.add-to-quote-${ids.out} ${quoteButton}, ${quoteButton}[data-product_id="${ids.out}"]`).count();
    const cart = await page.locator(`a.add_to_cart_button[data-product_id="${ids.out}"], a[href*="add-to-cart=${ids.out}"]`).count();
    return [out >= 1 && cart === 0, `quote buttons for out-of-stock product: ${out}, add-to-cart links: ${cart}`];
  });
  await check('shop page: Add to quote from the loop adds the second product', async () => {
    await page.locator(`${quoteButton}[data-product_id="${ids.out}"]`).first().click();
    await page.waitForTimeout(3000);
    await page.goto(ids.quote_page, { waitUntil: 'load' });
    const rows = await page.locator('tr.rfq_item').count();
    return [rows === 2, `${rows} row(s) in the quote list`];
  });
  await check('quote list: Update list changes a quantity', async () => {
    const qty = page.locator('tr.rfq_item input.qty').first();
    if (!(await qty.count())) return [true, 'no quantity inputs'];
    await qty.fill('3');
    await Promise.all([page.waitForLoadState('load'), page.locator('button[name="update_rfq"]').click()]);
    await page.waitForTimeout(2500);
    await page.goto(ids.quote_page, { waitUntil: 'load' });
    const v = await page.locator('tr.rfq_item input.qty').first().inputValue();
    return [v === '3', `quantity after update: ${v}`];
  });
  await check('quote list: the remove link takes a product out', async () => {
    await page.locator('tr.rfq_item td.product-remove a.remove').last().click();
    await page.waitForTimeout(3000);
    await page.goto(ids.quote_page, { waitUntil: 'load' });
    const rows = await page.locator('tr.rfq_item').count();
    return [rows === 1, `${rows} row(s) left`];
  });
  await check('request form: name, email, phone (required), company and message fields', async () => {
    const f = ['#rfq_display_name', '#rfq_email', '#rfq_phone', '#rfq_company', '#rfq_message'];
    const present = await Promise.all(f.map(s => page.locator(s).count()));
    const phoneRequired = await page.locator('#rfq_phone').getAttribute('required');
    return [present.every(n => n === 1) && phoneRequired !== null, `fields ${present}, phone required ${phoneRequired}`];
  });
  const before = mail().length;
  const countBefore = parseInt(php('option.php', 'wpheka_rfq_quote_count') || '0', 10) || 0;
  await check('request form: sending succeeds, empties the list and emails the shop', async () => {
    await page.fill('#rfq_display_name', 'Zz Quote');
    await page.fill('#rfq_email', 'zz-rfq-guest@example.invalid');
    await page.fill('#rfq_phone', '2045550100');
    await page.fill('#rfq_company', 'Zz Co');
    await page.fill('#rfq_message', 'Please quote ZZ RFQ items.');
    await page.locator('.wpheka-quote-request-form-submit').click();
    await page.waitForSelector('text=Your request has been sent successfully', { timeout: 60000 });
    await page.goto(ids.quote_page, { waitUntil: 'load' });
    const empty = await page.locator('.empty-quote-list').count();
    const sent = mail().slice(before);
    const toShop = sent.find(m => JSON.stringify(m.to).includes(ids.admin_email));
    const body = toShop ? toShop.message : '';
    const count = parseInt(php('option.php', 'wpheka_rfq_quote_count') || '0', 10) || 0;
    return [empty === 1 && !!toShop && body.includes('ZZ RFQ') && body.includes('Zz Co') && body.includes('Please quote ZZ RFQ items') && count === countBefore + 1,
      `empty list ${empty}, mails ${JSON.stringify(sent.map(m => [m.to, m.subject]))}, has product ${body.includes('ZZ RFQ')}, has company ${body.includes('Zz Co')}, count ${countBefore}->${count}`];
  });
  await ctx.close();

  // Display settings, each from the baseline.
  const cases = [
    ['setting button_type=link: shows a link, not a button', ['button_type=link'], ids.in_url, s => s.link === 1],
    ['setting hide_price=no: price shown again', ['hide_price=no'], ids.in_url, s => s.price >= 1],
    ['setting hide_add_to_cart=no: Add to cart shown again', ['hide_add_to_cart=no'], ids.in_url, s => s.cart === 1],
    ['setting out_of_stock_option=hide_out_of_stock: no button on an out-of-stock product', ['out_of_stock_option=hide_out_of_stock'], ids.out_url, s => s.button === 0],
    ['setting out_of_stock_option=only_out_of_stock: no button on an in-stock product', ['out_of_stock_option=only_out_of_stock'], ids.in_url, s => s.button === 0],
    ['setting out_of_stock_option=only_out_of_stock: button on an out-of-stock product', ['out_of_stock_option=only_out_of_stock'], ids.out_url, s => s.button === 1],
    ['setting user_type=logged: guests get no button', ['user_type=logged'], ids.in_url, s => s.button === 0],
  ];
  for (const [label, overrides, url, ok] of cases) {
    const read = JSON.parse(php('settings.php', ...overrides));
    for (const theme of ids.themes) {
      await check(`[${theme}] ${label}`, async () => {
        const g = await guest(browser, theme);
        const s = await productState(g.page, url);
        await g.ctx.close();
        return [ok(s), `plugin read ${JSON.stringify(read)}, page ${JSON.stringify(s)}`];
      });
    }
  }
  php('settings.php', 'hide_price=yes'); // back to the baseline

  // Requests without a valid nonce change nothing.
  await check('security: add to quote and send request refuse a request without a valid nonce', async () => {
    const g = await guest(browser);
    await g.page.goto(ids.quote_page, { waitUntil: 'load' });
    const ajax = ids.admin_url + 'admin-ajax.php';
    const add = await g.page.request.post(ajax, { form: { action: 'wpheka_add_to_quote', product_id: String(ids.in), security: 'bogus' } });
    const before2 = mail().length;
    const send = await g.page.request.post(ajax, { form: { action: 'send_rfq_list', rfq_email: 'zz-rfq-guest@example.invalid', rfq_message: 'x', 'wpheka-send-quote-request-nonce': 'bogus' } });
    const sendBody = await send.text();
    await g.page.goto(ids.quote_page, { waitUntil: 'load' });
    const rows = await g.page.locator('tr.rfq_item').count();
    await g.ctx.close();
    return [add.status() === 403 && rows === 0 && mail().length === before2 && !sendBody.includes('"success":true'), `add ${add.status()}, rows ${rows}, send ${send.status()} ${sendBody.slice(0, 60)}`];
  });

  const real = errors.filter(e => !/Transition was skipped/.test(e));
  record(real.length === 0, 'no JavaScript errors on the shop pages', JSON.stringify(real).slice(0, 400));
  await browser.close();
})();
