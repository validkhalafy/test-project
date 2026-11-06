<?php
declare(strict_types=1);

namespace WPArtCOA;

defined('ABSPATH') || exit;

/** Admin screen that customises the registration form: roles, fields, required flags, moderation, verification. */
final class MemberFormsAdmin
{
    public const SLUG = 'artiey-aet-member-form';

    public static function menu(): void
    {
        add_submenu_page('artiey-platform', 'فرم ثبت‌نام اعضا', 'فرم ثبت‌نام اعضا', 'manage_options', self::SLUG, [self::class, 'render']);
    }

    /** Approve or suspend a curator/collector (artists and galleries are approved by publishing their post). */
    public static function set_status(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('دسترسی کافی ندارید.', 'artiey-aet'));
        }
        $kind = sanitize_key((string) wp_unslash($_POST['kind'] ?? ''));
        $id = absint($_POST['id'] ?? 0);
        $status = sanitize_key((string) wp_unslash($_POST['status'] ?? ''));
        check_admin_referer('art_coa_person_status_' . $kind . '_' . $id);
        $tables = ['curator' => ['curators', 'wp-art-coa-curators'], 'collector' => ['collectors', 'wp-art-coa-collectors']];
        if (!isset($tables[$kind]) || $id <= 0 || !in_array($status, ['active', 'inactive'], true)) {
            wp_die(esc_html__('درخواست نامعتبر است.', 'artiey-aet'));
        }
        global $wpdb;
        $wpdb->update(Database::table($tables[$kind][0]), ['status' => $status], ['id' => $id]);
        Audit::log($kind, $id, $status === 'active' ? 'approved' : 'suspended', ['by' => get_current_user_id()]);
        Cache::flush_stats();
        if (class_exists('Artiey_AET_Cache_Control')) {
            \Artiey_AET_Cache_Control::invalidate_content();
        }
        wp_safe_redirect(add_query_arg(['page' => $tables[$kind][1], 'status_changed' => 1], admin_url('admin.php')));
        exit;
    }

    /** Inline approve/suspend form for one row of the people tables. */
    public static function status_cell(string $kind, object $row): string
    {
        $id = (int) $row->id;
        $active = ($row->status ?? 'active') === 'active';
        $next = $active ? 'inactive' : 'active';
        return '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline">'
            . '<input type="hidden" name="action" value="art_coa_set_person_status">'
            . '<input type="hidden" name="kind" value="' . esc_attr($kind) . '"><input type="hidden" name="id" value="' . $id . '">'
            . '<input type="hidden" name="status" value="' . esc_attr($next) . '">'
            . wp_nonce_field('art_coa_person_status_' . $kind . '_' . $id, '_wpnonce', true, false)
            . '<span style="margin-inline-end:6px">' . esc_html($active ? MemberForms::t('فعال', 'Active') : MemberForms::t('در انتظار/غیرفعال', 'Pending/inactive')) . '</span>'
            . '<button class="button button-small' . ($active ? '' : ' button-primary') . '">' . esc_html($active ? MemberForms::t('تعلیق', 'Suspend') : MemberForms::t('تأیید و انتشار', 'Approve')) . '</button></form>';
    }

    public static function save(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('دسترسی کافی ندارید.', 'artiey-aet'));
        }
        check_admin_referer('art_coa_member_form_save');
        $raw = isset($_POST['mf']) && is_array($_POST['mf']) ? (array) wp_unslash($_POST['mf']) : [];
        // Unchecked checkboxes are absent from the request; make them explicit zeros before sanitising.
        foreach (['moderation', 'verify_email', 'notify_admin'] as $flag) {
            $raw[$flag] = !empty($raw[$flag]) ? 1 : 0;
        }
        foreach (MemberForms::schema() as $role => $def) {
            $raw['roles'][$role]['enabled'] = !empty($raw['roles'][$role]['enabled']) ? 1 : 0;
            foreach ($def['fields'] as $field) {
                $raw['fields'][$role][$field['name']]['enabled'] = !empty($raw['fields'][$role][$field['name']]['enabled']) ? 1 : 0;
                $raw['fields'][$role][$field['name']]['required'] = !empty($raw['fields'][$role][$field['name']]['required']) ? 1 : 0;
            }
        }
        $clean = MemberForms::sanitize_settings($raw);
        // Never leave the form without a role.
        if (!array_filter(array_column((array) $clean['roles'], 'enabled'))) {
            $first = (string) array_key_first(MemberForms::schema());
            $clean['roles'][$first]['enabled'] = 1;
        }
        update_option(MemberForms::OPTION, $clean, false);
        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'saved' => 1], admin_url('admin.php')));
        exit;
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $s = MemberForms::settings();
        ?>
        <div class="wrap" dir="rtl">
            <h1>فرم ثبت‌نام اعضا</h1>
            <?php if (isset($_GET['saved'])) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                <div class="notice notice-success is-dismissible"><p>تنظیمات ذخیره شد.</p></div>
            <?php endif; ?>
            <p>شورت‌کد: <code>[art_coa_register]</code> — برای محدودکردن نقش‌ها: <code>[art_coa_register roles="artist,gallery" default="gallery"]</code>. ویرایش پروفایل: <code>[art_coa_member_profile]</code> (در داشبورد اعضا هم هست).</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="art_coa_member_form_save">
                <?php wp_nonce_field('art_coa_member_form_save'); ?>

                <h2>امنیت و تأیید</h2>
                <table class="form-table" role="presentation"><tbody>
                    <tr><th>تأیید ایمیل</th><td><label><input type="checkbox" name="mf[verify_email]" value="1" <?php checked(!empty($s['verify_email'])); ?>> قبل از ساخت پروفایل و ورود، ایمیل با پیوند یک‌بارمصرف (۴۸ ساعته) تأیید شود</label>
                        <p class="description">پیشنهاد می‌شود فعال بماند: از ثبت‌نام با ایمیل دیگران و تصاحب پروفایل هنرمند موجود جلوگیری می‌کند و وجود یا نبود یک ایمیل را فاش نمی‌کند. حساب‌های تأییدنشده بعد از ۷ روز حذف می‌شوند.</p></td></tr>
                    <tr><th>تأیید مدیر</th><td><label><input type="checkbox" name="mf[moderation]" value="1" <?php checked(!empty($s['moderation'])); ?>> پروفایل عمومی هنرمند، گالری، کیوریتور و مجموعه‌دار تا تأیید مدیر منتشر نشود</label>
                        <p class="description">پروفایل در حالت «در انتظار بررسی» ساخته می‌شود و با انتشار آن در وردپرس عمومی می‌شود. از اسپم و پروفایل جعلی در نتایج گوگل جلوگیری می‌کند.</p></td></tr>
                    <tr><th>ایمیل به مدیر</th><td><label><input type="checkbox" name="mf[notify_admin]" value="1" <?php checked(!empty($s['notify_admin'])); ?>> با هر ثبت‌نام جدید به ایمیل مدیر پیام برود</label></td></tr>
                    <tr><th><label for="mf-minpw">حداقل طول رمز عبور</label></th><td><input id="mf-minpw" type="number" min="8" max="64" name="mf[min_password]" value="<?php echo esc_attr((string) $s['min_password']); ?>"></td></tr>
                    <tr><th><label for="mf-terms">صفحهٔ قوانین</label></th><td><?php wp_dropdown_pages(['name' => 'mf[terms_page]', 'id' => 'mf-terms', 'selected' => (int) $s['terms_page'], 'show_option_none' => '— بدون تیک پذیرش قوانین —', 'option_none_value' => '0']); ?></td></tr>
                </tbody></table>

                <h2>نقش‌ها و فیلدها</h2>
                <p class="description">فیلدهای قفل‌شده (نام) ضروری‌اند و قابل حذف نیستند. عنوان خالی یعنی متن پیش‌فرض.</p>
                <?php foreach (MemberForms::schema() as $role => $def) :
                    $rs = $s['roles'][$role] ?? ['enabled' => 1, 'label' => '', 'desc' => '']; ?>
                    <div class="postbox" style="padding:12px 16px;margin-top:14px">
                        <h3 style="margin-top:0"><?php echo esc_html($def['icon'] . ' ' . $def['label'][0]); ?>
                            <label style="font-weight:400;margin-inline-start:14px"><input type="checkbox" name="mf[roles][<?php echo esc_attr($role); ?>][enabled]" value="1" <?php checked(!empty($rs['enabled'])); ?>> فعال در فرم</label></h3>
                        <p><input type="text" class="regular-text" name="mf[roles][<?php echo esc_attr($role); ?>][label]" value="<?php echo esc_attr((string) $rs['label']); ?>" placeholder="<?php echo esc_attr($def['label'][0]); ?>">
                           <input type="text" class="large-text" name="mf[roles][<?php echo esc_attr($role); ?>][desc]" value="<?php echo esc_attr((string) $rs['desc']); ?>" placeholder="<?php echo esc_attr($def['desc'][0]); ?>"></p>
                        <table class="widefat striped"><thead><tr><th>فیلد</th><th>نمایش</th><th>الزامی</th><th>عنوان سفارشی</th></tr></thead><tbody>
                        <?php foreach ($def['fields'] as $field) :
                            $fs = $s['fields'][$role][$field['name']] ?? ['enabled' => 1, 'required' => (int) $field['required'], 'label' => '']; ?>
                            <tr>
                                <td><?php echo esc_html($field['label'][0]); ?><?php echo !empty($field['locked']) ? ' 🔒' : ''; ?></td>
                                <td><input type="checkbox" name="mf[fields][<?php echo esc_attr($role); ?>][<?php echo esc_attr($field['name']); ?>][enabled]" value="1" <?php checked(!empty($fs['enabled'])); ?> <?php disabled(!empty($field['locked'])); ?>></td>
                                <td><input type="checkbox" name="mf[fields][<?php echo esc_attr($role); ?>][<?php echo esc_attr($field['name']); ?>][required]" value="1" <?php checked(!empty($fs['required'])); ?> <?php disabled(!empty($field['locked'])); ?>></td>
                                <td><input type="text" name="mf[fields][<?php echo esc_attr($role); ?>][<?php echo esc_attr($field['name']); ?>][label]" value="<?php echo esc_attr((string) $fs['label']); ?>" placeholder="<?php echo esc_attr($field['label'][0]); ?>"></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody></table>
                        <h4>فیلدهای اختصاصی این نقش</h4>
                        <p class="description">حداکثر ۲۰ فیلد قابل تعریف است. ساختار JSON: نام فنی، عنوان، نوع، اجباری، حداکثر طول و در فیلد انتخابی گزینه‌ها. این داده‌ها به‌صورت امن در اطلاعات حساب عضو ذخیره می‌شوند.</p>
                        <?php
                        $custom = (array) ($s['custom_fields'][$role] ?? []);
                        $custom_json = [];
                        foreach ($custom as $cf) {
                            $custom_json[] = [
                                'name' => $cf['name'], 'label' => $cf['label'][0] ?? '',
                                'type' => $cf['type'], 'required' => !empty($cf['required']),
                                'max' => (int) ($cf['max'] ?? 200),
                                'options' => (object) ($cf['options'] ?? []),
                            ];
                        }
                        ?>
                        <div class="acmf-builder" data-acmf-builder="<?php echo esc_attr($role); ?>">
                            <div class="acmf-builder__head"><strong>طراحی بصری فیلدها</strong><button type="button" class="button button-primary acmf-add">+ افزودن فیلد</button></div>
                            <p class="description">فیلدها را اضافه کنید و ترتیب آن‌ها را تغییر دهید. نام فنی فقط با حروف انگلیسی کوچک، عدد و زیرخط پذیرفته می‌شود.</p>
                            <div class="acmf-rows">
                                <?php foreach ($custom_json as $cf) : ?>
                                    <div class="acmf-row">
                                        <label>عنوان نمایشی<input type="text" data-key="label" value="<?php echo esc_attr((string) $cf['label']); ?>" maxlength="100"></label>
                                        <label>نام فنی<input type="text" data-key="name" value="<?php echo esc_attr((string) $cf['name']); ?>" maxlength="40" dir="ltr" pattern="[a-z][a-z0-9_]{1,39}"></label>
                                        <label>نوع فیلد<select data-key="type">
                                            <?php foreach (['text'=>'متن','email'=>'ایمیل','tel'=>'تلفن','url'=>'لینک','textarea'=>'متن بلند','number'=>'عدد','date'=>'تاریخ','select'=>'فهرست انتخاب','checkbox'=>'تأیید/چک‌باکس'] as $type => $label) : ?>
                                                <option value="<?php echo esc_attr($type); ?>" <?php selected($cf['type'], $type); ?>><?php echo esc_html($label); ?></option>
                                            <?php endforeach; ?>
                                        </select></label>
                                        <label>حداکثر طول<input type="number" data-key="max" min="1" max="2000" value="<?php echo esc_attr((string) $cf['max']); ?>"></label>
                                        <label class="acmf-required"><input type="checkbox" data-key="required" <?php checked(!empty($cf['required'])); ?>> اجباری</label>
                                        <label class="acmf-options">گزینه‌ها (هر گزینه در یک خط)<textarea data-key="options" rows="2" data-optmap="<?php echo esc_attr(wp_json_encode(array_flip((array) $cf['options']), JSON_UNESCAPED_UNICODE)); ?>"><?php echo esc_textarea(implode("\n", array_values((array) $cf['options']))); ?></textarea></label>
                                        <div class="acmf-actions"><button type="button" class="button acmf-up" aria-label="انتقال به بالا">↑</button><button type="button" class="button acmf-down" aria-label="انتقال به پایین">↓</button><button type="button" class="button-link-delete acmf-remove">حذف فیلد</button></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="acmf-preview" aria-live="polite"><strong>پیش‌نمایش فیلدهای این نقش</strong><div class="acmf-preview__items"></div></div>
                            <details><summary>ویرایش پیشرفته JSON (اختیاری)</summary><p class="description">در صورت ویرایش دستی، ابتدا صفحه را ذخیره کنید؛ فرم بصری در بارگذاری بعدی از همین تنظیمات ساخته می‌شود.</p>
                                <textarea class="large-text code acmf-json" rows="5" dir="ltr" spellcheck="false" name="mf[custom_fields][<?php echo esc_attr($role); ?>]"><?php echo esc_textarea(wp_json_encode($custom_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></textarea>
                            </details>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php submit_button('ذخیرهٔ تنظیمات'); ?>
            </form>
        </div>
        <style>
        .acmf-builder{border:1px solid #dcdcde;background:#fff;border-radius:10px;padding:14px;margin:12px 0 18px}.acmf-builder__head{display:flex;align-items:center;justify-content:space-between;gap:12px}.acmf-rows{display:grid;gap:10px;margin:12px 0}.acmf-row{display:grid;grid-template-columns:34px 1.2fr 1fr 1fr .65fr auto;gap:10px;align-items:end;border:1px solid #e2e4e7;border-radius:8px;padding:12px;background:#f9fafb}.acmf-row.is-dragging{opacity:.45;border:2px dashed #2271b1}.acmf-row.is-drop-target{outline:2px solid #2271b1;outline-offset:2px}.acmf-drag{cursor:grab;display:flex;align-items:center;justify-content:center;min-height:34px;border:1px solid #c3c4c7;border-radius:5px;background:#fff;color:#50575e;font-size:18px;touch-action:none}.acmf-drag:focus{outline:2px solid #2271b1;outline-offset:2px}.acmf-row[aria-grabbed=true]{background:#f0f6fc}.acmf-row label{display:grid;gap:5px;font-size:12px;font-weight:600}.acmf-row input[type=text],.acmf-row input[type=number],.acmf-row select,.acmf-row textarea{width:100%;max-width:100%}.acmf-row .acmf-required{display:flex;align-items:center;gap:5px}.acmf-options{grid-column:1/-1}.acmf-actions{grid-column:1/-1;display:flex;gap:8px;align-items:center}.acmf-json{margin-top:12px}.acmf-preview{border-top:1px solid #e2e4e7;padding-top:12px;margin-top:14px}.acmf-preview__items{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px;margin-top:10px}.acmf-preview__item{border:1px dashed #c3c4c7;border-radius:7px;padding:9px;background:#fbfbfc}.acmf-preview__item small{display:block;color:#646970;margin-top:4px}@media(max-width:900px){.acmf-row{grid-template-columns:34px 1fr 1fr}.acmf-options,.acmf-actions{grid-column:1/-1}}
        </style>
        <script>
        (function(){
          'use strict';
          function init(root){
            if(root.dataset.ready==='1')return;
            root.dataset.ready='1';
            var rows=root.querySelector('.acmf-rows'),json=root.querySelector('.acmf-json'),preview=root.querySelector('.acmf-preview__items');
            var dragged=null;
            /* Keys must not depend on the label's script (Persian labels produced empty keys). Existing keys are kept so saved member answers still match. */
            function optKey(label){var h=5381,i;for(i=0;i<label.length;i++){h=((h<<5)+h+label.charCodeAt(i))>>>0;}return 'o'+h.toString(36);}
            function makeHandle(){
              var h=document.createElement('button');h.type='button';h.className='button acmf-drag';h.textContent='⠿';
              h.setAttribute('aria-label','برای جابه‌جایی فیلد از ماوس بکشید یا با کلیدهای جهت‌دار مرتب کنید');
              h.title='گرفتن و جابه‌جایی فیلد';return h;
            }
            function enhance(row){
              if(!row.querySelector('.acmf-drag'))row.insertBefore(makeHandle(),row.firstChild);
              row.setAttribute('role','group');row.setAttribute('aria-roledescription','فیلد قابل جابه‌جایی');
            }
            function sync(){
              var out=[];
              rows.querySelectorAll('.acmf-row').forEach(function(row){
                enhance(row);
                var v={};
                row.querySelectorAll('[data-key]').forEach(function(el){
                  var k=el.dataset.key;
                  if(k==='required')v[k]=!!el.checked;
                  else if(k==='max')v[k]=Math.max(1,Math.min(2000,parseInt(el.value||'200',10)||200));
                  else if(k==='options'){
                    var opts={};
                                        var known={};try{known=JSON.parse(el.getAttribute('data-optmap')||'{}');}catch(err){known={};}
                    el.value.split(/\r?\n/).map(function(x){return x.trim()}).filter(Boolean).slice(0,30).forEach(function(x){
                      var key=Object.prototype.hasOwnProperty.call(known,x)?known[x]:optKey(x);
                      if(!Object.prototype.hasOwnProperty.call(opts,key))opts[key]=x;
                    });v[k]=opts;
                  } else v[k]=el.value.trim();
                });
                if(v.name&&/^[a-z][a-z0-9_]{1,39}$/.test(v.name)&&v.label)out.push(v);
              });
              json.value=JSON.stringify(out,null,2);
              preview.replaceChildren();
              out.forEach(function(field){
                var item=document.createElement('div'),label=document.createElement('strong'),meta=document.createElement('small');
                item.className='acmf-preview__item';label.textContent=field.label+(field.required?' *':'');
                meta.textContent=field.name+' · '+field.type;item.append(label,meta);preview.appendChild(item);
              });
            }
            function row(){
              var d=document.createElement('div');d.className='acmf-row';
              d.innerHTML='<label>عنوان نمایشی<input type="text" data-key="label" maxlength="100"></label><label>نام فنی<input type="text" data-key="name" maxlength="40" dir="ltr" pattern="[a-z][a-z0-9_]{1,39}"></label><label>نوع فیلد<select data-key="type"><option value="text">متن</option><option value="email">ایمیل</option><option value="tel">تلفن</option><option value="url">لینک</option><option value="textarea">متن بلند</option><option value="number">عدد</option><option value="date">تاریخ</option><option value="select">فهرست انتخاب</option><option value="checkbox">چک‌باکس</option></select></label><label>حداکثر طول<input type="number" data-key="max" min="1" max="2000" value="200"></label><label class="acmf-required"><input type="checkbox" data-key="required"> اجباری</label><label class="acmf-options">گزینه‌ها (هر گزینه در یک خط)<textarea data-key="options" rows="2"></textarea></label><div class="acmf-actions"><button type="button" class="button acmf-up">↑</button><button type="button" class="button acmf-down">↓</button><button type="button" class="button-link-delete acmf-remove">حذف فیلد</button></div>';
              enhance(d);return d;
            }
            function moveBefore(source,target,after){
              if(!source||!target||source===target)return;
              rows.insertBefore(source,after?target.nextSibling:target);
              sync();
            }
            root.addEventListener('click',function(e){
              var b=e.target.closest('button');if(!b)return;
              if(b.classList.contains('acmf-add')){
                if(rows.children.length>=20){window.alert('حداکثر ۲۰ فیلد برای هر نقش مجاز است.');return;}
                rows.appendChild(row());sync();
              }else if(b.classList.contains('acmf-remove')){
                b.closest('.acmf-row').remove();sync();
              }else if(b.classList.contains('acmf-up')){
                var up=b.closest('.acmf-row');if(up.previousElementSibling)rows.insertBefore(up,up.previousElementSibling);sync();
              }else if(b.classList.contains('acmf-down')){
                var down=b.closest('.acmf-row');if(down.nextElementSibling)rows.insertBefore(down.nextElementSibling,down);sync();
              }
            });
            /* Only the handle makes a row draggable; a draggable ancestor breaks mouse text selection inside inputs in Firefox. */
            rows.addEventListener('pointerdown',function(e){var h=e.target.closest('.acmf-drag');if(h){h.closest('.acmf-row').draggable=true;}});
            function undrag(){rows.querySelectorAll('.acmf-row').forEach(function(r){r.draggable=false;});}
            document.addEventListener('pointerup',undrag);document.addEventListener('pointercancel',undrag);
            rows.addEventListener('dragstart',function(e){
              var handle=e.target.closest('.acmf-drag'),r=e.target.closest('.acmf-row');
              if(!r||!handle){e.preventDefault();return;}
              dragged=r;r.classList.add('is-dragging');e.dataTransfer.effectAllowed='move';
              try{e.dataTransfer.setData('text/plain','artie-field');}catch(err){}
            });
            rows.addEventListener('dragover',function(e){
              if(!dragged)return;e.preventDefault();e.dataTransfer.dropEffect='move';
              var target=e.target.closest('.acmf-row');if(!target||target===dragged)return;
              rows.querySelectorAll('.is-drop-target').forEach(function(x){x.classList.remove('is-drop-target');});
              target.classList.add('is-drop-target');
            });
            rows.addEventListener('drop',function(e){
              if(!dragged)return;e.preventDefault();
              var target=e.target.closest('.acmf-row');
              if(target&&target!==dragged){
                var rect=target.getBoundingClientRect();moveBefore(dragged,target,e.clientY>rect.top+rect.height/2);
              }
            });
            function clearDrag(){
              if(dragged)dragged.classList.remove('is-dragging');
              dragged=null;rows.querySelectorAll('.is-drop-target').forEach(function(x){x.classList.remove('is-drop-target');});
            }
            rows.addEventListener('dragend',function(){clearDrag();undrag();sync();});
            rows.addEventListener('keydown',function(e){
              var handle=e.target.closest('.acmf-drag');if(!handle)return;
              var r=handle.closest('.acmf-row');
              if(e.altKey&&e.key==='ArrowUp'&&r.previousElementSibling){e.preventDefault();rows.insertBefore(r,r.previousElementSibling);sync();handle.focus();}
              if(e.altKey&&e.key==='ArrowDown'&&r.nextElementSibling){e.preventDefault();rows.insertBefore(r.nextElementSibling,r);sync();handle.focus();}
            });
            root.addEventListener('input',sync);root.addEventListener('change',sync);
            sync();
          }
          document.querySelectorAll('[data-acmf-builder]').forEach(init);
        })();
        </script>
        <?php
    }
}
