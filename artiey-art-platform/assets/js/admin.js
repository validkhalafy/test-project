/* Artiey Exhibition Timeline – settings page (tabs + color pickers). */
(function ($) {
	'use strict';

	$(function () {
		var $wrap = $('.aet-admin');
		if (!$wrap.length) {
			return;
		}

		$wrap.find('.aet-color').wpColorPicker();

		function show(id) {
			var $panel = $wrap.find('.aet-panel[data-panel="' + id + '"]');
			if (!$panel.length) {
				id = 'general';
				$panel = $wrap.find('.aet-panel[data-panel="general"]');
			}
			$wrap.find('.aet-panel').removeClass('is-active');
			$wrap.find('.aet-tabs .nav-tab').removeClass('nav-tab-active');
			$panel.addClass('is-active');
			$wrap.find('.aet-tabs .nav-tab[data-tab="' + id + '"]').addClass('nav-tab-active');
		}

		$wrap.find('.aet-tabs .nav-tab').on('click', function (e) {
			e.preventDefault();
			var id = $(this).data('tab');
			show(id);
			if (window.history && window.history.replaceState) {
				window.history.replaceState(null, '', '#' + id);
			}
		});

		show((window.location.hash || '').replace('#', '') || 'general');
	});
})(jQuery);
