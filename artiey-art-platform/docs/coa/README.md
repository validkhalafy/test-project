# WP COA Pro — راهنمای فارسی

**نسخه 1.11.0 | بهینه‌سازی سرعت و مصرف منابع**


## بهینه‌سازی عملکرد (نسخه 1.11.0)

- فهرست هنرمندان، مجموعه‌داران و گالری‌ها با کوئری‌های محدودتر و کش ۱۰ دقیقه‌ای ارائه می‌شود.
- آثار فعال متصل به یک پروفایل پس از بررسی امضا، کش می‌شوند؛ صدور گواهی، ابطال یا انتقال مالکیت این کش را باطل می‌کند.
- فهرست مدیریت پروفایل‌ها دیگر متن کامل فیلدهای بلند را برای هر ردیف دریافت نمی‌کند.
- تصویر پروفایل و کاور که از کتابخانه رسانه انتخاب شود، شناسه پیوست را ذخیره می‌کند و برای مرورگر از اندازه‌های responsive وردپرس استفاده می‌کند. برای نگاشت پروفایل‌های قدیمی، آن‌ها را ذخیره مجدد کنید.
- CSS درون‌خطی و خواندن تنظیمات برند فقط هنگام استفاده از مؤلفه‌های COA انجام می‌شود؛ تشخیص شورت‌کدها و بررسی JSON المنتور هم محدود شده است.

این تغییرات برای کاهش کار تکراری PHP، پایگاه داده و حجم تصاویر طراحی شده‌اند؛ نتیجه واقعی باید در محیط staging با Query Monitor، Lighthouse یا PageSpeed اندازه‌گیری شود. این بسته در سایت زنده نصب یا benchmark نشده است.

## صدور گواهی با تصویر اصل اثر، شناسه یکتا و QR Code (نسخه 1.10.0)

- در فرم «صدور گواهی»، تصویر اثر را از کتابخانه رسانه وردپرس انتخاب کنید یا در همان پنجره بارگذاری کنید. تصویر اختیاری است؛ فایل باید یک تصویر از فرمت‌های JPEG، PNG، WebP، GIF و در نسخه‌های سازگار WordPress، AVIF باشد و حداکثر ۲۵ مگابایت حجم داشته باشد. SVG پذیرفته نمی‌شود.
- هنگام صدور، کد تصادفی یکتا با قالب `COA-XXXXXXXX-XXXXXXXXXXXX` ساخته می‌شود. ستون دیتابیس دارای قید UNIQUE است تا کد تکراری ذخیره نشود. کد پس از صدور در اعلان موفقیت و نسخه چاپی نمایش داده می‌شود.
- افزونه QR Code را با کد محلی JavaScript و بدون سرویس تولید QR شخص ثالث یا درخواست خارجی می‌سازد. QR به صفحه استعلام همین گواهی اشاره دارد؛ مسیر `/coa-verify/CODE/` با پیوندهای یکتا و مسیر query در حالت خاموش‌بودن پیوندهای یکتا کار می‌کند.
- نسخه چاپی گواهی شامل تصویر اثر، کد یکتا و QR است. صفحه استعلام عمومی نیز QR را برای بازگشت به همان رکورد نشان می‌دهد. نمایش تصویر در نتیجه عمومی تابع دو سطح تنظیمات عمومی سایت و پروفایل صادرکننده است.
- هنگام صدور، شناسه پیوست و هش SHA-256 فایل اصلی در payload امضاشده HMAC نسخه ۶ ذخیره می‌شوند. در نتیجه، حذف فایل یا جایگزینی/تغییر محتوای آن باعث رد اعتبار یکپارچگی گواهی‌های نسخه ۶ می‌شود. تغییر نام فایل یا تولید بندانگشتی وردپرس به‌خودی‌خود هش فایل اصلی را عوض نمی‌کند.
- برای رکوردهای قدیمی، فیلد تصویر خالی می‌ماند و سازوکار بررسی امضاهای قبلی حفظ می‌شود؛ ارتقا، کد یا تصویر به رکوردهای قدیمی تزریق نمی‌کند.

