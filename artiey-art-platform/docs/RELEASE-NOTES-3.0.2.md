# Artiey Art Platform 3.0.2

* ویجت جدید «گالری چندرسانه‌ای (عکس و ویدیو)»: اسکرول افقی کشیدنی با فلش و نقطه (RTL)، شبکه، آجری و موزاییک؛ لایت‌باکس قابل‌دسترس با پخش ویدیو (یوتیوب با دامنهٔ حریم‌خصوصی، ویمئو، آپارات، فایل mp4/webm)، کلیدهای جهت‌دار، تلهٔ فوکوس، سوایپ و احترام به «حرکت کم». منبع: دستی، نوشتهٔ جاری یا نوشتهٔ مشخص (گالری، اثر، هنرمند، نمایشگاه). خروجی ImageGallery/VideoObject برای سئوی تصویر و ویدیو. شورت‌کد معادل: [artiey_media_gallery].
* ویدیو فقط وقتی بازدیدکننده کلیک می‌کند بارگذاری می‌شود (سرعت و حریم خصوصی)؛ نشانی‌ها با فهرست مجاز ارائه‌دهندگان اعتبارسنجی می‌شوند و داده‌های لایت‌باکس با JSON_HEX_* چاپ می‌شوند.
* ویجت جدید «کیوریتور»: کارت، ردیفی، فشرده و ویژه (بنر کاور)، حالت فهرست یا یک کیوریتور، فیلتر تخصص، ترتیب، تعداد ستون ریسپانسیو، و پنل استایل کامل. فقط کیوریتورهای فعال؛ ایمیل، تلفن و user_id هرگز خوانده یا نمایش داده نمی‌شوند. خروجی Person/ItemList. شورت‌کد: [artiey_curators].
* رفع باگ فرم‌ساز ثبت‌نام (۲.۳۲.۲۸/۲۹): فیلد عددی سفارشی فقط ۰ تا ۸۰ را می‌پذیرفت؛ گزینه‌های فارسی فیلد انتخابی کلید خالی می‌گرفتند و بی‌صدا حذف می‌شدند (کلید گزینه‌های قبلی حفظ می‌شود)؛ کشیدن ردیف‌ها انتخاب متن داخل فیلدها را در Firefox خراب می‌کرد.
* آزمون‌ها: تست‌های نسخه از هدر افزونه خوانده می‌شوند (قبلاً با هر بالابردن نسخه می‌شکستند)؛ tests/media-curator-widgets.php (76) و tests/js/media-gallery.dom.test.js (۳۰ بررسی رفتار در DOM).


## Scope and limits
- Based on 3.0.1. No database, route, CPT or slug changes; the only new options are none.
- Verified with PHP stubs, a DOM test (jsdom) and static wiring checks. **Not** verified on a live WordPress/Elementor: open the editor, add both widgets, and check the panel, live preview and mobile layout.
- Existing artist / artwork / gallery / directory widgets were not redesigned; the new gallery widget can be placed on their pages (source = current post).
- Video SEO: VideoObject needs a thumbnail; YouTube gets one automatically, Vimeo/Aparat/files need a poster image.

## Staging checklist
1. Elementor editor: both widgets appear under the Artiey category; every control changes the preview; switching layout/columns per device works.
2. Gallery: keyboard (Tab, arrows, Esc), screen reader announces the dialog, video plays and stops on close, swipe on a phone, RTL arrows point the right way.
3. Rich Results Test on a page with the gallery (ImageGallery/VideoObject) and on a page with curators (Person/ItemList).
4. Curators: approve one in the admin, confirm it appears; suspend it, confirm it disappears (cache up to 10 minutes unless the approval button was used).
5. Performance: Query Monitor on a page with each widget (curator picker adds one cached query in the editor).
