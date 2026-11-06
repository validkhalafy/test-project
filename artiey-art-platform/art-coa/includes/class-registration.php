<?php
declare(strict_types=1);

namespace WPArtCOA;

use WPArtCOA\Entities\Artist;
use WPArtCOA\Entities\Buyer;
use WPArtCOA\Entities\Collector;
use WPArtCOA\Entities\Gallery;

defined('ABSPATH') || exit;

final class Registration
{
    public const ROLES = [
        'artist'    => 'art_artist',
        'gallery'   => 'art_gallery',
        'curator'   => 'art_curator',
        'collector' => 'art_collector',
        'buyer'     => 'art_buyer',
    ];

    public static function init(): void
    {
        add_shortcode('art_coa_register', [self::class, 'render_form']);
        add_shortcode('art_coa_member_dashboard', [self::class, 'render_dashboard']);
        add_action('init', [self::class, 'rewrites']);
        add_filter('query_vars', static function (array $vars): array {
            $vars[] = 'art_coa_member';
            return $vars;
        });
        add_action('template_redirect', [self::class, 'member_page']);
    }

    public static function rewrites(): void
    {
        add_rewrite_rule('^art-coa/member-dashboard/?$', 'index.php?art_coa_member=dashboard', 'top');
    }

    public static function member_page(): void
    {
        if (get_query_var('art_coa_member') !== 'dashboard') {
            return;
        }
        status_header(200);
        nocache_headers();
        get_header();
        echo '<div class="art-coa-profile-wrap" dir="' . (I18n::is_fa() ? 'rtl' : 'ltr') . '">';
        echo self::render_dashboard([]);
        echo '</div>';
        get_footer();
        exit;
    }

    /** The register form, its validation and account creation live in MemberForms. */
    public static function render_form($atts = []): string
    {
        return MemberForms::render_register($atts);
    }

    public static function render_dashboard($atts = []): string
    {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html(I18n::t('reg_login_required')) . ' <a href="' . esc_url(wp_login_url(home_url('/art-coa/member-dashboard/'))) . '">' . esc_html(I18n::t('reg_login')) . '</a></p>';
        }

        $user_id = get_current_user_id();
        $role    = get_user_meta($user_id, 'art_coa_role', true) ?: self::detect_role($user_id);
        $entity_id = (int) get_user_meta($user_id, 'art_coa_entity_id', true);
        $slug    = (string) get_user_meta($user_id, 'art_coa_entity_slug', true);
        $user    = wp_get_current_user();

        $flash = self::get_flash();
        ob_start();
        ?>
        <div class="art-coa-member-dash" dir="<?php echo I18n::is_fa() ? 'rtl' : 'ltr'; ?>">
            <header class="acmd-header">
                <div><span class="acmd-eyebrow">ARTIE MEMBER SPACE</span><h2><?php echo esc_html(I18n::t('member_dashboard')); ?></h2><p><?php echo esc_html($user->display_name ?: $user->user_email); ?></p></div>
                <a class="button" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"><?php echo esc_html(I18n::t('reg_logout')); ?></a>
            </header>
            <nav class="acmd-nav" aria-label="<?php echo esc_attr(I18n::t('member_dashboard')); ?>">
                <a href="#acmd-overview"><?php echo esc_html(MemberForms::t('نمای کلی','Overview')); ?></a>
                <a href="#acmd-workspace"><?php echo esc_html(MemberForms::t('فضای کاری','Workspace')); ?></a>
                <a href="#acmd-profile"><?php echo esc_html(MemberForms::t('پروفایل و حساب','Profile & account')); ?></a>
                <a href="#acmd-following"><?php echo esc_html(MemberForms::t('دنبال‌شده‌ها','Following')); ?></a>
            </nav>
            <?php if ($flash): ?>
                <div class="art-coa-result <?php echo $flash['type'] === 'success' ? 'valid' : 'invalid'; ?>">
                    <p><?php echo esc_html($flash['message']); ?></p>
                </div>
            <?php endif; ?>

