<?php
declare(strict_types=1);

namespace WPArtCOA\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use WPArtCOA\AuctionTimeline;

defined('ABSPATH') || exit;

final class Auction_Timeline_Widget extends Widget_Base
{
    use Design_Controls;

    public function get_name(): string { return 'art_coa_auction_timeline'; }
    public function get_title(): string { return 'تایم‌لاین حراج‌ها'; }
    public function get_icon(): string { return 'eicon-price-list'; }
    public function get_categories(): array { return ['art-coa', 'artiey-art']; }
    public function get_style_depends(): array { return ['wp-art-coa-front', 'wp-art-coa-dynamic', 'artiey-aet-profile']; }
    public function get_script_depends(): array { return ['artiey-aet-profile']; }
    public function get_keywords(): array { return ['auction', 'bid', 'timeline', 'حراج', 'حراجی', 'تایم‌لاین', 'پیشنهاد']; }

    private function yes(string $id, string $label, bool $default = true, array $condition = []): void
    {
        $this->add_control($id, ['label' => $label, 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => $default ? 'yes' : ''] + ($condition ? ['condition' => $condition] : []));
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => 'حراج‌ها']);
        $this->yes('show_title', 'نمایش عنوان');
        $this->add_control('title', ['label' => 'عنوان', 'type' => Controls_Manager::TEXT, 'dynamic' => ['active' => true], 'default' => 'تایم‌لاین حراج‌ها', 'condition' => ['show_title' => 'yes']]);
        $this->add_control('title_tag', ['label' => 'تگ عنوان', 'type' => Controls_Manager::SELECT, 'options' => ['h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'div' => 'div'], 'default' => 'h2', 'condition' => ['show_title' => 'yes']]);
        $this->add_control('status', [
            'label' => 'کدام حراج‌ها', 'type' => Controls_Manager::SELECT, 'default' => 'all',
            'options' => ['all' => 'همه', 'live' => 'فقط زنده', 'upcoming' => 'فقط به‌زودی', 'closed' => 'فقط پایان‌یافته‌ها', 'sold' => 'فقط فروخته‌شده‌ها'],
        ]);
        $this->entity_picker('artist_id', 'artist', 'فقط آثار این هنرمند', 'همهٔ هنرمندان');
        $this->entity_picker('gallery_id', 'gallery', 'فقط آثار این گالری', 'همهٔ گالری‌ها');
        $this->add_control('item_tag', ['label' => 'تگ عنوان هر حراج', 'type' => Controls_Manager::SELECT, 'options' => ['h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'div' => 'div', 'p' => 'p'], 'default' => 'h3']);
        $this->add_control('order', ['label' => 'ترتیب', 'type' => Controls_Manager::SELECT, 'options' => ['desc' => 'جدیدترین ابتدا', 'asc' => 'قدیمی‌ترین ابتدا'], 'default' => 'desc']);
        $this->add_control('limit', ['label' => 'تعداد حراج', 'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => 60, 'default' => 12]);
        $this->add_control('presentation', ['label' => 'نحوه ارائه', 'type' => Controls_Manager::SELECT, 'options' => ['single' => 'تایم‌لاین یکپارچه', 'tabs' => 'تب‌بندی بر اساس وضعیت'], 'default' => 'single']);
        $this->add_control('auction_status_tabs', [
            'label' => 'تب‌های وضعیت حراج', 'type' => Controls_Manager::SELECT2, 'multiple' => true, 'label_block' => true,
            'options' => ['live' => 'حراج زنده', 'upcoming' => 'به‌زودی', 'closed' => 'پایان‌یافته', 'sold' => 'فروخته‌شده'],
            'default' => ['live', 'upcoming', 'closed'], 'condition' => ['presentation' => 'tabs'],
        ]);
        \Artiey_AET_Tabs::register_behavior_controls($this, 'presentation', 'tabs', ['live' => 'حراج زنده', 'upcoming' => 'به‌زودی', 'closed' => 'پایان‌یافته', 'sold' => 'فروخته‌شده']);
        $this->add_control('layout', ['label' => 'چیدمان', 'type' => Controls_Manager::SELECT, 'options' => ['line' => 'خط زمانی عمودی', 'cards' => 'فهرست کارتی', 'grid' => 'شبکهٔ پوستری', 'compact' => 'فشرده', 'alt' => 'دوطرفه (چپ و راست)'], 'default' => 'line']);
        $this->add_responsive_control('columns', [
            'label' => 'تعداد ستون‌ها', 'type' => Controls_Manager::SELECT, 'options' => ['1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴'], 'default' => '3',
            'selectors' => ['{{WRAPPER}} .art-coa-timeline' => '--art-coa-tl-cols: {{VALUE}};'],
            'condition' => ['layout' => 'grid'],
        ]);
        $this->yes('group', 'گروه‌بندی بر اساس ماه شروع', false);
        $this->end_controls_section();

        $this->start_controls_section('fields', ['label' => 'اطلاعات هر حراج']);
        $this->yes('show_image', 'تصویر اثر');
        $this->yes('show_artist', 'نام هنرمند / مرکز حراج');
        $this->yes('show_status', 'برچسب وضعیت');
        $this->yes('show_date', 'بازهٔ تاریخ حراج');
        $this->yes('show_countdown', 'زمان باقی‌مانده (حراج زنده)');
        $this->yes('show_price', 'مبلغ (پایه / آخرین پیشنهاد / نهایی)');
        $this->yes('show_bids', 'تعداد پیشنهادها');
        $this->yes('show_lot', 'شمارهٔ لات', false);
        $this->yes('show_estimate', 'برآورد قیمت', false);
        $this->add_control('button_text', ['label' => 'متن دکمه (خالی = بدون دکمه)', 'type' => Controls_Manager::TEXT, 'dynamic' => ['active' => true], 'default' => 'مشاهده حراج']);
        $this->add_control('link_target', ['label' => 'بازشدن پیوندها', 'type' => Controls_Manager::SELECT, 'options' => ['' => 'همین تب', '_blank' => 'تب جدید'], 'default' => '']);
        $this->add_control('empty_text', ['label' => 'پیام وقتی حراجی نیست', 'type' => Controls_Manager::TEXT, 'dynamic' => ['active' => true], 'placeholder' => 'حراجی برای نمایش وجود ندارد.']);
        $this->add_control('privacy', ['type' => Controls_Manager::RAW_HTML, 'content_classes' => 'elementor-descriptor', 'raw' => 'نام و ایمیل پیشنهاددهندگان هرگز نمایش داده نمی‌شود.']);
        $this->end_controls_section();

        $this->timeline_style(['live' => 'حراج زنده', 'upcoming' => 'به‌زودی', 'sold' => 'فروخته شد', 'past' => 'پایان‌یافته']);
        \Artiey_AET_Tabs::register_style_controls($this, 'auctiontimelinetabs');
        $this->register_design_controls('.art-coa-timeline', '', '', '');
        $this->register_arrow_controls('.art-coa-tl-arrow', 'auction_arrow', '.art-coa-tl-btn:hover', '.art-coa-tl-btn');
    }

    protected function render(): void
    {
        $s = $this->get_settings_for_display();
        $route = class_exists('\\Artiey_AET_Elementor_Layouts') ? \Artiey_AET_Elementor_Layouts::context() : [];
        $route_type = (string) ($route['entity_type'] ?? '');
        $route_slug = sanitize_title((string) ($route['slug'] ?? ''));
        $artist_id = absint($s['artist_id'] ?? 0);
        $gallery_id = absint($s['gallery_id'] ?? 0);
        if (!$artist_id && !$gallery_id && $route_slug !== '' && $route_type === 'artist') {
            $artist = \WPArtCOA\Entities\Artist::get_by_slug($route_slug);
            $artist_id = $artist ? (int) $artist->id : 0;
        } elseif (!$artist_id && !$gallery_id && $route_slug !== '' && $route_type === 'gallery') {
            $gallery = \WPArtCOA\Entities\Gallery::get_by_slug($route_slug);
            $gallery_id = $gallery ? (int) $gallery->id : 0;
        }
        $flag = static fn(string $k): bool => !empty($s[$k]);
        $args = [
            'status' => (string) ($s['status'] ?? 'all'), 'artist_id' => $artist_id, 'gallery_id' => $gallery_id, 'item_tag' => (string) ($s['item_tag'] ?? 'h3'),
            'order' => $s['order'] ?? 'desc', 'limit' => (int) ($s['limit'] ?? 12), 'layout' => $s['layout'] ?? 'line',
            'columns' => (int) ($s['columns'] ?? 3), 'group' => $flag('group'),
            'show_image' => $flag('show_image'), 'show_artist' => $flag('show_artist'), 'show_status' => $flag('show_status'),
            'show_date' => $flag('show_date'), 'show_countdown' => $flag('show_countdown'), 'show_price' => $flag('show_price'),
            'show_bids' => $flag('show_bids'), 'show_lot' => $flag('show_lot'), 'show_estimate' => $flag('show_estimate'),
            'button_text' => (string) ($s['button_text'] ?? ''), 'link_target' => (string) ($s['link_target'] ?? ''),
            'empty_text' => (string) ($s['empty_text'] ?? ''),
        ];
        echo '<div class="art-coa-elementor-widget art-coa-auction-timeline-widget">';
        if (!empty($s['show_title'])) {
            $tag = in_array(($s['title_tag'] ?? 'h2'), ['h1', 'h2', 'h3', 'h4', 'div'], true) ? (string) $s['title_tag'] : 'h2';
            echo '<' . $tag . ' class="art-coa-elementor-title">' . esc_html((string) ($s['title'] ?? '')) . '</' . $tag . '>';
        }
        if (($s['presentation'] ?? 'single') === 'tabs') {
            $allowed = ['live', 'upcoming', 'closed', 'sold'];
            $selected = array_values(array_intersect($allowed, array_map('sanitize_key', (array) ($s['auction_status_tabs'] ?? ['live', 'upcoming', 'closed']))));
            $labels = ['live' => 'حراج زنده', 'upcoming' => 'به‌زودی', 'closed' => 'پایان‌یافته', 'sold' => 'فروخته‌شده'];
            $panels = [];
            foreach ($selected as $tab_status) {
                $panel_args = $args;
                $panel_args['status'] = $tab_status;
                $panel = AuctionTimeline::render($panel_args);
                if (is_string($panel) && trim($panel) !== '') { $panels[] = ['key' => $tab_status, 'label' => $labels[$tab_status], 'html' => $panel]; }
            }
            $tabs_settings = $s;
            $tabs_settings['force_tabs'] = 'yes';
            $tabs_html = \Artiey_AET_Tabs::render($panels, $tabs_settings, 'وضعیت حراج‌ها');
            echo $tabs_html !== '' ? $tabs_html : '<p class="art-coa-empty">برای وضعیت‌های انتخاب‌شده حراجی یافت نشد.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } else {
            echo AuctionTimeline::render($args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes dynamic values.
        }
        echo '</div>';
    }
}
