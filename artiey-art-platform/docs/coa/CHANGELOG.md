# Changelog

## 1.11.0 — Performance improvements
- Reduced public profile directory queries to the fields needed for cards, excluding long education, awards, exhibition, and social/contact fields; cards configured without excerpts skip biography columns entirely.
- Cached signature-verified linked artwork lists per profile for 10 minutes; cache generations are invalidated after certificate issuance, revocation, and owner transfer. Repeated page views avoid re-running up to 24 HMAC checks for that profile during the cache window.
- Added attachment IDs for portrait and cover images and uses WordPress responsive image variants (`srcset`) where the media attachment is known. URL-only profile images remain compatible.
- Cached gallery profile lists and used a narrow query projection for gallery directory cards; gallery create/edit/status changes invalidate the cache. Public searches and missing profile slugs are not persisted as transients, reducing unbounded cache-key growth from arbitrary visitor input.
- Deferred plugin theme option reads and inline CSS until a COA component is actually used; Elementor JSON is only inspected when Elementor is active.


## 1.10.0 — Original artwork evidence, unique certificate code, and QR verification

- Added optional original-artwork image selection/upload through the WordPress Media Library on certificate issuance, with raster MIME checks, capability checks, readability checks, and a 25 MB limit; SVG is rejected.
- Newly issued certificates store the attachment ID and SHA-256 hash of the original file. HMAC payload schema v6 signs both fields, and verification checks that the attachment's current original file is still readable and has the expected SHA-256 digest.
- Preserved canonical v5 payload verification for existing certificates and v1–v4 legacy signatures. Existing certificate rows are not automatically assigned images or new codes.
- Added a local/offline QR generator to print output and public verification results, pointing to `/coa-verify/CODE/` when pretty permalinks are enabled or a query URL otherwise. No external QR API is called.
- Added post-issuance confirmation with the unique code and a direct nonce-protected print/QR action. The existing random code format and unique database index remain in place.
- Added original-art visibility controls at site and gallery-profile levels. REST image fields respect public-visibility settings.
- Added a one-time rewrite-rules version bump and 404 status for well-formed but unknown QR codes.
- Local checks: PHP syntax for all plugin PHP files; JavaScript syntax; QR matrix generation and offline image decode; ZIP integrity. Live WordPress/Elementor migration and browser tests remain required.

## 1.9.0 — Technical SEO and frontend performance

- Added clean, crawlable single-profile URLs under the managed profile page, while retaining legacy query URLs and redirecting public legacy links to their canonical URL when pretty permalinks are enabled.
- Added dynamically generated SEO titles and descriptions, optional per-profile SEO title/description fields, canonical and Open Graph/Twitter metadata, and integration filters for Yoast SEO, Rank Math, SEOPress, and AIOSEO.
- Added schema.org `ProfilePage` JSON-LD with Person/Organization main entities and breadcrumbs for published artist/collector profiles.
- Added a paginated XML sitemap for published profiles and a Sitemap directive in public `robots.txt` output.
- Missing/unpublished profile slugs return 404 and are marked noindex; contact information remains subject to existing privacy settings.
- Added short-lived transient caches for published profile lookups, directory result sets, and counts with generation-based invalidation on profile saves.
- Added database indexes for profile directory ordering and certificate-to-profile active-work lookups; schema version raised to 6 using `dbDelta`.
- Reduced shared front-end stylesheet loading to relevant shortcode/Elementor pages rather than every public page.
- Reduced profile-linked certificate query payload to the fields required for signature verification and display.
- PHP syntax checks passed under the available PHP 8.4 runtime. Live WordPress, SEO-plugin, sitemap discovery, and Elementor/browser performance tests remain required on staging; PHP 7.4 runtime testing was not available.

## 1.8.0 — Artist, collector, and collection profiles

