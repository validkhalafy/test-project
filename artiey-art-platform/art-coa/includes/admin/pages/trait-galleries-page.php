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
trait GalleriesPage
{
    public static function page_galleries(): void
    {
        self::handle_entity('gallery', Gallery::class);
        $edit  = self::editing(Gallery::class);
        $items = Gallery::all();
        ?>
        <div class="wrap art-coa-wrap">
            <h1><?php echo esc_html(I18n::t('galleries')); ?> <button type="button" class="button button-primary" onclick="document.getElementById('art-form-gallery').style.display='block'">+ <?php echo esc_html(I18n::t('add_gallery')); ?></button></h1>
            <?php
            self::form_open('gallery', 'gallery', $edit, $edit ? $edit->name : I18n::t('add_gallery'));
            self::field('name', I18n::t('gallery_name'), $edit->name ?? '', ['required' => true]);
            self::field('email', I18n::t('email'), $edit->email ?? '', ['type' => 'email']);
            self::field('phone', I18n::t('phone'), $edit->phone ?? '');
            self::field('city', I18n::t('city'), $edit->city ?? '');
            self::field('country', I18n::t('country'), $edit->country ?? '');
            self::field('address', I18n::t('address'), $edit->address ?? '', ['type' => 'textarea', 'rows' => 2]);
            self::field('website', I18n::t('website'), $edit->website ?? '', ['type' => 'url']);
            echo '<div class="art-coa-form-grid">';
            echo \WPArtCOA\Media::picker('logo', $edit, ['label' => I18n::t('logo'), 'shape' => 'square']); // phpcs:ignore
            echo \WPArtCOA\Media::picker('cover', $edit, ['label' => I18n::t('cover_image'), 'shape' => 'wide', 'help' => I18n::t('help_cover')]); // phpcs:ignore
            echo '</div>';
            self::field('brand_color', I18n::is_fa() ? 'رنگ سازمانی گالری' : 'Gallery brand color', $edit->brand_color ?? '#1a365d', ['type' => 'color']);
            self::field('brand_color_2', I18n::is_fa() ? 'رنگ دوم' : 'Secondary brand color', $edit->brand_color_2 ?? '#b32d2e', ['type' => 'color']);
            echo \WPArtCOA\Media::picker('signature', (object) ['signature_url' => $edit->signature_url ?? ''], ['label' => I18n::is_fa() ? 'تصویر امضا / مهر گالری' : 'Gallery signature / seal image', 'shape' => 'landscape']); // phpcs:ignore
            self::field('signature_name', I18n::is_fa() ? 'نام امضاکننده گالری' : 'Gallery signer name', $edit->signature_name ?? '');
            self::field('about', I18n::t('about_gallery'), $edit->about ?? '', ['type' => 'textarea']);
            self::form_close('gallery', $edit);
            ?>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th class="aap-s-a57414">ID</th><th class="aap-s-191371"></th><th>نام</th><th>اسلاگ</th><th>شهر</th><th>وضعیت</th><th class="aap-s-c66442"><?php echo esc_html(I18n::t('actions')); ?></th></tr></thead>
                <tbody>
                <?php if (!$items): ?>
                    <tr><td colspan="7" class="aap-center">گالری ثبت نشده</td></tr>
                <?php else: foreach ($items as $g): ?>
                    <tr>
                        <td><?php echo (int)$g->id; ?></td>
                        <td><?php echo self::thumb($g, 'logo'); // phpcs:ignore ?></td>
                        <td><strong><?php echo esc_html($g->name); ?></strong>
                            <br><a href="<?php echo esc_url(home_url('/art-coa/gallery/' . $g->slug)); ?>" target="_blank">مشاهده پروفایل</a>
                        </td>
                        <td><code><?php echo esc_html($g->slug); ?></code></td>
                        <td><?php echo esc_html($g->city); ?></td>
                        <td><span class="art-badge art-badge-<?php echo esc_attr($g->status); ?>"><?php echo esc_html($g->status); ?></span></td>
                        <td><a class="button button-small" href="<?php echo self::edit_url((int) $g->id); // phpcs:ignore ?>"><?php echo esc_html(I18n::t('edit')); ?></a></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