**محدودیت آزمون:** نحو PHP و JavaScript، ساختار ZIP و رمزگشایی محلی نمونه QR بررسی شده‌اند؛ نصب و مهاجرت انتها‌به‌انتها روی سایت واقعی وردپرس/Elementor انجام نشده است. مسیر تمیز QR به قوانین بازنویسی وردپرس وابسته است؛ پس از ارتقا، یک بار به «تنظیمات ← پیوندهای یکتا» برو و «ذخیره تغییرات» را بزن اگر مسیر استعلام 404 می‌دهد.

## بهبودهای سئو و سرعت (نسخه 1.9.0)

- برای پروفایل منتشرشده، عنوان و توضیحات سئو به‌صورت خودکار از نام، معرفی کوتاه و زندگی‌نامه تولید می‌شود؛ مدیر می‌تواند عنوان و توضیحات اختصاصی را در فرم پروفایل تعیین کند.
- آدرس canonical پروفایل هنرمند در پلتفرم 2.32.12 به `/artists/{slug}/` منتقل شده است. مسیر `/art-person-profile/{slug}/` فقط alias قدیمی است و برای پروفایل هنرمند منتشرشده به نشانی canonical ریدایرکت می‌شود. برای مجموعه‌داران و انواع دیگر پروفایل، مسیر صفحهٔ مدیریت‌شده و URL مبتنی بر query در حالت خاموش‌بودن پیوند یکتا حفظ می‌شود.
- عنوان، توضیحات، canonical و Open Graph/Twitter با فیلترهای شناخته‌شده Yoast SEO، Rank Math، SEOPress و AIOSEO یکپارچه می‌شوند. متادیتای جایگزین افزونه فقط هنگامی تولید می‌شود که افزونه سئوی شناخته‌شده فعال نباشد.
- داده ساختاریافته JSON-LD از نوع `ProfilePage` با موجودیت `Person` یا `Organization` و مسیر راهنما تولید می‌شود.
- نقشه سایت اختصاصی پروفایل‌های منتشرشده در `/coa-pro-sitemap.xml` ارائه می‌شود؛ نشانی آن نیز به خروجی عمومی `robots.txt` افزوده می‌شود.
- نامک ناموجود یا پروفایل منتشرنشده در درخواست پروفایل به پاسخ 404 و `noindex` منتهی می‌شود.
- کش کوتاه‌مدت برای فهرست‌ها، جست‌وجوها، تعداد پروفایل‌ها و بازیابی نامک اضافه شده است؛ ذخیره پروفایل نسل کش را تغییر می‌دهد.
- CSS اجزای افزونه فقط در صفحاتی بارگذاری می‌شود که شورت‌کد یا ویجت COA دارند؛ ایندکس‌های دیتابیس برای فهرست پروفایل و آثار متصل اضافه شده‌اند.

**فایل‌های سئو و کش:** این قابلیت‌ها به سئوی فنی کمک می‌کنند، اما رتبه‌بندی یا امتیاز Core Web Vitals را تضمین نمی‌کنند. آزمون نصب واقعی و اندازه‌گیری Lighthouse/PageSpeed باید روی سایت هدف انجام شود. برای URLهای تمیز، پیوندهای یکتا در تنظیمات وردپرس باید فعال باشند و برگه‌های پیشنهادی افزونه ایجاد شده باشند.

## پروفایل حرفه‌ای هنرمندان و کلکتورها (نسخه 1.8.0)

از مسیر **گواهی‌های اصالت ← هنرمندان و کلکتورها** می‌توان برای هنرمند، مجموعه‌دار، مجموعه هنری، بنیاد یا مجموعه سازمانی شناسنامه مستقل ایجاد کرد. پروفایل‌ها فارسی و انگلیسی هستند و شامل نام و نامک، عنوان کوتاه، زندگی‌نامه، تصویر پروفایل و کاور از کتابخانه رسانه وردپرس، سال تولد/درگذشت یا تأسیس، ملیت، شهر و کشور، رشته‌ها و رسانه‌ها، تحصیلات، جوایز، نمایشگاه‌ها، حوزه تمرکز مجموعه، وب‌سایت، پورتفولیو، شبکه‌های اجتماعی و راه‌های تماس با کنترل نمایش عمومی می‌شوند.