- Added a dedicated database table for rich bilingual artist and collector/collection profiles with identity, media, biographies, disciplines, education, awards, exhibitions, collection focus, location, social links, and optional public contact details.
- Added admin profile management, draft/private/published states, featured profiles, administrator-controlled verification, media-library image selection, and a profile list with linked-certificate counts.
- Added public artist and collector directories, dynamic profile details, responsive cards, bilingual display, optional registered-work lists, and privacy-controlled contact fields.
- Added Elementor directory and profile detail widgets with responsive layout and style controls.
- Added artist/collector profile IDs to newly issued certificates and HMAC schema v5 while retaining verification of legacy v1–v4 signatures.
- Added schema migration for the profile table and indexed certificate relationship fields; existing certificate records are retained.
- Local PHP syntax, archive, translation-catalog, and signature compatibility checks passed. Live WordPress and Elementor staging tests remain required.

# تغییرات WP COA Pro

## نسخه 1.7.1
- اصلاح متن پیش‌فرض عمومی تا حتی در سایت‌هایی با زبان پیشخوان انگلیسی، محتوای اولیه استعلام به فارسی نمایش داده شود.
- فارسی‌سازی عنوان برگه‌های پیشنهادی و تیتر پیش‌فرض ویجت‌های عمومی Elementor؛ فیلدها و امکان انتخاب زبان انگلیسی همچنان مستقل هستند.
- اصلاح ترجمه فارسی عنوان استعلام گواهی و به‌روزرسانی فایل‌های ترجمه.
- تغییر نسخه افزونه بدون تغییر ساختار پایگاه داده؛ داده‌های قبلی و پروفایل‌ها حفظ می‌شوند.

## نسخه 1.7.0
- تغییر زبان پیش‌فرض نمایش به فارسی و حفظ گزینه‌های انگلیسی، دوزبانه و زبان سایت.
- تکمیل کاتالوگ ترجمه فارسی برای برچسب‌های مدیریت و Elementor.
- ایجاد برگه‌های پیشنهادی جدید با محتوای فارسی؛ برگه‌های موجود دست‌نخورده می‌مانند.
- افزودن بهبودهای دسترس‌پذیری و واکنش‌گرایی به CSS اجزای عمومی.
- اجرای مهاجرت یک‌باره تنظیم پیش‌فرض زبان از `both` به `fa`.

# Changelog

## 1.5.0 - Gallery profiles and activity filters

- Added gallery/institution profile table with migration-safe schema upgrade.
- Added profile editor for bilingual identity, logo, brand colors, template, footer, status, and public-field visibility.
- Added activity taxonomy for galleries, museums, studios, auction houses, fairs, schools, collections, conservation, archives, digital art, advisors, and other institutions.
- Added issuer profile and activity selectors to certificate issuance.
- Added certificate list filters for gallery, activity type, and status; retained search and pagination.
- Added four printable certificate themes: classic, modern, minimal, and archive.
- Added signed per-certificate profile snapshots so later profile edits do not rewrite the visual identity of historical certificates.
- Applied profile-specific colors, logo, title, activity label, footer, and public-field visibility to print output, shortcode results, and REST responses.
- Added gallery and activity fields to the public-metadata CSV export.
- Upgraded HMAC metadata schema to v4 while retaining verification support for v2/v3 and legacy records.
- Validate the signed v4 profile snapshot before applying gallery branding to public output or print; tampered snapshots fall back to global branding and still display integrity failure.
- Preserve existing certificate records; profiles are deactivated rather than hard-deleted.

## 1.4.0
- Added explicit English translation fields for artwork title, artist, medium, description, institution, collector, and ownership metadata while preserving existing primary-language columns.
- Added a public Persian/English language selector, language-aware shortcode rendering, Elementor language and English text controls, and bilingual public API disclaimer metadata.
- Added bundled `fa_IR` gettext translations for plugin UI strings.
- Expanded printed certificate output to show bilingual metadata and collection/ownership fields. CSV export now includes English public metadata columns, while private collector/owner fields remain excluded by default.
- Added separate collector, current owner, and previous owner fields; collector/owner fields remain private publicly by default.
- Added ownership transfer processing with capability and nonce checks, row locking against concurrent transfers, an ownership audit event, and metadata re-signing within a database transaction.
- Bumped schema version to 3; v1 and v2 signatures keep using their original verification payloads for backward compatibility.
- PHP syntax checks and local signature tests run; live WordPress/Elementor integration requires staging validation.

