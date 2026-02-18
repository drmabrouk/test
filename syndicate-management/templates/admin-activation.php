<?php if (!defined('ABSPATH')) exit; ?>

<div class="sm-activation-wrap" dir="rtl" style="font-family: 'Rubik', sans-serif; padding: 20px;">
    <h1 style="margin-bottom: 30px;">إدارة تفعيل النظام والاشتراك السنوي</h1>

    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px;">
        <!-- Activation Form -->
        <div style="background: #fff; padding: 30px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
            <h3 style="margin-top: 0; color: #111F35; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 20px;">تفعيل رخصة جديدة</h3>

            <div style="margin-bottom: 25px;">
                <?php if (SM_Activation::is_active()): ?>
                    <div style="background: #def7ec; color: #03543f; padding: 15px; border-radius: 8px; text-align: center; border: 1px solid #84e1bc;">
                        <span class="dashicons dashicons-yes-alt" style="font-size: 24px; width: 24px; height: 24px;"></span>
                        <div style="font-weight: 800; font-size: 16px;">النظام مفعل حالياً</div>
                        <div style="font-size: 12px; margin-top: 5px;">تاريخ انتهاء الصلاحية: <?php echo $activation_data['end_date']; ?></div>
                    </div>
                <?php else: ?>
                    <div style="background: #fde8e8; color: #9b1c1c; padding: 15px; border-radius: 8px; text-align: center; border: 1px solid #f8b4b4;">
                        <span class="dashicons dashicons-warning" style="font-size: 24px; width: 24px; height: 24px;"></span>
                        <div style="font-weight: 800; font-size: 16px;">النظام غير مفعل / منتهي</div>
                        <div style="font-size: 12px; margin-top: 5px;">يرجى إدخال كود التفعيل الجديد لاستعادة كامل الوظائف.</div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!$is_unlocked): ?>
                <form method="post" style="background: #111F35; padding: 25px; border-radius: 10px; border: 1px solid #000; box-shadow: inset 0 0 10px rgba(255,255,255,0.1);">
                    <?php wp_nonce_field('sm_activation_action', 'sm_activation_nonce'); ?>
                    <div style="text-align: center; margin-bottom: 20px;">
                        <span class="dashicons dashicons-lock" style="font-size: 40px; width: 40px; height: 40px; color: #fbbf24;"></span>
                        <p style="margin: 10px 0 0 0; font-weight: 700; color: #fff; font-size: 16px;">الوصول مقيد لمدير النظام</p>
                        <p style="margin: 5px 0 0 0; color: #94a3b8; font-size: 11px;">الخطوة 1: أدخل كود الوصول العام (Master Code)</p>
                    </div>
                    <div class="sm-form-group" style="margin-bottom: 15px;">
                        <input type="password" name="dev_password" class="sm-input" placeholder="********" required style="text-align: center; letter-spacing: 5px; background: #1e293b; color: #fff; border-color: #334155;">
                    </div>
                    <button type="submit" name="sm_verify_dev_pass" class="sm-btn" style="width:100%; background:#fbbf24; color: #111F35; font-weight: 800;">فتح واجهة التفعيل</button>
                </form>
            <?php elseif (!$is_otp_sent): ?>
                <form method="post">
                    <?php wp_nonce_field('sm_activation_action', 'sm_activation_nonce'); ?>
                    <div style="margin-bottom: 15px; font-size: 13px; color: #111F35; font-weight: 700;">الخطوة 2: تحديد مدة التفعيل</div>

                    <div class="sm-form-group" style="margin-bottom: 20px;">
                        <label class="sm-label" style="font-weight: 700;">مدة التفعيل (بالسنوات):</label>
                        <select name="activation_duration" class="sm-select">
                            <option value="1">سنة واحدة</option>
                            <option value="2">سنتين</option>
                            <option value="3">3 سنوات</option>
                            <option value="5">5 سنوات</option>
                            <option value="10">10 سنوات</option>
                        </select>
                    </div>

                    <div class="sm-form-group" style="margin-bottom: 25px;">
                        <label class="sm-label" style="font-weight: 700;">تكلفة التفعيل:</label>
                        <input type="number" name="activation_cost" class="sm-input" value="0" step="0.01">
                    </div>

                    <button type="submit" name="sm_request_otp" class="sm-btn" style="width: 100%; height: 50px; font-weight: 800; font-size: 16px; background: #111F35;">إرسال رمز التحقق OTP</button>
                </form>
            <?php else: ?>
                <form method="post" style="background: #f0f4f8; padding: 25px; border-radius: 10px; border: 1px solid #cbd5e0;">
                    <?php wp_nonce_field('sm_activation_action', 'sm_activation_nonce'); ?>
                    <div style="text-align: center; margin-bottom: 20px;">
                        <span class="dashicons dashicons-email-alt" style="font-size: 40px; width: 40px; height: 40px; color: #3182ce;"></span>
                        <p style="margin: 10px 0 0 0; font-weight: 700; color: #111F35; font-size: 16px;">الخطوة 3: التحقق من OTP</p>
                        <p style="margin: 5px 0 0 0; color: #4a5568; font-size: 11px;">تم إرسال رمز مكون من 15 رقم إلى: website.developer@email.com</p>
                    </div>
                    <div class="sm-form-group" style="margin-bottom: 15px;">
                        <label class="sm-label">أدخل رمز OTP المستلم:</label>
                        <input type="text" name="activation_otp" class="sm-input" placeholder="000000000000000" required style="text-align: center; font-family: monospace; font-size: 1.4em; letter-spacing: 2px;">
                    </div>
                    <button type="submit" name="sm_finalize_activation" class="sm-btn" style="width:100%; background:#27ae60; color: #fff; font-weight: 800;">إتمام التفعيل النهائي</button>
                    <button type="button" onclick="location.reload()" class="sm-btn sm-btn-outline" style="width:100%; margin-top:10px; font-size:12px;">إلغاء والبدء من جديد</button>
                </form>
            <?php endif; ?>

            <div style="margin-top: 25px; padding-top: 20px; border-top: 1px dashed #e2e8f0; font-size: 12px; color: #64748b; line-height: 1.6;">
                <p>⚠️ ملاحظة: يعتمد كود التفعيل على تاريخ الدفع. بمجرد تفعيل الكود، سيتم تمديد صلاحية النظام لمدة 365 يوماً من تاريخ بدء الكود.</p>
            </div>
        </div>

        <!-- Activation Log -->
        <div style="background: #fff; padding: 30px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
            <h3 style="margin-top: 0; color: #111F35; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 20px;">سجل التفعيل والاشتراكات</h3>

            <div class="sm-table-container">
                <table class="sm-table">
                    <thead>
                        <tr>
                            <th>تاريخ التفعيل</th>
                            <th>بداية الفترة</th>
                            <th>نهاية الفترة</th>
                            <th>التكلفة</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="5" style="text-align:center; padding: 40px; color: #94a3b8;">لا توجد سجلات تفعيل سابقة.</td></tr>
                        <?php else:
                            $logs = array_reverse($logs);
                            foreach ($logs as $log):
                                $is_current = (SM_Activation::is_active() && $activation_data['start_date'] === $log['start_date'] && $activation_data['end_date'] === $log['end_date']);
                        ?>
                            <tr <?php echo $is_current ? 'style="background: #f0fff4;"' : ''; ?>>
                                <td style="font-size: 11px;"><?php echo $log['activated_at']; ?></td>
                                <td style="font-weight: 700;"><?php echo $log['start_date']; ?></td>
                                <td style="font-weight: 700; color: #e53e3e;"><?php echo $log['end_date']; ?></td>
                                <td style="font-weight: 700; color: #38a169;"><?php echo number_format($log['cost'], 2); ?> ج.م</td>
                                <td>
                                    <?php if ($is_current): ?>
                                        <span class="sm-badge sm-badge-low" style="background: #38a169; color: #fff;">فعال</span>
                                    <?php else: ?>
                                        <span class="sm-badge sm-badge-high" style="background: #e2e8f0; color: #64748b;">سابق</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.sm-form-group { display: flex; flex-direction: column; gap: 8px; }
.sm-label { font-size: 14px; color: #4a5568; }
.sm-textarea { width: 100%; border: 1px solid #cbd5e0; border-radius: 8px; padding: 12px; outline: none; transition: 0.2s; }
.sm-textarea:focus { border-color: #111F35; box-shadow: 0 0 0 3px rgba(17, 31, 53, 0.1); }
</style>