            <?php $welcome = isset($_GET['art_coa_welcome']) ? sanitize_key((string) $_GET['art_coa_welcome']) : ''; if ($welcome): ?>
                <div class="art-coa-result valid"><p><?php echo esc_html($welcome === 'pending'
                    ? MemberForms::t('خوش آمدید! پروفایل شما پس از بررسی مدیر عمومی می‌شود؛ تا آن زمان می‌توانید آن را کامل کنید.', 'Welcome! Your profile goes public after review; you can complete it meanwhile.')
                    : MemberForms::t('خوش آمدید! حساب شما فعال شد.', 'Welcome! Your account is active.')); ?></p></div>
            <?php endif; ?>
            <section id="acmd-overview" class="acmd-section" aria-label="<?php echo esc_attr(MemberForms::t('نمای کلی','Overview')); ?>">
            <div class="art-coa-stats-bar">
                <div class="art-coa-stat-pill">
                    <span class="lbl"><?php echo esc_html(I18n::t('reg_choose_role')); ?></span>
                    <span class="val aap-s-b6d701"><?php echo esc_html(I18n::t($role ?: 'person')); ?></span>
                </div>
                <div class="art-coa-stat-pill">
                    <span class="lbl"><?php echo esc_html(I18n::t('email')); ?></span>
                    <span class="val aap-s-32036f"><?php echo esc_html($user->user_email); ?></span>
                </div>
            </div>

            </section>
            <section id="acmd-workspace" class="acmd-section" aria-label="<?php echo esc_attr(MemberForms::t('فضای کاری','Workspace')); ?>">
            <?php
            match ($role) {
                'artist'    => self::dash_artist($entity_id, $slug),
                'gallery'   => self::dash_gallery($entity_id, $slug),
                'collector' => self::dash_collector($entity_id, $slug),
                'curator'   => self::dash_curator($slug),
                'buyer'     => self::dash_buyer($entity_id, $user_id),
                default     => print '<p>' . esc_html(I18n::t('reg_no_profile')) . '</p>',
            };
            ?>
            </section>
            <section id="acmd-profile" class="acmd-section" aria-label="<?php echo esc_attr(MemberForms::t('پروفایل و حساب','Profile & account')); ?>">
                <?php echo MemberForms::render_profile(); ?>
            </section>
            <section id="acmd-following" class="acmd-section" aria-label="<?php echo esc_attr(MemberForms::t('دنبال‌شده‌ها','Following')); ?>">
                <?php echo Follows::dashboard_html($user_id); ?>
            </section>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private static function dash_artist(int $id, string $slug): void
    {
        $stats = $id ? Stats::artist($id) : ['artworks' => 0, 'certs' => 0, 'exhibitions' => 0, 'sales' => 0];
        echo '<h3>' . esc_html(I18n::t('artist')) . '</h3>';
        self::print_stats([
            I18n::t('artworks') => $stats['artworks'],
            I18n::t('certificates') => $stats['certs'],
            I18n::t('exhibitions') => $stats['exhibitions'],
        ]);
        if ($id) {
            echo Hierarchy::render_html(Hierarchy::artist_tree($id), 'artist');
        }
        if ($slug) {
            echo '<p><a class="button button-primary" href="' . esc_url(ContentSync::artist_url((string) $slug)) . '">' . esc_html(I18n::t('view_profile')) . '</a></p>';
        }
        echo ArtistPortfolio::upload_shortcode([]);
        echo MemberTools::panel('artist', $id);
    }

    private static function dash_gallery(int $id, string $slug): void
    {
        $stats = $id ? Stats::gallery($id) : ['artworks' => 0, 'exhibitions' => 0, 'artists' => 0, 'certs' => 0];
        echo '<h3>' . esc_html(I18n::t('gallery')) . '</h3>';
        self::print_stats([
            I18n::t('artworks') => $stats['artworks'],
            I18n::t('exhibitions') => $stats['exhibitions'],
            I18n::t('artists') => $stats['artists'],
        ]);
        if ($id) {
            echo Hierarchy::render_html(Hierarchy::gallery_tree($id), 'gallery');
        }
        if ($slug) {
            echo '<p><a class="button button-primary" href="' . esc_url(home_url('/art-coa/gallery/' . $slug)) . '">' . esc_html(I18n::t('view_profile')) . '</a></p>';
        }
        echo MemberTools::panel('gallery', $id);
    }