## 1.3.0
- Improved Elementor controls and site-level defaults; added color, typography, responsive spacing, radius, and shadow controls.
- Added public visibility settings for the logo, artwork title, and artist name, consistently applied to shortcode and REST output.
- Improved RTL alignment and left-to-right certificate-code entry.
- Added lifecycle-to-audit consistency checks in public results, REST responses, and printable certificates; legacy records without event history are marked unavailable.
- Added 30-day issuance and cached recent-record integrity diagnostics to the dashboard; invalidate the diagnostic transient after issue/revoke operations.
- PHP syntax checks passed. Live WordPress and Elementor integration must still be verified on staging.

## 1.2.0
- Added a professional Settings screen for institution defaults, logo, footer, brand colors, public verification labels, disclaimer, field visibility, and public API/lookup toggles.
- Applied settings to newly created certificates, printable output, shortcode rendering, and public REST response fields.
- Added a WordPress Dashboard registry overview widget with certificate/event metrics, recent records, and quick links.
- Added an optional Elementor verification widget with content controls, alignment, color, typography, width, and border-radius controls.
- Kept Elementor-specific classes conditional so sites without Elementor can still activate the plugin safely.
- Added logo selection through the WordPress Media Library.
- PHP syntax checks passed; a live WordPress/Elementor browser test remains required.

## 1.1.0
- Refactored the monolithic implementation into installer, signature, ledger, repository, and controller classes.
- Added signed canonical metadata snapshots and live verification of stored public fields.
- Preserved verification support for legacy v1 records produced by 1.0.0.
- Added event history for issuance and revocation, per-certificate hash chain validation, and revocation reason.
- Added search, filters, pagination, read-only REST lookup, CSV export, and browser-print certificate output.
- Improved capability handling, validation, escaping, output restrictions, and documentation of security limits.
- PHP syntax checks passed; live WordPress integration remains unverified.


## 1.6.0 — WordPress pages and expanded Elementor integration

- Added a dedicated **Pages & Elementor** admin screen with an explicit, repeat-safe action to create three editable WordPress pages: verification, gallery/institution directory, and certificate overview. Existing managed pages are preserved instead of being overwritten.
- Added `[coa_gallery_directory]` with active-profile filtering, search by institution name/English name/slug, activity-type filtering, responsive cards, bilingual labels, optional logos/activity/template labels, and a configurable column count/limit.
- Added `[coa_public_stats]` for aggregate-only counts and `[coa_gallery_profile]` for a branded active-institution card.
- Added `[coa_certificate_guide]` with verification steps and a clear limitation notice.
- Added responsive, namespaced front-end CSS and global brand defaults.
- Added Elementor widgets for gallery directory, public statistics, individual gallery profile, and verification guide. Existing certificate verification widget remains.
- Public aggregate statistics are cached for five minutes; cache invalidation is wired to certificate issue/revoke and gallery profile changes.
- No database schema change was introduced. Live WordPress page creation, Elementor editor rendering, and browser/device testing remain to be completed on a staging site.


## 1.8.0 — Artist and collector profile system

- Added a dedicated bilingual profile table for artists, collectors, and collection entities.
- Added profile administration with biographies, specialties, education, awards, exhibitions, geographical facts, images, websites, portfolio and social URLs, publication status, featured status, and admin-controlled verification.
- Added public profile directory and detail shortcodes plus recommended WordPress pages.
- Added two Elementor widgets: Artist & Collector Directory and Profile Details.
- Added optional artist/collector profile associations on certificate issuance, included the IDs in signature schema v5, and linked names on public certificate results only when signatures validate.
- Added related-work lists which include only active certificates whose signatures verify.
- Kept email and phone private unless explicitly made public.
- Preserved verification compatibility for previous signature schemas v1–v4.
- Database migration adds profile tables and indexed relation columns; no certificate rows are deleted.
