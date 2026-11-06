=== WP COA Pro ===
Contributors: artiey
Tags: certificate, artwork, authenticity, gallery, provenance
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 1.11.0
License: GPLv2 or later

## WP COA Pro 1.11.0 — Performance improvements

- Narrower queries for public profile and gallery directories; shorter projections for the profile-management list.
- Ten-minute caching of signature-verified linked artwork, with invalidation after issuance, revocation, and ownership transfer.
- Cached gallery lists and public counts with explicit cache-generation invalidation.
- Responsive media-library images for profile portraits/covers when attachment IDs are known.
- Conditional COA theme settings and inline CSS, and reduced shortcode/Elementor content scanning on unrelated requests.
- Live site benchmarks are not included; validate TTFB, query count, and Core Web Vitals in staging.

## WP COA Pro 1.10.0 — Artwork image, unique code, and QR verification

- The certificate issuance screen can select or upload the original artwork image using the WordPress Media Library; raster images up to 25 MB are accepted (SVG is rejected).
- Each new certificate gets a cryptographically random human-readable code in the `COA-XXXXXXXX-XXXXXXXXXXXX` format; the database unique constraint prevents duplicate stored codes.
- The printed certificate includes the artwork image, unique code, and locally generated QR Code. The QR points to that certificate's public verification route.
- Signature schema v6 includes the attachment ID and SHA-256 digest of the original file. Removing or replacing the original file causes integrity verification to fail for v6 certificates. Legacy signatures remain supported.
- QR generation is performed locally in the browser and does not call an external QR service.
- PHP/JS syntax, ZIP integrity, and an offline QR decode sample were checked locally. Live WordPress, database migration, and Elementor staging tests are still required.

## WP COA Pro 1.9.0 — Technical SEO and Performance


- Dynamic SEO titles and descriptions for published artist/collector profiles, with per-profile overrides.
- Clean single-profile URLs under the managed profile page when WordPress pretty permalinks are enabled; legacy query URLs remain supported.
- Canonical, Open Graph/Twitter metadata and schema.org ProfilePage JSON-LD; filters integrate with Yoast SEO, Rank Math, SEOPress, and AIOSEO.
- Dedicated paginated XML sitemap at `/coa-pro-sitemap.xml`, also referenced in public robots.txt output.
- Missing/unpublished profile slugs return 404/noindex; public contact privacy controls remain active.
- Short-lived caches for public profile queries and counts; database indexes for listing and active-work lookups.
- Public component CSS is conditionally enqueued on pages that contain WP COA Pro components.

- Professional bilingual profiles for artists, individual collectors, art collections, foundations, and corporate collections.
- Profile fields include biographies, image and cover URLs/media-library selection, nationality, location, years, artistic disciplines, collection focus, education, awards, exhibitions, website, portfolio, and social links.
- Public profile directory and details shortcodes; Elementor directory and profile-detail widgets.
- Privacy controls for contact details, public visibility states, featured and administrator-verified badges.
- Certificate-to-profile links are included in signature schema v5, while legacy signatures v1–v4 remain verifiable.
- Database migration creates the profile table and adds relationship IDs without deleting existing certificates.


سامانه ثبت، مدیریت و استعلام گواهی اصالت آثار هنری؛ با پشتیبانی فارسی و انگلیسی، پروفایل اختصاصی مؤسسات، تاریخچه رویدادها و ابزارک‌های حرفه‌ای المنتور.

== معرفی ==

WP COA Pro برای ثبت گواهی اصالت آثار هنری، نگهداری اطلاعات اثر و هنرمند، مشخص‌کردن مؤسسه صادرکننده، کنترل وضعیت گواهی و نمایش نتیجه استعلام عمومی طراحی شده است. زبان اصلی نمایش در نسخه ۱.۷.۰ فارسی است؛ اطلاعات فارسی و انگلیسی هر اثر و هر مؤسسه جداگانه ذخیره می‌شود و مدیر سایت می‌تواند نمایش فارسی، انگلیسی یا دوزبانه را انتخاب کند.

