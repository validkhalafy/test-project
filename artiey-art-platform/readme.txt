=== Artiey Art Platform (پلتفرم جامع آرتی) ===
Requires at least: 6.2
Requires PHP: 8.1
Stable tag: 3.2.0

= 2.32.32 =
* سخت‌سازی ورودی‌های درخواست: افزودن خوانندهٔ scalar امن برای داده‌های GET/POST؛ ورودی‌های آرایه‌ای/شیء به مقدار پیش‌فرض تبدیل می‌شوند تا به TypeError در PHP 8+ و شکست درخواست منجر نشوند.
* اعمال خواندن امن ورودی در فرم‌های مزایده، فراخوان، آثار، تنظیمات، دنبال‌کردن، استعلام فروش، بازار ثانویه و ارسال نمونه‌کار.
* اصلاح تشخیص درخواست Elementor در برابر action آرایه‌ای و malformed query string.
* آزمون‌های جدید برای ورودی‌های ناقص/آرایه‌ای و بررسی رگرسیون؛ ۷۲۳ آزمون خودکار موفق.

پلتفرم یکپارچه هنرهای تجسمی: گالری، هنرمند، اثر، نمایشگاه، تایم‌لاین، فهرست‌های فیلترپذیر، سامانه گواهی WP COA Pro و ماژول جامع بازار/عضویت WP Art COA Pro.

= 2.32.32 =
* ممیزی اتصالات بین ماژول‌ها: ۲۶۵۹ فراخوانی استاتیک بین کلاس‌ها، ستون‌های SQL کد جدید، شورت‌کدها، هندل‌های استایل، query varها، هندلرهای admin_post و مسیرهای /art-coa/ بررسی شد.
* رفع باگ اتصال: در حالت «تأیید مدیر» پروفایل کیوریتور و مجموعه‌دار برای همیشه پنهان می‌ماند چون در ادمین راهی برای تأییدشان نبود. اکنون ستون «وضعیت» و دکمهٔ «تأیید و انتشار / تعلیق» (با nonce، فقط manage_options، فقط این دو نوع) در صفحه‌های کیوریتورها و مجموعه‌دارها هست و ایمیل اعلان مدیر به صفحهٔ درست لینک می‌دهد (برای هنرمند و گالری: فهرست نوشته‌های در انتظار بررسی).
* رفع باگ ظاهر: کارت‌های «فراخوان» (art-coa-call-card) در هیچ استایل‌شیتی استایل نداشتند. اضافه شد.
* رفع باگ بارگذاری CSS: صفحه‌هایی که فقط [art_coa_calls]، [art_coa_my_follows]، [art_coa_follow_button] یا [art_coa_sitemap] داشتند (از جمله صفحه‌های «ساخت صفحات») استایل نمی‌گرفتند؛ [art_coa_certificate_full] هم به فهرست سبک اضافه شد.
* آزمون جدید tests/connections.php برای جلوگیری از برگشت این نوع ایرادها.

= 2.32.26 =
* رفع باگ اسلاگ: ویرایش اسلاگ از ویرایشگر بلوکی/REST (درصدی‌کدشده)، خالی‌کردن اسلاگ برای ساخت از عنوان، و اسلاگ «سبک‌های هنری» (taxonomy) به لاتین تبدیل نمی‌شد. اکنون هر سه تبدیل می‌شوند، و ذخیرهٔ دوبارهٔ پست قدیمی با اسلاگ فارسی آدرسش را تغییر نمی‌دهد.
* امنیت ثبت‌نام: نام کاربری تصادفی (قبلاً از ایمیل ساخته می‌شد و از ?author= و آرشیو نویسنده نشت می‌کرد)؛ نام مستعار عمومی نام شخص است؛ جلوگیری از pre-hijack (لینک تأیید در مرورگری غیر از مرورگر ثبت‌نام، رمز را بازنشانی می‌کند)؛ شکست ارسال ایمیل حساب را پاک می‌کند؛ سقف ارسال دوباره بر اساس IP؛ profile.php برای تغییر رمز در دسترس اعضاست.
* رفع باگ: اگر «نام نمایشی» خالی بود، نام عمومی حساب به‌جای نام و نام خانوادگی یک نام عمومی تصادفی می‌شد.
* بهینه‌سازی: schema و تنظیمات فرم در هر درخواست یک بار ساخته می‌شوند؛ CSS شخصی‌سازی هنگام ذخیره کامپایل و در جلوی سایت فقط خوانده می‌شود.
* سئو: سایت‌مپ تصویری /art-image-sitemap.xml برای آثار، نمایشگاه‌ها، گالری‌ها و هنرمندان (غیرفعال هنگام وجود افزونهٔ سئو) و افزودن به robots.txt؛ hasMap و @id در اسکیمای ArtGallery.
* مستندات: docs/RELEASE-CHECKLIST-2.32.26.md (تست‌های پیش از انتشار، ریسک‌ها و نقاط تأثیر).

= 2.32.25 =
* فرم ثبت‌نام حرفه‌ای و نقش‌محور: هنرمند، گالری‌دار، کیوریتور، مجموعه‌دار و خریدار با فیلدهای اختصاصی هر نقش؛ با انتخاب نقش، پنل مربوط جایگزین می‌شود. نقش‌ها و فیلدها از «فرم ثبت‌نام اعضا» قابل روشن/خاموش، الزامی‌کردن و تغییر عنوان هستند و فیلتر artiey_aet_member_roles نقش تازه اضافه می‌کند.
* رفع باگ: فیلدهای هم‌نام بین نقش‌ها (bio، website) همه ارسال می‌شدند و مقدار پنل پنهان مقدار هنرمند را خالی می‌کرد؛ اکنون فیلدها f[role][field] هستند و پنل‌های غیرفعال disabled.
* رفع باگ: کیوریتور قابل ثبت‌نام نبود؛ نام‌خانوادگی هنرمند اختیاری نمایش داده می‌شد ولی نبودش ساخت پروفایل را می‌شکست و کاربر نیمه‌کاره می‌ماند. اکنون در شکست، حساب ساخته‌شده حذف می‌شود.
* امنیت: تأیید ایمیل با پیوند ۴۸ ساعته (توکن هش‌شده)، بدون افشای وجود ایمیل، حذف خودکار حساب‌های تأییدنشده پس از ۷ روز، ایمیل ارسال دوباره؛ پروفایل پس از تأیید ایمیل ساخته می‌شود تا ثبت‌نام با ایمیل دیگران پروفایل هنرمند موجود را تصاحب نکند. honeypot، توکن زمانی امضاشده، nonce، سیاست رمز عبور و نقش فقط از سمت سرور.
* تأیید مدیر: پروفایل عمومی هنرمند/گالری/کیوریتور/مجموعه‌دار تا تأیید مدیر منتشر نمی‌شود و ایمیل اعلان به مدیر می‌رود.
* هر عضو فقط صفحهٔ خودش: شناسهٔ پروفایل فقط از حساب کاربر و ستون user_id خوانده می‌شود، هرگز از درخواست. شورت‌کد جدید [art_coa_member_profile] (و داخل داشبورد) ویرایش پروفایل با نوار تکمیل؛ اعضا به wp-admin و نوار مدیریت دسترسی ندارند. داشبورد کیوریتور اضافه شد.
* سئو: noindex برای داشبورد، تأیید ایمیل و وضعیت‌های ثبت‌نام؛ توضیح متا و JSON-LD برای صفحهٔ ثبت‌نام (در صورت نبود افزونهٔ سئو)؛ پیوند نقش‌ها بدون جاوااسکریپت؛ فرم معنایی با label، fieldset، aria و autocomplete.

= 2.32.24 =
* سلسله‌ارتباطات هنرمند: گواهی‌ها، تاریخچهٔ مالکیت و نام مالکان اکنون با یک کوئری برای کل آثار خوانده می‌شود (قبلاً سه کوئری به‌ازای هر اثر)؛ همین برای نام مالک در درخت گالری هم اعمال شد. خروجی تغییری نکرده و تاریخچه همچنان حداکثر ۱۰ ردیف برای هر اثر است.
* افزودن آزمون شمارش کوئری و درستی نگاشت گواهی/تاریخچه/مالک با دیتابیس ساختگی.

= 2.32.23 =
* رفع باگ اعلان مدیریتی بازسازی مسیرها: لینک «تنظیمات پیوندهای یکتا» به‌دلیل رشتهٔ خراب و escape نادرست نمایش داده نمی‌شد.
* کش ماژول گواهی (Cache::get): مقدارهای false/null/۰ اکنون کش می‌شوند (قبلاً در هر درخواست دوباره محاسبه می‌شدند)؛ مقدارهای قدیمی خوانده می‌شوند.
* ابطال کش بین دو ماژول: تغییر اثر، فروش، پایان حراج و صدور/ابطال/انتقال گواهی، کش فهرست‌ها و آمار پلتفرم را هم تازه می‌کند.
* صفحاتی با فرم/توکن/پیشنهاد زندهٔ حراج (ثبت‌نام، داشبورد، فروشگاه، حراج، فراخوان، بازار ثانویه) خودکار DONOTCACHEPAGE و no-cache می‌گیرند.
* منوی جدید «کش و مسیرها»: بازسازی مسیرها و پاک‌سازی کش داخلی، کش المنتور و درخواست پاک‌سازی افزونه‌های کش شناخته‌شده.
* سلسله‌ارتباطات: کوئری‌های درخت گالری دسته‌ای شد (حذف N+1)؛ پروفایل عمومی گالری و مجموعه‌دار دیگر آثار پیش‌نویس، مالک، گواهی‌های غیرفعال و تاریخچهٔ انتقال را نشان نمی‌دهد؛ آثار پیش‌نویسِ لینک‌شده به نمایشگاه هم پنهان می‌شوند.
* منوی جدید «ساخت صفحات»: ۱۶ صفحهٔ شورت‌کدی با تشخیص صفحهٔ موجود (کلاسیک/المنتور)، بدون صفحهٔ تکراری، بدون تصاحب صفحهٔ نامرتبط، احترام به صفحهٔ به‌زباله‌رفته، عنوان/نامک قابل ویرایش و ترمیم.
* منوی جدید «ظاهر و شخصی‌سازی»: رنگ اصلی/تأکیدی، گردی گوشه، سایه، فونت، اندازهٔ متن، عرض پروفایل و CSS سفارشی ایمن با پیش‌نمایش زنده؛ استایل ویجت‌های Elementor همچنان اولویت دارد.

