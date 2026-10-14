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
// WP_CLI overrides how WP-CLI is called (words split on spaces) on a machine
// where `wp` is a script node cannot start: WP_CLI="php /path/to/wp-cli.phar".
const WP_CLI = (process.env.WP_CLI || 'wp').split(' ');
const php = (file, ...args) => execFileSync(WP_CLI[0], [...WP_CLI.slice(1), '--path=' + process.env.WP_PATH, 'eval-file', path.join(__dirname, file), ...args], { encoding: 'utf8', env: process.env, stdio: ['ignore', 'pipe', 'ignore'] }).trim().split('\n').filter(l => !l.startsWith('Deprecated')).pop();
const record = (ok, name, detail) => { const line = `${ok ? 'PASS' : 'FAIL'}|${name}${ok ? '' : ' -- ' + detail}`; fs.appendFileSync(path.join(STATE, 'results'), line + '\n'); console.log('  ' + line); };
// A crash anywhere in this script is a FAIL, never a silent stop: run.sh also
// fails a non-zero exit and a missing "ran to the end" case.
const crashed = kind => e => {
  const lines = String((e && e.stack) || e).split('\n');
  try { record(false, `frontend.js: ${kind}`, [lines[0], (lines.find(l => /^\s+at /.test(l)) || '').trim()].filter(Boolean).join(' ')); } catch (ignored) {}
  process.exit(1);
};
process.on('unhandledRejection', crashed('no unhandled promise rejection'));
process.on('uncaughtException', crashed('no uncaught exception'));
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
    // Every button in the page, shown or not: a second, hidden copy is still a bug.
    inPage: await page.locator(quoteButton).count(),
  };
}

(async () => {
  // PW_CHANNEL=chrome runs the installed Google Chrome instead of Playwright's
  // bundled Chromium (for a machine without the matching browser download).
  const browser = await chromium.launch(process.env.PW_CHANNEL ? { channel: process.env.PW_CHANNEL } : {});

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
  // Exactly one Add to quote button on a product page, in either position, for
  // simple and variable products, in and out of stock. Out of stock, WooCommerce
  // prints no add-to-cart form, and the plugin's fallback for that case used to
  // add a second button when the position was "after".
  const products = [
    ['simple in stock', ids.in_url, 'in'],
    ['simple out of stock', ids.out_url, 'out'],
    ['variable in stock', ids.var_in_url, 'in'],
    ['variable out of stock', ids.var_out_url, 'out'],
  ];
  for (const position of ['before', 'after']) {
    for (const option of ['show_all', 'only_out_of_stock']) {
      const read = JSON.parse(php('settings.php', `button_position=${position}`, `out_of_stock_option=${option}`));
      for (const [label, url, stock] of products) {
        const want = option === 'only_out_of_stock' && stock === 'in' ? 0 : 1;
        for (const theme of ids.themes) {
          await check(`[${theme}] button_position=${position}, out_of_stock_option=${option}, ${label}: ${want} Add to quote button`, async () => {
            const g = await guest(browser, theme);
            const s = await productState(g.page, url);
            await g.ctx.close();
            return [s.inPage === want && s.button === want, `plugin read ${JSON.stringify(read)}, page ${JSON.stringify(s)}`];
          });
        }
      }
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

  // The add-to-quote handlers check the product, not only the nonce: a direct
  // request can name any id. Each refusal gets the plugin's error response and
  // leaves the quote list as it was; real products still add.
  {
    const g = await guest(browser);
    const ajax = ids.admin_url + 'admin-ajax.php';
    await g.page.goto(ids.in_url, { waitUntil: 'load' });
    const singleNonce = await g.page.evaluate(() => window.wpheka_rfq_frontend_params && window.wpheka_rfq_frontend_params.add_to_quote_nonce);
    await g.page.goto(ids.shop_url, { waitUntil: 'load' });
    const shopNonce = await g.page.evaluate(() => window.wpheka_rfq_shop_params && window.wpheka_rfq_shop_params.add_to_quote_nonce);
    const add = async (action, fields) => {
      const res = await g.page.request.post(ajax, { form: { action, security: action === 'wpheka_add_to_quote' ? singleNonce : shopNonce, ...fields } });
      const text = await res.text();
      try { return JSON.parse(text).result; } catch (e) { return `(${res.status()}) ${text.slice(0, 60)}`; }
    };
    const rows = async () => { await g.page.goto(ids.quote_page, { waitUntil: 'load' }); return g.page.locator('tr.rfq_item').count(); };
    await check('add to quote: the test has both nonces', async () => [!!singleNonce && !!shopNonce, `single ${singleNonce}, shop ${shopNonce}`]);

    const refusals = [
      ['a product that does not exist', { product_id: '999999999' }],
      ['a draft product', { product_id: String(ids.draft) }],
      ['a private product', { product_id: String(ids.private) }],
      ['a variation id posted as the product', { product_id: String(ids.var_in_variation) }],
    ];
    for (const action of ['wpheka_add_to_quote', 'wpheka_add_to_quote_shop']) {
      for (const [label, fields] of refusals) {
        await check(`add to quote (${action}): refuses ${label}`, async () => {
          const result = await add(action, fields);
          return [result === 'false', `result ${result}`];
        });
      }
    }
    await check('add to quote: refuses a variation of another product', async () => {
      const result = await add('wpheka_add_to_quote', { product_id: String(ids.var_in), variation_id: String(ids.var_out_variation), attribute_size: 'Small' });
      return [result === 'false', `result ${result}`];
    });
    php('settings.php', 'hide_price=yes', 'out_of_stock_option=hide_out_of_stock');
    for (const action of ['wpheka_add_to_quote', 'wpheka_add_to_quote_shop']) {
      await check(`add to quote (${action}): refuses a product the button rules hide`, async () => {
        const result = await add(action, { product_id: String(ids.out) });
        return [result === 'false', `result ${result}`];
      });
    }
    php('settings.php', 'hide_price=yes'); // back to the baseline
    await check('add to quote: nothing refused reached the quote list', async () => {
      const n = await rows();
      return [n === 0, `${n} row(s)`];
    });
    await check('add to quote: a published product still adds (product page)', async () => {
      const result = await add('wpheka_add_to_quote', { product_id: String(ids.in) });
      return [result === 'true', `result ${result}`];
    });
    await check('add to quote: a variation of the product still adds (product page)', async () => {
      const result = await add('wpheka_add_to_quote', { product_id: String(ids.var_in), variation_id: String(ids.var_in_variation), attribute_size: 'Small' });
      return [result === 'true', `result ${result}`];
    });
    await check('add to quote: a published product still adds (shop page)', async () => {
      const result = await add('wpheka_add_to_quote_shop', { product_id: String(ids.out) });
      return [result === 'true', `result ${result}`];
    });
    await check('add to quote: the quote list holds exactly the three real products', async () => {
      const n = await rows();
      return [n === 3, `${n} row(s)`];
    });
    await g.ctx.close();
  }

  const real = errors.filter(e => !/Transition was skipped/.test(e));
  record(real.length === 0, 'no JavaScript errors on the shop pages', JSON.stringify(real).slice(0, 400));
  await browser.close();
  record(true, 'frontend.js: ran to the end');
})();
