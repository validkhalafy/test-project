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
trait PeoplePage
{
    public static function page_curators(): void
    {
        self::handle_entity('curator', Curator::class);
        $edit  = self::editing(Curator::class);
        $items = Curator::all();
        ?>
        <div class="wrap art-coa-wrap">
            <h1><?php echo esc_html(I18n::t('curators')); ?> <button type="button" class="button button-primary" onclick="document.getElementById('art-form-curator').style.display='block'">+ <?php echo esc_html(I18n::t('add_curator')); ?></button></h1>
            <?php
            self::form_open('curator', 'curator', $edit, $edit ? $edit->display_name : I18n::t('add_curator'));
            self::field('display_name', I18n::t('display_name'), $edit->display_name ?? '', ['required' => true]);
            self::field('specialties', I18n::t('specialties'), $edit->specialties ?? '');
            self::field('affiliation', I18n::is_fa() ? 'وابستگی سازمانی' : 'Institutional affiliation', $edit->affiliation ?? '');
            self::field('years_experience', I18n::is_fa() ? 'سابقهٔ حرفه‌ای (سال)' : 'Years of experience', $edit->years_experience ?? '', ['type' => 'number', 'min' => 0, 'max' => 80]);
            self::field('languages', I18n::is_fa() ? 'زبان‌ها' : 'Languages', $edit->languages ?? '', ['hint' => I18n::is_fa() ? 'با ویرگول جدا کنید.' : 'Separate with commas.']);
            self::field('education', I18n::is_fa() ? 'تحصیلات و آموزش تخصصی' : 'Education and specialist training', $edit->education ?? '', ['type' => 'textarea']);
            self::field('credentials', I18n::is_fa() ? 'سوابق، پروژه‌ها و دستاوردهای شاخص' : 'Selected projects and credentials', $edit->credentials ?? '', ['type' => 'textarea']);
            self::field('curatorial_statement', I18n::is_fa() ? 'بیانیه و رویکرد کیوریتوری' : 'Curatorial statement', $edit->curatorial_statement ?? '', ['type' => 'textarea']);
            self::field('email', I18n::t('email'), $edit->email ?? '', ['type' => 'email']);
            self::field('phone', I18n::t('phone'), $edit->phone ?? '', ['type' => 'tel']);
            self::field('website', I18n::is_fa() ? 'وب‌سایت' : 'Website', $edit->website ?? '', ['type' => 'url']);
            self::field('instagram_url', 'Instagram', $edit->instagram_url ?? '', ['type' => 'url']);
            self::field('linkedin_url', 'LinkedIn', $edit->linkedin_url ?? '', ['type' => 'url']);
            echo \WPArtCOA\Media::picker('avatar', $edit, ['label' => I18n::t('avatar'), 'shape' => 'square']); // phpcs:ignore
            echo \WPArtCOA\Media::picker('cover', $edit, ['label' => I18n::is_fa() ? 'تصویر سربرگ پروفایل' : 'Profile cover']); // phpcs:ignore
            self::field('bio', I18n::t('bio'), $edit->bio ?? '', ['type' => 'textarea']);
            self::form_close('curator', $edit);
            ?>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th class="aap-s-a57414">ID</th><th class="aap-s-191371"></th><th>نام</th><th>وابستگی</th><th>تخصص</th><th>وضعیت</th><th class="aap-s-c66442"><?php echo esc_html(I18n::t('actions')); ?></th></tr></thead>
                <tbody>
                <?php if (!$items): ?>
                    <tr><td colspan="7" class="aap-center">کیوریتوری ثبت نشده</td></tr>
                <?php else: foreach ($items as $c): ?>
                    <tr>
                        <td><?php echo (int)$c->id; ?></td>
                        <td><?php echo self::thumb($c, 'avatar', 'is-round'); // phpcs:ignore ?></td>
                        <td><strong><?php echo esc_html($c->display_name); ?></strong></td>
                        <td><?php echo esc_html($c->affiliation ?: '—'); ?></td>
                        <td><?php echo esc_html($c->specialties); ?></td>
                        <td><?php echo \WPArtCOA\MemberFormsAdmin::status_cell('curator', $c); // phpcs:ignore ?></td>
                        <td><a class="button button-small" href="<?php echo self::edit_url((int) $c->id); // phpcs:ignore ?>"><?php echo esc_html(I18n::t('edit')); ?></a></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function page_collectors(): void
    {
        self::handle_entity('collector', Collector::class);
        $edit  = self::editing(Collector::class);
        $items = Collector::all();
        ?>
        <div class="wrap art-coa-wrap">
            <h1><?php echo esc_html(I18n::t('collectors')); ?> <button type="button" class="button button-primary" onclick="document.getElementById('art-form-collector').style.display='block'">+ <?php echo esc_html(I18n::t('add_collector')); ?></button></h1>
            <?php
            self::form_open('collector', 'collector', $edit, $edit ? $edit->display_name : I18n::t('add_collector'));
            self::field('display_name', I18n::t('display_name'), $edit->display_name ?? '', ['required' => true]);
            self::field('collection_name', I18n::t('collection_name'), $edit->collection_name ?? '');
            echo \WPArtCOA\Media::picker('avatar', $edit, ['label' => I18n::t('avatar'), 'shape' => 'square']); // phpcs:ignore
            self::field('bio', I18n::t('bio'), $edit->bio ?? '', ['type' => 'textarea']);
            self::checkbox('is_public', I18n::t('is_public'), !empty($edit->is_public));
            self::form_close('collector', $edit);
            ?>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th class="aap-s-a57414">ID</th><th class="aap-s-191371"></th><th>نام</th><th>مجموعه</th><th>عمومی</th><th>وضعیت</th><th class="aap-s-c66442"><?php echo esc_html(I18n::t('actions')); ?></th></tr></thead>
                <tbody>
                <?php if (!$items): ?>
                    <tr><td colspan="7" class="aap-center">مجموعه‌داری ثبت نشده</td></tr>
                <?php else: foreach ($items as $c): ?>
                    <tr>
                        <td><?php echo (int)$c->id; ?></td>
                        <td><?php echo self::thumb($c, 'avatar', 'is-round'); // phpcs:ignore ?></td>
                        <td><strong><?php echo esc_html($c->display_name); ?></strong></td>
                        <td><?php echo esc_html($c->collection_name); ?></td>
                        <td><?php echo $c->is_public ? 'بله' : 'خیر'; ?></td>
                        <td><?php echo \WPArtCOA\MemberFormsAdmin::status_cell('collector', $c); // phpcs:ignore ?></td>
                        <td><a class="button button-small" href="<?php echo self::edit_url((int) $c->id); // phpcs:ignore ?>"><?php echo esc_html(I18n::t('edit')); ?></a></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
