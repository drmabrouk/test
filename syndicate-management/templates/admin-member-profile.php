<?php if (!defined('ABSPATH')) exit;

$member_id = intval($_GET['member_id'] ?? 0);
$member = SM_DB::get_member_by_id($member_id);

if (!$member) {
    echo '<div class="error"><p>العضو غير موجود.</p></div>';
    return;
}

$user = wp_get_current_user();
$is_sys_manager = in_array('sm_system_admin', (array)$user->roles);
$is_syndicate_admin = in_array('sm_syndicate_admin', (array)$user->roles);

// GEOGRAPHIC ACCESS CHECK
if ($is_syndicate_admin) {
    $my_gov = get_user_meta($user->ID, 'sm_governorate', true);
    if ($my_gov && $member->governorate !== $my_gov) {
        echo '<div class="error" style="padding:20px; background:#fff5f5; color:#c53030; border-radius:8px; border:1px solid #feb2b2;"><h4>⚠️ عذراً، لا تملك صلاحية الوصول لهذا الملف.</h4><p>هذا العضو يتبع لمحافظة أخرى غير المسجلة في حسابك.</p></div>';
        return;
    }
}

$grades = SM_Settings::get_professional_grades();
$specs = SM_Settings::get_specializations();
$govs = SM_Settings::get_governorates();
$statuses = SM_Settings::get_membership_statuses();
$finance = SM_Finance::calculate_member_dues($member->id);
?>