= 2.32.22 =
* آزادسازی اسلاگ پروفایل هنرمند، گالری، اثر و نمایشگاه پس از حذف دائمی نوشته؛ رکورد تاریخی و روابط حفظ می‌شوند اما نامک عمومی دیگر رزرو نمی‌ماند.
* پاک‌سازی کش URLها و فهرست‌ها پس از حذف دائمی محتوا و بازسازی یک‌بارهٔ مسیرها.
* حفظ رفتار امن برای انتقال به زباله‌دان؛ اسلاگ تنها پس از حذف دائمی آزاد می‌شود.

= 2.32.21 =
* اصلاح بازسازی مسیرهای وردپرس پس از ارتقا: افزایش نسخهٔ مستقل Rewrite Schema و اجرای یک‌بارهٔ flush پس از ثبت همهٔ مسیرهای CPT و COA.
* پاک‌سازی کش داخلی مسیرها، فهرست هنرمندان، پروفایل‌ها، گالری‌ها، آمار، روابط گالری و داده‌های Elementor با نسل‌بندی کش و حذف transientهای قدیمی؛ بدون تخلیهٔ سراسری object cache.
* جلوگیری از اجرای دو flush جداگانه برای مسیرهای COA و پلتفرم در یک درخواست.
* ترمیم صفحهٔ مدیریت‌شدهٔ `/artists/` در صورت حذف یا باقی‌ماندن در وضعیت پیش‌نویس؛ صفحهٔ مدیریت‌شدهٔ آرشیو برای بازدیدکنندگان ناشناس منتشر می‌شود، اما صفحهٔ نامرتبط یا مسیر اشغال‌شده تصاحب نمی‌شود.
* افزودن اعلان مدیریتی یک‌باره برای اطلاع از بازسازی مسیرها و یادآوری پاک‌سازی کش میزبان/CDN در صورت تداوم ۴۰۴.
* حفظ مسیرهای قدیمی به‌عنوان ریدایرکت ۳۰۱ سازگار با موتورهای جست‌وجو؛ کش قدیمی حذف می‌شود اما aliasهای لازم حذف نمی‌شوند.

= 2.32.18 =
* اصلاح کنترل دسترسی مسیرهای عمومی پروفایل COA: پروفایل مجموعه‌دار فقط با وضعیت فعال و گزینهٔ انتشار عمومی نمایش داده می‌شود؛ شناسهٔ اسلاگ و مسیرهای قدیمی نیز پیش از ریدایرکت اعتبارسنجی می‌شوند.
* اصلاح رفتار مسیرهای نامعتبر COA و فراخوان‌ها: پاسخ HTTP 404 واقعی همراه با noindex به‌جای خروجی موفق برای نوع مسیر یا شناسهٔ نامعتبر.
* اصلاح انتقال شناسه و اسلاگ فراخوان در قالب Elementor تا ویجت‌های وابسته بتوانند رکورد جاری را درست تشخیص دهند.
* محدودکردن فهرست حراج و بازار ثانویه به آثار دارای وضعیت عمومی؛ وضعیت انتشار اثر از وضعیت حراج جداگانه خوانده می‌شود تا حراج‌های زمان‌بندی‌شده یا پایان‌یافته اشتباهاً حذف نشوند.
* پنهان‌کردن نام هنرمند غیرفعال و مجموعه‌دار خصوصی در کارت‌ها و صفحات عمومی حراج و بازار ثانویه.
* افزودن آزمون‌های رگرسیون برای حریم خصوصی، کنترل وضعیت انتشار، مسیرهای 404 و زمینهٔ Elementor.

= 2.32.16 =
* افزودن پنل ویرایش تب‌ها به تمام ویجت‌های تب‌دار Art COA: فروشگاه، فهرست حراج، تایم‌لاین نمایشگاه، تایم‌لاین حراج، سلسله‌مراتب هنرمند، پورتفولیو، بیوگرافی، تاریخچه و نمودارها.
* امکان مرتب‌سازی، تغییر عنوان، افزودن آیکون و فعال/غیرفعال‌کردن تب‌ها در یک کنترل مشترک Elementor.
* اصلاح نمایش کنترل‌های تب سلسله‌مراتب تا فقط هنگام انتخاب نوع ارتباط «هنرمند» در دسترس باشد.
* رفع باگ حذف همهٔ تب‌ها: repeater خالیِ ذخیره‌شده اکنون خروجی تب‌ها را خالی می‌کند؛ ویجت‌های قدیمی بدون تنظیم جدید تب‌های قبلی را حفظ می‌کنند.
* افزودن آزمون رگرسیون برای ویرایشگر تب‌های تمام ویجت‌های COA.
* افزودن پنل ویرایش تب‌ها در ویجت‌های پروفایل هنرمند، گالری، اثر، نمایشگاه، فهرست و آمار: تغییر ترتیب با کشیدن ردیف‌ها، ویرایش عنوان، افزودن آیکون و فعال/غیرفعال‌کردن هر تب.
* اصلاح پیش‌نمایش زنده Elementor؛ مجموعه‌تب‌هایی که قبلاً راه‌اندازی شده‌اند پس از جایگزینی محتوای داخلی دوباره همگام می‌شوند و تب باز تا حد امکان حفظ می‌شود.
* اصلاح حالت پیکربندی که تمام تب‌ها غیرفعال شده‌اند؛ خروجی خالی می‌ماند و تب‌های خاموش ناخواسته بازنمی‌گردند.
* حفظ ترتیب و عناوین پیش‌فرض برای صفحات قدیمی و fallback امن هنگام وجود تنظیمات تکرارشده یا نامعتبر.
* بهبود نمایش آیکون تب‌ها و حذف قانون CSS متناقض مربوط به وضعیت پنل‌های پنهان.

= 2.32.14 =
* افزودن ۴۵ برچسب پویای مستقل برای تمام شورت‌کدهای داخلی پلتفرم آرتی، Art COA و ماژول سازگاری COA.
* افزودن برچسب پویای عمومی برای انتخاب تمام شورت‌کدهای ثبت‌شدهٔ وردپرس، از جمله شورت‌کدهای افزونه‌ها و قالب‌های دیگر.
* افزودن تنظیم پارامترهای شورت‌کد، انتخاب خروجی HTML یا متن ساده و اتصال خودکار به هنرمند/گالری/اثر جاری در شورت‌کدهای سازگار.
* گروه‌بندی برچسب‌ها در بخش‌های مجزای Dynamic Tags برای دسترسی و جست‌وجوی آسان‌تر.
* برچسب‌ها در کنترل‌های متنی Elementor در دسترس‌اند؛ استفاده از قابلیت Dynamic Tags به Elementor Pro نیاز دارد.

= 2.32.13 =

* Reorganizes Elementor widget content controls into focused panels for sources, section visibility, filters, relationships, chart data, portfolio, history, and output behavior.
* Expands shared Art COA design controls with dedicated panels for visual presets, cards, responsive grids, typography, buttons, images, borders, shadows, and spacing.
* Enables section-by-section control for exhibition profiles in both stacked and tab layouts.
* Adds clear content panels for gallery profiles, the exhibition calendar, artist directory, portfolio, statistics, artist bio, COA shop, hierarchy, auction lists, artwork history, and charts.
* Preserves existing Elementor control IDs and saved widget settings while exposing more styling controls.

* بازطراحی پنل ویرایش ویجت‌های Elementor با گروه‌بندی تنظیمات منبع، چیدمان، بخش‌ها، فیلترها، ارتباطات، نمونه آثار، نمودارها و تاریخچه.
* گسترش ابزارهای طراحی مشترک WP Art COA: قالب‌های آماده، کارت‌ها، شبکه واکنش‌گرا، تایپوگرافی، دکمه‌ها، تصاویر، حاشیه، سایه و فاصله‌گذاری.
* فعال‌سازی انتخاب مستقل بخش‌های پروفایل نمایشگاه در هر دو حالت پشت‌سرهم و تب‌دار.
* تفکیک پنل محتوایی ویجت‌های گالری، تقویم نمایشگاه، آرشیو، نمونه آثار، آمار، بیوگرافی، فروشگاه، سلسله ارتباطات، حراج و نمودار.
* حفظ شناسهٔ کنترل‌های نسخه‌های قبلی برای نگهداری تنظیمات ذخیره‌شدهٔ صفحات.

= 2.32.12 =

* Unifies the public artist archive and artist profiles under the canonical `/artists/` namespace.
* Adopts the existing `/artists/` Gutenberg or Elementor page, preserving its design and adding the archive renderer only when absent.
* Redirects superseded managed archive pages and legacy artist directory routes (`/art-coa/artists/`) to `/artists/`; old published artist profiles move to `/artists/{slug}/`.
* Fixes legacy profile routing when the managed profile page is found by path instead of its stored page ID.