- وضعیت پیش‌نویس، خصوصی یا منتشرشده و نشان ویژه/تأیید مدیر.
- فیلدهای ایمیل و تلفن به‌طور پیش‌فرض خصوصی‌اند؛ هر کدام باید جداگانه برای نمایش عمومی فعال شوند.
- هنگام صدور گواهی می‌توان یک پروفایل منتشرشده هنرمند و یک پروفایل منتشرشده کلکتور/مجموعه را متصل کرد. شناسه این ارتباط در امضای HMAC نسخه 5 قرار می‌گیرد؛ نام درج‌شده روی گواهی نیز در زمان صدور ثبت می‌شود.
- صفحه عمومی فقط پروفایل‌های منتشرشده را نمایش می‌دهد. فهرست آثار پیوسته فقط گواهی‌های فعال با امضای معتبر را نشان می‌دهد.
- برای پیوندهای مستقل و پویا، از صفحه «نمای پروفایل» ایجادشده در بخش «برگه‌ها و المنتور» استفاده کنید. در صورت ناموجود بودن برگه، لینک مستقل پروفایل به صفحه اصلی سایت برمی‌گردد تا مدیر برگه پیشنهادی را ایجاد کند.

### شورت‌کدهای پروفایل

- فهرست هنرمندان: `[coa_artist_directory columns="3" language="both"]`
- فهرست کلکتورها و مجموعه‌ها: `[coa_collector_directory columns="3" language="both"]`
- فهرست قابل تنظیم: `[coa_profile_directory type="artist" featured_only="1" limit="24" language="fa"]`
- جزئیات بر اساس پارامترهای URL: `[coa_profile_view language="both"]`
- جزئیات یک پروفایل مشخص: `[coa_profile_view slug="artist-slug" language="both"]`

در Elementor، ویجت‌های «فهرست هنرمندان و کلکتورها» و «جزئیات پروفایل هنرمند یا کلکتور» به دسته WP COA Pro اضافه شده‌اند. پس از ارتقا ابتدا صفحه‌های پیشنهادی را از پیشخوان ایجاد کنید؛ برگه‌ها و محتوای سفارشی موجود بازنویسی نمی‌شوند.

## زبان و نمایش

زبان پیش‌فرض نمایش در این نسخه فارسی است. رابط مدیریتی در وردپرس فارسی با کاتالوگ `fa_IR` ترجمه می‌شود؛ در وردپرس انگلیسی، متن‌های رابط به زبان اصلی انگلیسی نمایش داده می‌شوند. داده‌های اثر و مؤسسه می‌توانند به‌صورت جداگانه به فارسی و انگلیسی ثبت شوند. در تنظیمات و ابزارک‌های Elementor انتخاب فارسی، انگلیسی، فارسی و انگلیسی یا زبان سایت وجود دارد.

## شروع سریع

1. از فایل‌های سایت و پایگاه داده پشتیبان بگیرید.
2. بسته ZIP را از مسیر «افزونه‌ها ← افزودن افزونه ← بارگذاری افزونه» نصب یا جایگزین کنید.
3. از منوی «گواهی‌های اصالت ← تنظیمات»، نام صادرکننده، زبان، رنگ‌ها و نمایش عمومی را تنظیم کنید.
4. از «گواهی‌های اصالت ← برگه‌ها و المنتور» برگه‌های پیشنهادی را ایجاد کنید. این عمل تنها با کلیک مدیر انجام می‌شود و برگه‌های قبلی را بازنویسی نمی‌کند.
5. برای فهرست گالری‌ها و مؤسسات از `[coa_gallery_directory columns="3" show_filters="1" language="fa"]` و برای آمار عمومی از `[coa_public_stats language="fa"]` استفاده کنید.

## شورت‌کدهای اصلی

- استعلام: `[coa_verify language="fa"]`
- نمایش دوزبانه فرم استعلام: `[coa_verify language="both"]`
- فهرست گالری‌ها: `[coa_gallery_directory language="fa"]`
- آمار عمومی: `[coa_public_stats language="fa"]`
- کارت پروفایل: `[coa_gallery_profile id="1" language="both"]`
- راهنمای استعلام: `[coa_certificate_guide language="fa"]`

## ابزارک‌های Elementor

