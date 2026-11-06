(function () {
  'use strict';
  // Tooltip for the public SVG charts: hover, keyboard focus and touch all show the same text.
  function init(plot) {
    var tip = plot.querySelector('.art-coa-chart-tip');
    if (!tip || plot.__ackReady) return;
    plot.__ackReady = true;
    function show(el) {
      var text = el.getAttribute('data-tip');
      if (!text) return;
      tip.textContent = text;
      tip.hidden = false;
      var pr = plot.getBoundingClientRect();
      var er = el.getBoundingClientRect();
      var x = er.left - pr.left + er.width / 2;
      tip.style.left = Math.max(8, Math.min(pr.width - 8, x)) + 'px';
      tip.style.top = Math.max(0, er.top - pr.top - 6) + 'px';
    }
    function hide() { tip.hidden = true; }
    var targets = plot.querySelectorAll('[data-tip]');
    for (var i = 0; i < targets.length; i++) {
      targets[i].addEventListener('mouseenter', function () { show(this); });
      targets[i].addEventListener('focus', function () { show(this); });
      targets[i].addEventListener('click', function () { show(this); });
      targets[i].addEventListener('mouseleave', hide);
      targets[i].addEventListener('blur', hide);
    }
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') hide(); });
  }
  function boot() {
    var plots = document.querySelectorAll('[data-art-chart]');
    for (var i = 0; i < plots.length; i++) init(plots[i]);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
  // Elementor preview / AJAX-loaded fragments
  if (window.MutationObserver) {
    new MutationObserver(function () { boot(); }).observe(document.body, { childList: true, subtree: true });
  }
})();