* یکپارچه‌سازی آرشیو محتوایی هنرمندان روی `/artists/` و پروفایل تکی روی `/artists/{slug}/`؛ مسیر پایهٔ هنرمندان در تنظیمات قفل شد تا لینک‌ها دوباره چندپاره نشوند.
* مهاجرت امن برگهٔ مدیریت‌شدهٔ `/artists/` به شورت‌کد آرشیو اصلی بدون حذف چیدمان Elementor، عنوان یا محتوای غیرمرتبط.
* اتصال شورت‌کدهای قدیمی فهرست هنرمندان و ویجت قدیمی COA به آرشیو محتوایی هنرمندان؛ فهرست مجموعه‌داران مستقل باقی می‌ماند.
* افزودن فیلتر کیوریتور بر اساس رابطهٔ شناسه‌دار هنرمند ← اثر ← نمایشگاه ← کیوریتور، در کنار فیلترهای شهر، ملیت، سبک، رسانه، گالری، آثار، نمایشگاه و مرتب‌سازی.
* ریدایرکت مسیرهای قدیمی `/art-coa/artist/{slug}/` و `/art-person-profile/{slug}/` به `/artists/{slug}/` فقط برای پروفایل منتشرشده و با تطبیق دقیق؛ مسیر فروشگاه آثار `/art-coa/shop/artist/{slug}/` مستقل باقی می‌ماند.
* پشتیبانی Elementor از نوع محتوای هنرمند حفظ شد؛ یک قالب پویا برای همهٔ هنرمندان کافی است و نمایش خودکار پروفایل نیز در حالت پیش‌فرض فعال می‌ماند.

= 2.32.10 =
* بازگردانی ظاهر گروه‌بندی‌شده و باز/بسته‌شوندهٔ منوی پلتفرم آرتی در تمام صفحات پیشخوان وردپرس؛ فایل‌های سبک منو در صفحات عمومی مدیریت نیز بارگذاری می‌شوند.
* حفظ محدودیت فایل CSS داشبورد به صفحهٔ اصلی پلتفرم؛ فایل‌های سنگین داشبورد در صفحات نامرتبط بارگذاری نمی‌شوند.
* رفع رگرسیون ظاهری ایجادشده در نسخهٔ 2.32.5 بدون بازگرداندن بارگذاری سایر دارایی‌های مدیریتی.

= 2.32.9 =
* اولویت‌دهی به سند و شناسهٔ درخواست فعال Elementor پیش از متغیرهای سراسری وردپرس؛ جلوگیری از نمایش پروفایل هنرمند اشتباه در پیش‌نمایش‌های تو‌در‌تو یا قالب‌ها.
* افزودن رکورد جاری به فهرست انتخاب ویجت حتی اگر به‌دلیل سقف ۳۰۰ رکورد در کش گزینه‌ها قرار نگرفته باشد.
* پیش‌بارگذاری متادیتای تصاویر نمونه‌کار همراه با واکشی گروهی؛ جلوگیری از درخواست‌های تکی پیوست هنگام نمایش زیرنویس‌ها و لینک تصویر.
* افزودن ظاهر مشخص و سبک برای پیام‌های پیش‌نمایش سبک نمودار و تاریخچه در محیط Elementor.
* رفع خطای نحوی در resolver سند Elementor و بازیابی امن شناسهٔ هنرمند از سند فعال و پارامترهای درخواست ویرایش؛ همراه با اعتبارسنجی نوع نوشته و مجوز.
* اصلاح شرط نمایش تنظیمات وابسته به انتخاب چندگانهٔ بخش‌ها با اپراتور contains؛ رفع نمایش نادرست کنترل‌های نمودار، شورت‌کد، بیوگرافی، نمونه‌کار و تاریخچه.
* سبک‌سازی پیش‌نمایش زندهٔ ویجت هنرمند: عدم اجرای کوئری‌های سنگین نمودارها، تاریخچهٔ COA، شمارندهٔ آمار و مسیرهای fallback COA در درخواست ویرایشگر.
* محدود کردن تعداد کارت‌های آثار، تصاویر و گالری‌ها در پیش‌نمایش Elementor و حفظ خروجی کامل در سایت عمومی.
* اعتبارسنجی گروهی تصاویر نمونه‌کار با یک کوئری محدود به پیوست‌های تصویری به‌جای بررسی جداگانهٔ هر شناسه؛ افزودن کش درخواست‌محور اطلاعات هنرمند.
* افزودن آزمون رگرسیون برای شرط‌های Elementor، نرمال‌سازی انتخاب بخش‌ها و بازیابی پست هنرمند در ویرایشگر.

= 2.32.8 =
* بهینه‌سازی راه‌اندازی Elementor: جلوگیری از اجرای مجدد dbDelta و مهاجرت ماژول COA در به‌روزرسانی‌های جزئی افزونه؛ اجرای مهاجرت فقط هنگام تغییر نسخهٔ مستقل ماژول.
* انتقال ترمیم محتوای پروفایل‌های قدیمی به صف پس‌زمینه با بسته‌های محدود ۱۲تایی و حذف بررسی/تغییر ساختار جدول از درخواست زمان‌بندی مهاجرت.
* عدم ثبت منوها، داشبوردها، متاباکس‌ها و هوک‌های مدیریتی غیرضروری در صفحات عمومی و پوستهٔ ویرایشگر Elementor؛ حفظ آن‌ها در صفحات عادی مدیریت.
* کش درخواست‌محور برای گزینه‌های انتخاب موجودیت در Elementor و افزایش عمر کش پایدار برای کاهش درخواست‌های تکراری.
* رفع اشکال ویجت پروفایل هنرمند و تکمیل روابط آثار، گالری‌ها و نمایشگاه‌ها از مسیر وردپرس و COA.
* بازیابی آثار و گالری‌های دارای رابطهٔ معتبر COA حتی در صورت ناقص بودن متای آینه‌ای وردپرس؛ تطبیق شناسه با اسلاگ برای جلوگیری از لینک اشتباه.
* مرتب‌سازی خط زمانی تحصیلات، جوایز و نمایشگاه‌ها؛ اتصال کیوریتور فعال به پروفایل عمومی و پیش‌بارگذاری گروهی آن.
* کش نگاشت شناسهٔ هنرمند COA در سطح درخواست و حفظ محدودیت نمایش اطلاعات مالکیت خصوصی.

= 2.32.6 =
* رفع نقص ویجت هنرمند در شناسایی گالری‌های مرتبط از طریق آثار ثبت‌شده در وردپرس و رابطهٔ مستقیم جداول COA؛ لینک‌های آینه‌ای با نوع محتوا، وضعیت انتشار و اسلاگ تطبیق داده می‌شوند تا شناسهٔ قدیمی به پروفایل نامرتبط نرسد.
* تکمیل تایم‌لاین نمایشگاه‌های هنرمند با ادغام ارتباط مستقیم وردپرس و رابطهٔ نمایشگاه↔اثر↔هنرمند در COA؛ فقط صفحات عمومی منتشرشدهٔ متناظر نمایش داده می‌شوند.
* نمایش نام کیوریتور در کارت نمایشگاه‌های پروفایل هنرمند و مرتب‌سازی قطعی نمایشگاه‌های گذشته از جدید به قدیم، مستقل از ترتیب منبع داده.
* نمایش آثار کاتالوگِ منتشرشده در کنار تصاویر نمونهٔ هنرمند و افزودن کنترل تعداد آثار (۱ تا ۴۸) در Elementor.
* مرتب‌سازی تایم‌لاین تحصیلات و جوایز بر اساس سال (قدیمی به جدید)، پشتیبانی از ارقام فارسی/عربی/لاتین و انتقال موارد بدون تاریخ به انتهای فهرست.
* جلوگیری از نمایش نام مالک در تاریخچهٔ عمومی، مگر اینکه مجموعه‌دار به‌صورت عمومی و فعال ثبت شده باشد؛ عدم نمایش نام خریدار یا مالک خصوصی.
* آزمون‌های رگرسیون برای رابطه‌های هنرمند، ترتیب تاریخ‌ها، وضعیت نمایشگاه‌ها و حریم خصوصی مالکیت.

= 2.32.5 =
* محدود کردن فایل‌های CSS و JavaScript منوی مدیریت به صفحات مرتبط با پلتفرم آرتی.
* بارگذاری فقط استایل سبک برای صفحات استعلام و نمایش ساده گواهی؛ حذف بارگذاری فایل تعاملی ۷۰ کیلوبایتی و JavaScript غیرضروری در این صفحات.
* تشخیص گروهی شورت‌کدها به‌جای اسکن مکرر محتوای صفحه.
* پیش‌بارگذاری گروهی تصاویر و فرادادهٔ کارت‌های پروفایل برای کاهش کوئری‌های N+1.
* تجمیع آمار داشبورد COA در یک درخواست SQL و اصلاح نسخه‌گذاری کش فایل‌های استاتیک.

= 2.32.4 =
* ارتقای ویجت پروفایل گالری Elementor با اتصال واقعی به هنرمندان، کیوریتورها، مجموعه‌داران عمومی و حراج‌های مرتبط.
* افزودن تنظیمات حرفه‌ای کارت‌ها و کنترل‌های واکنش‌گرای استایل در Elementor.
* رعایت حریم خصوصی مجموعه‌داران و هماهنگی کوئری‌ها با ساختار دیتابیس Art COA.

== نسخه یکپارچه ==
این افزونه نسخه‌های مستقل «Artiey Exhibition Timeline»، «WP COA Pro» و «WP Art COA Pro» را جایگزین می‌کند. در صورت فعال‌بودن نسخه مستقل WP Art COA Pro، هنگام فعال‌سازی این افزونه آن نسخه غیرفعال می‌شود؛ جداول و داده‌های آن نگه داشته می‌شوند. حداقل PHP موردنیاز ۸٫۱ است.

== راهنمای داده و مهاجرت ==
منبع اصلی اطلاعات گالری، هنرمند، اثر و نمایشگاه پست‌تایپ‌های افزونه است. برای چک‌لیست ورود اطلاعات و توضیح آینه‌سازی به جدول‌های art_*، فایل docs/DATA-COMPLETENESS.md را ببینید. مرجع نهایی گواهی‌ها COA Pro است؛ به‌ویژه پس از ارتقا به ۲٫۸، جدول art_certificates فقط نمایهٔ گواهی‌هاست.

