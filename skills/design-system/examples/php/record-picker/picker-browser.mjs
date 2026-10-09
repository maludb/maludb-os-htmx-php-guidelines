// picker-browser.mjs — the headless-browser pass for the record picker (design-system references/record-picker.md), as run on the
// exemplar (ProcessCore's purchase order) and the first retrofit (the cidery). Adapt the five constants to the application under
// proof: BASE (a php -S of the scratch checkout), the sign-in, FORM (an add form with a picker), FIELD (the hidden input's id of
// a required picker whose source has at least 30 rows seeded so paging shows) and DEP (a dependent picker on the same form, or
// null). Run with Playwright: `ln -s /var/www/web/node_modules node_modules; S=$PWD node picker-browser.mjs` (chromium is cached).
// It prints one ok/FAIL line per check and writes six screenshots (desktop, phone, dark). 19 checks on the exemplar.
import { chromium } from 'playwright';
const S = process.env.S || '.', B = process.env.BASE || 'http://127.0.0.1:8389';   // BASE: the scratch php -S
const results = []; const ok = (n, c) => { results.push((c ? 'ok   ' : 'FAIL ') + n); console.log((c ? 'ok   ' : 'FAIL ') + n); };
const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 } });
const page = await ctx.newPage();
const errors = []; page.on('pageerror', e => errors.push(e.message)); page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
await page.goto(B + '/login'); await page.fill('input[name=email]', 'owner@processcore.test'); await page.fill('input[name=password]', 'owner-dev-password');
await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
await page.goto(B + '/purchase-orders/new'); await page.waitForSelector('#purchase-order-form-field-supplier-id-open');
await page.screenshot({ path: S + '/1-form-desktop.png', fullPage: false });
// open the supplier picker
await page.click('#purchase-order-form-field-supplier-id-open');
await page.waitForSelector('#record-picker.show'); await page.waitForSelector('#record-picker-list .record-picker-row');
ok('the modal opens with the first page', (await page.$$('#record-picker .record-picker-row')).length === 25);
await page.waitForFunction(() => document.activeElement.id === 'record-picker-search', null, { timeout: 3000 }).catch(() => {}); ok('the search box has focus', await page.evaluate(() => document.activeElement.id === 'record-picker-search'));
ok('the title is the source\'s', (await page.textContent('#record-picker-title')).trim() === 'Suppliers');
ok('the footer offers New supplier', (await page.textContent('#record-picker-create-btn')).includes('New supplier'));
await page.type('#record-picker-search', 'supplier 1'); await page.waitForTimeout(700);
const n = (await page.$$('#record-picker .record-picker-row')).length; ok('typing narrows the list (' + n + ' rows)', n === 10 || n === 11);
await page.screenshot({ path: S + '/2-picker-desktop.png' });
await page.keyboard.press('ArrowDown'); ok('ArrowDown moves to the first row', await page.evaluate(() => document.activeElement.classList.contains('record-picker-row')));
await page.keyboard.press('ArrowDown'); const label = await page.evaluate(() => document.activeElement.dataset.label);
await page.keyboard.press('Enter'); await page.waitForSelector('#record-picker', { state: 'hidden' });
ok('Enter picks the focused row (' + label + ')', (await page.textContent('#purchase-order-form-field-supplier-id-open')).trim() === label && (await page.inputValue('#purchase-order-form-field-supplier-id')) !== '');
await page.waitForFunction(() => document.activeElement.id === 'purchase-order-form-field-supplier-id-open', null, { timeout: 3000 }).catch(() => {}); ok('focus returns to the opener', await page.evaluate(() => document.activeElement.id === 'purchase-order-form-field-supplier-id-open'));
ok('the form is flagged dirty', await page.evaluate(() => document.querySelector('.page-header-form').classList.contains('is-dirty')));
// the item picker: the supplier is included; pick the first row; the line row refreshes (unit options appear)
await page.click('#purchase-order-form-line-n1-item-id-open'); await page.waitForSelector('#record-picker.show'); await page.waitForSelector('#record-picker .record-picker-row');
const items = await page.$$eval('#record-picker .record-picker-row', r => r.map(x => x.dataset.label));
ok('the item picker lists the purchasable items (' + items.length + ')', items.length >= 4 && items[0].startsWith('BAND-34'));
await page.click('#record-picker .record-picker-row:nth-child(2)'); await page.waitForSelector('#record-picker', { state: 'hidden' });
await page.waitForFunction(() => document.querySelectorAll('#purchase-order-form-line-n1-purchase-unit-code option').length > 0);
ok('picking an item refreshes the line row (units filled, label kept)', (await page.textContent('#purchase-order-form-line-n1-item-id-open')).includes(items[1].split(' — ')[0]));
// Enter in the search box picks the first match
await page.click('#purchase-order-form-field-supplier-id-open'); await page.waitForSelector('#record-picker.show'); await page.waitForSelector('#record-picker .record-picker-row');
await page.type('#record-picker-search', 'acme'); await page.waitForTimeout(600); await page.keyboard.press('Enter'); await page.waitForSelector('#record-picker', { state: 'hidden' });
ok('Enter in the search box picks the first match', (await page.textContent('#purchase-order-form-field-supplier-id-open')).trim() === 'Acme Packaging');
// Show more
await page.click('#purchase-order-form-field-supplier-id-open'); await page.waitForSelector('#record-picker .record-picker-more');
await page.click('#record-picker-more'); await page.waitForFunction(() => document.querySelectorAll('#record-picker .record-picker-row').length > 25);
ok('Show more appends page 2 in place', (await page.$$('#record-picker .record-picker-row')).length === 32 && !(await page.$('#record-picker-more')));
ok('the chosen row is marked active', await page.evaluate(() => document.querySelector('#record-picker .record-picker-row.active')?.dataset.label === 'Acme Packaging'));
await page.keyboard.press('Escape'); await page.waitForSelector('#record-picker', { state: 'hidden' });
// the picker's New supplier opens the inline panel
await page.click('#purchase-order-form-field-supplier-id-open'); await page.waitForSelector('#record-picker.show'); await page.click('#record-picker-create-btn'); await page.waitForSelector('#record-picker', { state: 'hidden' });
ok('New supplier in the picker opens the inline panel and focuses its name', await page.evaluate(() => !document.getElementById('purchase-order-form-new-supplier').classList.contains('d-none') && document.activeElement.id === 'purchase-order-form-new-supplier-name'));
// phone, light
const phone = await browser.newContext({ viewport: { width: 375, height: 740 }, storageState: await ctx.storageState() });
const p2 = await phone.newPage(); p2.on('pageerror', e => errors.push(e.message));
await p2.goto(B + '/purchase-orders/new'); await p2.waitForSelector('#purchase-order-form-field-supplier-id-open');
ok('no horizontal scroll at 375px', await p2.evaluate(() => document.documentElement.scrollWidth <= 375));
await p2.screenshot({ path: S + '/3-form-phone.png' });
await p2.click('#purchase-order-form-field-supplier-id-open'); await p2.waitForSelector('#record-picker.show'); await p2.waitForSelector('#record-picker .record-picker-row');
await p2.type('#record-picker-search', 'pick'); await p2.waitForTimeout(600);
const box = await p2.evaluate(() => { const r = document.querySelector('#record-picker .modal-content').getBoundingClientRect(); return [r.width, r.height]; });
ok('the modal fills the phone screen (' + box.join('x') + ')', box[0] >= 374 && box[1] >= 700);
const rowH = await p2.evaluate(() => document.querySelector('#record-picker .record-picker-row').getBoundingClientRect().height);
ok('rows are touch targets (' + rowH + 'px)', rowH >= 44);
await p2.screenshot({ path: S + '/4-picker-phone.png' });
// dark
await p2.evaluate(() => document.documentElement.classList.add('app-skin-dark')); await p2.waitForTimeout(200);
await p2.screenshot({ path: S + '/5-picker-phone-dark.png' });
await page.evaluate(() => document.documentElement.classList.add('app-skin-dark'));
await page.click('#purchase-order-form-field-supplier-id-open'); await page.waitForSelector('#record-picker.show'); await page.waitForSelector('#record-picker .record-picker-row'); await page.waitForTimeout(300);
await page.screenshot({ path: S + '/6-picker-desktop-dark.png' });
ok('no page errors', errors.length === 0);
if (errors.length) console.log('errors: ' + errors.join(' | '));
await browser.close();
