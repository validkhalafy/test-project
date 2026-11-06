/* Behaviour test for assets/js/follow.js in Chromium with a mocked admin-ajax.
   Needs Playwright with Chromium:  node tests/js/follow.browser.test.js */
const { chromium } = require('playwright');
const fs = require('fs');
const root = require('path').resolve(__dirname, '..', '..') + '/';
const js = fs.readFileSync(root + 'assets/js/follow.js', 'utf8');
const css = fs.readFileSync(root + 'assets/css/follow.css', 'utf8');
const btn = (following, count, guest) => `
<div class="aet-follow aet-follow--md aet-follow--solid" data-aet-follow="artist:7" data-following="${following ? 1 : 0}" data-own="0">
 ${guest ? `<a href="https://example.test/wp-login.php" rel="nofollow" class="aet-follow-btn" data-aet-follow-btn data-label-follow="دنبال کردن" data-label-following="دنبال می‌کنید" data-label-unfollow="لغو دنبال کردن" aria-pressed="false"><span class="aet-follow-label">دنبال کردن</span></a>`
 : `<form method="post" class="aet-follow-form"><input type="hidden" name="follow_action" value="follow"><button type="submit" class="aet-follow-btn" data-aet-follow-btn data-label-follow="دنبال کردن" data-label-following="دنبال می‌کنید" data-label-unfollow="لغو دنبال کردن" aria-pressed="false"><span class="aet-follow-label">دنبال کردن</span></button></form>`}
 <span class="aet-follow-count"><strong data-aet-follow-count="${count}">${count}</strong> دنبال‌کننده</span>
 <span class="aet-follow-status" role="status"></span></div>`;
let pass = 0, fail = 0; const ok = (c, n) => { c ? pass++ : fail++; console.log((c ? '  ok    ' : '  FAIL  ') + n); };

async function page(browser, html, server) {
  const p = await browser.newPage();
  await p.exposeFunction('__server', server);
  await p.setContent(`<!doctype html><html lang="fa" dir="rtl"><head><style>${css}</style></head><body>${html}
  <script>
   window.ArtieyFollow={ajax:'/ajax',login:'/login',fa:true,i18n:{follow:'دنبال کردن',following:'دنبال می‌کنید',unfollow:'لغو دنبال کردن',error:'خطا',followed:'دنبال شد',unfollowed:'لغو شد',thousand:'هزار',million:'میلیون'}};
   window.__calls=[];
   window.fetch=async function(url,opt){ const body=Object.fromEntries(new URLSearchParams(opt.body)); body.subjects=new URLSearchParams(opt.body).getAll('subjects[]'); window.__calls.push(body); const r=await window.__server(body); return {status:r.status||200,json:async()=>r.json}; };
  </script><script>${js}</script></body></html>`);
  await p.waitForTimeout(80);
  return p;
}

(async () => {
  const browser = await chromium.launch();
  // Logged-in user, two widgets for the same subject (header + followers panel).
  let following = false, count = 4, nonceUses = [];
  let p = await page(browser, btn(false, 4, false) + btn(false, 4, false), async (b) => {
    if (b.action === 'artiey_follow_state') return { json: { success: true, data: { loggedIn: true, nonce: 'n1', states: { 'artist:7': { following, count, countText: String(count) } } } } };
    nonceUses.push(b.nonce);
    if (b.nonce === 'n1') return { status: 403, json: { success: false, data: { code: 'nonce', nonce: 'n2' } } };
    following = b.follow_action === 'follow'; count += following ? 1 : -1;
    return { json: { success: true, data: { following, count } } };
  });
  ok((await p.evaluate(() => window.__calls[0].subjects)).join() === 'artist:7', 'one batched state request with each subject once');
  await p.click('.aet-follow-btn >> nth=0');
  await p.waitForTimeout(120);
  const st = await p.evaluate(() => [...document.querySelectorAll('.aet-follow')].map(b => [b.dataset.following, b.querySelector('[data-aet-follow-count]').textContent, b.querySelector('.aet-follow-btn').getAttribute('aria-pressed')]));
  ok(st.every(s => s[0] === '1' && s[1] === '۵' && s[2] === 'true'), 'click follows; every widget of the same profile updates (state, count, aria-pressed)');
  ok(nonceUses.join() === 'n1,n2', 'an expired nonce (cached page) is refreshed and the request retried once');
  ok((await p.evaluate(() => document.querySelector('input[name=follow_action]').value)) === 'unfollow', 'no-JS fallback field follows the new state');
  ok(!(await p.evaluate(() => window.location.href)).includes('/ajax'), 'form submit is intercepted (no page navigation)');
  await p.mouse.move(1, 1); await p.hover('.aet-follow-btn >> nth=0');
  ok((await p.textContent('.aet-follow-btn >> nth=0')).includes('لغو دنبال کردن'), 'hovering a followed button offers "unfollow"');
  await p.click('.aet-follow-btn >> nth=0'); await p.waitForTimeout(120);
  ok((await p.evaluate(() => document.querySelector('.aet-follow').dataset.following)) === '0' && (await p.textContent('[data-aet-follow-count]')) === '۴', 'second click unfollows; count shown in Persian digits');
  await p.close();

  // Server error: optimistic change is rolled back.
  p = await page(browser, btn(false, 9, false), async (b) => b.action === 'artiey_follow_state'
    ? { json: { success: true, data: { loggedIn: true, nonce: 'n', states: {} } } }
    : { status: 429, json: { success: false, data: { code: 'rate' } } });
  await p.click('.aet-follow-btn'); await p.waitForTimeout(120);
  ok((await p.evaluate(() => document.querySelector('.aet-follow').dataset.following)) === '0' && (await p.textContent('[data-aet-follow-count]')) === '۹', 'failed request rolls the optimistic update back');
  await p.close();

  // Guest on a cached page: state says logged out → normal login link navigation is kept.
  p = await page(browser, btn(false, 1, true), async (b) => ({ json: { success: true, data: { loggedIn: false, nonce: '', states: { 'artist:7': { following: false, count: 1234, countText: '۱٫۲ هزار' } } } } }));
  ok((await p.textContent('[data-aet-follow-count]')) === '۱٫۲ هزار', 'cached count is refreshed from the server after load');
  const prevented = await p.evaluate(() => { const a = document.querySelector('a.aet-follow-btn'); let d = null; a.addEventListener('click', e => { d = e.defaultPrevented; e.preventDefault(); }); a.click(); return d; });
  ok(prevented === false, 'guests follow the login link (not hijacked by JS)');
  ok((await p.evaluate(() => window.__calls.filter(c => c.action === 'artiey_follow_toggle').length)) === 0, 'guests never send toggle requests');
  await p.close();
  await browser.close();
  console.log(`\n${pass} passed, ${fail} failed`); process.exit(fail ? 1 : 0);
})();