== ماژول‌ها ==
* گالری‌ها: پروفایل کامل، ساعات کاری، نقشه، هنرمندان، پوستر نمایشگاه‌ها (جاری/آینده/گذشته)
* هنرمندان: پروفایل حرفه‌ای، سوابق، جوایز، نمونه آثار، گالری‌های همکار
* آثار هنری: تصویر، مشخصات، قیمت و وضعیت فروش، گواهی اصالت، آثار دیگر هنرمند
* نمایشگاه‌ها: پوستر، تاریخ شمسی، تایم‌لاین پویا
* فهرست فیلترپذیر (AJAX): گالری، نمایشگاه، هنرمند، اثر — فیلتر شهر، سبک، وضعیت، هنرمند، گالری، مرتب‌سازی
* گواهی اصالت (WP COA Pro 1.11.1): صدور، امضای HMAC، ابطال، انتقال مالکیت، زنجیره رویداد، QR، استعلام عمومی، REST
* اتصال دوطرفه: گالری/هنرمند ↔ پروفایل گواهی، صدور گواهی از روی اثر با فرم پیش‌پر، نمایش وضعیت گواهی در صفحه اثر
* سئو: Schema.org (ArtGallery, ExhibitionEvent, VisualArtwork, Person) و ماژول سئوی COA
* المنتور: دسته «پلتفرم آرتی» با ۶ ابزارک شامل آمار پویای قابل تنظیم + ابزارک‌های «WP COA Pro»؛ فهرست‌های انتخابی با کش برای ویرایشگر سریع‌تر
* ماژول تکمیلی WP Art COA Pro: پروفایل/عضویت، گواهی و استعلام، تقویم، فروشگاه، بازار ثانویه، حراج، فراخوان و ابزارک‌های اختصاصی؛ مدیریت در زیرمنوی پلتفرم

== شورت‌کدها ==
[artiey_timeline] [artiey_directory type="galleries|exhibitions|artists|artworks"]
[artiey_gallery_profile] [artiey_artist_profile] [artiey_artwork_profile]
[artiey_related_exhibitions] [artiey_gallery_field] [artiey_artist_field] [artiey_artwork_field]
[artiey_stats metrics="galleries,artists,artworks,exhibitions,current,upcoming"] (آمار کلی پویا)
[coa_verify] [coa_gallery_directory] [coa_public_stats] [coa_certificate_guide] ... (ماژول COA)
[art_coa_verify] [art_coa_certificate] [art_coa_calendar] [art_coa_timeline] [art_coa_artwork_history] [art_coa_directory] (ماژول WP Art COA Pro)

== Changelog ==
= 3.0.3 =
* ویجت جدید «اسکرول هوشمند گالری (۲بعدی / ۳بعدی)»: یک اسکرول حرفه‌ای با سه حالت — تخت (اسکرول‌اسنپ)، ۳بعدی کاورفلو و ۳بعدی استوانه‌ای — و دکمهٔ تعویض ۲بعدی/۳بعدی برای بازدیدکننده. کشیدن با ماوس/انگشت، کلیدهای جهت‌دار (RTL درست)، چرخ افقی، حرکت خودکار، فلش و نقطه، اعلان برای صفحه‌خوان، و بازگشت خودکار به حالت تخت برای «حرکت کم»، مرورگر بدون ۳بعدی، کمتر از ۳ اسلاید و موبایل (قابل تنظیم). بدون جاوااسکریپت هم یک اسکرول تخت کار می‌کند.
* منبع پویا برای همهٔ صفحات: «هوشمند» بر اساس صفحهٔ جاری (گالری ← آثارش، هنرمند ← آثارش، اثر ← آثار بیشتر همان هنرمند، نمایشگاه ← آثار گالری و هنرمندانش، سایر صفحات ← تازه‌ترین آثار)؛ «نوشته‌ها» (هر نوع‌نوشتهٔ عمومی، طبقه‌بندی، شناسه‌ها، ترتیب، تعداد)؛ «مرتبط» (هم‌سبک/هم‌دسته با صفحه)؛ «شورت‌کدها» (خروجی هر شورت‌کد یک اسلاید، یا افزودن به اسلایدهای نوشته‌ها). یک بار در قالب Theme Builder یا ویجت سراسری بگذارید و روی همهٔ صفحات خودش را تطبیق می‌دهد. شورت‌کد: [artiey_smart_scroller]، قلاب: artiey_aet_smart_scroller_slides.
* امنیت و حریم خصوصی: فقط نوشته‌های منتشرشدهٔ بدون رمز؛ نوع‌نوشته‌ها در برابر فهرست مجاز (attachment و revision حذف)؛ شناسه‌ها، ترم‌ها و ترتیب پاک‌سازی؛ در حالت هوشمند هیچ ورودی نمی‌تواند نتیجه را گسترش دهد؛ شورت‌کدها فقط از ویرایشگر Elementor اجرا می‌شوند، نه از شورت‌کد عمومی.
* رفع باگ: متن اعلان صفحه‌خوان (و برچسب‌های screen-reader-text) در هر سه ویجت به کلاس قالب وابسته بود و در قالب‌هایی که آن را تعریف نکرده‌اند دیده می‌شد؛ اکنون CSS خود ویجت آن را مخفی می‌کند.
* آزمون: tests/smart-scroller.php (۷۱) و tests/js/smart-scroller.dom.test.js (۴۹ بررسی رفتار در DOM، شامل ریاضی ۳بعدی، RTL و موارد امنیتی). با ۵ باگ عمدی در JS راستی‌آزمایی شد که تست‌ها آن‌ها را می‌گیرند.

= 3.0.2 =
* ویجت جدید «گالری چندرسانه‌ای (عکس و ویدیو)»: اسکرول افقی کشیدنی با فلش و نقطه (RTL)، شبکه، آجری و موزاییک؛ لایت‌باکس قابل‌دسترس با پخش ویدیو (یوتیوب با دامنهٔ حریم‌خصوصی، ویمئو، آپارات، فایل mp4/webm)، کلیدهای جهت‌دار، تلهٔ فوکوس، سوایپ و احترام به «حرکت کم». منبع: دستی، نوشتهٔ جاری یا نوشتهٔ مشخص (گالری، اثر، هنرمند، نمایشگاه). خروجی ImageGallery/VideoObject برای سئوی تصویر و ویدیو. شورت‌کد معادل: [artiey_media_gallery].
* ویدیو فقط وقتی بازدیدکننده کلیک می‌کند بارگذاری می‌شود (سرعت و حریم خصوصی)؛ نشانی‌ها با فهرست مجاز ارائه‌دهندگان اعتبارسنجی می‌شوند و داده‌های لایت‌باکس با JSON_HEX_* چاپ می‌شوند.
* ویجت جدید «کیوریتور»: کارت، ردیفی، فشرده و ویژه (بنر کاور)، حالت فهرست یا یک کیوریتور، فیلتر تخصص، ترتیب، تعداد ستون ریسپانسیو، و پنل استایل کامل. فقط کیوریتورهای فعال؛ ایمیل، تلفن و user_id هرگز خوانده یا نمایش داده نمی‌شوند. خروجی Person/ItemList. شورت‌کد: [artiey_curators].
* رفع باگ فرم‌ساز ثبت‌نام (۲.۳۲.۲۸/۲۹): فیلد عددی سفارشی فقط ۰ تا ۸۰ را می‌پذیرفت؛ گزینه‌های فارسی فیلد انتخابی کلید خالی می‌گرفتند و بی‌صدا حذف می‌شدند (کلید گزینه‌های قبلی حفظ می‌شود)؛ کشیدن ردیف‌ها انتخاب متن داخل فیلدها را در Firefox خراب می‌کرد.
* آزمون‌ها: تست‌های نسخه از هدر افزونه خوانده می‌شوند (قبلاً با هر بالابردن نسخه می‌شکستند)؛ tests/media-curator-widgets.php (76) و tests/js/media-gallery.dom.test.js (۳۰ بررسی رفتار در DOM).


= 2.32.32 =
* آزادسازی نامک آینه‌های هنرمند/گالری/اثر/نمایشگاه هنگام انتقال به زباله‌دان یا حذف دائمی، با حفظ سوابق.
* همگام‌سازی مجدد هنگام بازیابی از زباله‌دان.
* کاهش پرس‌وجوهای تکراری schema در همگام‌سازی محتوا با کش ستون‌ها در هر درخواست.
* بازسازی یک‌باره قوانین Rewrite در ارتقا.


= 2.32.3 =
* Pages & Elementor: managed page links now validate the page ownership meta key and automatically recover from stale or recycled WordPress page IDs instead of pointing at unrelated pages.
* COA gallery directory: filtering and result limits now run in SQL, search requests do not create transient-cache entries, and profile lookup by slug uses the indexed unique slug.
* COA public statistics: active gallery count uses a scalar COUNT query rather than loading every gallery row into PHP.

= 2.32.2 =
* Certificate lookup: codes typed with Persian/Arabic digits, typographic dashes, spaces or invisible direction marks are now normalised (COA Pro page, QR route and the Art COA endpoint) instead of silently becoming "not found".
* Verification form: the form now submits back to a page that contains the shortcode — on the QR route (/coa-verify/CODE/) it previously posted to the home page, and with plain permalinks the page id was dropped from the GET query.
* The Art COA verify endpoint/shortcode/REST now respects the COA Pro "Enable public lookup" switch.
* Hierarchy widget: visitors now see a privacy-safe gallery/artist hierarchy (published artworks only, active certificates, no owner names, ownership history or e-mails) instead of an empty widget; collector/buyer trees stay admin-only.

