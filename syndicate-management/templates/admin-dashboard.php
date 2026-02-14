<?php if (!defined('ABSPATH')) exit; ?>
<div class="sm-content-wrapper" dir="rtl">
    <h1>نظام إدارة النقابة - لوحة التحكم</h1>
    <hr>

    <?php include SM_PLUGIN_DIR . 'templates/public-dashboard-summary.php'; ?>

    <div style="display: flex; gap: 20px; margin-top: 20px; flex-wrap: wrap;">
        <?php if (current_user_can('إدارة_الأعضاء')): ?>
        <div style="flex: 1; min-width: 300px; background: #fff; padding: 25px; border: 1px solid var(--sm-border-color); border-radius: var(--sm-radius); box-shadow: var(--sm-shadow);">
            <h3 style="color: var(--sm-primary-color);">إدارة النظام</h3>
            <p>يمكنك إضافة أعضاء جدد، إدارة أعضاء النقابة، واستعراض كافة السجلات.</p>
            <a href="admin.php?page=sm-members" class="sm-btn">إدارة الأعضاء</a>
        </div>
        <?php endif; ?>


        <?php if (current_user_can('manage_options')): ?>
        <div style="flex: 1; min-width: 300px; background: #fff; padding: 25px; border: 1px solid var(--sm-border-color); border-radius: var(--sm-radius); box-shadow: var(--sm-shadow);">
            <h3 style="color: var(--sm-primary-color);">الأكواد المختصرة للمطور</h3>
            <ul style="list-style: disc; padding-right: 20px;">
                <li><code>[sm_login]</code> - نموذج تسجيل الدخول بالعربية.</li>
                <li><code>[sm_admin]</code> - لوحة الإدارة الشاملة (Frontend).</li>
            </ul>
            <p style="font-size: 0.9em; color: #666;">تنبيه: يتم توجيه المستخدمين تلقائياً إلى لوحة التحكم بعد تسجيل الدخول.</p>
        </div>
        <?php endif; ?>
    </div>
</div>