توجه: بررسی دیجیتال و امضای اطلاعات، به‌تنهایی اصالت فیزیکی اثر، صحت انتساب اثر به هنرمند یا مالکیت قانونی را اثبات نمی‌کند.

== امکانات ==

* صدور گواهی با شناسه تصادفی یکتا و شماره قابل استعلام.
* ثبت عنوان اثر، نام هنرمند، مواد و تکنیک، سال خلق، ابعاد، شماره اموال و توضیحات عمومی به فارسی و انگلیسی.
* تفکیک اطلاعات هنرمند، مجموعه‌دار، مالک فعلی، مالک پیشین و مؤسسه صادرکننده.
* تنظیمات هویت بصری مؤسسه شامل نام، لوگو، رنگ‌ها، عنوان گواهی و پاورقی.
* پروفایل مستقل برای گالری‌ها، موزه‌ها، آموزشگاه‌ها، حراجی‌ها، آتلیه‌ها، مجموعه‌داران، مراکز مرمت و سایر مؤسسات هنری.
* چهار قالب چاپی گواهی: کلاسیک موزه‌ای، مدرن، مینیمال و آرشیوی.
* امضای فراداده با HMAC-SHA256 و بررسی تغییرات داده‌های امضاشده.
* ثبت رویدادهای صدور، ابطال و انتقال مالکیت، همراه با زنجیره هش برای آشکارسازی دست‌کاری‌های رایج.
* فرم استعلام عمومی با شورت‌کد و مسیر REST فقط‌خواندنی.
* خروجی CSV از اطلاعات عمومی با کاهش خطر تزریق فرمول در صفحه‌گسترده.
* پیشخوان مدیریتی، سوابق رویدادها و ابزارهای ایجاد برگه‌های وردپرس.
* پنج ابزارک اختصاصی Elementor برای استعلام، فهرست گالری‌ها، آمار عمومی، کارت پروفایل گالری و راهنمای استعلام.
* تنظیمات واکنش‌گرا برای ستون‌ها، رنگ‌ها، فاصله داخلی، گوشه‌ها و نمایش فیلدها.
* نمایش اطلاعات مجموعه‌دار و مالکان در صفحات عمومی به‌صورت غیرفعال، مگر آنکه مدیر صریحاً نمایش آن‌ها را فعال کند.

== نصب ==

۱. پیش از نصب یا ارتقا، از فایل‌های وردپرس و پایگاه داده نسخه پشتیبان تهیه کنید.
۲. فایل ZIP را از مسیر «افزونه‌ها ← افزودن افزونه ← بارگذاری افزونه» بارگذاری کنید.
۳. افزونه را فعال کنید و از منوی «گواهی‌های اصالت» تنظیمات را باز کنید.
۴. در «تنظیمات»، نام مؤسسه، زبان پیش‌فرض، رنگ‌ها و اطلاعات عمومی را بررسی کنید.
۵. در «برگه‌ها و المنتور»، برگه‌های پیشنهادی را ایجاد کنید. برگه‌های موجود توسط افزونه بازنویسی نمی‌شوند.
۶. ابتدا صدور، استعلام، ابطال، تاریخچه و نمایش موبایل را در سایت آزمایشی بررسی کنید.

== زبان فارسی و انگلیسی ==

* زبان پیش‌فرض نسخه ۱.۷.۰ فارسی است.
* در تنظیمات عمومی و ابزارک‌های Elementor می‌توانید «فارسی»، «انگلیسی»، «فارسی و انگلیسی» یا «زبان سایت» را انتخاب کنید.
* برای فرم استعلام: `[coa_verify language="fa"]` یا `[coa_verify language="en"]` یا `[coa_verify language="both"]`.
* برای فهرست گالری‌ها: `[coa_gallery_directory columns="3" show_filters="1" language="fa"]`.
* برای آمار عمومی: `[coa_public_stats show_total="1" show_active="1" show_galleries="1" language="fa"]`.
* برای کارت یک گالری: `[coa_gallery_profile id="1" language="both"]` یا با نامک پروفایل.
* برای راهنمای استعلام: `[coa_certificate_guide language="fa"]`.
* در حالت «زبان سایت»، زبان با توجه به زبان محلی وردپرس تعیین می‌شود. در سایت فارسی، نمایش راست‌چین و در زبان انگلیسی، نمایش چپ‌چین خواهد بود.