= 2.32.1 =
* Fixed Elementor tab sets jumping back to the default/last tab (or the URL-hash tab) whenever any element was added inside a panel (maps, sliders, Elementor re-renders). An already enhanced tab set now keeps the tab the visitor opened.
* Fixed gallery, artist and artwork Elementor profile widgets in "tabs" layout rendering nothing when the section list had never been saved (older pages); they now show every section, like the stacked layout. Empty tab output now shows the empty-state message.
* Tab URL hashes can now also target a tab by its stable key (panel ids contain a per-request counter).
* "Pages & Elementor" admin screen: the result notice is a warning when any page failed to be created, and non-published managed pages show their status.
* Art COA public pages (verify / register / member): a stored page ID pointing to a deleted or trashed page no longer blocks re-creation; an existing published page that already holds the shortcode is reused. Created pages have comments/pings closed.
* Corrected the "?aet_id=" control label in Elementor profile widgets.

= 2.32.0 =
* Proxy (maximum) bidding (setting "Proxy bidding", on by default): the amount a bidder enters is their hidden maximum; the shown price rises only as far as needed, one increment above the runner-up. Equal maximums: the earlier bidder wins. The maximum is shown only to its owner and is never in public output. A leader whose maximum reaches the reserve buys at the reserve.
* Winner follow-up: sold auctions get a payment deadline (setting, default 3 days) and the winner is e-mailed it. The auctions admin shows payment status with "Mark paid" (optionally records the ownership transfer in the history and moves the artwork to the winning collector) and "Not paid" (cancels the sale, returns the artwork to the catalogue, e-mails the bidder).
* The admin "close auction" button now uses the same closing logic as the cron (marks the artwork sold and sends the e-mails). Database version 2.3.5 adds columns; run automatically.

= 2.31.0 =
* Auctions behave more like real ones: bidding requires a signed-in account (setting "Only signed-in users can bid", on by default), sellers can no longer bid on their own auction (by account or e-mail), and the outbid bidder, the winner and the seller are e-mailed. Sold auctions mark the artwork sold and mask the winner's name.
* One bidding engine (Auction::place_bid) now serves both the classic form and the new AJAX route POST /wp-json/art-coa/v1/auction/{id}/bid; the form still works without JavaScript.
* Live auction page: ticking countdown, polling of GET /wp-json/art-coa/v1/auction/{id}/state every 15 seconds (paused in background tabs), quick-bid chips, "you hold the highest bid" notice, reserve met / not met indicator (amount never exposed) and screen-reader announcements.
* Not included yet: proxy (maximum) bidding, payment/invoice and automatic ownership transfer.

= 2.30.6 =
* Extended the shared Pro tabs system to the standalone Artist Bio and Portfolio widgets, including selectable tab sections and shared responsive behaviour/style controls.
* Added Portfolio tabs for all artworks and artworks with an active certificate; certification filtering uses the COA certificates table, not a display label.
* Extended the artist hierarchy to include COA exhibitions linked through exhibition artworks, while de-duplicating already-linked WordPress exhibition posts.
* Retained canonical WordPress artist identity with COA relational IDs for certificates, ownership history, artwork, gallery and auction relations.
* Hardened portfolio request filters to accept scalar values only and skip unused artist/gallery/year/medium option queries when filters are hidden, reducing redundant database work in tabbed layouts.
* Added regression tests for artist bio/portfolio tabs, active-certificate queries and artist-exhibition relationship coverage.

= 2.30.5 =
* Fixed artist/gallery/artwork Elementor profile tabs that could fail after widgets were inserted or re-rendered dynamically in Elementor preview.
* Added MutationObserver and Elementor frontend hook initialization, plus on-click recovery for tab groups that were not initialized at DOM ready.
* Matched each tab to its own panel before activation; a broken/unmatched tab can no longer leave all profile content inactive.
* Synced hidden/aria-hidden/active states and added an explicit CSS rule for hidden tab panels so theme or Elementor display rules cannot keep inactive panels visible.
* Bumped plugin/asset version to invalidate cached profile JavaScript and CSS.

= 2.30.4 =
* Profile widgets now support horizontal and vertical clickable tabs, a separate mobile orientation, and independent tabs for each selected section.
* Added responsive width, content alignment and padding controls for tab navigation; keyboard navigation follows tab orientation and RTL layout.
* Artist activity and sales charts can connect either automatically to COA or through dedicated manual shortcode fields.
* Manual chart mode supports only [art_coa_charts] and [artiey_charts], with {artist_id}, {artist_post_id} and {months} placeholders; arbitrary shortcodes are rejected.
* Editor-only diagnostics explain missing shortcode configuration or a missing automatic COA relationship without exposing these messages to ordinary visitors.
* Audited all bundled WordPress Media Library pickers and fixed selection restoration, repeated-frame behavior, stale multi-image selections, unsupported media feedback, and form change events.
* Hardened gallery and artwork saves: missing meta-box payloads no longer wipe existing metadata; image galleries reject non-image attachments, duplicate IDs, and invalid media.
* COA-to-WordPress media bridge now refuses invalid attachment IDs without overwriting a previously valid image reference; URL-only updates no longer clear attachment IDs unintentionally.
* COA person-profile image picker caches its media frame, restores the selected attachment, reports unavailable Media Library errors, and clears stale IDs when URLs are manually edited.
* COA settings logo picker and certificate issuance picker now handle unavailable Media Library states and restore/cache media selection safely.
* Added regression tests for all media picker code paths and image-backed save handlers.

= 2.30.2 =
* Fixed the artist cover and portfolio image picker: use WordPress MediaFrame Backbone events (`select`/`open`) instead of event names that never fire.
* Decoupled the media-picker script from optional Leaflet CDN loading so a blocked map library cannot disable image selection.
* Restored current selections when the modal opens, reset stale selections safely, and added clear/status feedback.
* Restricted cover/portfolio metadata to actual image attachments, de-duplicated portfolio IDs, and protected existing metadata if the custom form payload is absent.


= 2.30.0 =
* WordPress artist posts are the canonical source for names, profile fields, biography, images and publication status; COA artist rows remain relational mirrors whose numeric IDs are preserved for certificates, artwork history, ownership and auction relationships.
* Legacy COA/member artist creation and updates now write through the canonical WordPress artist post, then synchronize to the existing relational row instead of inserting a parallel artist.
* Artist adoption/migration matches stable identity (user ID, national ID, email), detects conflicts across identity fields, and fails safely rather than choosing an arbitrary row or duplicating profiles.
* Artist point lookups, the COA biography renderer and public COA artist profile read canonical WordPress fields while preserving the relational artist ID needed for joins.
* Mirroring and profile lookup fail closed when multiple COA rows point to the same WordPress artist; ambiguous legacy records are queued/reported for review instead of being automatically deleted or merged.
* Published/draft visibility follows the canonical WordPress post status; editors may preview drafts they can edit.
* Expanded the artist Elementor profile with independent statement, biography read-more, education/awards timeline, activity and privacy-aware sales charts, and artist-wide artwork history.
* Added responsive controls and styles for profile sections, charts, career timeline, and artwork-history events.
* Improved the artist-section settings migration so once sections are disabled, they are not silently re-enabled on every request.


= 2.29.8 =
* Fixed the exhibition calendar gallery column width control with a dedicated CSS variable while keeping the saved Elementor control key compatible.
* Reworked the calendar grid minimum width to avoid unsupported multiplication inside CSS calc() and improve horizontal scrolling at narrow widths.
* Added a dedicated gallery-column style section with responsive typography for the gallery header and gallery names, separate colours, cell padding, logo width/height and alignment controls.
* Added an optional gallery-name caption below gallery logos, off by default to preserve existing layouts.
* Added sanitization and regression tests for the new caption option and gallery-column controls.

= 2.29.7 =
* Added professional arrow and navigation controls to exhibition, event, auction, and artwork-history Elementor widgets.
* Added multiple arrow glyphs, frames, hover colours, responsive sizing, focus styling, shadows, and motion presets.
* Improved RTL-safe previous/next navigation markup and accessible icon-only mode.
* Added shared arrow styling to timeline CTA links and bumped the asset version to prevent stale CSS caching.


= 2.29.5 =

- Added editable main title and subtitle controls to both exhibition timeline widgets.
- Added per-widget selection of title-bar subtitle components while keeping existing global settings as the default.
- Added responsive typography, color, alignment, and spacing controls for timeline headings.
- Preserved existing output when new heading fields remain empty.

= 2.29.4 =
* Add stacked or accessible tabbed section layouts to artist, gallery, and artwork Elementor profile widgets, while preserving stacked layouts as the default.
* Add Elementor controls for tab colours, active state, border, radius, spacing, panel background, and responsive padding.
* Add directory card skins (standard, editorial, compact horizontal, and framed) with responsive styling.
* Fix profile tab widget dependencies so the shared frontend JS is loaded whenever these widgets require it.
* Improve tab behavior with scoped panel selection, ARIA states, keyboard navigation (arrows/Home/End), and re-initialization after AJAX updates.
* Harden directory AJAX against overlapping requests and browsers without AbortController; provide accessible loading/error feedback without forcing a full-page redirect on REST errors.
* Improve AJAX query-string encoding/history handling and use per-form search debounce timers.

= 2.29.3 =
* Rebuild the Artiey admin submenu after all bundled modules register their pages, with a stable dashboard-first order and distinct content, certificate, market, and settings groups.
* Remove duplicate submenu destinations, including the malformed COA Pro menu slug and duplicated dashboard entry, while retaining different pages that serve different functions.
* Rename similarly titled certificate/profile/settings pages so their purpose is clear instead of appearing as repeated generic entries.
* Add accessible, collapsible section headings to the WordPress sidebar; the active section opens automatically and other sections can be expanded when needed.
* Keep new-item links and unrelated extension pages, and deduplicate shared taxonomy screens across post types.

