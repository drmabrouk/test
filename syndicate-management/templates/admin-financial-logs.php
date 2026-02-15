<?php if (!defined('ABSPATH')) exit;

global $wpdb;

$user = wp_get_current_user();
$is_sys_manager = in_array('sm_system_admin', (array)$user->roles);
$is_syndicate_admin = in_array('sm_syndicate_admin', (array)$user->roles);
$my_gov = get_user_meta($user->ID, 'sm_governorate', true);

$where = "1=1";
if ($is_syndicate_admin && $my_gov) {
    // Only show payments for members in their governorate
    $where = $wpdb->prepare("EXISTS (SELECT 1 FROM {$wpdb->prefix}sm_members m WHERE m.id = p.member_id AND m.governorate = %s)", $my_gov);
}

$search = isset($_GET['member_search']) ? sanitize_text_field($_GET['member_search']) : '';
if ($search) {
    $where .= $wpdb->prepare(" AND EXISTS (SELECT 1 FROM {$wpdb->prefix}sm_members m WHERE m.id = p.member_id AND (m.name LIKE %s OR m.national_id LIKE %s))", '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%');
}

$payments = $wpdb->get_results("SELECT p.*, u.display_name as staff_name FROM {$wpdb->prefix}sm_payments p LEFT JOIN {$wpdb->base_prefix}users u ON p.created_by = u.ID WHERE $where ORDER BY p.created_at DESC LIMIT 200");
?>

<div class="sm-financial-logs" dir="rtl">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <h3 style="margin:0;">سجل العمليات المالية الشامل</h3>
        <button onclick="location.reload()" class="sm-btn sm-btn-outline" style="width:auto;"><span class="dashicons dashicons-update"></span> تحديث السجل</button>
    </div>

    <div class="sm-table-container">
        <table class="sm-table">
            <thead>
                <tr>
                    <th>كود العملية</th>
                    <th>التاريخ والوقت</th>
                    <th>المسؤول</th>
                    <th>العضو</th>
                    <th>التفاصيل (بالعربية)</th>
                    <th>فاتورة رقمية</th>
                    <th>فاتورة ورقية</th>
                    <th>المبلغ</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                    <tr><td colspan="9" style="text-align:center; padding: 40px; color: #718096;">لا توجد عمليات مالية مسجلة بعد.</td></tr>
                <?php else: ?>
                    <?php foreach ($payments as $p):
                        $member = SM_DB::get_member_by_id($p->member_id);
                    ?>
                        <tr>
                            <td style="font-family: monospace; font-weight: 700; color: #111F35;">#<?php echo $p->id; ?></td>
                            <td style="font-size: 11px; color: #718096;"><?php echo $p->created_at; ?></td>
                            <td style="font-weight: 600; font-size: 12px;"><?php echo esc_html($p->staff_name ?: 'النظام'); ?></td>
                            <td style="font-weight: 700; font-size: 12px;"><?php echo esc_html($member->name ?? 'عضو محذوف'); ?></td>
                            <td style="font-size: 13px;"><?php echo esc_html($p->details_ar ?: $p->payment_type); ?></td>
                            <td style="font-size: 10px; color: #3182ce; font-family: monospace;"><?php echo esc_html($p->digital_invoice_code); ?></td>
                            <td style="font-size: 10px; color: #d69e2e; font-family: monospace; font-weight: 700;"><?php echo esc_html($p->paper_invoice_code ?: '---'); ?></td>
                            <td style="font-weight: 800; color: #38a169;"><?php echo number_format($p->amount, 2); ?></td>
                            <td>
                                <?php if ($is_sys_manager): ?>
                                    <button onclick="smDeleteTransaction(<?php echo $p->id; ?>)" class="sm-btn sm-btn-outline" style="color:#e53e3e; border-color:#feb2b2; padding:2px 8px; font-size:11px;">حذف/تراجع</button>
                                <?php else: ?>
                                    <span style="font-size: 10px; color: #999;">لا توجد صلاحية</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function smDeleteTransaction(id) {
    if (!confirm('هل أنت متأكد من حذف هذه العملية المالية؟ سيتم إزالتها نهائياً من السجل.')) return;

    const formData = new FormData();
    formData.append('action', 'sm_delete_transaction_ajax');
    formData.append('transaction_id', id);
    formData.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

    fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            smShowNotification('تم حذف العملية بنجاح');
            location.reload();
        } else {
            alert('خطأ: ' + res.data);
        }
    });
}
</script>