در دسته «WP COA Pro» این ابزارک‌ها در دسترس‌اند: استعلام گواهی اصالت، فهرست گالری‌ها، آمار عمومی، کارت پروفایل گالری و راهنمای اعتبارسنجی. برای هر ابزارک می‌توان زبان و گزینه‌های ظاهری مربوط به آن را تنظیم کرد.

## ملاحظات امنیتی و دقت

امضای HMAC از تغییرات فراداده امضاشده در برابر دست‌کاری‌های معمول هشدار می‌دهد؛ اما اصالت فیزیکی اثر یا مالکیت قانونی را مستقلاً اثبات نمی‌کند. تاریخچه رویدادها زنجیره هشِ آشکارکننده دست‌کاری دارد، اما بلاک‌چین یا دفترکل تغییرناپذیر نیست. چاپ گواهی از مرورگر انجام می‌شود و QR Code محلی برای استعلام گواهی در نسخه 1.10.0 اضافه شده است؛ تولید PDF سمت سرور، ووکامرس و بلاک‌چین در این نسخه تکمیل نشده‌اند.

برای نصب عملیاتی، ابتدا روی محیط آزمایشی نصب کنید و صدور، استعلام، ابطال، سوابق رویدادها، نقش‌های کاربری و نمایش Elementor را آزمایش کنید.

---

# WP COA Pro 1.8.0

WP COA Pro is a foundational WordPress plugin for issuing and verifying artwork certificates of authenticity (COA). This build is a more structured MVP and is **not a recovered copy of the previously discussed v4.1.0 project**. Version 1.11.0 improves an optional original-art image attachment, signed SHA-256 evidence hash, unique verification code display, and locally generated QR links.

## Gallery & activity profiles (v1.5.0)

- Create separate institution profiles for galleries, museums, artist studios, auction houses, art fairs, art schools, collections, conservation studios, archives, digital-art registries, art advisors, and other issuers.
- Each profile controls its bilingual institution name, logo URL, primary/accent colors, certificate title, bilingual footer, public-field visibility, and one of four certificate templates (museum classic, modern, minimal, archive).
- Newly issued certificates store and HMAC-sign a snapshot of the selected profile. Updating a profile affects newly issued certificates; it does not silently restyle previously issued certificates or change their signed branding history.
- The certificate list supports filtering by issuer/gallery, activity type, and status. These filters compose with search and pagination. The CSV export includes issuer-profile and activity metadata.
- Public shortcode results, REST output field visibility, and printable certificates use the profile snapshot only when the v4 HMAC validates. If the signature fails, profile-specific branding is not trusted and the site-wide defaults are used while the integrity warning remains visible. Legacy certificates without a signed snapshot continue to use site-wide settings.
- Profiles are deactivated instead of hard-deleted so existing certificates keep their profile reference. The default profile cannot be deactivated.

### Upgrade note

On plugin load, the versioned database migration creates the gallery-profile table and adds profile/snapshot columns to the certificate table using WordPress `dbDelta`. Existing certificate rows are retained; old signature schema versions remain verifiable. Back up the database before upgrading production sites.

## Features implemented

- Certificate issuance with random, human-readable codes (`COA-XXXXXXXX-XXXXXXXXXXXX`).
- Certificate fields for artwork title, artist, medium/materials, year, dimensions, inventory/accession number, issuing institution, public description, and private administrator notes.
- Dedicated certificate and event-history database tables, with schema upgrades from the earlier 1.0.0 starter build.
- HMAC-SHA256 integrity signatures over a canonical snapshot of the certificate's public metadata.
- Verification recomputes the canonical metadata and HMAC; changing a signed public field causes an integrity failure.
- Backward verification support for legacy v1 and canonical v2/v3 signatures from earlier builds.
- Certificate revocation with a required reason, actor ID, timestamp, and recorded event.
- Per-certificate SHA-256 hash-chained event history plus an administrative chain-integrity check. This is tamper-evident application history, **not** cryptographically immutable storage against a database administrator who can rewrite the chain.
- Public shortcode: `[coa_verify]`.
- Read-only public REST endpoint: `/wp-json/wp-coa-pro/v1/certificates/{CODE}`.
- Printable certificate design that can be printed or saved as PDF through the browser print dialog.
- CSV export of public metadata, including Persian/primary and English columns for title, artist, medium, description, and issuer plus an integrity-check result; collector and ownership details are intentionally excluded from CSV by default. Spreadsheet formula-injection mitigation is applied.
- Capability checks, nonces, server-side input sanitization, context-aware output escaping, prepared lookup queries, restricted public fields, pagination, and database transactions for issue/revoke operations when supported by the database engine.