== ابزارک‌های Elementor ==

پس از فعال‌بودن Elementor، ابزارک‌ها در دسته «WP COA Pro» نمایش داده می‌شوند:

* استعلام گواهی اصالت
* فهرست گالری‌ها و مؤسسات
* آمار عمومی سامانه
* کارت پروفایل گالری
* راهنمای اعتبارسنجی گواهی

هر ابزارک تنظیمات محتوا و استایل مربوط به خود را دارد. وجود این ابزارک‌ها تضمین‌کننده کارکرد کامل در تمام پوسته‌ها و نسخه‌های Elementor نیست؛ لطفاً ابتدا در یک صفحه آزمایشی بررسی کنید.

== امنیت و محدودیت‌ها ==

* برای عملیات مدیریتی از مجوزها، nonce، پاک‌سازی ورودی و گریز متناسب خروجی استفاده می‌شود.
* امضای دیجیتال تغییر داده‌های امضاشده را آشکار می‌کند؛ اما به‌تنهایی گواهی کارشناسی اصالت فیزیکی نیست.
* تاریخچه رویدادها زنجیره هشِ آشکارکننده دست‌کاری دارد، ولی دفترکل تغییرناپذیر یا بلاک‌چین نیست.
* چاپ گواهی از مرورگر انجام می‌شود؛ QR Code برای صفحه استعلام در نسخه 1.10.0 اضافه شده است، اما تولید PDF سمت سرور هنوز موجود نیست.
* اتصال ووکامرس، پرداخت، صدور توکن، ثبت روی بلاک‌چین و فرآیندهای خودکار KYC/AML در این نسخه پیاده‌سازی نشده‌اند.
* پیش از صدور گواهی واقعی، برای امنیت بیشتر یک کلید ثابت و تصادفی با حداقل ۳۲ نویسه در `wp-config.php` تعریف کنید:

`define('WP_COA_PRO_SIGNING_KEY', 'کلید-تصادفی-منحصر-به-فرد-خود-را-قرار-دهید');`

از همین مقدار نمونه به‌عنوان کلید واقعی استفاده نکنید. کلید را محرمانه نگه دارید و پس از صدور گواهی آن را تغییر ندهید؛ تغییر یا گم‌شدن کلید ممکن است بررسی امضاهای قبلی را ناممکن کند.

== تغییرات نسخه ==

= 1.10.0 =
* افزودن انتخاب یا بارگذاری تصویر اصل اثر از کتابخانه رسانه وردپرس، با کنترل نوع فایل و سقف ۲۵ مگابایت.
* افزودن شناسه یکتای گواهی با قالب COA و نمایش آن پس از صدور.
* تولید محلی QR Code برای صفحه استعلام عمومی و نمایش آن در گواهی چاپی و نتیجه عمومی.
* افزودن شناسه پیوست و هش SHA-256 فایل اصلی به payload امضاشده نسخه ۶؛ حفظ اعتبارسنجی امضاهای نسخه‌های پیشین.
* افزودن مسیر استعلام اختصاصی و پاسخ 404 برای کدهای ناشناخته در مسیر QR.
* بررسی نحو PHP و JavaScript، بسته ZIP و رمزگشایی نمونه QR در محیط محلی؛ آزمون نصب زنده هنوز لازم است.

