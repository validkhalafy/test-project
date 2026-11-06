/* Art COA — dynamic front end: catalogs, live search, lightbox, live verify, drag&drop upload. Vanilla JS, no dependencies. */
(function () {
  'use strict';
  var C = window.ArtCoaFront || { rest: '/wp-json/art-coa/v1/', nonce: '', maxMb: 8, i18n: {} };
  var T = C.i18n || {};

  function $(sel, ctx) { return (ctx || document).querySelector(sel); }
  function $$(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }
  function debounce(fn, ms) {
    var t;
    return function () {
      var a = arguments, self = this;
      clearTimeout(t);
      t = setTimeout(function () { fn.apply(self, a); }, ms);
    };
  }
  function parseCfg(el) {
    try { return JSON.parse(el.getAttribute('data-config') || '{}'); } catch (e) { return {}; }
  }
  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) { n.className = cls; }
    if (text != null) { n.textContent = text; }
    return n;
  }

  /* ===================================================================
   * Catalog: filters, live search, sort, "load more", shareable URL state
   * ================================================================= */
  function initCatalog(root) {
    var cfg = parseCfg(root);
    var form = $('.art-coa-toolbar', root);
    var cards = $('[data-cards]', root);
    var meta = $('.art-coa-result-meta', root);
    var pager = $('.art-coa-pager', root);
    var prefix = cfg.prefix || 'ac_';
    var state = { page: cfg.page || 1, pages: cfg.pages || 1, busy: false, ctrl: null };

    var more = el('button', 'art-coa-btn art-coa-loadmore', T.loadMore || 'Load more');
    more.type = 'button';
    more.hidden = true;
    root.appendChild(more);
    if (pager) { pager.hidden = true; }

    function params(page) {
      var p = new URLSearchParams();
      var k;
      for (k in (cfg.preset || {})) { if (cfg.preset[k] !== '') { p.set(k, cfg.preset[k]); } }
      if (form) {
        $$('input[name],select[name]', form).forEach(function (f) {
          if (f.name.indexOf(prefix) !== 0) { return; }
          if (f.type === 'checkbox' && !f.checked) { return; }
          if (f.value !== '') { p.set(f.name.slice(prefix.length), f.value); }
        });
      }
      p.set('per_page', cfg.per_page || 12);
      p.set('page', page);
      return p;
    }

    function syncUrl() {
      if (!form || !window.history.replaceState) { return; }
      var u = new URL(window.location.href);
      Array.from(u.searchParams.keys()).forEach(function (k) { if (k.indexOf(prefix) === 0) { u.searchParams.delete(k); } });
      $$('input[name],select[name]', form).forEach(function (f) {
        if (f.name.indexOf(prefix) !== 0) { return; }
        if (f.type === 'checkbox' && !f.checked) { return; }
        if (f.value !== '') { u.searchParams.set(f.name, f.value); }
      });
      window.history.replaceState(null, '', u.toString());
    }

    function load(page, append) {
      if (state.ctrl) { state.ctrl.abort(); }
      state.ctrl = window.AbortController ? new AbortController() : null;
      state.busy = true;
      root.classList.add('is-loading');
      if (cards) { cards.setAttribute('aria-busy', 'true'); }
      more.disabled = true;
      if (append) { more.textContent = T.loading || '…'; }

      var url = cfg.endpoint + (cfg.endpoint.indexOf('?') > -1 ? '&' : '?') + params(page).toString();
      return fetch(url, { credentials: 'same-origin', signal: state.ctrl ? state.ctrl.signal : undefined })
        .then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.json(); })
        .then(function (d) {
          var before = append ? cards.children.length : 0;
          if (append) { cards.insertAdjacentHTML('beforeend', d.html); } else { cards.innerHTML = d.html; }
          if (append && cards.children[before]) {
            // keep keyboard / screen-reader position: move focus to the first newly added card
            var first = cards.children[before];
            var focusable = first.matches('a,button') ? first : first.querySelector('a,button');
            if (focusable) { focusable.focus({ preventScroll: false }); }
          }
          if (meta) { meta.textContent = d.label; }
          state.page = d.page;
          state.pages = d.pages;
          more.hidden = d.page >= d.pages;
          if (!append) { syncUrl(); }
        })
        .catch(function (e) {
          if (e && e.name === 'AbortError') { return; }
          if (meta) { meta.textContent = T.error || 'Error'; }
        })
        .then(function () {
          state.busy = false;
          root.classList.remove('is-loading');
          if (cards) { cards.removeAttribute('aria-busy'); }
          more.disabled = false;
          more.textContent = T.loadMore || 'Load more';
        });
    }

    more.hidden = state.page >= state.pages;
    more.addEventListener('click', function () { if (!state.busy) { load(state.page + 1, true); } });

    if (form) {
      var run = debounce(function () { load(1, false); }, 280);
      form.addEventListener('submit', function (e) { e.preventDefault(); load(1, false); });
      form.addEventListener('input', function (e) {
        if (e.target.type === 'search' || e.target.type === 'number') { run(); }
      });
      form.addEventListener('change', function (e) {
        if (e.target.tagName === 'SELECT' || e.target.type === 'checkbox') { load(1, false); }
      });
      var reset = $('.js-reset', form);
      if (reset) {
        reset.addEventListener('click', function (e) {
          e.preventDefault();
          form.reset();
          $$('input[type=search],input[type=number]', form).forEach(function (i) { i.value = ''; });
          $$('select', form).forEach(function (s) { s.selectedIndex = 0; });
          $$('input[type=checkbox]', form).forEach(function (c) { c.checked = false; });
          load(1, false);
        });
      }
    }
  }

  /* ===================================================================
   * Live global search box
   * ================================================================= */
  function initLiveSearch(root) {
    var cfg = parseCfg(root);
    var input = $('input', root);
    var panel = $('.art-coa-livesearch-panel', root);
    var ctrl = null;
    var uid = 'ls' + Math.floor(Math.random() * 1e6);
    var active = -1;
    panel.id = uid + '-list';
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-controls', panel.id);
    input.setAttribute('aria-expanded', 'false');
    var live = el('p', 'aap-sr-only');
    live.setAttribute('role', 'status');
    live.setAttribute('aria-live', 'polite');
    root.appendChild(live);

    function setOpen(open) {
      panel.hidden = !open;
      input.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (!open) { active = -1; input.removeAttribute('aria-activedescendant'); }
    }
    function items() { return $$('a.art-coa-ls-item', panel); }
    function setActive(n) {
      var list = items();
      if (!list.length) { return; }
      active = (n + list.length) % list.length;
      list.forEach(function (a, k) { a.setAttribute('aria-selected', k === active ? 'true' : 'false'); a.classList.toggle('is-active', k === active); });
      input.setAttribute('aria-activedescendant', list[active].id);
      list[active].scrollIntoView({ block: 'nearest' });
    }

    function render(list) {
      panel.textContent = '';
      active = -1;
      live.textContent = list.length ? (T.resultsCount ? T.resultsCount.replace('{n}', list.length) : String(list.length)) : (T.noResults || '');
      if (!list.length) {
        panel.appendChild(el('p', 'art-coa-empty', T.noResults || ''));
      }
      list.forEach(function (r, k) {
        var a = el('a', 'art-coa-ls-item');
        a.href = r.url;
        a.id = uid + '-o' + k;
        a.setAttribute('role', 'option');
        a.setAttribute('aria-selected', 'false');
        if (r.thumb) {
          var im = el('img'); im.src = r.thumb; im.alt = ''; im.loading = 'lazy'; a.appendChild(im);
        } else {
          a.appendChild(el('span', 'art-coa-ls-ph', '•'));
        }
        var box = el('span', 'art-coa-ls-text');
        box.appendChild(el('strong', '', r.title));
        if (r.sub) { box.appendChild(el('small', '', r.sub)); }
        a.appendChild(box);
        a.appendChild(el('em', 'art-coa-ls-type', r.label));
        panel.appendChild(a);
      });
      setOpen(true);
    }

    var run = debounce(function () {
      var q = input.value.trim();
      if (q.length < 2) { setOpen(false); return; }
      if (ctrl) { ctrl.abort(); }
      ctrl = window.AbortController ? new AbortController() : null;
      fetch(cfg.endpoint + (cfg.endpoint.indexOf('?') > -1 ? '&' : '?') + 'q=' + encodeURIComponent(q), { signal: ctrl ? ctrl.signal : undefined })
        .then(function (r) { return r.json(); })
        .then(function (d) { render(d.results || []); })
        .catch(function () {});
    }, 250);

    input.addEventListener('input', run);
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { setOpen(false); }
      else if (e.key === 'ArrowDown') { e.preventDefault(); if (panel.hidden && items().length) { setOpen(true); } setActive(active + 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(active - 1); }
      else if (e.key === 'Enter' && active > -1) { e.preventDefault(); items()[active].click(); }
    });
    document.addEventListener('click', function (e) { if (!root.contains(e.target)) { setOpen(false); } });
  }

  /* ===================================================================
   * Lightbox (groups via data-art-lightbox="name")
   * ================================================================= */
  var lb = null;
  function openLightbox(group, index) {
    var items = $$('a[data-art-lightbox="' + group + '"]').map(function (a) {
      var im = $('img', a);
      return { src: a.getAttribute('href'), alt: im ? im.getAttribute('alt') || '' : '' };
    });
    if (!items.length) { return; }
    var i = index;
    var last = document.activeElement;

    lb = el('div', 'art-coa-lb');
    lb.setAttribute('role', 'dialog');
    lb.setAttribute('aria-modal', 'true');
    lb.setAttribute('aria-label', T.gallery || 'Image viewer');
    var img = el('img', 'art-coa-lb-img');
    var cap = el('p', 'art-coa-lb-cap');
    var close = el('button', 'art-coa-lb-btn art-coa-lb-close', '×');
    close.type = 'button'; close.setAttribute('aria-label', T.close || 'Close');
    var prev = el('button', 'art-coa-lb-btn art-coa-lb-prev', '‹'); prev.type = 'button'; prev.setAttribute('aria-label', T.prev || 'Previous');
    var next = el('button', 'art-coa-lb-btn art-coa-lb-next', '›'); next.type = 'button'; next.setAttribute('aria-label', T.next || 'Next');
    lb.appendChild(img); lb.appendChild(cap); lb.appendChild(close);
    if (items.length > 1) { lb.appendChild(prev); lb.appendChild(next); }

    function show(n) {
      i = (n + items.length) % items.length;
      img.src = items[i].src;
      img.alt = items[i].alt;
      cap.textContent = items[i].alt;
    }
    function shut() {
      document.removeEventListener('keydown', key);
      document.body.classList.remove('art-coa-lb-open');
      if (lb && lb.parentNode) { lb.parentNode.removeChild(lb); }
      lb = null;
      if (last && last.focus) { last.focus(); }
    }
    function key(e) {
      if (e.key === 'Tab') {
        var f = $$('button', lb);
        if (!f.length) { return; }
        var firstB = f[0], lastB = f[f.length - 1];
        if (e.shiftKey && document.activeElement === firstB) { e.preventDefault(); lastB.focus(); }
        else if (!e.shiftKey && document.activeElement === lastB) { e.preventDefault(); firstB.focus(); }
      } else if (e.key === 'Escape') { shut(); }
      else if (e.key === 'ArrowLeft') { show(document.dir === 'rtl' || document.documentElement.dir === 'rtl' ? i + 1 : i - 1); }
      else if (e.key === 'ArrowRight') { show(document.dir === 'rtl' || document.documentElement.dir === 'rtl' ? i - 1 : i + 1); }
    }
    close.addEventListener('click', shut);
    prev.addEventListener('click', function () { show(i - 1); });
    next.addEventListener('click', function () { show(i + 1); });
    lb.addEventListener('click', function (e) { if (e.target === lb) { shut(); } });
    document.addEventListener('keydown', key);
    document.body.classList.add('art-coa-lb-open');
    document.body.appendChild(lb);
    show(i);
    close.focus();
  }

  document.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('a[data-art-lightbox]') : null;
    if (a && !e.metaKey && !e.ctrlKey) {
      e.preventDefault();
      var g = a.getAttribute('data-art-lightbox');
      openLightbox(g, $$('a[data-art-lightbox="' + g + '"]').indexOf(a));
      return;
    }
    var c = e.target.closest ? e.target.closest('[data-art-copy]') : null;
    if (c) {
      var url = c.getAttribute('data-art-copy');
      var done = function () {
        var old = c.textContent;
        c.textContent = T.copied || 'Copied';
        setTimeout(function () { c.textContent = old; }, 1600);
      };
      if (navigator.clipboard) { navigator.clipboard.writeText(url).then(done); }
      else {
        var ta = el('textarea'); ta.value = url; document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); done(); } catch (x) {}
        document.body.removeChild(ta);
      }
    }
  });

  /* ===================================================================
   * Live certificate verification (progressive enhancement of the form)
   * ================================================================= */
  function initVerify(root) {
    var form = $('form', root);
    if (!form || !form.querySelector('input[name="art_coa_verify"]')) { return; }
    form.addEventListener('submit', function (e) {
      var input = form.querySelector('input[name="art_coa_verify"]');
      var serial = input.value.trim().replace(/[^A-Za-z0-9\-]/g, '');
      if (!serial || !window.fetch) { return; }
      e.preventDefault();

      $$('.art-coa-result', root).forEach(function (n) { n.parentNode.removeChild(n); });
      var box = el('div', 'art-coa-result');
      box.setAttribute('role', 'status');
      box.setAttribute('aria-live', 'polite');
      box.setAttribute('tabindex', '-1');
      box.appendChild(el('p', '', T.verifying || '…'));
      root.appendChild(box);

      fetch(C.rest + 'verify/' + encodeURIComponent(serial), { credentials: 'same-origin' })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
        .then(function (res) {
          box.textContent = '';
          var d = res.d || {};
          if (res.ok && d.valid && d.data) {
            box.className = 'art-coa-result valid';
            box.appendChild(el('h3', '', '✅ ' + (T.valid || 'Valid')));
            if (d.data.image_url) {
              var im = el('img', 'art-coa-verify-img'); im.src = d.data.image_url; im.alt = d.data.artwork_title || ''; im.loading = 'lazy';
              box.appendChild(im);
            }
            var rows = [[T.serial, d.data.serial_number], [T.artwork, d.data.artwork_title], [T.artist, d.data.artist_name], [T.owner, d.data.issued_to], [T.date, (d.data.issued_at_utc || '').slice(0, 10)]];
            var tbl = el('table');
            rows.forEach(function (r) {
              if (!r[1]) { return; }
              var tr = el('tr'); tr.appendChild(el('td', '', r[0] || '')); tr.appendChild(el('td', '', String(r[1]))); tbl.appendChild(tr);
            });
            box.appendChild(tbl);
          } else {
            box.className = 'art-coa-result invalid';
            box.appendChild(el('h3', '', '❌ ' + (T.invalid || 'Invalid')));
            box.appendChild(el('p', '', d.message || (d.code === 'art_coa_rate_limited' ? d.message : '') || T.error || ''));
          }
          box.focus();
          if (window.history.replaceState) {
            var u = new URL(window.location.href); u.searchParams.set('art_coa_verify', serial); window.history.replaceState(null, '', u.toString());
          }
        })
        .catch(function () { box.className = 'art-coa-result invalid'; box.textContent = T.error || 'Error'; });
    });
  }

  /* ===================================================================
   * Drag & drop image upload (REST → Media Library attachment IDs)
   * ================================================================= */
  function initDropzone(dz) {
    var multiple = dz.getAttribute('data-multiple') === '1';
    var max = parseInt(dz.getAttribute('data-max') || '1', 10);
    var value = $('.js-value', dz);
    var area = $('.art-coa-dz-area', dz);
    var file = $('input[type=file]', dz);
    var list = $('.art-coa-dz-previews', dz);
    var err = $('.art-coa-dz-error', dz);
    var types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    function ids() { return $$('li.is-done', list).map(function (li) { return li.getAttribute('data-id'); }); }
    function sync() { value.value = ids().join(','); }
    function fail(msg) { err.textContent = msg; err.hidden = !msg; }

    function addItem(f) {
      var li = el('li', 'is-uploading');
      var im = el('img'); im.alt = '';
      var bar = el('span', 'art-coa-dz-bar'); var fill = el('i'); bar.appendChild(fill);
      var del = el('button', 'js-del', '×'); del.type = 'button';
      li.appendChild(im); li.appendChild(bar); li.appendChild(del);
      if (!multiple) { list.textContent = ''; }
      list.appendChild(li);
      if (window.FileReader) {
        var fr = new FileReader(); fr.onload = function () { im.src = fr.result; }; fr.readAsDataURL(f);
      }
      del.addEventListener('click', function () { if (li.xhr) { li.xhr.abort(); } list.removeChild(li); sync(); });

      var xhr = new XMLHttpRequest();
      li.xhr = xhr;
      var fd = new FormData(); fd.append('file', f);
      xhr.open('POST', C.rest + 'upload');
      xhr.setRequestHeader('X-WP-Nonce', C.nonce);
      xhr.upload.onprogress = function (e) { if (e.lengthComputable) { fill.style.width = Math.round(e.loaded / e.total * 100) + '%'; } };
      xhr.onload = function () {
        var d = {};
        try { d = JSON.parse(xhr.responseText); } catch (x) {}
        if (xhr.status >= 200 && xhr.status < 300 && d.id) {
          li.className = 'is-done'; li.setAttribute('data-id', d.id); im.src = d.thumb || d.url;
          li.removeChild(bar); sync();
        } else {
          list.removeChild(li); fail(d.message || T.error || 'Error');
        }
      };
      xhr.onerror = function () { list.removeChild(li); fail(T.error || 'Error'); };
      xhr.send(fd);
    }

    function take(files) {
      fail('');
      Array.prototype.slice.call(files).forEach(function (f) {
        if (types.indexOf(f.type) < 0) { fail(T.badType); return; }
        if (f.size > C.maxMb * 1048576) { fail(T.tooBig); return; }
        if (multiple && $$('li', list).length >= max) { return; }
        addItem(f);
      });
    }

    area.addEventListener('click', function () { file.click(); });
    area.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); file.click(); } });
    file.addEventListener('change', function () { take(file.files); file.value = ''; });
    ['dragenter', 'dragover'].forEach(function (n) { area.addEventListener(n, function (e) { e.preventDefault(); area.classList.add('is-over'); }); });
    ['dragleave', 'drop'].forEach(function (n) { area.addEventListener(n, function (e) { e.preventDefault(); area.classList.remove('is-over'); }); });
    area.addEventListener('drop', function (e) { take(e.dataTransfer.files); });
    list.addEventListener('click', function (e) {
      if (e.target.classList.contains('js-del') && !e.target.parentNode.xhr) {
        list.removeChild(e.target.parentNode); sync();
      }
    });

    // Block form submit while an upload is still running / a required image is missing.
    var form = dz.closest('form');
    if (form) {
      form.addEventListener('submit', function (e) {
        if ($('li.is-uploading', list)) { e.preventDefault(); fail(T.uploading || ''); return; }
        if (dz.getAttribute('data-required') === '1' && !ids().length) { e.preventDefault(); fail(T.badType || ''); }
      });
    }
  }

  /* Live auction page: ticking countdown, polling of the public state endpoint (paused while the tab is hidden),
     AJAX bidding for signed-in users (the classic form still works without JavaScript) and quick-bid chips. */
  function initAuctionLive(box) {
    var id = box.getAttribute('data-auction-id');
    if (!id || box.getAttribute('data-live') !== '1') { return; }
    var endEl = $('[data-au="ends"]', box), bidEl = $('[data-au="bid"]', box), cntEl = $('[data-au="count"]', box);
    var yourMaxEl = $('[data-au="yourmax"]', box), leadEl = $('[data-au="lead"]', box), reserveEl = $('[data-au="reserve"]', box), msgEl = $('[data-au="msg"]', box);
    var form = $('.art-coa-bid-form', box), amount = $('input[name="amount"]', box);
    var endTs = endEl ? parseInt(endEl.getAttribute('data-ends-ts') || '0', 10) : 0;
    var offset = 0; // server clock - browser clock (seconds)
    var base = C.rest + 'auction/' + encodeURIComponent(id);
    var headers = C.nonce ? { 'X-WP-Nonce': C.nonce } : {};
    var lastCount = cntEl ? parseInt(cntEl.textContent.replace(/[^0-9]/g, '') || '0', 10) : 0;

    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function fmt(left) {
      var d = Math.floor(left / 86400), h = Math.floor((left % 86400) / 3600), m = Math.floor((left % 3600) / 60), s = left % 60;
      return (d > 0 ? d + 'd ' : '') + pad(h) + ':' + pad(m) + ':' + pad(s);
    }
    function tick() {
      if (!endEl || !endTs) { return; }
      var left = endTs - Math.floor(Date.now() / 1000) - offset;
      endEl.textContent = left > 0 ? fmt(left) : '00:00:00';
      endEl.classList.toggle('is-soon', left > 0 && left <= 300);
    }
    function say(text, ok, code) {
      if (!msgEl) { return; }
      msgEl.textContent = text || '';
      msgEl.className = 'art-coa-bid-msg' + (text ? (ok ? (code === 'outbid' ? ' is-warn' : ' is-ok') : ' is-err') : '');
    }
    function chips(d) {
      $$('.art-coa-bid-chip', box).forEach(function (c) {
        var v = d.min + parseInt(c.getAttribute('data-k') || '0', 10) * d.step;
        c.setAttribute('data-amount', String(v));
        if (c.getAttribute('data-k') === '0') { c.textContent = d.min_label; }
      });
    }
    function apply(d) {
      if (!d) { return; }
      if (!d.live) { window.location.reload(); return; }
      offset = Math.floor(Date.now() / 1000) - (d.ends - d.left);
      endTs = d.ends;
      if (bidEl) { bidEl.textContent = d.bid; }
      if (cntEl) { cntEl.textContent = String(d.count); }
      if (amount) { amount.min = String(d.min); }
      var hint = $('.art-coa-bid-min', box);
      if (hint) { hint.textContent = d.min_label; }
      if (leadEl) { leadEl.hidden = !d.you_lead; }
      if (yourMaxEl) { yourMaxEl.textContent = d.your_max || ''; yourMaxEl.hidden = !d.your_max; }
      if (reserveEl && d.has_reserve) {
        reserveEl.textContent = reserveEl.getAttribute(d.reserve_met ? 'data-met-text' : 'data-unmet-text') || reserveEl.textContent;
        reserveEl.classList.toggle('is-met', !!d.reserve_met);
        reserveEl.classList.toggle('is-unmet', !d.reserve_met);
      }
      // Someone else bid while this viewer was watching: tell them (once per change).
      if (d.count > lastCount && !d.you_lead && C.nonce && lastCount > 0) { say(T.auOutbid || '', false); }
      lastCount = d.count;
      chips(d);
    }
    function poll() {
      if (document.hidden) { return; }
      fetch(base + '/state', { credentials: 'same-origin', cache: 'no-store', headers: headers })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(apply)
        .catch(function () { /* offline: keep the last values */ });
    }

    $$('.art-coa-bid-chip', box).forEach(function (c) {
      c.addEventListener('click', function () { if (amount) { amount.value = c.getAttribute('data-amount'); amount.focus(); } });
    });

    // AJAX bid: only for signed-in users (the REST route needs the cookie + nonce); otherwise the normal POST is used.
    if (form && C.nonce && window.fetch) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = $('button[type="submit"]', form);
        var nameEl = $('input[name="bidder_name"]', form), phoneEl = $('input[name="bidder_phone"]', form);
        if (btn) { btn.disabled = true; }
        say(T.auSending || '', true);
        var h = { 'Content-Type': 'application/json', 'X-WP-Nonce': C.nonce };
        fetch(base + '/bid', {
          method: 'POST', credentials: 'same-origin', headers: h,
          body: JSON.stringify({ amount: amount ? amount.value : '', name: nameEl ? nameEl.value : '', phone: phoneEl ? phoneEl.value : '' })
        }).then(function (r) { return r.json().catch(function () { return null; }); })
          .then(function (d) {
            if (btn) { btn.disabled = false; }
            if (!d) { say(T.auNetErr || '', false); return; }
            say(d.message || '', !!d.ok, d.code);
            if (d.state) { apply(d.state); }
            if (d.ok && amount) { amount.value = ''; }
          })
          .catch(function () { if (btn) { btn.disabled = false; } say(T.auNetErr || '', false); });
      });
    }

    tick();
    window.setInterval(tick, 1000);
    window.setInterval(poll, 15000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) { poll(); } });
  }

  function boot() {
    $$('.art-coa-auction-single[data-auction-id]').forEach(initAuctionLive);
    $$('.art-coa-catalog[data-config]').forEach(initCatalog);
    $$('.art-coa-livesearch[data-config]').forEach(initLiveSearch);
    $$('.art-coa-verify').forEach(initVerify);
    $$('.art-coa-dz').forEach(initDropzone);
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