<div class="sm-member-profile-view" dir="rtl">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; background: #fff; padding: 20px; border-radius: 12px; border: 1px solid var(--sm-border-color); box-shadow: var(--sm-shadow);">
        <div style="display: flex; align-items: center; gap: 20px;">
            <div style="width: 80px; height: 80px; background: #f0f4f8; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 40px; border: 3px solid var(--sm-primary-color);">
                👤
            </div>
            <div>
                <h2 style="margin:0; color: var(--sm-dark-color);"><?php echo esc_html($member->name); ?></h2>
                <div style="display: flex; gap: 10px; margin-top: 5px;">
                    <span class="sm-badge sm-badge-low"><?php echo $grades[$member->professional_grade] ?? $member->professional_grade; ?></span>
                    <span class="sm-badge" style="background: #e2e8f0; color: #4a5568;"><?php echo $govs[$member->governorate] ?? $member->governorate; ?></span>
                </div>
            </div>
        </div>
        <div style="display: flex; gap: 10px;">
            <button onclick="editSmMember(JSON.parse(this.dataset.member))" data-member='<?php echo esc_attr(wp_json_encode($member)); ?>' class="sm-btn" style="background: #3182ce; width: auto;"><span class="dashicons dashicons-edit"></span> تعديل البيانات</button>
            <a href="<?php echo admin_url('admin-ajax.php?action=sm_print&print_type=id_card&member_id='.$member->id); ?>" target="_blank" class="sm-btn" style="background: #27ae60; width: auto; text-decoration:none; display:flex; align-items:center; gap:8px;"><span class="dashicons dashicons-id-alt"></span> طباعة الكارنيه</a>
            <?php if ($is_sys_manager): ?>
                <button onclick="deleteMember(<?php echo $member->id; ?>, '<?php echo esc_js($member->name); ?>')" class="sm-btn" style="background: #e53e3e; width: auto;"><span class="dashicons dashicons-trash"></span> حذف العضو</button>
            <?php endif; ?>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
        <div style="display: flex; flex-direction: column; gap: 30px;">
            <!-- Basic Info -->
            <div style="background: #fff; padding: 25px; border-radius: 12px; border: 1px solid var(--sm-border-color); box-shadow: var(--sm-shadow);">
                <h3 style="margin-top:0; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 20px;">البيانات الأساسية</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div><label class="sm-label">الرقم القومي:</label> <div class="sm-value"><?php echo esc_html($member->national_id); ?></div></div>
                    <div><label class="sm-label">كود العضوية:</label> <div class="sm-value"><?php echo esc_html($member->member_code); ?></div></div>
                    <div><label class="sm-label">التخصص:</label> <div class="sm-value"><?php echo esc_html($specs[$member->specialization] ?? $member->specialization); ?></div></div>
                    <div><label class="sm-label">الدرجة العلمية:</label> <div class="sm-value"><?php echo esc_html($member->academic_degree); ?></div></div>
                    <div><label class="sm-label">رقم الهاتف:</label> <div class="sm-value"><?php echo esc_html($member->phone); ?></div></div>
                    <div><label class="sm-label">البريد الإلكتروني:</label> <div class="sm-value"><?php echo esc_html($member->email); ?></div></div>
                </div>
            </div>

            <!-- Professional Licenses -->
            <div style="background: #fff; padding: 25px; border-radius: 12px; border: 1px solid var(--sm-border-color); box-shadow: var(--sm-shadow);">
                <h3 style="margin-top:0; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 20px;">تراخيص مزاولة المهنة والمنشآت</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                    <div>
                        <h4 style="color: var(--sm-primary-color); margin-top:0;">ترخيص مزاولة المهنة</h4>
                        <div style="margin-top: 10px;">
                            <label class="sm-label">رقم الترخيص:</label> <?php echo esc_html($member->license_number ?: 'غير متوفر'); ?><br>
                            <label class="sm-label">تاريخ الانتهاء:</label> <?php echo esc_html($member->license_expiration_date ?: 'غير محدد'); ?>
                        </div>
                    </div>
                    <div>
                        <h4 style="color: #38a169; margin-top:0;">ترخيص المنشأة</h4>
                        <div style="margin-top: 10px;">
                            <label class="sm-label">اسم المنشأة:</label> <?php echo esc_html($member->facility_name ?: 'غير متوفر'); ?><br>
                            <label class="sm-label">تاريخ الانتهاء:</label> <?php echo esc_html($member->facility_license_expiration_date ?: 'غير محدد'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 30px;">
            <!-- Financial Status -->
            <div style="background: #fff; padding: 25px; border-radius: 12px; border: 1px solid var(--sm-border-color); box-shadow: var(--sm-shadow);">
                <h3 style="margin-top:0; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 20px;">الوضع المالي</h3>
                <div style="text-align: center; padding: 10px 0;">
                    <div style="font-size: 0.9em; color: #718096;">الرصيد المتبقي</div>
                    <div style="font-size: 2.2em; font-weight: 900; color: <?php echo $finance['balance'] > 0 ? '#e53e3e' : '#38a169'; ?>;">
                        <?php echo number_format($finance['balance'], 2); ?> ج.م
                    </div>
                </div>
                <div style="margin-top: 20px; display: flex; flex-direction: column; gap: 10px;">
                    <div style="display: flex; justify-content: space-between;"><span>إجمالي المستحق:</span> <strong><?php echo number_format($finance['total_owed'], 2); ?></strong></div>
                    <div style="display: flex; justify-content: space-between;"><span>إجمالي المسدد:</span> <strong style="color:#38a169;"><?php echo number_format($finance['total_paid'], 2); ?></strong></div>
                </div>
                <button onclick="smOpenFinanceModal(<?php echo $member->id; ?>)" class="sm-btn" style="margin-top: 20px; background: var(--sm-dark-color);">إدارة المدفوعات والفواتير</button>
            </div>

            <!-- Account Status -->
            <div style="background: #fff; padding: 25px; border-radius: 12px; border: 1px solid var(--sm-border-color); box-shadow: var(--sm-shadow);">
                <h4 style="margin-top:0;">حالة الحساب</h4>
                <div style="display: flex; align-items: center; gap: 10px; margin-top: 15px;">
                    <div style="width: 12px; height: 12px; border-radius: 50%; background: <?php echo $member->membership_status === 'active' ? '#38a169' : '#cbd5e0'; ?>;"></div>
                    <span style="font-weight: 700;"><?php echo $statuses[$member->membership_status] ?? $member->membership_status; ?></span>
                </div>
                <div style="font-size: 0.8em; color: #718096; margin-top: 10px;">
                    تاريخ التسجيل: <?php echo $member->registration_date; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function deleteMember(id, name) {
    if (!confirm('هل أنت متأكد من حذف العضو: ' + name + ' نهائياً من النظام؟ لا يمكن التراجع عن هذا الإجراء.')) return;
    const formData = new FormData();
    formData.append('action', 'sm_delete_member_ajax');
    formData.append('member_id', id);
    formData.append('nonce', '<?php echo wp_create_nonce("sm_delete_member"); ?>');

    fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            window.location.href = '<?php echo add_query_arg('sm_tab', 'members'); ?>';
        } else {
            alert('خطأ: ' + res.data);
        }
    });
}
</script>
