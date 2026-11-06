# Artiey Art Platform 3.0.3

* ویجت جدید «اسکرول هوشمند گالری (۲بعدی / ۳بعدی)»: یک اسکرول حرفه‌ای با سه حالت — تخت (اسکرول‌اسنپ)، ۳بعدی کاورفلو و ۳بعدی استوانه‌ای — و دکمهٔ تعویض ۲بعدی/۳بعدی برای بازدیدکننده. کشیدن با ماوس/انگشت، کلیدهای جهت‌دار (RTL درست)، چرخ افقی، حرکت خودکار، فلش و نقطه، اعلان برای صفحه‌خوان، و بازگشت خودکار به حالت تخت برای «حرکت کم»، مرورگر بدون ۳بعدی، کمتر از ۳ اسلاید و موبایل (قابل تنظیم). بدون جاوااسکریپت هم یک اسکرول تخت کار می‌کند.
* منبع پویا برای همهٔ صفحات: «هوشمند» بر اساس صفحهٔ جاری (گالری ← آثارش، هنرمند ← آثارش، اثر ← آثار بیشتر همان هنرمند، نمایشگاه ← آثار گالری و هنرمندانش، سایر صفحات ← تازه‌ترین آثار)؛ «نوشته‌ها» (هر نوع‌نوشتهٔ عمومی، طبقه‌بندی، شناسه‌ها، ترتیب، تعداد)؛ «مرتبط» (هم‌سبک/هم‌دسته با صفحه)؛ «شورت‌کدها» (خروجی هر شورت‌کد یک اسلاید، یا افزودن به اسلایدهای نوشته‌ها). یک بار در قالب Theme Builder یا ویجت سراسری بگذارید و روی همهٔ صفحات خودش را تطبیق می‌دهد. شورت‌کد: [artiey_smart_scroller]، قلاب: artiey_aet_smart_scroller_slides.
* امنیت و حریم خصوصی: فقط نوشته‌های منتشرشدهٔ بدون رمز؛ نوع‌نوشته‌ها در برابر فهرست مجاز (attachment و revision حذف)؛ شناسه‌ها، ترم‌ها و ترتیب پاک‌سازی؛ در حالت هوشمند هیچ ورودی نمی‌تواند نتیجه را گسترش دهد؛ شورت‌کدها فقط از ویرایشگر Elementor اجرا می‌شوند، نه از شورت‌کد عمومی.
* رفع باگ: متن اعلان صفحه‌خوان (و برچسب‌های screen-reader-text) در هر سه ویجت به کلاس قالب وابسته بود و در قالب‌هایی که آن را تعریف نکرده‌اند دیده می‌شد؛ اکنون CSS خود ویجت آن را مخفی می‌کند.
* آزمون: tests/smart-scroller.php (۷۱) و tests/js/smart-scroller.dom.test.js (۴۹ بررسی رفتار در DOM، شامل ریاضی ۳بعدی، RTL و موارد امنیتی). با ۵ باگ عمدی در JS راستی‌آزمایی شد که تست‌ها آن‌ها را می‌گیرند.


## Limits
- Verified with PHP stubs and a jsdom DOM test; **not** on a live WordPress/Elementor, and 3D rendering was not seen in a real browser (jsdom has no layout/GPU). Judge the look, depth and speed on staging, on a phone too.
- The "smart" source depends on the relation meta (_aw_artist, _aw_gallery, _ae_gallery, _ae_artists, _ag_artists). Content created without those links will simply show fewer or no slides; use the "posts" source for it.
- Shortcode slides are rendered as-is inside a card; heavy or interactive shortcodes inside 3D slides are the author's responsibility.
- No Google structured data is printed for this widget: slides are plain crawlable links, which is what helps internal linking.

## Staging checklist
1. Elementor: add it to a Theme Builder single template for galleries, artists, artworks and exhibitions; confirm each page shows the right slides (smart mode).
2. 3D: coverflow and cylinder on desktop, tablet and a real phone; drag, arrows, keyboard, the 2D/3D switch; with the OS "reduce motion" setting on it must stay flat.
3. RTL arrows and drag direction; focus order (only the centred slide's links are tabbable); screen reader announcement.
4. Shortcode source: add two shortcodes, confirm each is one slide.
5. Performance: Query Monitor (one query per widget), Lighthouse/INP on a page with the widget; check image sizes and the "eager images" setting for above-the-fold use.