= 1.9.0 =
* افزودن عنوان و توضیحات سئوی اختصاصی و پویا برای پروفایل هنرمندان و مجموعه‌داران.
* افزودن URL تمیز برای صفحات پروفایل در صورت فعال‌بودن پیوندهای یکتا و حفظ مسیرهای قدیمی مبتنی بر query.
* افزودن canonical، Open Graph، Twitter Cards و داده ساختاریافته JSON-LD از نوع ProfilePage.
* یکپارچه‌سازی فیلترهای سئو با Yoast SEO، Rank Math، SEOPress و AIOSEO.
* افزودن نقشه سایت XML صفحه‌بندی‌شده برای پروفایل‌های منتشرشده و اعلام آن در robots.txt.
* پاسخ 404 و noindex برای نامک پروفایل ناموجود یا منتشرنشده.
* افزودن کش کوتاه‌مدت برای فهرست‌ها و تعداد پروفایل‌ها، شاخص‌های ترکیبی دیتابیس و بارگذاری شرطی CSS عمومی.
* نسخه دیتابیس به ۶ ارتقا یافت؛ مهاجرت با dbDelta انجام می‌شود و رکوردهای گواهی حذف نمی‌شوند.
* آزمون نحوی PHP در محیط محلی موفق است؛ آزمون واقعی وردپرس و Elementor باید روی staging انجام شود.

= 1.8.0 =
* افزودن پروفایل دوزبانه هنرمندان و کلکتورها/مجموعه‌های هنری همراه با زندگی‌نامه، تصاویر، سوابق، پیوندها و کنترل حریم خصوصی.
* افزودن فهرست عمومی و نمایه پویا و ابزارک‌های اختصاصی Elementor.
* پیوند شناسه‌های هنرمند و کلکتور به گواهی با حفظ سازگاری امضاهای نسخه‌های قبلی.

= 1.7.1 =
* متن پیش‌فرض عمومی فارسی است، حتی اگر زبان پیشخوان وردپرس انگلیسی باشد.
* عنوان برگه‌های پیشنهادی و تیتر اولیه ابزارک‌های عمومی Elementor فارسی است.
* ترجمه فارسی عنوان استعلام اصلاح شد؛ فیلدهای متن انگلیسی و انتخاب زبان حفظ شده‌اند.
* ساختار پایگاه داده تغییر نکرده و نیازی به مهاجرت دیتابیس نیست.

= 1.7.0 =
* تغییر زبان پیش‌فرض به فارسی و حفظ امکان نمایش انگلیسی یا دوزبانه.
* تکمیل ترجمه فارسی برچسب‌ها، ابزارک‌ها، برگه‌ها و پیام‌های اجرایی.
* فارسی‌سازی محتوای برگه‌های پیشنهادی جدید؛ برگه‌های موجود و ویرایش‌های مدیر بازنویسی نمی‌شوند.
* بهبود خوانایی راست‌چین، تمرکز صفحه‌کلید، پشتیبانی از کاهش حرکت و حالت کنتراست بالا.
* مهاجرت یک‌باره مقدار پیش‌فرض زبان از حالت دوزبانه نسخه ۱.۶ به فارسی. مدیر می‌تواند پس از ارتقا زبان را از تنظیمات تغییر دهد.

= 1.6.0 =
* افزودن ابزارهای ایجاد برگه وردپرس، فهرست گالری‌ها، آمار عمومی و ابزارک‌های اختصاصی Elementor.

== یادداشت آزمون ==

آزمون نحو PHP، صحت فایل‌های ترجمه و ساختار ZIP در محیط توسعه انجام می‌شود. این آزمون‌ها جایگزین نصب و بررسی انتها‌به‌انتها روی نسخه واقعی وردپرس، پایگاه داده، پوسته و Elementor نیستند. پیش از استفاده عملیاتی، روی سایت آزمایشی بررسی کنید.


== 1.8.0 ==
* Added bilingual artist and collector/collection profiles with biography, practice, education, exhibitions, awards, images, web links, social links and privacy controls.
* Added public artist and collector directories and dynamic profile-detail shortcode.
* Added Elementor directory and profile detail widgets.
* Added signed relationships from certificates to artist and collector profile IDs using signature schema v5.
* Preserved verification of legacy signature schema v1–v4.
* Added Pages & Elementor recommendations for artist directory, collector directory and profile detail pages.
