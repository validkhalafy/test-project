/* Behaviour test for assets/js/smart-scroller.js in a DOM. Needs jsdom:  npm i --no-save jsdom   then   node tests/js/smart-scroller.dom.test.js */
const { JSDOM } = require('jsdom'); const fs = require('fs'); const path = require('path'); const { execFileSync } = require('child_process');
const root = path.resolve(__dirname, '..', '..'); const js = fs.readFileSync(path.join(root, 'assets', 'js', 'smart-scroller.js'), 'utf8');
const gen = (opts) => execFileSync('php', [path.join(__dirname, 'gen-smart-html.php'), JSON.stringify(opts)], { encoding: 'utf8' });
let pass = 0, fail = 0; const ok = (c, n) => { c ? pass++ : fail++; console.log((c ? '  ok    ' : '  FAIL  ') + n); };
const tick = (ms = 40) => new Promise(r => setTimeout(r, ms));

async function boot(opts, env = {}) {
  const html = gen(opts);
  const dom = new JSDOM('<!doctype html><html dir="' + (env.rtl === false ? 'ltr' : 'rtl') + '"><body>' + html + '</body></html>', { runScripts: 'outside-only', pretendToBeVisual: true, url: 'https://example.test/' });
  const w = dom.window;
  const mq = (q) => ({ matches: q.includes('reduced-motion') ? !!env.reduce : (q.includes('max-width') ? !!env.small : false), addEventListener() {}, removeEventListener() {} });
  w.matchMedia = mq;
  w.CSS = { supports: () => env.no3d ? false : true };
  // jsdom has no layout: give slides a size and place them for the flat-mode maths
  Object.defineProperty(w.HTMLElement.prototype, 'offsetWidth', { configurable: true, get() { return this.classList && this.classList.contains('aetss-slide') ? 300 : 0; } });
  Object.defineProperty(w.HTMLElement.prototype, 'offsetHeight', { configurable: true, get() { return this.classList && this.classList.contains('aetss-slide') ? 380 : 0; } });
  Object.defineProperty(w.HTMLElement.prototype, 'clientWidth', { configurable: true, get() { return this.classList && this.classList.contains('aetss-stage') ? 900 : 0; } });
  if (env.timers) { const si = w.setInterval; w.setInterval = (fn, ms) => { env.timers.push(fn); return si.call(w, () => {}, 1e9); }; }
  w.eval(js); await tick();
  return w;
}
const el = (w, s) => w.document.querySelector(s);
const slides = (w) => [...w.document.querySelectorAll('.aetss-slide')];
const rot = (s) => parseFloat((s.style.transform.match(/rotateY\((-?[\d.]+)deg\)/) || [0, NaN])[1]);
const tx = (s) => parseFloat((s.style.transform.match(/translate3d\((-?[\d.]+)px/) || [0, NaN])[1]);
const sc = (s) => parseFloat((s.style.transform.match(/scale\(([\d.]+)\)/) || [0, NaN])[1]);
const key = (w, k, target) => (target || el(w, '.aetss-stage')).dispatchEvent(new w.KeyboardEvent('keydown', { key: k, bubbles: true, cancelable: true }));

(async () => {
  /* ---------- coverflow math (LTR) ---------- */
  let w = await boot({ n: 7, mode: 'coverflow', toggle: true, dots: true, arrows: true, angle: 40, depth: 250, spread: 60 }, { rtl: false });
  let s = slides(w); const root1 = el(w, '.aetss');
  ok(root1.classList.contains('aetss-ready') && root1.classList.contains('aetss--coverflow'), 'script upgrades the server markup to 3D coverflow');
  ok(rot(s[0]) === 0 && tx(s[0]) === 0 && sc(s[0]) === 1, 'the active slide is centred, flat and full size');
  ok(rot(s[1]) === -40 && tx(s[1]) === 180, 'LTR: next slide sits on the right (x=+0.6*width) and turns toward the centre (-40deg)');
  ok(tx(s[2]) > tx(s[1]) && tx(s[2]) < 2 * tx(s[1]), 'slides further out are compressed (tighter than linear spacing)');
  ok(s[3].style.zIndex < s[1].style.zIndex && parseFloat(s[3].style.opacity) < parseFloat(s[1].style.opacity), 'depth ordering: further slides are behind and fainter');
  ok(s[0].getAttribute('aria-hidden') === 'false' && s[1].getAttribute('aria-hidden') === 'true', 'only the centred slide is exposed to assistive tech');
  ok(s[1].querySelector('.aetss-title a').getAttribute('tabindex') === '-1' && !s[0].querySelector('.aetss-title a').hasAttribute('tabindex'), 'links on side slides are removed from the tab order');
  ok(el(w, '.aetss-track').style.height === '380px', 'the track height follows the tallest slide');
  ok(w.document.querySelectorAll('.aetss-dot').length === 7, 'dots are built (7)');
  /* loop wrap: previous slide of the first one is the last, shown on the left */
  ok(tx(s[6]) === -180 && rot(s[6]) === 40, 'with loop on, the last slide appears to the left of the first');
  el(w, '.aetss-next').click(); s = slides(w);
  ok(rot(s[1]) === 0 && tx(s[0]) === -180 && el(w, '.aetss').__aetss.state().active === 1, 'next moves the carousel by one');
  ok(/اثر 2/.test(el(w, '.aetss-live').textContent), 'the live region announces the new slide');
  ok(el(w, '.aetss-dot[aria-selected="true"]') === w.document.querySelectorAll('.aetss-dot')[1], 'the matching dot is selected');
  key(w, 'ArrowRight'); ok(root1.__aetss.state().active === 2, 'LTR: ArrowRight goes forward');
  key(w, 'ArrowLeft'); key(w, 'ArrowLeft'); ok(root1.__aetss.state().active === 0, 'LTR: ArrowLeft goes back');
  key(w, 'End'); ok(root1.__aetss.state().active === 6, 'End jumps to the last slide'); key(w, 'Home'); ok(root1.__aetss.state().active === 0, 'Home jumps to the first');
  root1.__aetss.goTo(6); root1.__aetss.step(1); ok(root1.__aetss.state().active === 0, 'loop: next after the last wraps to the first');

  /* ---------- RTL mirrors ---------- */
  w = await boot({ n: 7, mode: 'coverflow', angle: 40, spread: 60 }, { rtl: true }); s = slides(w);
  ok(tx(s[1]) === -180 && rot(s[1]) === 40, 'RTL: the next slide sits on the LEFT and turns the opposite way');
  key(w, 'ArrowLeft'); ok(el(w, '.aetss').__aetss.state().active === 1, 'RTL: ArrowLeft goes forward');
  key(w, 'ArrowRight'); ok(el(w, '.aetss').__aetss.state().active === 0, 'RTL: ArrowRight goes back');

  /* ---------- 2D / 3D toggle ---------- */
  w = await boot({ n: 6, mode: 'coverflow', toggle: true }, { rtl: false }); const r = el(w, '.aetss'); const btn = el(w, '.aetss-mode');
  ok(btn.getAttribute('aria-pressed') === 'true', 'the switch starts pressed in 3D mode');
  btn.click(); s = slides(w);
  ok(r.classList.contains('aetss--flat') && s.every(x => x.style.transform === '') && btn.getAttribute('aria-pressed') === 'false', 'switching to 2D clears every 3D transform');
  btn.click(); s = slides(w);
  ok(r.classList.contains('aetss--coverflow') && rot(s[1]) !== 0 && btn.getAttribute('aria-pressed') === 'true', 'switching back restores 3D');
  r.__aetss.setMode('cylinder'); s = slides(w);
  ok(r.classList.contains('aetss--cylinder') && /translateZ\(/.test(s[0].style.transform) && /translateZ\(-/.test(el(w, '.aetss-track').style.transform), 'cylinder: slides sit on a ring and the track is pulled back by the radius');
  ok(rot(s[0]) === 0 && Math.abs(rot(s[1]) - 60) < 0.01, 'cylinder: six slides are 60 degrees apart');
  ok(s[3].style.visibility === 'hidden' || parseFloat(s[3].style.opacity) < 0.3, 'cylinder: slides on the back of the ring are hidden/faded');

  /* ---------- clicks on side slides ---------- */
  w = await boot({ n: 6, mode: 'coverflow' }, { rtl: false }); s = slides(w); const rr = el(w, '.aetss');
  let ev = new w.MouseEvent('click', { bubbles: true, cancelable: true }); s[2].querySelector('.aetss-title a').dispatchEvent(ev);
  ok(ev.defaultPrevented && rr.__aetss.state().active === 2, 'clicking a side slide brings it to the centre instead of navigating away');
  ev = new w.MouseEvent('click', { bubbles: true, cancelable: true }); slides(w)[2].querySelector('.aetss-title a').dispatchEvent(ev);
  ok(!ev.defaultPrevented, 'clicking the centred slide follows its link');

  /* ---------- drag ---------- */
  w = await boot({ n: 8, mode: 'coverflow', drag: true, spread: 55 }, { rtl: false }); const st = el(w, '.aetss-stage'); const dr = el(w, '.aetss');
  const mouse = (t, x) => new w.MouseEvent(t, { clientX: x, bubbles: true, cancelable: true });
  st.dispatchEvent(mouse('pointerdown', 500)); w.document.dispatchEvent(mouse('pointermove', 300)); w.document.dispatchEvent(mouse('pointerup', 300));
  ok(dr.__aetss.state().active > 0, 'dragging left moves forward in LTR (active=' + dr.__aetss.state().active + ')');
  ev = new w.MouseEvent('click', { bubbles: true, cancelable: true }); slides(w)[dr.__aetss.state().active].querySelector('.aetss-title a').dispatchEvent(ev);
  ok(ev.defaultPrevented, 'the click that ends a drag does not open a link');
  ok(!dr.classList.contains('aetss-dragging'), 'the dragging state is cleared afterwards');
  w = await boot({ n: 8, mode: 'coverflow', drag: false }, { rtl: false }); const st2 = el(w, '.aetss-stage');
  st2.dispatchEvent(new w.MouseEvent('pointerdown', { clientX: 500, bubbles: true })); w.document.dispatchEvent(new w.MouseEvent('pointermove', { clientX: 100 })); w.document.dispatchEvent(new w.MouseEvent('pointerup', { clientX: 100 }));
  ok(el(w, '.aetss').__aetss.state().active === 0, 'drag can be switched off');

  /* ---------- wheel ---------- */
  w = await boot({ n: 6, mode: 'coverflow', wheel: true }, { rtl: false }); const wst = el(w, '.aetss-stage'); const wr = el(w, '.aetss');
  let we = new w.WheelEvent('wheel', { deltaX: 120, deltaY: 5, bubbles: true, cancelable: true }); wst.dispatchEvent(we);
  ok(we.defaultPrevented && wr.__aetss.state().active === 1, 'a horizontal wheel/trackpad gesture moves the carousel');
  we = new w.WheelEvent('wheel', { deltaX: 120, deltaY: 5, bubbles: true, cancelable: true }); wst.dispatchEvent(we); ok(wr.__aetss.state().active === 1, 'a second gesture inside the throttle window is ignored');
  we = new w.WheelEvent('wheel', { deltaX: 2, deltaY: 120, bubbles: true, cancelable: true }); wst.dispatchEvent(we); ok(!we.defaultPrevented, 'vertical scrolling is never hijacked');
  w = await boot({ n: 6, mode: 'coverflow', wheel: false }, { rtl: false }); we = new w.WheelEvent('wheel', { deltaX: 120, deltaY: 0, bubbles: true, cancelable: true }); el(w, '.aetss-stage').dispatchEvent(we); ok(!we.defaultPrevented, 'wheel handling is off by default');

  /* ---------- environment fallbacks ---------- */
  w = await boot({ n: 6, mode: 'coverflow', toggle: true }, { reduce: true }); s = slides(w);
  ok(el(w, '.aetss').classList.contains('aetss--flat') && el(w, '.aetss').classList.contains('aetss--forced-flat') && s.every(x => x.style.transform === ''), 'prefers-reduced-motion forces flat mode and hides the 3D switch');
  w = await boot({ n: 6, mode: 'coverflow' }, { no3d: true }); ok(el(w, '.aetss').classList.contains('aetss--flat'), 'a browser without 3D transforms gets the flat scroller');
  w = await boot({ n: 6, mode: 'coverflow', mobile3d: false }, { small: true }); ok(el(w, '.aetss').classList.contains('aetss--flat') && el(w, '.aetss').classList.contains('aetss--forced-flat'), 'phones stay flat unless 3D on mobile is enabled');
  w = await boot({ n: 6, mode: 'coverflow', mobile3d: true }, { small: true }); ok(el(w, '.aetss').classList.contains('aetss--coverflow'), '...and get 3D when the author enables it');
  w = await boot({ n: 2, mode: 'coverflow' }, {}); ok(el(w, '.aetss').classList.contains('aetss--flat'), 'two slides are never shown in 3D');

  /* ---------- autoplay ---------- */
  let timers = []; w = await boot({ n: 6, mode: 'coverflow', autoplay: true, interval: 3000, loop: true }, { timers }); const ap = el(w, '.aetss');
  ok(timers.length === 1, 'autoplay starts one timer'); timers[0](); ok(ap.__aetss.state().active === 1, 'each tick advances one slide');
  ap.dispatchEvent(new w.MouseEvent('mouseenter')); timers[0](); ok(ap.__aetss.state().active === 1, 'hovering pauses autoplay');
  ap.dispatchEvent(new w.MouseEvent('mouseleave')); timers[0](); ok(ap.__aetss.state().active === 2, 'leaving resumes it');
  timers = []; w = await boot({ n: 6, mode: 'coverflow', autoplay: true }, { timers, reduce: true }); ok(timers.length === 0, 'reduced motion never starts autoplay');

  /* ---------- non-looping ends ---------- */
  w = await boot({ n: 4, mode: 'coverflow', loop: false, arrows: true }, {}); const nl = el(w, '.aetss');
  ok(el(w, '.aetss-prev').disabled === true && el(w, '.aetss-next').disabled === false, 'loop off: previous is disabled at the first slide');
  nl.__aetss.goTo(3); nl.__aetss.step(1); ok(nl.__aetss.state().active === 3 && el(w, '.aetss-next').disabled === true, 'loop off: cannot go past the last slide');

  console.log('\n' + pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
})();