## Professional administration and Elementor

Version 1.4.0 added bilingual Persian/English metadata and public rendering, collection/ownership fields, ownership transfer events, and an upgrade migration that keeps earlier signature formats verifiable. Version 1.3.0 improved Elementor controls and lifecycle checks. Version 1.2.0 introduced the administrative Settings screen under **COA Certificates → Settings**, with:

- Default issuing institution, logo picker, and printable certificate footer.
- Primary and accent colors used by certificate output and verification styling.
- Editable public verification title, intro, placeholder, button label, and disclaimer.
- Visibility switches for the institution logo, artwork title, artist name, medium, year, dimensions, inventory number, issuer, issue date, and public description. These switches apply to both the shortcode lookup and the read-only REST response.
- Separate switches for public lookup and the read-only REST API.
- A WordPress Dashboard widget showing total, active, and revoked certificates, event count, five most recent certificates, and quick links.
- An optional Elementor widget named **WP COA Certificate Verification**. When Elementor is active, its Content and Style panels allow text customization, RTL-aware alignment, body/heading typography, panel and input colors, button colors, responsive width, panel padding, input/button padding, corner radius, and result-panel shadow. Global brand settings supply initial defaults; individual widgets can override them.

Existing shortcode usage remains supported: `[coa_verify]`. The public form supports `language="fa"`, `language="en"`, `language="both"`, or `language="site"`; for example, `[coa_verify language="both"]`. Optional customization attributes include `title`, `title_en`, `description`, `description_en`, `placeholder`, `placeholder_en`, `button_text`, `button_text_en`, `primary_color`, `border_color`, `border_radius`, `show_title`, and `hide_description`.

## Version 1.3.0 verification notes

PHP syntax checks are run against the packaged PHP files. Elementor registration and dashboard rendering depend on a live WordPress installation; they have not been browser-tested here. Use a staging site and confirm Elementor is activated before relying on the widget in a production page.


## Version 1.3.0 improvements

- Elementor field defaults now inherit the current site-level verification labels and brand primary color. Added style controls for text, input and button colors, responsive padding, panel background, shadow, and corner radius.
- Public lookup settings can hide the institution logo, artwork title, and artist name in both shortcode results and REST responses.
- Public verification styling is more tolerant of RTL layouts and keeps certificate-code entry left-to-right for reliable code input.
- The public lookup, REST response, and printable certificate now reconcile certificate lifecycle state against the tamper-evident event history when history exists. Legacy records with no event history are explicitly marked as unavailable instead of incorrectly passing or failing this check.
- The dashboard adds a 30-day issuance count and a cached metadata-integrity scan of up to the latest 200 records. The scan is labeled as a summary, not a complete audit, and its transient is invalidated when certificates are issued or revoked.
- PHP syntax checks passed with the local PHP CLI. Live installation, Elementor editor rendering, and WordPress/database integration still require staging verification.

## WordPress pages, public components, and Elementor (1.6.0)

Version 1.6.0 adds a page-integration screen under **COA Certificates → Pages & Elementor**. From that screen, an administrator can explicitly create three editable WordPress pages: a bilingual certificate lookup page, an activity-filterable gallery/institution directory, and an introductory certificate overview with public aggregate statistics. The action is idempotent and does not overwrite pages after they have been created; page content can be edited with the WordPress block editor, the Classic Editor, or Elementor.

New shortcodes:

- `[coa_gallery_directory columns="3" show_filters="1" language="both"]` — responsive cards for active gallery/institution profiles, with search and activity-type filters.
- `[coa_public_stats show_total="1" show_active="1" show_galleries="1" language="both"]` — aggregate certificate and active-institution counts. It does not expose individual certificate or owner data.
- `[coa_gallery_profile id="1" language="both"]` or `[coa_gallery_profile slug="profile-slug"]` — branded profile card for one active institution.
- `[coa_certificate_guide language="both"]` — three-step public verification guide with a notice describing the limits of digital verification.

