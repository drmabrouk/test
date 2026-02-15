<?php if (!defined('ABSPATH')) exit; ?>
<script>
/**
 * SYNDICATE MANAGEMENT - CORE UI ENGINE (ULTRA HARDENED V5)
 * Standard linking and routing fix.
 */
(function(window) {
    const SM_UI = {
        showNotification: function(message, isError = false) {
            const toast = document.createElement('div');
            toast.className = 'sm-toast';
            toast.style.cssText = "position:fixed; top:20px; left:50%; transform:translateX(-50%); background:white; padding:15px 30px; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.15); z-index:10001; display:flex; align-items:center; gap:10px; border-right:5px solid " + (isError ? '#e53e3e' : '#38a169');
            toast.innerHTML = `<strong>${isError ? '✖' : '✓'}</strong> <span>${message}</span>`;
            document.body.appendChild(toast);
            setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = '0.5s'; setTimeout(() => toast.remove(), 500); }, 3000);
        },

        openInternalTab: function(tabId, element) {
            const target = document.getElementById(tabId);
            if (!target || !element) return;
            const container = target.parentElement;
            container.querySelectorAll('.sm-internal-tab').forEach(p => p.style.setProperty('display', 'none', 'important'));
            target.style.setProperty('display', 'block', 'important');
            element.parentElement.querySelectorAll('.sm-tab-btn').forEach(b => b.classList.remove('sm-active'));
            element.classList.add('sm-active');
        }
    };

    window.smShowNotification = SM_UI.showNotification;
    window.smOpenInternalTab = SM_UI.openInternalTab;

    window.smSubmitPayment = function(btn) {
        const form = document.getElementById('record-payment-form');
        const formData = new FormData(form);
        formData.append('action', 'sm_record_payment_ajax');
        formData.append('nonce', '<?php echo wp_create_nonce("sm_finance_action"); ?>');

        btn.disabled = true;
        btn.innerText = 'جاري المعالجة...';

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                smShowNotification('تم تسجيل الدفعة بنجاح');
                if (typeof smOpenFinanceModal === 'function') {
                    smOpenFinanceModal(form.querySelector('[name="member_id"]').value);
                } else {
                    location.reload();
                }
            } else {
                smShowNotification('خطأ: ' + res.data, true);
                btn.disabled = false;
                btn.innerText = 'تأكيد استلام المبلغ';
            }
        });
    };

    // MEDIA UPLOADER FOR LOGO
    window.smOpenMediaUploader = function(inputId) {
        const frame = wp.media({
            title: 'اختر شعار النقابة',
            button: { text: 'استخدام هذا الشعار' },
            multiple: false
        });
        frame.on('select', function() {
            const attachment = frame.state().get('selection').first().toJSON();
            document.getElementById(inputId).value = attachment.url;
        });
        frame.open();
    };

    window.smToggleUserDropdown = function() {
        const menu = document.getElementById('sm-user-dropdown-menu');
        if (menu.style.display === 'none') {
            menu.style.display = 'block';
            document.getElementById('sm-profile-view').style.display = 'block';
            document.getElementById('sm-profile-edit').style.display = 'none';
        } else {
            menu.style.display = 'none';
        }
    };

    window.smEditProfile = function() {
        document.getElementById('sm-profile-view').style.display = 'none';
        document.getElementById('sm-profile-edit').style.display = 'block';
    };

    window.smSaveProfile = function() {
        const name = document.getElementById('sm_edit_display_name').value;
        const email = document.getElementById('sm_edit_user_email').value;
        const pass = document.getElementById('sm_edit_user_pass').value;
        const nonce = '<?php echo wp_create_nonce("sm_profile_action"); ?>';

        const formData = new FormData();
        formData.append('action', 'sm_update_profile_ajax');
        formData.append('display_name', name);
        formData.append('user_email', email);
        formData.append('user_pass', pass);
        formData.append('nonce', nonce);

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                smShowNotification('تم تحديث الملف الشخصي بنجاح');
                setTimeout(() => location.reload(), 500);
            } else {
                smShowNotification('خطأ: ' + res.data, true);
            }
        });
    };

    document.addEventListener('click', function(e) {
        const dropdown = document.querySelector('.sm-user-dropdown');
        const menu = document.getElementById('sm-user-dropdown-menu');
        if (dropdown && !dropdown.contains(e.target)) {
            if (menu) menu.style.display = 'none';
        }
    });

})(window);
</script>

<?php
$user = wp_get_current_user();
$roles = (array)$user->roles;
$is_admin = in_array('administrator', $roles) || current_user_can('manage_options');
$is_sys_admin = in_array('sm_system_admin', $roles);
$is_syndicate_admin = in_array('sm_syndicate_admin', $roles);
$is_syndicate_member = in_array('sm_syndicate_member', $roles);
$is_member = in_array('sm_member', $roles);
$is_officer = $is_syndicate_admin || $is_syndicate_member;

