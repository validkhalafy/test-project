/* Art COA — robust WordPress Media Library pickers (single + sortable multi). */
(function ($, window) {
  'use strict';

  var T = window.ArtCoaMedia || { pick: 'Choose image', use: 'Use this image', remove: 'Remove', error: 'Media Library is unavailable.', badImage: 'Please choose an image file.' };
  var MAX_MULTI = 24;

  function wpMediaReady() {
    return !!(window.wp && window.wp.media && typeof window.wp.media === 'function');
  }

  function modelJSON(model) {
    return model && typeof model.toJSON === 'function' ? model.toJSON() : {};
  }

  function isImage(item) {
    var data = item && typeof item.toJSON === 'function' ? item.toJSON() : (item || {});
    var mime = String(data.mime || data.mime_type || '');
    return data.type === 'image' || /^image\//i.test(mime);
  }

  function showStatus($box, message, isError) {
    var $status = $box.find('.art-coa-media-status').first();
    if (!$status.length) {
      $status = $('<p class="art-coa-media-status description" role="status" aria-live="polite"></p>').appendTo($box);
    }
    $status.text(message || '').toggleClass('is-error', !!isError);
  }

  function attachmentFromId(id) {
    if (!wpMediaReady() || !id) { return null; }
    return window.wp.media.attachment(parseInt(id, 10));
  }

  function frame(title, multiple) {
    return window.wp.media({
      title: title || T.pick,
      button: { text: T.use },
      library: { type: 'image' },
      multiple: multiple ? 'add' : false
    });
  }

  function restoreSelection(mediaFrame, ids) {
    if (!mediaFrame || !mediaFrame.state || !mediaFrame.state()) { return; }
    var selection = mediaFrame.state().get('selection');
    if (!selection || !selection.reset || !selection.add) { return; }
    selection.reset();
    ids.forEach(function (rawId) {
      var id = parseInt(rawId, 10);
      if (!id || id < 1) { return; }
      var attachment = attachmentFromId(id);
      if (!attachment) { return; }
      selection.add(attachment);
      if (typeof attachment.fetch === 'function') { attachment.fetch(); }
    });
  }

  /* ---------- single picker ---------- */
  $(document).on('click', '.art-coa-picker .js-pick', function (e) {
    e.preventDefault();
    var $box = $(this).closest('.art-coa-picker');
    if (!wpMediaReady()) {
      showStatus($box, T.error || 'کتابخانه رسانه وردپرس در دسترس نیست. صفحه را تازه‌سازی کنید.', true);
      return;
    }

    var f = $box.data('frame');
    if (!f) {
      f = frame($box.data('title'), false);
      f.on('select', function () {
        var selection = f.state().get('selection');
        var selected = selection && selection.first ? selection.first() : null;
        var att = modelJSON(selected);
        if (!att.id || !att.url || !isImage(selected)) {
          showStatus($box, T.badImage || 'لطفاً یک فایل تصویری انتخاب کنید.', true);
          return;
        }
        $box.find('.js-id').val(String(att.id)).trigger('change');
        $box.find('.js-url').val(String(att.url)).trigger('change');
        $box.find('.art-coa-picker-preview img').attr('src', previewUrl(att)).prop('hidden', false);
        $box.find('.art-coa-picker-ph').prop('hidden', true);
        $box.find('.art-coa-picker-preview').addClass('has-image');
        $box.find('.js-remove').prop('hidden', false);
        showStatus($box, '', false);
      });
      f.on('open', function () {
        var current = parseInt($box.find('.js-id').val(), 10) || 0;
        restoreSelection(f, current ? [current] : []);
      });
      $box.data('frame', f);
    }
    showStatus($box, '', false);
    f.open();
  });

  function previewUrl(att) {
    var s = att && att.sizes ? att.sizes : {};
    return (s.medium || s.thumbnail || s.large || {}).url || (att && att.url) || '';
  }

  $(document).on('click', '.art-coa-picker .js-remove', function (e) {
    e.preventDefault();
    var $box = $(this).closest('.art-coa-picker');
    $box.find('.js-id, .js-url').val('').trigger('change');
    $box.find('.art-coa-picker-preview img').attr('src', '').prop('hidden', true);
    $box.find('.art-coa-picker-ph').prop('hidden', false);
    $box.find('.art-coa-picker-preview').removeClass('has-image');
    $(this).prop('hidden', true);
    showStatus($box, '', false);
  });

  /* ---------- multi picker ---------- */
  function listIds($m) {
    var ids = [];
    $m.find('.art-coa-multi-list li').each(function () {
      var id = parseInt($(this).attr('data-id'), 10) || 0;
      if (id > 0 && ids.indexOf(id) === -1) { ids.push(id); }
    });
    return ids;
  }

  function syncMulti($m) {
    $m.find('.js-ids').val(listIds($m).join(',')).trigger('change');
  }

  function initSortable($m) {
    if ($.fn.sortable && !$m.data('sortable')) {
      $m.find('.art-coa-multi-list').sortable({ update: function () { syncMulti($m); } });
      $m.data('sortable', true);
    }
  }

  $('.art-coa-multi').each(function () { initSortable($(this)); });

  $(document).on('click', '.art-coa-multi .js-add', function (e) {
    e.preventDefault();
    var $m = $(this).closest('.art-coa-multi');
    if (!wpMediaReady()) {
      showStatus($m, T.error || 'کتابخانه رسانه وردپرس در دسترس نیست. صفحه را تازه‌سازی کنید.', true);
      return;
    }

    var f = $m.data('frame');
    if (!f) {
      f = frame($m.data('title'), true);
      f.on('select', function () {
        var have = {};
        listIds($m).forEach(function (id) { have[String(id)] = true; });
        var selection = f.state().get('selection');
        var added = 0;
        var rejected = 0;
        if (selection && selection.each) {
          selection.each(function (model) {
            var att = modelJSON(model);
            var id = parseInt(att.id, 10) || 0;
            if (!id || !att.url || !isImage(model)) { rejected++; return; }
            if (have[String(id)]) { return; }
            if (Object.keys(have).length >= MAX_MULTI) { rejected++; return; }
            have[String(id)] = true;
            var $li = $('<li>').attr('data-id', id);
            $('<img>').attr({ src: (att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url), alt: '' }).appendTo($li);
            $('<button type="button" class="js-del">').attr('aria-label', T.remove).text('×').appendTo($li);
            $m.find('.art-coa-multi-list').append($li);
            added++;
          });
        }
        syncMulti($m);
        initSortable($m);
        if (rejected) {
          showStatus($m, 'برخی فایل‌ها اضافه نشدند؛ فقط تصویر پذیرفته می‌شود و حداکثر ' + MAX_MULTI + ' تصویر قابل انتخاب است.', true);
        } else {
          showStatus($m, added ? ('تعداد تصویر اضافه‌شده: ' + added) : 'تصویر جدیدی اضافه نشد.', false);
        }
      });
      f.on('open', function () {
        // The visible list is authoritative. Removed images must not reappear from stale frame selection.
        restoreSelection(f, listIds($m));
      });
      $m.data('frame', f);
    }
    showStatus($m, '', false);
    f.open();
  });

  $(document).on('click', '.art-coa-multi .js-del', function (e) {
    e.preventDefault();
    var $m = $(this).closest('.art-coa-multi');
    $(this).closest('li').remove();
    syncMulti($m);
    showStatus($m, 'تصویر حذف شد؛ تغییرات را ذخیره کنید.', false);
  });
})(jQuery, window);
