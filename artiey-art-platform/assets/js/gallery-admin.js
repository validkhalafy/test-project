/* Artiey Exhibition Timeline – gallery editor (tabs, media pickers, artist filter, map picker). */
(function ($) {
	'use strict';

	$(function () {
		/* ---------- Tabs ---------- */
		$('.aetg').each(function () {
			var $box = $(this);
			var $tabs = $box.find('.aetg-tab');
			var show = function (id) {
				var $panel = $box.find('.aetg-panel').filter(function () { return String($(this).data('panel')) === String(id); }).first();
				if (!$panel.length) { id = $tabs.first().data('tab'); $panel = $box.find('.aetg-panel').filter(function () { return String($(this).data('panel')) === String(id); }).first(); }
				$box.find('.aetg-panel').removeClass('is-active').attr('hidden', true);
				$tabs.removeClass('is-active').attr({'aria-selected':'false', tabindex:'-1'});
				$panel.addClass('is-active').removeAttr('hidden');
				$tabs.filter(function () { return String($(this).data('tab')) === String(id); }).addClass('is-active').attr({'aria-selected':'true', tabindex:'0'});
				if (id === 'location') { initMap(); }
			};
			$box.find('.aetg-tabs').attr('role','tablist');
			$box.find('.aetg-panel').each(function (i) { if (!this.id) { this.id = 'aetg-panel-' + i; } $(this).attr('role','tabpanel'); });
			$tabs.attr({role:'tab', tabindex:'-1'}).each(function () { var $tab=$(this); var id=$tab.data('tab'); var $panel=$box.find('.aetg-panel').filter(function () { return String($(this).data('panel')) === String(id); }).first(); $tab.attr('aria-controls', $panel.attr('id') || ''); $panel.attr('aria-labelledby', this.id || ''); });
			$tabs.on('click', function () { show($(this).data('tab')); });
			$tabs.on('keydown', function (e) { var i=$tabs.index(this); if(e.key==='ArrowRight'||e.key==='ArrowDown'){e.preventDefault();$tabs.eq((i+1)%$tabs.length).focus().trigger('click');} else if(e.key==='ArrowLeft'||e.key==='ArrowUp'){e.preventDefault();$tabs.eq((i-1+$tabs.length)%$tabs.length).focus().trigger('click');} else if(e.key==='Home'){e.preventDefault();$tabs.first().focus().trigger('click');} else if(e.key==='End'){e.preventDefault();$tabs.last().focus().trigger('click');} });
			show($tabs.first().data('tab'));
		});




		/* ---------- WordPress Media Library pickers ---------- */
		function mediaStatus($field) {
			var $status = $field.find('.aetg-media-status').first();
			if (!$status.length) {
				$status = $('<p class="aetg-media-status" role="status" aria-live="polite"></p>');
				$field.append($status);
			}
			return $status;
		}

		function currentMediaIds($field, multiple) {
			var ids = ($field.find('.aetg-media-input').val() || '').split(',').map(function (id) {
				return $.trim(id);
			}).filter(function (id) {
				return /^\d+$/.test(id) && parseInt(id, 10) > 0;
			});
			ids = ids.filter(function (id, index) { return ids.indexOf(id) === index; });
			return multiple ? ids : ids.slice(0, 1);
		}

		$(document).on('click', '.aetg-media-pick', function (e) {
			e.preventDefault();
			var $field = $(this).closest('.aetg-media');
			var multiple = String($field.data('multiple')) === '1';
			var $status = mediaStatus($field);

			if (!window.wp || !wp.media) {
				$status.addClass('is-error').text('کتابخانه رسانه وردپرس بارگذاری نشده است. صفحه را تازه‌سازی کنید و دوباره تلاش کنید.');
				return;
			}

			var frame = $field.data('aetgFrame');
			if (!frame) {
				frame = wp.media({
					title: multiple ? 'انتخاب نمونه آثار' : 'انتخاب تصویر کاور',
					button: { text: 'استفاده از تصویر(ها)' },
					library: { type: 'image' },
					multiple: multiple ? 'add' : false
				});

				// MediaFrame uses Backbone.Events, not jQuery event namespaces.
				// The old "select.aetg" / "open.aetg" names never matched the emitted events.
				frame.on('select', function () {
					var state = frame.state();
					var selection = state && state.get ? state.get('selection') : null;
					var selected = [];
					var ids = [];
					var $preview = $field.find('.aetg-preview').first();
					if (!selection) {
						$status.addClass('is-error').text('انتخاب تصویر دریافت نشد؛ دوباره تلاش کنید.');
						return;
					}
					selection.each(function (attachment) {
						var data = attachment && attachment.toJSON ? attachment.toJSON() : {};
						var mime = String(data.mime || data.mime_type || '');
						var isImage = data.type === 'image' || /^image\//i.test(mime);
						if (!data.id || !isImage || ids.indexOf(String(data.id)) !== -1) { return; }
						ids.push(String(data.id));
						selected.push(data);
					});
					if (!multiple && selected.length > 1) {
						selected = selected.slice(-1);
						ids = [String(selected[0].id)];
					}
					$preview.empty();
					selected.forEach(function (data) {
						var sizes = data.sizes || {};
						var size = multiple ? (sizes.thumbnail || sizes.medium) : (sizes.medium || sizes.large || sizes.thumbnail);
						var url = size && size.url ? size.url : data.url;
						if (url) { $preview.append($('<img>', { src: url, alt: data.alt || '', loading: 'lazy' })); }
					});
					$field.find('.aetg-media-input').val(ids.join(',')).trigger('change');
					if (ids.length) {
						$status.removeClass('is-error').text(multiple ? ('تعداد تصاویر انتخاب‌شده: ' + ids.length) : 'تصویر انتخاب شد.');
					} else {
						$status.removeClass('is-error').text('تصویری انتخاب نشد.');
					}
				});

				frame.on('open', function () {
					var state = frame.state();
					var selection = state && state.get ? state.get('selection') : null;
					if (!selection) { return; }
					// Reset stale in-memory picks and restore IDs saved in the field.
					selection.reset();
					currentMediaIds($field, multiple).forEach(function (id) {
						var attachment = wp.media.attachment(parseInt(id, 10));
						selection.add(attachment);
						if (attachment && attachment.fetch) { attachment.fetch(); }
					});
				});

				$field.data('aetgFrame', frame);
			}

			$status.removeClass('is-error').text('');
			frame.open();
		});

		$(document).on('click', '.aetg-media-clear', function (e) {
			e.preventDefault();
			var $field = $(this).closest('.aetg-media');
			var frame = $field.data('aetgFrame');
			if (frame && frame.state && frame.state()) {
				var selection = frame.state().get('selection');
				if (selection && selection.reset) { selection.reset(); }
			}
			$field.find('.aetg-media-input').val('').trigger('change');
			$field.find('.aetg-preview').empty();
			mediaStatus($field).removeClass('is-error').text('تصویر(ها) پاک شد؛ برای ذخیره تغییرات، نوشته را به‌روزرسانی کنید.');
		});

		/* ---------- Repeatable rows (critics, press, team) ---------- */
		function repCount($rep) {
			var n = $rep.find('> .aetg-rep-rows > .aetg-rep-row').length;
			$rep.find('> label .aetg-rep-count').text('(' + n + '/' + ($rep.data('max') || 20) + ')');
			$rep.find('> .aetg-rep-add').prop('disabled', n >= (parseInt($rep.data('max'), 10) || 20));
		}
		$('.aetg-repeater').each(function () { repCount($(this)); });
		$(document).on('click', '.aetg-rep-add', function (e) {
			e.preventDefault();
			var $rep = $(this).closest('.aetg-repeater');
			var max = parseInt($rep.data('max'), 10) || 20;
			var $rows = $rep.find('> .aetg-rep-rows');
			if ($rows.children('.aetg-rep-row').length >= max) { return; }
			var tpl = $rep.find('> template.aetg-rep-tpl').html() || '';
			var key = 'n' + Date.now().toString(36) + Math.floor(Math.random() * 1000);
			var $row = $(tpl.replace(/__i__/g, key));
			$rows.append($row);
			$row.find('input,textarea').first().trigger('focus');
			repCount($rep);
		});
		$(document).on('click', '.aetg-rep-remove', function (e) {
			e.preventDefault();
			var $rep = $(this).closest('.aetg-repeater');
			var $row = $(this).closest('.aetg-rep-row');
			var filled = $row.find('input[type=text],input[type=url],textarea').filter(function () { return $.trim($(this).val()) !== ''; }).length;
			if (filled && !window.confirm('این ردیف حذف شود؟')) { return; }
			$row.remove();
			repCount($rep);
		});
		$(document).on('click', '.aetg-rep-up, .aetg-rep-down', function (e) {
			e.preventDefault();
			var $row = $(this).closest('.aetg-rep-row');
			if ($(this).hasClass('aetg-rep-up')) { $row.prev('.aetg-rep-row').before($row); } else { $row.next('.aetg-rep-row').after($row); }
			$(this).trigger('focus');
		});

		/* ---------- Artist filter ---------- */
		$(document).on('input', '.aetg-filter', function () {
			var q = $(this).val().toLowerCase();
			$(this).closest('.aetg-checklist-wrap').find('.aetg-checklist label').each(function () {
				var t = $(this).text().toLowerCase();
				$(this).toggleClass('is-hidden', q !== '' && t.indexOf(q) === -1);
			});
		});

		/* ---------- Map picker ---------- */
		var map = null;
		var marker = null;
		var $lat = $('input[name="aetg[lat]"]');
		var $lng = $('input[name="aetg[lng]"]');

		function setPoint(lat, lng, pan) {
			$lat.val(lat.toFixed(6));
			$lng.val(lng.toFixed(6));
			if (!map) {
				return;
			}
			if (!marker) {
				marker = L.marker([lat, lng], { draggable: true }).addTo(map);
				marker.on('dragend', function () {
					var p = marker.getLatLng();
					$lat.val(p.lat.toFixed(6));
					$lng.val(p.lng.toFixed(6));
				});
			} else {
				marker.setLatLng([lat, lng]);
			}
			if (pan) {
				map.setView([lat, lng], Math.max(map.getZoom(), 16));
			}
		}

		function initMap() {
			var el = document.getElementById('aetg-map');
			if (!el || map) {
				if (map) {
					setTimeout(function () { map.invalidateSize(); }, 50);
				}
				return;
			}
			if (typeof L === 'undefined') {
				$(el).replaceWith('<p class="aetg-hint">نقشه بارگذاری نشد؛ مختصات را دستی وارد کنید.</p>');
				return;
			}
			var lat = parseFloat($lat.val());
			var lng = parseFloat($lng.val());
			var has = !isNaN(lat) && !isNaN(lng);
			var dLat = has ? lat : parseFloat(el.getAttribute('data-default-lat'));
			var dLng = has ? lng : parseFloat(el.getAttribute('data-default-lng'));

			map = L.map(el).setView([dLat, dLng], has ? 16 : 11);
			L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
				maxZoom: 19,
				attribution: '&copy; OpenStreetMap'
			}).addTo(map);

			if (has) {
				setPoint(lat, lng, false);
			}
			map.on('click', function (e) {
				setPoint(e.latlng.lat, e.latlng.lng, false);
			});
			setTimeout(function () { map.invalidateSize(); }, 100);
		}

		$lat.add($lng).on('change', function () {
			var lat = parseFloat($lat.val());
			var lng = parseFloat($lng.val());
			if (!isNaN(lat) && !isNaN(lng) && map) {
				setPoint(lat, lng, true);
			}
		});

		$('#aetg-geocode').on('click', function () {
			var q = $.trim($('#aetg-address').val());
			var $msg = $('#aetg-geo-msg');
			if (!q) {
				$msg.text('ابتدا آدرس را بنویسید.');
				return;
			}
			if (!map) {
				$msg.text('نقشه در دسترس نیست؛ مختصات را دستی وارد کنید.');
				return;
			}
			$msg.text('در حال جستجو...');
			$.getJSON('https://nominatim.openstreetmap.org/search', { format: 'json', limit: 1, q: q })
				.done(function (res) {
					if (res && res.length) {
						setPoint(parseFloat(res[0].lat), parseFloat(res[0].lon), true);
						$msg.text('مکان پیدا شد؛ در صورت نیاز نشانگر را جابه‌جا کنید.');
					} else {
						$msg.text('آدرس پیدا نشد؛ روی نقشه کلیک کنید.');
					}
				})
				.fail(function () {
					$msg.text('جستجو انجام نشد؛ روی نقشه کلیک کنید یا مختصات را دستی وارد کنید.');
				});
		});
	});
})(jQuery);