$active_tab = isset($_GET['sm_tab']) ? sanitize_text_field($_GET['sm_tab']) : 'summary';
$is_restricted = $is_member || $is_syndicate_member;
if ($is_restricted && !in_array($active_tab, ['my-profile', 'member-profile'])) {
    $active_tab = 'my-profile';
}

$syndicate = SM_Settings::get_syndicate_info();
$stats = array();

if ($active_tab === 'summary') {
    $stats = SM_DB::get_statistics();
}

// Dynamic Greeting logic
$hour = (int)current_time('G');
$greeting = ($hour >= 5 && $hour < 12) ? 'صباح الخير' : 'مساء الخير';
?>

<div class="sm-admin-dashboard" dir="rtl" style="font-family: 'Rubik', sans-serif; background: #fff; border: 1px solid var(--sm-border-color); border-radius: 12px; overflow: hidden;">
    <!-- OFFICIAL SYSTEM HEADER -->
    <div class="sm-main-header">
        <div style="display: flex; align-items: center; gap: 20px;">
            <?php if (!empty($syndicate['syndicate_logo'])): ?>
                <div style="background: white; padding: 5px; border: 1px solid var(--sm-border-color); border-radius: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                    <img src="<?php echo esc_url($syndicate['syndicate_logo']); ?>" style="height: 45px; width: auto; object-fit: contain; display: block;">
                </div>
            <?php else: ?>
                <div style="background: #f1f5f9; padding: 5px; border: 1px solid var(--sm-border-color); border-radius: 10px; height: 45px; width: 45px; display: flex; align-items: center; justify-content: center; color: #94a3b8;">
                    <span class="dashicons dashicons-building" style="font-size: 24px; width: 24px; height: 24px;"></span>
                </div>
            <?php endif; ?>
            <div>
                <h1 style="margin:0; border: none; padding: 0; color: var(--sm-dark-color); font-weight: 800; font-size: 1.3em; text-decoration: none; line-height: 1;">
                    <?php echo esc_html($syndicate['syndicate_name']); ?>
                </h1>
                <div style="display: inline-flex; flex-direction: column; align-items: center; padding: 5px 15px; background: #f0f4f8; color: #111F35; border-radius: 12px; font-size: 11px; font-weight: 700; margin-top: 6px; border: 1px solid #cbd5e0; line-height: 1.4;">
                    <div>
                        <?php
                        if ($is_admin || $is_sys_admin) echo 'مدير النظام';
                        elseif ($is_syndicate_admin) echo 'مسؤول نقابة';
                        elseif ($is_syndicate_member) echo 'عضو نقابة';
                        elseif ($is_member) echo 'عضو';
                        else echo 'مستخدم النظام';
                        ?>
                    </div>
                    <?php
                    $my_gov_key = get_user_meta($user->ID, 'sm_governorate', true);
                    $govs = SM_Settings::get_governorates();
                    $my_gov_label = $govs[$my_gov_key] ?? '';
                    if ($my_gov_label): ?>
                        <div style="width: 100%; height: 1px; background: #cbd5e0; margin: 3px 0;"></div>
                        <div style="color: var(--sm-primary-color); font-size: 10px;"><?php echo esc_html($my_gov_label); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 20px;">
            <div class="sm-header-info-box" style="text-align: right; border-left: 1px solid var(--sm-border-color); padding-left: 15px;">
                <div style="font-size: 0.85em; font-weight: 700; color: var(--sm-dark-color);"><?php echo date_i18n('l j F Y'); ?></div>
            </div>

            <?php if (current_user_can('sm_manage_licenses')): ?>
                <div style="display: flex; gap: 10px;">
                    <button onclick="window.location.href='<?php echo add_query_arg('sm_tab', 'practice-licenses'); ?>&action=new'" class="sm-btn" style="background: #2c3e50; height: 38px; font-size: 11px; color: white !important; width: auto;">+ إصدار ترخيص مزاولة</button>
                    <button onclick="window.location.href='<?php echo add_query_arg('sm_tab', 'facility-licenses'); ?>&action=new'" class="sm-btn" style="background: #27ae60; height: 38px; font-size: 11px; color: white !important; width: auto;">+ تسجيل منشأة جديدة</button>
                </div>
            <?php endif; ?>

            <div class="sm-user-dropdown" style="position: relative;">
                <div class="sm-user-profile-nav" onclick="smToggleUserDropdown()" style="display: flex; align-items: center; gap: 12px; background: white; padding: 6px 12px; border-radius: 50px; border: 1px solid var(--sm-border-color); cursor: pointer;">
                    <div style="text-align: right;">
                        <div style="font-size: 0.85em; font-weight: 700; color: var(--sm-dark-color);"><?php echo $greeting . '، ' . $user->display_name; ?></div>
                        <div style="font-size: 0.7em; color: #38a169;">متصل الآن <span class="dashicons dashicons-arrow-down-alt2" style="font-size: 10px; width: 10px; height: 10px;"></span></div>
                    </div>
                    <?php echo get_avatar($user->ID, 32, '', '', array('style' => 'border-radius: 50%; border: 2px solid var(--sm-primary-color);')); ?>
                </div>
                <div id="sm-user-dropdown-menu" style="display: none; position: absolute; top: 110%; left: 0; background: white; border: 1px solid var(--sm-border-color); border-radius: 8px; width: 260px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); z-index: 1000; animation: smFadeIn 0.2s ease-out; padding: 10px 0;">
                    <div id="sm-profile-view">
                        <div style="padding: 10px 20px; border-bottom: 1px solid #f0f0f0; margin-bottom: 5px;">
                            <div style="font-weight: 800; color: var(--sm-dark-color);"><?php echo $user->display_name; ?></div>
                            <div style="font-size: 11px; color: var(--sm-text-gray);"><?php echo $user->user_email; ?></div>
                        </div>
                        <?php if (!$is_member): ?>
                            <a href="javascript:smEditProfile()" class="sm-dropdown-item"><span class="dashicons dashicons-edit"></span> تعديل البيانات الشخصية</a>
                        <?php endif; ?>
                        <?php if ($is_member): ?>
                            <a href="javascript:smEditProfile()" class="sm-dropdown-item"><span class="dashicons dashicons-lock"></span> تغيير كلمة المرور</a>
                        <?php endif; ?>
                        <?php if ($is_admin): ?>
                            <a href="<?php echo add_query_arg('sm_tab', 'global-settings'); ?>" class="sm-dropdown-item"><span class="dashicons dashicons-admin-generic"></span> إعدادات النظام</a>
                        <?php endif; ?>
                        <a href="javascript:location.reload()" class="sm-dropdown-item"><span class="dashicons dashicons-update"></span> تحديث الصفحة</a>
                    </div>

                    <div id="sm-profile-edit" style="display: none; padding: 15px;">
                        <div style="font-weight: 800; margin-bottom: 15px; font-size: 13px; border-bottom: 1px solid #eee; padding-bottom: 10px;">تعديل الملف الشخصي</div>
                        <div class="sm-form-group" style="margin-bottom: 10px;">
                            <label class="sm-label" style="font-size: 11px;">الاسم المفضل:</label>
                            <input type="text" id="sm_edit_display_name" class="sm-input" style="padding: 8px; font-size: 12px;" value="<?php echo esc_attr($user->display_name); ?>" <?php if ($is_member) echo 'disabled style="background:#f1f5f9; cursor:not-allowed;"'; ?>>
                        </div>
                        <div class="sm-form-group" style="margin-bottom: 10px;">
                            <label class="sm-label" style="font-size: 11px;">البريد الإلكتروني:</label>
                            <input type="email" id="sm_edit_user_email" class="sm-input" style="padding: 8px; font-size: 12px;" value="<?php echo esc_attr($user->user_email); ?>" <?php if ($is_member) echo 'disabled style="background:#f1f5f9; cursor:not-allowed;"'; ?>>
                        </div>
                        <div class="sm-form-group" style="margin-bottom: 15px;">
                            <label class="sm-label" style="font-size: 11px;">كلمة مرور جديدة (اختياري):</label>
                            <input type="password" id="sm_edit_user_pass" class="sm-input" style="padding: 8px; font-size: 12px;" placeholder="********">
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button onclick="smSaveProfile()" class="sm-btn" style="flex: 1; height: 32px; font-size: 11px; padding: 0;">حفظ</button>
                            <button onclick="document.getElementById('sm-profile-edit').style.display='none'; document.getElementById('sm-profile-view').style.display='block';" class="sm-btn sm-btn-outline" style="flex: 1; height: 32px; font-size: 11px; padding: 0;">إلغاء</button>
                        </div>
                    </div>

                    <hr style="margin: 5px 0; border: none; border-top: 1px solid #eee;">
                    <a href="<?php echo wp_logout_url(home_url('/sm-login')); ?>" class="sm-dropdown-item" style="color: #e53e3e;"><span class="dashicons dashicons-logout"></span> تسجيل الخروج</a>
                </div>
            </div>
        </div>
    </div>

    <div class="sm-admin-layout" style="display: flex; min-height: 800px;">
        <!-- SIDEBAR -->
        <?php $is_restricted = $is_member || $is_syndicate_member; ?>
        <div class="sm-sidebar" style="width: 280px; flex-shrink: 0; background: var(--sm-bg-light); border-left: 1px solid var(--sm-border-color); padding: 20px 0;">
            <ul style="list-style: none; padding: 0; margin: 0;">

                <?php if (!$is_restricted): ?>
                <li class="sm-sidebar-item <?php echo $active_tab == 'summary' ? 'sm-active' : ''; ?>">
                    <a href="<?php echo add_query_arg('sm_tab', 'summary'); ?>" class="sm-sidebar-link"><span class="dashicons dashicons-dashboard"></span> لوحة المعلومات</a>
                </li>
                <?php endif; ?>

                <?php if ($is_restricted): ?>
                    <li class="sm-sidebar-item <?php echo in_array($active_tab, ['my-profile', 'member-profile']) ? 'sm-active' : ''; ?>">
                        <a href="<?php echo add_query_arg('sm_tab', 'my-profile'); ?>" class="sm-sidebar-link"><span class="dashicons dashicons-admin-users"></span> ملفي الشخصي</a>
                    </li>
                <?php endif; ?>

                <?php if (!$is_restricted && ($is_admin || $is_sys_admin || $is_syndicate_admin)): ?>
                    <li class="sm-sidebar-item <?php echo $active_tab == 'members' ? 'sm-active' : ''; ?>">
                        <a href="<?php echo add_query_arg('sm_tab', 'members'); ?>" class="sm-sidebar-link"><span class="dashicons dashicons-groups"></span> إدارة الأعضاء</a>
                    </li>
                    <li class="sm-sidebar-item <?php echo $active_tab == 'update-requests' ? 'sm-active' : ''; ?>" style="position: relative;">
                        <a href="<?php echo add_query_arg('sm_tab', 'update-requests'); ?>" class="sm-sidebar-link">
                            <span class="dashicons dashicons-edit"></span> طلبات التحديث
                            <?php
                            $pending_count = count(SM_DB::get_update_requests('pending'));
                            if ($pending_count > 0): ?>
                                <span class="sm-sidebar-badge"><?php echo $pending_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (!$is_restricted && ($is_admin || $is_sys_admin || $is_syndicate_admin)): ?>
                    <li class="sm-sidebar-item <?php echo $active_tab == 'finance' ? 'sm-active' : ''; ?>">
                        <a href="<?php echo add_query_arg('sm_tab', 'finance'); ?>" class="sm-sidebar-link"><span class="dashicons dashicons-money-alt"></span> الاستحقاقات المالية</a>
                    </li>
                    <li class="sm-sidebar-item <?php echo $active_tab == 'financial-logs' ? 'sm-active' : ''; ?>">
                        <a href="<?php echo add_query_arg('sm_tab', 'financial-logs'); ?>" class="sm-sidebar-link"><span class="dashicons dashicons-media-spreadsheet"></span> سجل العمليات المالية</a>
                    </li>
                    <li class="sm-sidebar-item <?php echo $active_tab == 'practice-licenses' ? 'sm-active' : ''; ?>">
                        <a href="<?php echo add_query_arg('sm_tab', 'practice-licenses'); ?>" class="sm-sidebar-link"><span class="dashicons dashicons-id-alt"></span> تراخيص مزاولة المهنة</a>
                    </li>
                    <li class="sm-sidebar-item <?php echo $active_tab == 'facility-licenses' ? 'sm-active' : ''; ?>">
                        <a href="<?php echo add_query_arg('sm_tab', 'facility-licenses'); ?>" class="sm-sidebar-link"><span class="dashicons dashicons-building"></span> تراخيص المنشآت</a>
                    </li>
                <?php endif; ?>

                <?php if ($is_admin || $is_sys_admin || $is_syndicate_admin): ?>
                    <li class="sm-sidebar-item <?php echo $active_tab == 'staffs' ? 'sm-active' : ''; ?>">
                        <a href="<?php echo add_query_arg('sm_tab', 'staffs'); ?>" class="sm-sidebar-link"><span class="dashicons dashicons-admin-users"></span> إدارة مستخدمي النظام</a>
                    </li>
                <?php endif; ?>

                <?php if ($is_admin || $is_sys_admin || $is_syndicate_admin || $is_syndicate_member): ?>
                    <li class="sm-sidebar-item <?php echo $active_tab == 'printing' ? 'sm-active' : ''; ?>">
                        <a href="<?php echo add_query_arg('sm_tab', 'printing'); ?>" class="sm-sidebar-link"><span class="dashicons dashicons-printer"></span> مركز الطباعة</a>
                    </li>
                <?php endif; ?>

                <?php if ($is_admin || $is_sys_admin || $is_syndicate_admin || $is_syndicate_member): ?>
                    <li class="sm-sidebar-item <?php echo $active_tab == 'surveys' ? 'sm-active' : ''; ?>">
                        <a href="<?php echo add_query_arg('sm_tab', 'surveys'); ?>" class="sm-sidebar-link"><span class="dashicons dashicons-clipboard"></span> استطلاعات الرأي</a>
                    </li>
                <?php endif; ?>

                <?php if ($is_admin || $is_sys_admin): ?>
                    <li class="sm-sidebar-item <?php echo $active_tab == 'global-settings' ? 'sm-active' : ''; ?>">
                        <a href="<?php echo add_query_arg('sm_tab', 'global-settings'); ?>" class="sm-sidebar-link"><span class="dashicons dashicons-admin-generic"></span> إعدادات النظام</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- CONTENT AREA -->
        <div class="sm-main-panel" style="flex: 1; min-width: 0; padding: 40px; background: #fff;">

            <?php
            switch ($active_tab) {
                case 'summary':
                    include SM_PLUGIN_DIR . 'templates/public-dashboard-summary.php';
                    break;

                case 'members':
                    if ($is_admin || current_user_can('sm_manage_members')) {
                        echo '<h3 style="margin-top:0;">إدارة الأعضاء</h3>';
                        include SM_PLUGIN_DIR . 'templates/admin-members.php';
                    }
                    break;

                case 'finance':
                    if ($is_admin || $is_sys_admin || $is_syndicate_admin) {
                        include SM_PLUGIN_DIR . 'templates/admin-finance.php';
                    }
                    break;

                case 'financial-logs':
                    if ($is_admin || $is_sys_admin || $is_syndicate_admin) {
                        include SM_PLUGIN_DIR . 'templates/admin-financial-logs.php';
                    }
                    break;

                case 'practice-licenses':
                    if ($is_admin || $is_sys_admin || $is_syndicate_admin) {
                        include SM_PLUGIN_DIR . 'templates/admin-practice-licenses.php';
                    }
                    break;

                case 'facility-licenses':
                    if ($is_admin || $is_sys_admin || $is_syndicate_admin) {
                        include SM_PLUGIN_DIR . 'templates/admin-facility-licenses.php';
                    }
                    break;


                case 'messaging':
                    include SM_PLUGIN_DIR . 'templates/messaging-center.php';
                    break;

                case 'staff':
                case 'staffs':
                    if ($is_admin || current_user_can('sm_manage_users')) {
                        include SM_PLUGIN_DIR . 'templates/admin-staff.php';
                    }
                    break;

                case 'member-profile':
                case 'my-profile':
                    if ($active_tab === 'my-profile') {
                        $member_by_wp = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sm_members WHERE wp_user_id = %d", get_current_user_id()));
                        if ($member_by_wp) $_GET['member_id'] = $member_by_wp->id;
                    }
                    include SM_PLUGIN_DIR . 'templates/admin-member-profile.php';
                    break;


                case 'printing':
                    if ($is_admin || current_user_can('sm_print_reports')) {
                        include SM_PLUGIN_DIR . 'templates/printing-cards.php';
                    }
                    break;



                case 'surveys':
                    if (!$is_restricted && ($is_admin || $is_sys_admin || $is_syndicate_admin)) {
                        include SM_PLUGIN_DIR . 'templates/admin-surveys.php';
                    }
                    break;

                case 'update-requests':
                    if ($is_admin || $is_sys_admin || $is_syndicate_admin) {
                        include SM_PLUGIN_DIR . 'templates/admin-update-requests.php';
                    }
                    break;

                case 'global-settings':
                    if ($is_admin || current_user_can('sm_manage_system')) {
                        ?>
                        <div class="sm-tabs-wrapper" style="display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #eee; overflow-x: auto; white-space: nowrap; padding-bottom: 10px;">
                            <button class="sm-tab-btn sm-active" onclick="smOpenInternalTab('syndicate-settings', this)">السلطة</button>
                            <button class="sm-tab-btn" onclick="smOpenInternalTab('professional-settings', this)">الدرجات والتخصصات</button>
                            <button class="sm-tab-btn" onclick="smOpenInternalTab('design-settings', this)">تصميم النظام</button>
                            <button class="sm-tab-btn" onclick="smOpenInternalTab('backup-settings', this)">مركز النسخ الاحتياطي</button>
                            <?php if ($is_admin): ?>
                                <button class="sm-tab-btn" onclick="smOpenInternalTab('activity-logs', this)">سجل النشاطات</button>
                            <?php endif; ?>
                        </div>
                        <div id="professional-settings" class="sm-internal-tab" style="display:none;">
                            <form method="post">
                                <?php wp_nonce_field('sm_admin_action', 'sm_admin_nonce'); ?>
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:30px;">
                                    <div class="sm-form-group">
                                        <label class="sm-label">الدرجات الوظيفية (درجة واحدة في كل سطر):</label>
                                        <textarea name="professional_grades" class="sm-textarea" rows="8"><?php
                                            foreach (SM_Settings::get_professional_grades() as $k => $v) echo "$k|$v\n";
                                        ?></textarea>
                                        <p style="font-size:11px; color:#666; margin-top:5px;">التنسيق: key|Label (مثال: expert|خبير)</p>
                                    </div>
                                    <div class="sm-form-group">
                                        <label class="sm-label">التخصصات المهنية (تخصص واحد في كل سطر):</label>
                                        <textarea name="specializations" class="sm-textarea" rows="8"><?php
                                            foreach (SM_Settings::get_specializations() as $k => $v) echo "$k|$v\n";
                                        ?></textarea>
                                        <p style="font-size:11px; color:#666; margin-top:5px;">التنسيق: key|Label (مثال: massage|تدليك رياضي)</p>
                                    </div>
                                </div>
                                <button type="submit" name="sm_save_professional_options" class="sm-btn" style="width:auto; margin-top:10px;">حفظ الخيارات المهنية</button>
                            </form>
                        </div>

                        <div id="syndicate-settings" class="sm-internal-tab">
                            <form method="post">
                                <?php wp_nonce_field('sm_admin_action', 'sm_admin_nonce'); ?>
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                                    <div class="sm-form-group"><label class="sm-label">اسم النقابة:</label><input type="text" name="syndicate_name" value="<?php echo esc_attr($syndicate['syndicate_name']); ?>" class="sm-input"></div>
                                    <div class="sm-form-group"><label class="sm-label">اسم مسؤول النقابة:</label><input type="text" name="syndicate_officer_name" value="<?php echo esc_attr($syndicate['syndicate_officer_name'] ?? ''); ?>" class="sm-input"></div>
                                    <div class="sm-form-group"><label class="sm-label">رقم الهاتف:</label><input type="text" name="syndicate_phone" value="<?php echo esc_attr($syndicate['phone']); ?>" class="sm-input"></div>
                                    <div class="sm-form-group"><label class="sm-label">البريد الإلكتروني:</label><input type="email" name="syndicate_email" value="<?php echo esc_attr($syndicate['email']); ?>" class="sm-input"></div>
                                    <div class="sm-form-group">
                                        <label class="sm-label">شعار النقابة:</label>
                                        <div style="display:flex; gap:10px;">
                                            <input type="text" name="syndicate_logo" id="sm_syndicate_logo_url" value="<?php echo esc_attr($syndicate['syndicate_logo']); ?>" class="sm-input">
                                            <button type="button" onclick="smOpenMediaUploader('sm_syndicate_logo_url')" class="sm-btn" style="width:auto; font-size:12px; background:var(--sm-secondary-color);">رفع/اختيار</button>
                                        </div>
                                    </div>
                                    <div class="sm-form-group" style="grid-column: span 2;"><label class="sm-label">العنوان:</label><input type="text" name="syndicate_address" value="<?php echo esc_attr($syndicate['address']); ?>" class="sm-input"></div>

                                    <div class="sm-form-group" style="grid-column: span 2; background: #fffaf0; padding: 20px; border-radius: 8px; border: 1px solid #feebc8; margin-top: 10px;">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <div>
                                                <h4 style="margin:0; color: #744210;">قسم تصميم النظام</h4>
                                                <p style="margin: 5px 0 0 0; font-size: 12px; color: #975a16;">يمكنك التحكم في الألوان والخطوط والمظهر العام للنظام من خلال تبويب "تصميم النظام".</p>
                                            </div>
                                            <button type="button" onclick="smOpenInternalTab('design-settings', document.querySelector('[onclick*=\"design-settings\"]'))" class="sm-btn" style="width:auto; background:#d69e2e;">انتقل للتصميم</button>
                                        </div>
                                    </div>

                                </div>
                                <button type="submit" name="sm_save_settings_unified" class="sm-btn" style="width:auto; margin-top:20px;">حفظ الإعدادات</button>
                            </form>
                        </div>
                        <div id="design-settings" class="sm-internal-tab" style="display:none;">
                            <form method="post">
                                <?php wp_nonce_field('sm_admin_action', 'sm_admin_nonce'); $appearance = SM_Settings::get_appearance(); ?>
                                <h4 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">إعدادات الألوان والمظهر</h4>
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-top:20px;">
                                    <div class="sm-form-group"><label class="sm-label">اللون الأساسي (#F63049):</label><input type="color" name="primary_color" value="<?php echo esc_attr($appearance['primary_color'] ?? '#F63049'); ?>" class="sm-input"></div>
                                    <div class="sm-form-group"><label class="sm-label">اللون الثانوي (#D02752):</label><input type="color" name="secondary_color" value="<?php echo esc_attr($appearance['secondary_color'] ?? '#D02752'); ?>" class="sm-input"></div>
                                    <div class="sm-form-group"><label class="sm-label">لون التمييز (#8A244B):</label><input type="color" name="accent_color" value="<?php echo esc_attr($appearance['accent_color'] ?? '#8A244B'); ?>" class="sm-input"></div>
                                    <div class="sm-form-group"><label class="sm-label">لون الهيدر (#111F35):</label><input type="color" name="dark_color" value="<?php echo esc_attr($appearance['dark_color'] ?? '#111F35'); ?>" class="sm-input"></div>
                                    <div class="sm-form-group"><label class="sm-label">حجم الخط (بكسل):</label><input type="text" name="font_size" value="<?php echo esc_attr($appearance['font_size'] ?? '15px'); ?>" class="sm-input"></div>
                                    <div class="sm-form-group"><label class="sm-label">نصف قطر الزوايا (بكسل):</label><input type="text" name="border_radius" value="<?php echo esc_attr($appearance['border_radius'] ?? '12px'); ?>" class="sm-input"></div>
                                </div>
                                <h4 style="margin-top:20px; border-bottom:1px solid #eee; padding-bottom:10px;">مكونات واجهة المستخدم</h4>
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-top:20px;">
                                    <div class="sm-form-group">
                                        <label class="sm-label">نمط الجداول:</label>
                                        <select name="table_style" class="sm-select">
                                            <option value="modern" <?php selected($appearance['table_style'] ?? '', 'modern'); ?>>عصري - بدون حدود</option>
                                            <option value="classic" <?php selected($appearance['table_style'] ?? '', 'classic'); ?>>كلاسيكي - بحدود كاملة</option>
                                        </select>
                                    </div>
                                    <div class="sm-form-group">
                                        <label class="sm-label">نمط الأزرار:</label>
                                        <select name="button_style" class="sm-select">
                                            <option value="flat" <?php selected($appearance['button_style'] ?? '', 'flat'); ?>>مسطح (Flat)</option>
                                            <option value="gradient" <?php selected($appearance['button_style'] ?? '', 'gradient'); ?>>متدرج (Gradient)</option>
                                        </select>
                                    </div>
                                </div>
                                <button type="submit" name="sm_save_appearance" class="sm-btn" style="width:auto;">حفظ تصميم النظام</button>
                            </form>
                        </div>

                        <div id="backup-settings" class="sm-internal-tab" style="display:none;">
                            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:30px;">
                                <h4 style="margin-top:0;">مركز النسخ الاحتياطي وإدارة البيانات</h4>
                                <?php $backup_info = SM_Settings::get_last_backup_info(); ?>
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:30px;">
                                    <div style="background:white; padding:15px; border-radius:8px; border:1px solid #eee;">
                                        <div style="font-size:12px; color:#718096;">آخر تصدير ناجح:</div>
                                        <div style="font-weight:700; color:var(--sm-primary-color);"><?php echo $backup_info['export']; ?></div>
                                    </div>
                                    <div style="background:white; padding:15px; border-radius:8px; border:1px solid #eee;">
                                        <div style="font-size:12px; color:#718096;">آخر استيراد ناجح:</div>
                                        <div style="font-weight:700; color:var(--sm-secondary-color);"><?php echo $backup_info['import']; ?></div>
                                    </div>
                                </div>
                                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:20px;">
                                    <div style="background:white; padding:20px; border-radius:8px; border:1px solid #eee;">
                                        <h5 style="margin-top:0;">تصدير البيانات الشاملة</h5>
                                        <p style="font-size:12px; color:#666; margin-bottom:15px;">قم بتحميل نسخة كاملة من بيانات الأعضاء بصيغة JSON.</p>
                                        <div style="display:flex; gap:10px;">
                                            <form method="post">
                                                <?php wp_nonce_field('sm_admin_action', 'sm_admin_nonce'); ?>
                                                <button type="submit" name="sm_download_backup" class="sm-btn" style="background:#27ae60; width:auto;">تصدير الآن (JSON)</button>
                                            </form>
                                        </div>
                                    </div>
                                    <div style="background:white; padding:20px; border-radius:8px; border:1px solid #eee;">
                                        <h5 style="margin-top:0;">استيراد البيانات</h5>
                                        <p style="font-size:12px; color:#e53e3e; margin-bottom:15px;">تحذير: سيقوم الاستيراد بمسح البيانات الحالية واستبدالها بالنسخة المرفوعة.</p>
                                        <form method="post" enctype="multipart/form-data">
                                            <?php wp_nonce_field('sm_admin_action', 'sm_admin_nonce'); ?>
                                            <input type="file" name="backup_file" required style="margin-bottom:10px; font-size:11px;">
                                            <button type="submit" name="sm_restore_backup" class="sm-btn" style="background:#2980b9; width:auto;">بدء الاستيراد</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php if ($is_admin): ?>
                        <div id="activity-logs" class="sm-internal-tab" style="display:none;">
                            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:30px;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                                    <div>
                                        <h4 style="margin:0;">سجل نشاطات النظام الشامل</h4>
                                        <div style="font-size:12px; color:#718096; margin-top:5px;">يتم الاحتفاظ بآخر 200 نشاط فقط تلقائياً.</div>
                                    </div>
                                    <button onclick="smDeleteAllLogs()" class="sm-btn" style="background:#e53e3e; width:auto; font-size:12px;">مسح كافة النشاطات</button>
                                </div>
                                <div class="sm-table-container">
                                    <table class="sm-table">
                                        <thead>
                                            <tr>
                                                <th>الوقت</th>
                                                <th>المستخدم</th>
                                                <th>الإجراء</th>
                                                <th>التفاصيل</th>
                                                <th>الإجراءات</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $limit = 20;
                                            $page_num = isset($_GET['log_page']) ? max(1, intval($_GET['log_page'])) : 1;
                                            $offset = ($page_num - 1) * $limit;
                                            $all_logs = SM_Logger::get_logs($limit, $offset);
                                            $total_logs = SM_Logger::get_total_logs();
                                            $total_pages = ceil($total_logs / $limit);

                                            foreach ($all_logs as $log):
                                                $can_rollback = strpos($log->details, 'ROLLBACK_DATA:') === 0;
                                                $details_display = $can_rollback ? 'بيانات مستعادة' : esc_html($log->details);
                                            ?>
                                                <tr>
                                                    <td style="font-size: 0.85em; color: #718096;"><?php echo esc_html($log->created_at); ?></td>
                                                    <td style="font-weight: 600;">
                                                        <?php echo esc_html($log->display_name ?: 'مستخدم غير معروف'); ?>
                                                    </td>
                                                    <td style="font-weight:700; color:var(--sm-primary-color);"><?php echo esc_html($log->action); ?></td>
                                                    <td style="font-size:0.9em;"><?php echo $details_display; ?></td>
                                                    <td>
                                                        <div style="display:flex; gap:8px;">
                                                            <span style="font-size:11px; color:#718096;">لا يوجد إجراءات</span>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if ($total_pages > 1): ?>
                                    <div style="display:flex; justify-content:center; gap:10px; margin-top:20px;">
                                        <?php if ($page_num > 1): ?>
                                            <a href="<?php echo add_query_arg('log_page', $page_num - 1); ?>" class="sm-btn sm-btn-outline" style="width:auto; padding:5px 15px; text-decoration:none;">السابق</a>
                                        <?php endif; ?>
                                        <span style="align-self:center; font-size:13px;">صفحة <?php echo $page_num; ?> من <?php echo $total_pages; ?></span>
                                        <?php if ($page_num < $total_pages): ?>
                                            <a href="<?php echo add_query_arg('log_page', $page_num + 1); ?>" class="sm-btn sm-btn-outline" style="width:auto; padding:5px 15px; text-decoration:none;">التالي</a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php
                    }
                    break;

            }
            ?>

        </div>
    </div>
</div>


<style>
.sm-sidebar-item { border-bottom: 1px solid #e2e8f0; transition: 0.2s; }
.sm-sidebar-link {
    padding: 15px 25px;
    cursor: pointer; font-weight: 600; color: #4a5568 !important;
    display: flex; align-items: center; gap: 12px;
    text-decoration: none !important;
    width: 100%;
}
.sm-sidebar-item:hover { background: #edf2f7; }
.sm-sidebar-item.sm-active {
    background: #fff !important;
    border-right: 4px solid var(--sm-primary-color) !important;
}
.sm-sidebar-item.sm-active .sm-sidebar-link {
    color: var(--sm-primary-color) !important;
}

.sm-sidebar-badge {
    position: absolute; left: 15px; top: 50%; transform: translateY(-50%);
    background: #e53e3e; color: white; border-radius: 20px; padding: 2px 8px; font-size: 10px; font-weight: 800;
}

.sm-dropdown-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 20px;
    text-decoration: none !important;
    color: var(--sm-dark-color) !important;
    font-size: 13px;
    font-weight: 600;
    transition: 0.2s;
}
.sm-dropdown-item:hover { background: var(--sm-bg-light); color: var(--sm-primary-color) !important; }

@keyframes smFadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* FORCE VISIBILITY FOR PANELS */
.sm-admin-dashboard .sm-main-tab-panel {
    width: 100% !important;
}
.sm-tab-btn { padding: 10px 20px; border: 1px solid #e2e8f0; background: #f8f9fa; cursor: pointer; border-radius: 5px 5px 0 0; }
.sm-tab-btn.sm-active { background: var(--sm-primary-color) !important; color: #fff !important; border-bottom: none; }
.sm-quick-btn { background: #48bb78 !important; color: white !important; padding: 8px 15px; border-radius: 6px; font-size: 13px; font-weight: 700; border: none; cursor: pointer; display: inline-block; }
.sm-refresh-btn { background: #718096; color: white; padding: 8px 15px; border-radius: 6px; font-size: 13px; border: none; cursor: pointer; }
.sm-logout-btn { background: #e53e3e; color: white; padding: 8px 15px; border-radius: 6px; font-size: 13px; text-decoration: none; font-weight: 700; display: inline-block; }
</style>