The existing `[coa_verify]` shortcode remains supported. Responsive styles are namespaced and included in `assets/css/public-components.css`; they use the site-wide institution colours as defaults.

When Elementor is active, the **WP COA Pro** category includes four additional widgets: **COA — Gallery Directory**, **COA — Public Statistics**, **COA — Gallery Profile**, and **COA — Verification Guide**, alongside **WP COA Certificate Verification**. Each widget provides content controls and relevant style controls, including language, visibility toggles, responsive columns/padding, colours, and card presentation. The gallery profile widget keeps the selected institution's own branding colours while allowing layout and background styling. Elementor rendering requires a live WordPress/Elementor environment and has not been browser-tested in this package environment.

The new page set is created only when an administrator clicks **Create missing recommended pages**; activating/upgrading the plugin does not unexpectedly publish pages. A page that has already been created is not overwritten on subsequent runs. Public aggregate statistics are cached for five minutes and the cache is invalidated when certificates are issued/revoked or gallery profiles change. See `QA-REPORT.md` for the exact local checks and the live-integration limitations.

## Installation

1. Back up the WordPress files and database; use a staging site for first installation.
2. In WordPress, open **Plugins → Add New Plugin → Upload Plugin** and upload `wp-coa-pro.zip`.
3. Activate the plugin. The activation/upgrade routine creates or upgrades the database tables and grants the `manage_coa_certificates` capability to the Administrator role.
4. Open **COA Certificates** in the dashboard and issue a test certificate.
5. Create a public WordPress page containing `[coa_verify]` to provide the lookup form.
6. Test both an active and a revoked certificate, metadata integrity status, print/save-as-PDF, CSV export, and the event history before considering production use.

## Signing key

By default, the plugin creates a random signing key in a non-autoloaded WordPress option. For a higher-security deployment, configure a stable secret **before issuing any new certificates** in `wp-config.php`:

```php
define('WP_COA_PRO_SIGNING_KEY', 'replace-with-a-unique-random-secret-at-least-32-characters-long');
```

Use a genuinely random secret, not the example string. Protect backups and the `wp-config.php` file. Keep the key stable: records signed using the constant can no longer be verified if that constant is removed or changed. Records signed with the database option retain that key source when a constant is introduced later. Back up the key securely; losing it can prevent verification.

The database-option fallback is convenient for initial testing, but a database-level attacker who can read or rewrite both records and the key is outside the protection provided by this fallback. For stronger tamper resistance, keep the signing secret outside the database and maintain independent, access-controlled backups or external anchors.

## Integrity model and limitations

- The signature protects the stored, signed metadata against unnoticed changes during normal application use. It does not prove that the physical object is genuine, that the artist attribution is correct, or that ownership claims are legally valid.
- Revocation status and internal revocation reason are separate from the signed issuance snapshot. Revocation does not rewrite the original certificate metadata.
- The event chain detects many accidental edits or partial tampering. It is not a WORM ledger or blockchain and can be rewritten by an actor with sufficient database privileges.
- **Print / PDF** uses the browser print interface. It is not server-side PDF generation and does not yet embed a QR-code image.
- The public REST API is read-only; ownership transfers are available to authorized administrators in the dashboard and are recorded as audit events. API write operations, WooCommerce integration, payments, token issuance, blockchain anchoring, and KYC/AML workflows are not implemented in this build.
- No live WordPress installation was available during packaging. PHP syntax checks and local integrity smoke tests are not a substitute for integration tests on the target WordPress/PHP/database stack.
- Multisite and high-volume production deployments have not been validated.

## Database tables

The plugin uses tables prefixed with the site's WordPress database prefix:

- `*_coa_certificates`: certificate metadata, lifecycle status, signature snapshot, and internal notes.
- `*_coa_events`: issuance/revocation events with a previous-event hash and current event hash.

The plugin does not automatically delete certificates or event data when uninstalled.

## Development and quality checks

The delivered package was checked for PHP syntax errors using PHP 8.4.24 and for signature creation/verification behavior using a local smoke test. The declared minimum PHP baseline remains 7.4; test with the actual site's PHP version and WordPress version before deployment. No claim is made that a live WordPress activation or end-to-end browser test was run.

## Upgrade note