= 2.29.2 =
* Fix unresolved global class references in namespaced content bridge and catalog rendering, preventing runtime fatal errors during legacy migration and artist cards without images.
* Make mirrored slugs collision-safe across multiple suffix candidates and cap their length safely.
* Preserve lookup of long legacy slugs before shortening, preventing duplicate relational rows during migration.
* Respect optional/older table columns when updating mirrored records, and treat missing insert IDs as sync failures.
* Retry failed legacy-row migrations a bounded number of times and retain final failures for diagnostics.
* Check database update failures when repairing stale links and normalizing legacy slugs.
* Retry failed content-mirror writes in bounded background batches; retain database-query cursors on transient SELECT errors instead of skipping content.
* Queue failed mirror writes made by legacy admin forms so saved WordPress content is not permanently disconnected from the compatibility tables.

= 2.29.1 =
* Prevent invalid legacy edit IDs from creating duplicate WordPress posts.
* Reset the content-sync suppression flag in finally blocks so a thrown hook cannot disable syncing for the rest of the request.
* Treat failed mirror-table updates and incomplete migration schemas as sync failures instead of false success.


= 2.29.0 =
* Unified the platform dashboard and moved artist, gallery, artwork, and exhibition management to their canonical WordPress post types.
* Added a resumable migration bridge from legacy Art COA tables while preserving existing numeric IDs used by certificates, ownership, auctions, and history.
* Standardized default public URL bases to artists, galleries, artworks, exhibitions, and art-styles; legacy singular paths redirect to canonical URLs.
* Fixed artist creation when the optional display name is blank but first and last names are present.
* Added UTF-8 fallbacks when PHP mbstring is unavailable and a no-DOM fallback for extracting biography text from Elementor content.
* Aligned archive URLs with configurable rewrite bases.

= 2.28.0 =
* Improved: artwork page and single auction page redesigned (responsive, consistent cards, larger bid form).
* Improved: dark scheme for biography and auction cards; AA contrast for muted text; focus rings.

= 2.27.0 =
* Improved: profile re-sync runs in background batches (WP-Cron); empty posts no longer blank filled bios.
* Improved: gallery, curator and exhibition pages share the artist page design.
* New: English translations for the new biography and auction strings.

= 2.26.0 =
* New: Elementor widget 'Active auctions list' with full customisation; redesigned responsive auction cards; new [art_coa_auction] attributes.
* Fix: invalid repeat(min()) CSS on the timeline grid at tablet width.

= 2.25.1 =
* New: global settings for the artist biography section; shortcode attributes inherit them.
* Fix: wrong shop link on the artist page.

= 2.25.0 =
* New: artist biography section (lead, read-more, facts, statement, education/awards timelines), Elementor widget and [art_coa_artist_bio] shortcode, fully customisable.
* Improved: responsive, consistent artist profile page and cards.

= 2.24.2 =
* Fix: biography duplicated text / raw CSS on public art-coa profiles (polluted post_content was mirrored into table columns). Sync now stores cleaned text only, output is cleaned, existing rows are re-synced on upgrade.

= 2.24.1 =
* Fixed: artist/gallery profile biography still showed the profile many times, with raw Elementor CSS text. Old pages had stored nested copies of the profile (and <style> blocks) in post_content. The biography is now cleaned on read (style/script blocks, Elementor and profile markup and repeated paragraphs removed), our own output is no longer stored when a profile post is saved, and posts already polluted are repaired once on update.

= 2.24.0 =
* Fixed: title, subtitle, date and card style controls of the timeline widgets had no effect because the stylesheet forced values with !important. All timeline and history rules are now overridable and every Elementor control is scoped so it wins.
* Exhibition timeline widget rewritten: five layouts (cards, vertical line, two-sided, poster grid, compact), month grouping, live/upcoming/past status chips, countdown, location, button, ascending/descending order, status filter, all-years mode, schema.org Event data and a full style panel (typography and colours for title, subtitle, dates, chips, cards, images, line, month headings, button and status colours).
* New auction timeline widget and shortcode [art_coa_auction_timeline] (live, upcoming, closed and sold auctions; bidder identities are never shown). The widget file existed but was never registered and called a missing method.
* Artwork history (sales and ownership) widget: order option and full typography/colour controls; shortcode [art_coa_artwork_history] unchanged.
* Fixed repeated text in the artist/gallery profile widgets: the biography is no longer read from the HTML an Elementor page stored in post_content, the automatic profile block no longer re-adds sections beside a hand-built layout (the previously unused profile guard is now wired in), and explicit widgets/shortcodes are never silently skipped.
* Artist and gallery profile widgets: new "Profile sections" style panel (section cards, biography, specifications, chips, social buttons, photo grid, avatar, Latin name).
* Main timeline widget (.aet): title and subtitle colours, text alignment, full-title wrapping, subtitle toggle, bar shadow and hover brightness.

= 2.23.1 =
* Improved the Elementor exhibition timeline with configurable cards/vertical timeline, date/poster/subtitle toggles, color controls, and duplicate-heading/filter fixes.
* Added an Elementor artwork-history widget for auctions, WooCommerce sale audit events, completed-sale records, secondary-market listings, and ownership transfers using existing Artie data, with automatic current-artwork detection on artwork profiles.
* Added the [art_coa_artwork_history] shortcode and bumped frontend asset versions. Buyer identity is never exposed; sale prices appear only when the source record provides them and the widget enables price display.


= 2.23.0 =
* Links and slugs: editable URL bases of the post types (Settings > Advanced), automatic rewrite flush, shorter slugs cut at word boundaries.
* Renamed art-coa profile slugs answer with a 301 to the new address; unknown profiles return a real 404 with noindex; differently-cased URLs redirect to the canonical form.

= 2.22.0 =
* Charts for artist progress, activity and sales (server-rendered SVG, accessible data table, dark mode, cached); shortcode [art_coa_charts] and an Elementor widget.
* Sales and profile-completeness charts are visible only to admins and the profile owner.
* art-coa Elementor widgets: pickers, dynamic tags, scheme/hover/typography/border controls; exhibition timeline per-widget colours, striping, today/Friday highlight.

= 2.21.0 =
* ویجت‌های المنتور: پنل «ظاهر» مشترک و کامل برای همهٔ ویجت‌های پلتفرم؛ طرح روشن/تیره/خودکار، رنگ‌ها، تایپوگرافی متن و تیترها، حاشیه و سایه، جلوهٔ اشاره، نسبت و گردی تصویر، ستون‌ها و فاصله‌ها، دکمه‌ها و فیلترها.
* ویجت‌های پروفایل: انتخاب رکورد از لیست جست‌وجوپذیر، خواندن از پارامتر ‎?aet_id=‎، پیام سفارشی برای حالت خالی، و ویجت تازهٔ «پروفایل نمایشگاه».
* ویجت فهرست: مرتب‌سازی پیش‌فرض، انتخاب دستی (با ترتیب دلخواه)، استثناکردن شناسه‌ها، پیام خالی سفارشی و نمایش/پنهان‌کردن شمارنده؛ تگ‌های دینامیک المنتور برای متن‌ها و پیوندها.
* ویجت‌های گواهی و آرشیو: تایپوگرافی، حاشیه، سایه، گردی و رنگ دکمه‌ها.
* رفع اشکال: پاک‌سازی کش فهرست گزینه‌های ویجت‌ها فقط هنگام بارگذاری المنتور اجرا می‌شد؛ حالا در هر ذخیره (شامل نمایشگاه‌ها) کار می‌کند و هوک حذف سه‌بار ثبت نمی‌شد.
* امنیت: نمایش‌ندادن پیش‌نویس/خصوصی از راه ویجت یا ‎?aet_id=‎ برای بازدیدکننده؛ محدودیت نرخ و اندازهٔ ورودی روی REST تایم‌لاین و فهرست؛ referrer-policy روی نقشهٔ جاسازی‌شده.
* سئو: noindex خودکار برای نمای فیلترشده/صفحه‌بندی‌شده، گزینهٔ noindex برای هر صفحه، BreadcrumbList، og:site_name/og:locale/article:modified_time و برچسب‌های Twitter، و توضیح متای پیش‌فرض برای نمایشگاه‌ها.
* تست: ۷۹ بررسی (شامل ساختار همهٔ ویجت‌ها).

= 2.20.0 =
* افزودن کنترل‌های طراحی Elementor برای رنگ متن و زمینه، رنگ تأکیدی، تایپوگرافی عنوان، گردی کارت‌ها، فاصله شبکه و فاصله داخلی در ویجت‌های Art COA.
* تکمیل شخصی‌سازی ویجت‌های پروفایل گالری، هنرمند، اثر، فهرست و آمار با کنترل رنگ‌ها، تایپوگرافی، ستون‌ها و فاصله کارت‌ها.
* بازطراحی پیش‌فرض کارت‌ها، تقویم، تایم‌لاین و فرم‌های عمومی با چیدمان واکنش‌گرا، گوشه‌های نرم و حالت‌های تعاملی سبک.

= 2.19.4 =
* جلوگیری از تکرار بخش‌های پروفایل هنگام ترکیب ویجت Elementor و پروفایل خودکار.
* جلوگیری از اجرای فیلترهای صفحه و درج ویجت‌های تکراری داخل متن بیوگرافی.

= 2.19.3 =
* رفع اشکال ویجت‌های پروفایل برای تشخیص نوشتهٔ جاری در قالب‌های تکی و رعایت انتخاب خالی بخش‌ها.
* تکمیل بخش بیوگرافی در چیدمان پیش‌فرض و پاک‌سازی فهرست انتخاب محتوا پس از حذف.

= 2.19.2 =
* رفع اشکال بارگذاری استایل ویجت‌ها و راه‌اندازی سازگار با زمان بارگذاری Elementor.
* یکپارچه‌سازی دستهٔ ویجت‌ها و تکمیل عنوان‌ها، فیلترها و اعتبارسنجی ورودی‌ها.

