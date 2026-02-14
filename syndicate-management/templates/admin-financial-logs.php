<?php if (!defined('ABSPATH')) exit;

global $wpdb;
$logs = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sm_logs WHERE action = 'Financial Transaction' ORDER BY created_at DESC LIMIT 100");
?>

<div class="sm-financial-logs" dir="rtl">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <h3 style="margin:0;">سجل العمليات المالية والنشاط</h3>
        <button onclick="location.reload()" class="sm-btn sm-btn-outline" style="width:auto;"><span class="dashicons dashicons-update"></span> تحديث السجل</button>
    </div>

    <div class="sm-table-container">
        <table class="sm-table">
            <thead>
                <tr>
                    <th>التاريخ والوقت</th>
                    <th>بواسطة</th>
                    <th>تفاصيل العملية</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="3" style="text-align:center; padding: 40px; color: #718096;">لا توجد عمليات مالية مسجلة بعد.</td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $log):
                        $u = get_userdata($log->user_id);
                        $name = $u ? $u->display_name : 'النظام';
                    ?>
                        <tr>
                            <td style="font-size: 13px; color: #718096;"><?php echo $log->created_at; ?></td>
                            <td style="font-weight: 700;"><?php echo esc_html($name); ?></td>
                            <td><?php echo esc_html($log->details); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