When replacing the earlier 1.0.0 starter build, the upgrader adds missing fields and the event table without intentionally deleting existing rows. Back up the database first. Existing version-1 and version-2 signatures are checked using their original algorithms and canonical payloads; newly issued certificates and ownership-transfer updates use signature schema version 3.


## Version 1.4.0 — bilingual and collection management

### Persian / English
- Bundled `languages/wp-coa-pro-fa_IR.po` and `.mo` files translate the plugin interface when the WordPress locale is `fa_IR`.
- Certificate metadata supports a primary/Persian value and an English translation for artwork title, artist, medium, public description, collector, current owner, previous owner, and issuing institution.
- Printed certificates show bilingual labels and both language values where the English translation is supplied.
- Public lookup supports `language="fa"`, `language="en"`, `language="both"`, or `language="site"`; the visitor language selector is enabled by default. Example: `[coa_verify language="both"]`.
- The Elementor widget has a language selector and separate English fields for heading, description, placeholder, and button.
- The public REST response preserves existing keys and adds `*_en` variants when translations exist.

### Collector and ownership
- Collector / collection name is stored separately from current owner and previous owner.
- Ownership transfer is available from **COA Certificates** via each active certificate's **Transfer ownership** panel. It stores the former owner and appends an `ownership_transferred` audit event.
- Certificate metadata is re-signed after a transfer, and the audit event is written in the same database transaction.
- Collector and owner data are private on public pages and in public REST output by default. Enable public visibility only after obtaining appropriate authorization in **COA Certificates → Settings**.

### Upgrade / integrity
- Database schema version 3 adds nullable/empty-default metadata columns without deleting existing records.
- Signature schema v3 signs the bilingual metadata and collection/ownership fields. Existing v1 and v2 records continue to be checked using their original canonical payload format.
- WordPress upgrades the schema automatically when the plugin loads; always back up the database and files before upgrading a production site.

### Validation status
- PHP syntax lint and local signature-format tests were run on the packaged files. A live WordPress database migration, logged-in ownership-transfer test, and browser/Elementor visual test still need to be run on a staging site.


## Artist and collector profiles (v1.8.0)

Version 1.8.0 introduces structured, bilingual artist and collector/collection profiles in a dedicated `coa_person_profiles` table. The profile management screen is available under **COA Certificates → هنرمندان و کلکتورها**. Profile data includes Persian and English display names and taglines, biography, avatar and cover image URLs, nationality and location, birth/death/founding years, artistic disciplines, education, awards, exhibitions, collecting focus, website and portfolio URLs, social links, and optional public contact information. Profiles can be drafts, private, or published; verified badges can only be toggled by users with `manage_options`.

New shortcodes:

- `[coa_artist_directory columns="3" language="both"]` — public directory of published artist profiles.
- `[coa_collector_directory columns="3" language="both"]` — public directory of published collectors and collections.
- `[coa_profile_directory type="artist" columns="3" featured_only="1"]` — type-selectable profile directory with search.
- `[coa_profile_view language="both"]` — displays the profile selected by `coa_profile_type` and `coa_profile_slug` URL parameters, or by shortcode `id`/`slug` attributes.

The **Pages & Elementor** screen now offers recommended pages for artist directory, collector directory, and profile details. Create the pages there, then place the profile-detail shortcode/widget on the detail page so directory cards can link to it. Elementor adds **COA — Artist & Collector Directory** and **COA — Profile Details** widgets with content and style controls.

When creating a certificate, administrators can optionally associate an existing published artist and collector profile. Linked profile IDs are stored on the certificate and included in signature schema v5; the displayed artwork/artist/collector names are snapshotted into the signed certificate data. Public certificate results link to the associated profile only when the certificate signature validates and the linked profile is published. Profile pages show only active, signature-valid certificates linked to that exact profile ID.

Contact privacy is opt-in: email and phone remain hidden until explicitly enabled on each profile. Draft/private profiles are not available publicly. Updating a profile's biography or social links does not alter historical certificate snapshots; historical artist/collector names on issued certificates remain signed as issued. Signature schemas v1–v4 remain supported for existing records; the migration adds profile tables and indexed relation columns without deleting certificate data.

Live WordPress and Elementor installation tests still require a staging site. Use the included `QA-REPORT.md` for local checks and known limitations.
