<?php
declare(strict_types=1);

namespace WPArtCOA\Admin\Pages;

use WPArtCOA\Database;
use WPArtCOA\Entities\Artist;
use WPArtCOA\Entities\Artwork;
use WPArtCOA\Entities\Certificate;
use WPArtCOA\Entities\Collector;
use WPArtCOA\Entities\Curator;
use WPArtCOA\Entities\Gallery;
use WPArtCOA\Entities\Exhibition;
use WPArtCOA\I18n;
use WPArtCOA\Dates;

defined('ABSPATH') || exit;

/** Admin screens split out of Menus (used as a trait so existing self:: calls keep working). */
trait Helpers
{
    /* ------------------------------------------------------------------
     * Form helpers (shared by every entity page)
     * ---------------------------------------------------------------- */

    /** Record being edited via ?edit=ID (or null). */
    private static function editing(string $class): ?object
    {
        $id = absint($_GET['edit'] ?? 0); // phpcs:ignore WordPress.Security.NonceVerification
        return $id ? $class::get($id) : null;
    }

    private static function edit_url(int $id): string
    {
        return esc_url(add_query_arg('edit', $id));
    }

    private static function thumb(?object $row, string $field, string $class = ''): string
    {
        $u = \WPArtCOA\Media::url($row, $field, 'thumbnail');
        return $u ? '<img class="art-coa-admin-thumb ' . esc_attr($class) . '" src="' . esc_url($u) . '" alt="">' : '<span class="art-coa-admin-thumb is-empty">—</span>';
    }

    private static function form_open(string $id, string $entity, ?object $edit, string $title): void
    {
        echo '<div id="art-form-' . esc_attr($id) . '" class="art-coa-form art-coa-form--wide"' . (($edit || !empty($_GET['add'])) ? '' : ' style="display:none"') . '>';
        echo '<h2 class="aap-s-45e6a6">' . esc_html($edit ? I18n::t('edit') . ': ' . $title : $title) . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="art_coa_entity_form">';
        echo '<input type="hidden" name="entity" value="' . esc_attr($entity) . '">';
        wp_nonce_field('art_coa_save_' . $entity);
        echo '<input type="hidden" name="art_coa_action" value="' . esc_attr(($edit ? 'update_' : 'save_') . $entity) . '">';
        if ($edit) {
            echo '<input type="hidden" name="item_id" value="' . (int) $edit->id . '">';
        }
    }

    private static function form_close(string $id, ?object $edit): void
    {
        echo '<p class="art-coa-form-actions"><button class="button button-primary">' . esc_html(I18n::t('save')) . '</button> ';
        if ($edit) {
            echo '<a class="button" href="' . esc_url(remove_query_arg(['edit', 'add', 'art_coa_action', 'art_coa_notice'])) . '">' . esc_html(I18n::t('cancel')) . '</a>';
        } else {
            echo '<button type="button" class="button" onclick="document.getElementById(\'art-form-' . esc_attr($id) . '\').style.display=\'none\'">' . esc_html(I18n::t('cancel')) . '</button>';
        }
        echo '</p></form></div>';
    }

    /** @param array{type?:string,required?:bool,rows?:int,attrs?:string} $o */
    private static function field(string $name, string $label, $value = '', array $o = []): void
    {
        $type = $o['type'] ?? 'text';
        $req  = !empty($o['required']);
        echo '<p><label>' . esc_html($label) . ($req ? ' *' : '') . '<br>';
        if ($type === 'textarea') {
            echo '<textarea name="' . esc_attr($name) . '" rows="' . (int) ($o['rows'] ?? 3) . '" class="widefat"' . ($req ? ' required' : '') . '>' . esc_textarea((string) $value) . '</textarea>';
        } else {
            echo '<input type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '" class="widefat"' . ($req ? ' required' : '') . ' ' . ($o['attrs'] ?? '') . '>';
        }
        echo '</label></p>';
    }

    /** @param array<string|int,string> $options */
    private static function select(string $name, string $label, array $options, $current = '', bool $required = false): void
    {
        echo '<p><label>' . esc_html($label) . ($required ? ' *' : '') . '<br><select name="' . esc_attr($name) . '" class="widefat"' . ($required ? ' required' : '') . '>';
        foreach ($options as $val => $text) {
            echo '<option value="' . esc_attr((string) $val) . '"' . selected((string) $current, (string) $val, false) . '>' . esc_html((string) $text) . '</option>';
        }
        echo '</select></label></p>';
    }

    private static function checkbox(string $name, string $label, bool $checked): void
    {
        echo '<p><input type="hidden" name="' . esc_attr($name) . '" value="0"><label><input type="checkbox" name="' . esc_attr($name) . '" value="1"' . checked($checked, true, false) . '> ' . esc_html($label) . '</label></p>';
    }