= 2.19.1 =
* اصلاح چیدمان پروفایل هنرمند و حذف تکرار اطلاعات آثار و ارتباط مالکیت از صفحه عمومی.
* اصلاح ارسال، ذخیره و بازگشت امن فرم‌های اصلی مدیریت.
* بهبود تب‌ها و انتخاب رسانه در ویرایشگر گالری و نمایشگاه.
* بهبود ظاهر فرم‌ها و واکنش‌گرایی پروفایل هنرمند.

= 2.19.0 =
* تکمیل صفحات عمومی هنرمند، گالری، اثر و نمایشگاه در ماژول art-coa: نام لاتین، سال تولد/تأسیس، شهر، رسانه، تحصیلات، جوایز، بیانیهٔ هنری، ملیت، ساعات کاری، اطلاعات مراجعه، موقعیت نقشه، شبکه‌های اجتماعی، ادیشن، مواد، وضعیت فیزیکی، پیشینه، افتتاحیه و شرایط ورود.
* همگام‌سازی یک‌طرفهٔ این فیلدها از متای پست‌تایپ‌ها به جدول‌های art_* (ستون‌های جدید با مهاجرت افزایشی و بدون حذف داده)؛ ویرایش همچنان روی پست انجام می‌شود.
* دادهٔ ساختاریافتهٔ Schema.org غنی‌تر: sameAs از شبکه‌های اجتماعی، geo، openingHours، birthDate، knowsAbout، award، alumniOf، material، artEdition.
* تست‌ها: ۵۶ بررسی؛ اصلاح stub تست‌ها.

= 2.18.0 =
* تبدیل خودکار اسلاگ‌های فارسی و عربی به فینگلیش خوانا برای برگه‌ها، نوشته‌ها و انواع محتوای افزونه.
* حفظ اسلاگ‌های لاتین سفارشی و بررسی یکتایی اسلاگ پس از تبدیل.
* استفاده از اسلاگ فینگلیش برای پروفایل‌های جدید بازار، حراج و کیوریتور؛ داده‌ها و نشانی‌های موجود دسته‌جمعی تغییر نمی‌کنند.
* تکمیل راهنمای مدیریت پیوندهای یکتا و ملاحظات سئو.

= 2.17.0 =
* کش کوتاه‌مدت برای نتیجهٔ فهرست‌ها و آمار پروفایل‌ها، با پاک‌سازی خودکار پس از تغییر محتوا.
* افزودن ایندکس‌های مرکب برای فیلترهای پرتکرار اثر، گواهی، نمایشگاه و کیوریتور.
* محدودسازی سبک‌تر درخواست‌های عمومی و بارگذاری تصویر؛ شمارش تلاش‌ها پیش از پردازش فایل انجام می‌شود.
* جلوگیری از نمایش پروفایل غیرفعال و آمار آثار/گواهی‌های غیرعمومی در صفحات عمومی.
* شمارش عمومی فقط داده‌های منتشرشده را نشان می‌دهد؛ آمار فروش و انتقال مالکیت در خروجی عمومی نمایش داده نمی‌شود.

= 2.16.0 =
* گسترش پروفایل کیوریتور با وابستگی سازمانی، سابقه، تحصیلات، دستاوردها، زبان‌ها، راه‌های تماس، شبکه‌های اجتماعی و تصویر کاور.
* افزودن فهرست عمومی قابل جست‌وجو و صفحهٔ اختصاصی کیوریتور با سوابق نمایشگاه‌های کیوریت‌شده و آمار همکاری.
* پیوند نام کیوریتور از صفحهٔ نمایشگاه به پروفایل عمومی و افزودن عنوان، توضیحات و دادهٔ ساختاریافتهٔ Person برای سئو.
* ارتقای افزایشی پایگاه داده بدون حذف اطلاعات قبلی.

= 2.15.0 =
* تکمیل اطلاعات حراج با شماره لات، برگزارکننده، محل، بازه برآورد، گزارش وضعیت اثر، پیشینه مالکیت، مستندات اصالت، بازدید و ارسال.
* بازطراحی صفحه لات برای نمایش مشخصات اثر، برآورد و گزارش‌های تکمیلی در کنار پیشنهادها و تاریخچه.
* افزودن جست‌وجو، مرتب‌سازی و صفحه‌بندی فهرست حراج‌ها؛ اطلاعات جدید در کارت‌ها و پنل مدیریت نیز نمایش داده می‌شوند.
* ارتقای بدون حذف دادهٔ قبلی ساختار جدول حراج‌ها برای ذخیرهٔ فیلدهای تکمیلی.

= 2.14.0 =
* افزودن نوع گالری (فیزیکی، آنلاین یا هر دو) به شناسنامه و پروفایل عمومی و امکان فیلترکردن فهرست گالری‌ها بر اساس آن.
* جداسازی پوستر نمایشگاه‌های برگزارشده در پروفایل گالری؛ نمایش از همان نمایشگاه‌های پیوندخورده با پوستر و تاریخ ثبت‌شده.
* افزودن بخش پوسترهای گذشته به تنظیمات نمایش پروفایل، ابزارک پروفایل المنتور و داده‌های ساختاریافته سئو.

= 2.13.0 =
* گسترش جست‌وجوی فهرست‌ها به فیلدهای تکمیلی گالری، هنرمند، اثر و نمایشگاه، با پشتیبانی از گونه‌های رایج حروف عربی/فارسی و ارقام فارسی/لاتین.
* افزودن فیلتر هنرمند برای نمایشگاه‌ها، جست‌وجوی آثار/نمایشگاه‌ها بر اساس هنرمند و گالری مرتبط، شهر برای آثار و اعمال واقعی تعداد ستون‌های انتخاب‌شده.
* کش کوتاه‌مدت گزینه‌های شهر، سبک، گالری، هنرمند و شناسه‌های جست‌وجو؛ باطل‌سازی کش پس از تغییر محتوای مرتبط.
* بهبود درخواست‌های AJAX فیلترها: لغو جست‌وجوی قدیمی، حفظ فیلترها در حالت جایگزین، اعلام وضعیت بارگذاری و پشتیبانی از عقب/جلو مرورگر.

= 2.12.0 =
* فعال‌سازی و آماده‌سازی پست‌تایپ‌های گالری، هنرمند، اثر و نمایشگاه برای ویرایش Elementor؛ به‌روزرسانی خودکار تنظیمات پشتیبانی هنگام ارتقا.
* افزودن فیلدهای عنوان سئو و توضیح متا برای هر صفحه، همراه با Open Graph و Twitter Card در نبود افزونه سئو.
* جلوگیری از تداخل خروجی متادیتا با Yoast SEO، Rank Math، AIOSEO، SEOPress و The SEO Framework.

= 2.11.0 =
* افزودن ویجت اختصاصی آمار پویای پلتفرم با انتخاب شاخص‌ها، عنوان و رنگ تأکیدی.
* کش ده‌دقیقه‌ای گزینه‌های گالری، هنرمند و اثر در کنترل‌های المنتور؛ پاک‌سازی کش هنگام ذخیره، حذف یا تغییر وضعیت محتوا.
* غیرفعال‌کردن دریافت تعداد کل و کش‌سازی داده‌های غیرضروری در پرس‌وجوی گزینه‌ها.

= 2.10.0 =
* نوار آمار پویا برای صفحهٔ هنرمند، گالری، اثر و نمایشگاه: آثار، نمایشگاه‌ها، هنرمندان همکار، گواهی‌ها و مدت برگزاری.
* شورت‌کد [artiey_stats] برای آمار کلی گالری‌ها، هنرمندان، آثار و نمایشگاه‌های جاری/پیش‌رو.
* کش کوتاه آمار باطل‌شونده پس از ذخیره محتوا، تغییر وضعیت انتشار، تغییر طبقه‌بندی یا رویدادهای گواهی؛ شمار نتیجهٔ فهرست‌ها همچنان از همان پرس‌وجوی واقعی می‌آید.

= 2.9.0 =
* پروفایل هنرمند: بیانیهٔ هنری و ملیت/خاستگاه فرهنگی، در فرم، صفحه عمومی و Schema.org.
* پروفایل گالری: اطلاعات مراجعه و دسترسی، در فرم، صفحه عمومی و دادهٔ ساختاریافته.
* پروفایل اثر: مواد، وضعیت فیزیکی و پیشینه/مالکیت، در فرم، صفحه عمومی و VisualArtwork.
* پروفایل نمایشگاه: کیوریتور و شرایط بازدید، در فرم، صفحه عمومی و دادهٔ رویداد.
* همگام‌سازی محتوای افزونه اصلی با ستون‌های تازهٔ جدول‌های art_*؛ مهاجرت افزایشی و بدون حذف داده با dbDelta هنگام ارتقای نسخه.

= 2.8.0 =
* موتور واحد گواهی: COA Pro تنها مرجع صدور، امضا، ابطال و انتقال است. art_certificates فقط شاخص مشتق‌شده است (CertificateMirror) و هیچ‌چیز از آن امضا یا تأیید نمی‌شود.
* حذف: صدور/ابطال گواهی Art COA، کلاس Hmac و رمز HMAC، فرم صدور دوم، مسیر تأیید دوگانه و QR ابری (api.qrserver.com)؛ QR حالا محلی و بدون درخواست بیرونی است.
* مدیریت: صفحهٔ «گواهی‌ها» فهرست COA Pro را نشان می‌دهد و هر گواهی را به اثر هنری وصل می‌کند؛ رویدادهای ابطال و انتقال به‌صورت خودکار همگام می‌شوند.
* ساختار: art_certificates بدون الزام اثر و HMAC و با ستون coa_id (مهاجرت خودکار).
* تست: ۴۳ بررسی.

