/* global wp, jQuery */
(function ($) {
    'use strict';
    $(function () {
        var $id = $('#artwork_image_id');
        var $preview = $('#wp-coa-artwork-image-preview');
        var $select = $('#wp-coa-select-artwork-image');
        var $remove = $('#wp-coa-remove-artwork-image');
        var frame = null;
        if (!$id.length || !window.wp || !wp.media) { return; }

        function showAttachment(attachment) {
            if (!attachment || !attachment.id || !attachment.url || !/^image\/(jpeg|png|webp|gif|avif)$/i.test(attachment.mime || '')) {
                window.alert('لطفاً یک تصویر رستری معتبر انتخاب کنید. فایل SVG پذیرفته نمی‌شود.');
                return;
            }
            $id.val(String(attachment.id)).trigger('change');
            $preview.empty();
            $('<img>', {
                src: (attachment.sizes && attachment.sizes.medium && attachment.sizes.medium.url) ? attachment.sizes.medium.url : attachment.url,
                alt: attachment.alt || attachment.title || 'تصویر اصل اثر',
                css: { display: 'block', maxWidth: '320px', maxHeight: '260px', width: 'auto', height: 'auto', border: '1px solid #dcdcde', padding: '4px', background: '#fff' }
            }).appendTo($preview);
            $('<p>', { text: 'فایل: ' + (attachment.filename || '') + (attachment.filesizeInBytes ? ' — ' + (attachment.filesizeInBytes / 1024 / 1024).toFixed(2) + ' MB' : ''), css: { marginTop: '6px' } }).appendTo($preview);
            $remove.prop('hidden', false);
        }

        $select.on('click', function (event) {
            event.preventDefault();
            if (!frame) {
                frame = wp.media({
                    title: 'انتخاب یا بارگذاری تصویر اصل اثر',
                    button: { text: 'استفاده از این تصویر' },
                    library: { type: 'image' },
                    multiple: false
                });
                frame.on('select', function () {
                    var selection = frame.state().get('selection');
                    var item = selection && selection.first();
                    if (item) { showAttachment(item.toJSON()); }
                });
                frame.on('open', function () {
                    var selection = frame.state().get('selection');
                    if (!selection || !selection.reset) { return; }
                    selection.reset();
                    var currentId = parseInt($id.val(), 10) || 0;
                    if (currentId > 0) {
                        var attachment = wp.media.attachment(currentId);
                        selection.add(attachment);
                        if (attachment && attachment.fetch) { attachment.fetch(); }
                    }
                });
            }
            frame.open();
        });

        $remove.on('click', function (event) {
            event.preventDefault();
            $id.val('0').trigger('change');
            $preview.empty();
            $remove.prop('hidden', true);
        });
    });
}(jQuery));