    private static function artist_options(): array
    {
        $o = ['' => I18n::t('select')];
        foreach (Artist::all(500) as $a) {
            $o[(int) $a->id] = $a->display_name ?: trim($a->first_name . ' ' . $a->last_name);
        }
        return $o;
    }

    private static function gallery_options(string $none = '—'): array
    {
        $o = ['' => $none];
        foreach (Gallery::all(500) as $g) {
            $o[(int) $g->id] = $g->name;
        }
        return $o;
    }

    public static function handle_entity_submission(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to perform this action.', 'artiey-aet'), '', ['response' => 403]);
        }
        $entity = sanitize_key((string) wp_unslash($_POST['entity'] ?? ''));
        $classes = [
            'artist' => Artist::class, 'artwork' => Artwork::class, 'gallery' => Gallery::class,
            'curator' => Curator::class, 'collector' => Collector::class, 'exhibition' => Exhibition::class,
        ];
        if (!isset($classes[$entity])) {
            wp_die(esc_html__('Invalid entity.', 'artiey-aet'), '', ['response' => 400]);
        }
        check_admin_referer('art_coa_save_' . $entity);
        $data = wp_unslash($_POST);
        $action = sanitize_key((string) ($data['art_coa_action'] ?? ''));
        if (!in_array($action, ['save_' . $entity, 'update_' . $entity], true)) {
            wp_die(esc_html__('Invalid form action.', 'artiey-aet'), '', ['response' => 400]);
        }
        $class = $classes[$entity];
        $is_update = $action === 'update_' . $entity;
        if (in_array($entity, ['artist', 'artwork', 'gallery', 'exhibition'], true) && class_exists('\\WPArtCOA\\ContentBridge')) {
            $result = \WPArtCOA\ContentBridge::save_from_form($entity, $data, $is_update ? absint($data['item_id'] ?? 0) : 0);
        } else {
            $result = $is_update ? $class::update(absint($data['item_id'] ?? 0), $data) : $class::create($data);
        }
        $page = ['artist'=>'wp-art-coa-artists','artwork'=>'wp-art-coa-artworks','gallery'=>'wp-art-coa-galleries','curator'=>'wp-art-coa-curators','collector'=>'wp-art-coa-collectors','exhibition'=>'wp-art-coa-exhibitions'][$entity];
        $notice = ['success' => !empty($result['success']), 'message' => (string) ($result['message'] ?? (!empty($result['success']) ? 'با موفقیت ذخیره شد.' : 'ذخیره‌سازی انجام نشد.'))];
        if (!empty($result['serial'])) { $notice['message'] .= ' سریال: ' . (string) $result['serial']; }
        set_transient('art_coa_entity_notice_' . get_current_user_id(), $notice, 60);
        $url = admin_url('admin.php?page=' . $page);
        if ($is_update) { $url = add_query_arg('edit', absint($data['item_id'] ?? 0), $url); }
        elseif (empty($result['success'])) { $url = add_query_arg('add', '1', $url); }
        wp_safe_redirect(add_query_arg('art_coa_notice', '1', $url));
        exit;
    }

    public static function render_entity_notice(): void
    {
        if (empty($_GET['art_coa_notice'])) { return; }
        $notice = get_transient('art_coa_entity_notice_' . get_current_user_id());
        delete_transient('art_coa_entity_notice_' . get_current_user_id());
        if (!is_array($notice)) { return; }
        $class = !empty($notice['success']) ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html((string) $notice['message']) . '</p></div>';
    }

    /**
     * Create / update handler shared by artists, artworks, galleries, curators, collectors, exhibitions.
     * Actions: save_{entity} (create) and update_{entity} (edit, with item_id).
     */
    private static function handle_entity(string $entity, string $class): ?array
    {
        $action = sanitize_key((string) ($_POST['art_coa_action'] ?? ''));
        if (!in_array($action, ['save_' . $entity, 'update_' . $entity], true)) {
            return null;
        }
        check_admin_referer('art_coa_save_' . $entity);
        if (!current_user_can('manage_options')) {
            return null;
        }
        $data = wp_unslash($_POST);
        if (in_array($entity, ['artist', 'artwork', 'gallery', 'exhibition'], true) && class_exists('\\WPArtCOA\\ContentBridge')) {
            $res = \WPArtCOA\ContentBridge::save_from_form($entity, $data, $action === 'save_' . $entity ? 0 : absint($data['item_id'] ?? 0));
        } else {
            $res = $action === 'save_' . $entity
                ? $class::create($data)
                : $class::update(absint($data['item_id'] ?? 0), $data);
        }
        self::notice($res);
        return $res;
    }

    private static function notice(array $res): void
    {
        $class = !empty($res['success']) ? 'notice-success' : 'notice-error';
        $msg   = $res['message'] ?? (!empty($res['success']) ? 'با موفقیت ذخیره شد.' : 'خطا');
        if (!empty($res['serial'])) {
            $msg .= ' سریال: ' . $res['serial'];
        }
        echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($msg) . '</p></div>';
    }
}