= 2.7.0 =
* یکپارچه‌سازی گواهی: فهرست گواهی‌های COA Pro کنار گواهی‌های Art COA در پیشخوان؛ کد Art COA در فرم تأیید COA Pro شناسایی و به صفحهٔ تأیید هدایت می‌شود؛ لینک COA Pro در منوی پلتفرم. امضای هیچ گواهی‌ای تغییر نکرده است.
* تست: ۸ بررسی تازه برای تأیید یکپارچه (امضا، دفتر ثبت، ابطال، تنظیمات حریم خصوصی).

= 2.6.0 =
* ساختار: صفحه‌های مدیریت art-coa به trait جداگانه تقسیم شد (class-menus.php کوچک است).
* داده: ContentSync محتوای پست‌تایپ‌ها (هنرمند، گالری، اثر، نمایشگاه) را یک‌طرفه و بدون حذف به جدول‌های یکپارچه می‌نگارد؛ گواهی‌ها دست‌نخورده‌اند.
* تست: tests/run.php (بدون وابستگی) با ۳۲ بررسی برای گواهی، HMAC، امنیت، کاتالوگ، مزایده و تبدیل قیمت ووکامرس.

= 2.5.0 =
* سیستم طراحی مشترک (ui-system.css): توکن‌های رنگ/فاصله/شکل با حالت تیره، حلقهٔ فوکوس، هدف لمسی ۴۴px، احترام به کاهش حرکت، و ابزارک‌های مشترک؛ برای هر سه ماژول.
* حذف حدود ۱۲۵ استایل درون‌خطی (به کلاس‌های CSS)؛ موارد پویا (متغیرهای رنگ) و سندهای چاپ مستقل دست‌نخورده ماندند.
* دسترس‌پذیری: کمبوباکس جستجوی زنده با کیبورد، تله فوکوس و برچسب در لایت‌باکس، aria-busy و انتقال فوکوس در «بارگذاری بیشتر»، اعلام نتیجهٔ تأیید گواهی، برچسب فیلدها.

= 2.4.1 =
* امنیت: ثبت رویدادها (Audit) دیگر به هدرهای قابل جعل X-Forwarded-For اعتماد نمی‌کند.

= 2.4.0 =
* پل ووکامرس: اثر قابل‌فروش به محصول یکتا (موجودی ۱، فروش تکی) تبدیل می‌شود؛ پرداخت سفارش اثر را «فروخته‌شده» می‌کند و لغو/بازگشت وجه آن را دوباره به فروش می‌گذارد؛ دکمهٔ خرید در صفحهٔ اثر؛ تبدیل خودکار ریال/تومان؛ سازگاری با HPOS و بلوک‌های سبد/پرداخت.
* دکان (Dokan): اگر هنرمند فروشندهٔ دکان (نقش seller) باشد، محصول به نام او ثبت می‌شود.

= 2.3.4 =
* بستن حراج منقضی‌شده فقط در صورت فعال‌بودن (جلوگیری از رقابت بین درخواست‌ها)، پاک‌سازی کار زمان‌بندی‌شدهٔ حراج هنگام حذف افزونه، و محدودیت/پاک‌سازی ورودی مبلغ و اطلاعات پیشنهاددهنده.

= 2.3.3 =
* اصلاح ثبت هم‌زمان پیشنهاد حراج: خواندن ردیف با قفل تراکنشی انجام می‌شود تا پیشنهادهای هم‌زمان از حداقل قیمت عبور نکنند؛ ثبت پیشنهاد و به‌روزرسانی حراج اتمی شد.
* اجرای مطمئن ارتقای جدول پیش از بررسی انقضا، برای جلوگیری از خطای جدول ناموجود در نخستین درخواست عمومی پس از ارتقا.

= 2.3.2 =
* کاهش بار پایگاه‌داده: بررسی انقضای حراج‌ها از هر درخواست سایت به کار زمان‌بندی‌شدهٔ پنج‌دقیقه‌ای منتقل شد؛ صفحات حراج همچنان هنگام نمایش وضعیت را به‌روز می‌کنند.
* افزودن ایندکس ترکیبی وضعیت و زمان پایان برای یافتن سریع حراج‌های منقضی‌شده.

= 2.3.1 =
* Unified art-coa module: image uploads (media picker + front-end drag&drop REST upload) for artworks, exhibition posters, galleries, artists, curators, collectors; photo galleries; artwork pages.
* Dynamic catalogs (server-rendered first page + REST live filter/search/load-more), edit forms in admin, member panel to submit artworks/exhibitions (moderated).
* Performance: schema migrations gated by DB version (no dbDelta/DESCRIBE per request), catalog images primed in one query, filter facets cached, conditional assets, deferred JS.
* SEO: artwork VisualArtwork schema, sitemap + canonical for artworks, noindex for filtered URLs.
* Security: REST rate limits (search/catalog/verify), certificate verify no longer exposes HMAC/ULID, wp_unslash on input, attachment ownership checks, public listings restricted to approved items.

= 2.2.0 =
* اصلاح لینک نامک‌دار فراخوان‌ها و پاسخ ۴۰۴ برای فراخوان ناموجود؛ افزودن فیلتر اوپن‌استودیو و اصلاح فیلترهای نوع/وضعیت.
* ذخیره و نمایش ساعت مهلت و رویداد با منطقهٔ زمانی تنظیم‌شده در وردپرس؛ بستن مهلت‌های منقضی‌شده با کار زمان‌بندی‌شده، نه هنگام رندر هر صفحه.
* افزودن عنوان صفحه و دادهٔ ساختاریافتهٔ Event برای رویدادهای دارای تاریخ.
* یک‌بار ارتقای جدول‌های فراخوان و هم‌ترازکردن تاریخ‌های قدیمی با منطقهٔ زمانی جاری سایت.
* جلوگیری از پاک‌شدن یا کوتاه‌شدن تاریخ نمایشگاه در صورت ورود بازهٔ نامعتبر؛ تاریخ‌های قبلی حفظ می‌شوند.

= 2.1.0 =
* یکپارچه‌سازی ماژول WP Art COA Pro 1.0.0 در افزونه اصلی، همراه با حفظ جداول، داده‌ها و کلیدهای امضای قبلی.
* انتقال مدیریت Art COA به زیرمنوی «پلتفرم آرتی» و بارگذاری پویا و محدود CSS/JS.
* غیرفعال‌سازی نسخه مستقل WP Art COA Pro هنگام فعال‌سازی نسخه یکپارچه؛ داده‌ها حذف نمی‌شوند.
* حداقل نسخه PHP به ۸٫۱ تغییر کرد.

= 2.0.1 =
* کاهش بار فیلتر «اکنون باز است» با کش کوتاه‌مدت و حذف محاسبه شمارش کل نتایج در اسکن داخلی.

= 2.0.0 =
* ادغام Artiey Exhibition Timeline 1.3.0 و WP COA Pro 1.11.0 در یک افزونه
* نوع محتوای جدید «آثار هنری» و فهرست فیلترپذیر آثار
* پل اتصال گواهی: اتصال گالری/هنرمند به پروفایل‌های COA، صدور گواهی از روی اثر، نمایش وضعیت گواهی
* پیشخوان یکپارچه «پلتفرم آرتی» با آمار و بررسی سلامت سیستم
* حفاظت در برابر فعال‌بودن هم‌زمان افزونه‌های قدیمی

= 3.2.0 =
* Certificate of authenticity connected everywhere: one verified (signature-checked) certificate service for artwork posts and catalogue rows, admin link/unlink by code, certificate panel on artwork, module artwork and auction pages, badges on artwork, shop and auction cards, certificate card widget for the current artwork, certificate data in JSON-LD.
* Artwork page/widget: certificate and market (buy, live auction, inquiry) sections, accessible lightbox, gallery fallback for related works.
* Artist page: certified-artworks section; no database writes on public views; tab layout no longer drops sections.
* Shop: linked cards, sorting, certified-only filter, pagination, protected inquiry form; widget follows the artist/gallery of the page.
* Auction: fixed visitor search, scheduled lots, localized statuses, SEO meta and Product/Offer schema.

= 3.1.0 =
* Follow / followers for artists, galleries, curators and public collectors: live AJAX buttons that stay correct behind page caches, followers and following panels (privacy-safe), new Elementor widget and [artiey_follow] / [artiey_followers] shortcodes.
* Professional gallery profile: statement, critics, awards, team, press, followers and open-call sections with a repeatable-rows admin editor, new Elementor controls and richer ArtGallery JSON-LD.
* Curators: Media Library images with srcset, "current curator" and "gallery partners" widget modes, stats and follow buttons.
* Open calls: canonical slug URLs, SEO meta + Event schema, filters/search/pagination, live countdown, spam protection, duplicate-application guard, full admin editing, application statuses and CSV export, new Elementor widget.
* Fixed: draft galleries visible through the widget, list layout of calls, double-unslashed call data, duplicated iframe attribute, inactive artists on gallery pages, plain-text emails on public profiles.

= 3.0.5 =
* Fixed legacy-plugin detection for all bundled replacements: Artiey Exhibition Timeline, WP COA Pro, and WP Art COA Pro are now checked in both site-active and network-active plugin lists.
* Prevented bundled modules from loading during a request in which a legacy replacement is still active, avoiding class redeclaration when WordPress loads the legacy plugin later in its plugin order.
* Scheduled safe deactivation of legacy copies for both site-level and Multisite network-level activation; existing plugin data and database tables are preserved.
* Added an isolated regression test for legacy-plugin load-order and Multisite deactivation behavior.

= 3.0.4 =
* Fixed stale statistics and relationship counts after entity metadata changes and deletions.
* Added targeted full-page cache purges for edited, deleted, and status-changed platform entities and pages.
* Preserved targeted cache invalidation; no full-domain cache purge is triggered on every save.