    private static function dash_collector(int $id, string $slug): void
    {
        $stats = $id ? Stats::collector($id) : ['artworks' => 0, 'acquisitions' => 0, 'certificates' => 0];
        echo '<h3>' . esc_html(I18n::t('collector')) . '</h3>';
        self::print_stats([
            I18n::t('artworks') => $stats['artworks'],
            I18n::t('sales_total') => $stats['acquisitions'],
            I18n::t('certificates') => $stats['certificates'],
        ]);
        if ($id) {
            echo Hierarchy::render_html(Hierarchy::collector_tree($id), 'collector');
            echo SecondaryMarket::collector_panel($id, get_current_user_id());
            echo Auction::collector_panel($id);
        }
        if ($slug) {
            echo '<p><a class="button button-primary" href="' . esc_url(home_url('/art-coa/collector/' . $slug)) . '">' . esc_html(I18n::t('view_profile')) . '</a></p>';
        }
        echo '<p><a class="button" href="' . esc_url(home_url('/art-coa/secondary-market/')) . '">' . esc_html(I18n::t('secondary_market')) . '</a> ' . '<a class="button button-primary" href="' . esc_url(home_url('/art-coa/auctions/')) . '">' . esc_html(I18n::t('auction_title')) . '</a></p>';
        echo MemberTools::panel('collector', $id);
    }

    private static function dash_curator(string $slug): void
    {
        echo '<h3>' . esc_html(MemberForms::t('کیوریتور', 'Curator')) . '</h3>';
        if ($slug) {
            echo '<p><a class="button button-primary" href="' . esc_url(home_url('/art-coa/curator/' . $slug)) . '">' . esc_html(I18n::t('view_profile')) . '</a></p>';
        }
    }

    private static function dash_buyer(int $id, int $user_id): void
    {
        $user = wp_get_current_user();
        $tree = Hierarchy::buyer_tree($id, $user->display_name);
        $count = count($tree['purchases'] ?? []);
        echo '<h3>' . esc_html(I18n::t('buyer')) . '</h3>';
        self::print_stats([
            I18n::t('sales_total') => $count,
        ]);
        echo Hierarchy::render_html($tree, 'buyer');
        echo '<p class="description">' . esc_html(I18n::t('reg_buyer_hint')) . '</p>';
    }

    private static function print_stats(array $items): void
    {
        echo '<div class="art-coa-stats-bar">';
        foreach ($items as $label => $val) {
            echo '<div class="art-coa-stat-pill"><span class="val">' . esc_html(Calendar::to_persian_digits((string) $val)) . '</span><span class="lbl">' . esc_html((string) $label) . '</span></div>';
        }
        echo '</div>';
    }

    private static function detect_role(int $user_id): string
    {
        $user = new \WP_User($user_id);
        foreach (self::ROLES as $key => $wp_role) {
            if (in_array($wp_role, (array) $user->roles, true)) {
                return $key;
            }
        }
        return '';
    }

    private static function set_flash(string $type, string $message): void
    {
        if (!session_id() && !headers_sent()) {
            // Use transient instead of session for reliability
        }
        set_transient('art_coa_flash_' . (get_current_user_id() ?: self::guest_key()), [
            'type'    => $type,
            'message' => $message,
        ], 120);
    }

    private static function get_flash(): ?array
    {
        $key = 'art_coa_flash_' . (get_current_user_id() ?: self::guest_key());
        $data = get_transient($key);
        if ($data) {
            delete_transient($key);
            return is_array($data) ? $data : null;
        }
        return null;
    }

    private static function guest_key(): string
    {
        return substr(md5($_SERVER['REMOTE_ADDR'] ?? 'x'), 0, 12);
    }
}
