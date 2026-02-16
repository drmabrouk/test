<?php if (!defined('ABSPATH')) exit; ?>
<div style="display:grid; grid-template-columns: 1fr 1fr; gap: 30px;">
    <div>
        <h4 style="border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 15px;">كشف الحساب المستحق</h4>
        <?php
        $in_grace = false;
        $current_month = (int)date('n');
        if ($current_month >= 1 && $current_month <= 3) {
            foreach ($dues['breakdown'] as $item) {
                if (strpos($item['item'], 'تجديد عضوية') !== false && $item['penalty'] == 0) {
                    $in_grace = true;
                    break;
                }
            }
        }
        if ($in_grace): ?>
            <div style="background: #ebf8ff; color: #2b6cb0; padding: 15px; border-radius: 8px; border: 1px solid #bee3f8; margin-bottom: 15px; font-size: 13px;">
                <span class="dashicons dashicons-info" style="font-size: 18px; width: 18px; height: 18px;"></span> أنت حالياً في <strong>فترة السماح</strong> لتجديد العضوية (يناير - مارس). يمكنك التجديد الآن بدون أي غرامات تأخير.
            </div>
        <?php endif; ?>

        <div style="background: #fff; border: 1px solid #eee; border-radius: 8px; overflow: hidden;">
            <table class="sm-table" style="font-size: 13px;">
                <thead>
                    <tr style="background:#f8fafc;">
                        <th>البند</th>
                        <th>القيمة</th>
                        <th>الغرامة</th>
                        <th>المجموع</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dues['breakdown'] as $item):
                        $is_late = $item['penalty'] > 0;
                    ?>
                    <tr <?php echo $is_late ? 'style="background: #fff5f5;"' : ''; ?>>
                        <td style="<?php echo $is_late ? 'color: #c53030; font-weight: 700;' : ''; ?>">
                            <?php if ($is_late): ?><span class="dashicons dashicons-warning" style="font-size: 16px; width: 16px; height: 16px; color: #e53e3e; vertical-align: middle;"></span><?php endif; ?>
                            <?php echo $item['item']; ?>
                        </td>
                        <td><?php echo number_format($item['amount'], 2); ?></td>
                        <td style="color:#e53e3e; font-weight: 700;"><?php echo number_format($item['penalty'], 2); ?></td>
                        <td style="font-weight:900;"><?php echo number_format($item['total'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#f1f5f9; font-weight: 800;">
                        <td colspan="3">الإجمالي المطلوب</td>
                        <td style="color:var(--sm-primary-color); font-size:1.1em;"><?php echo number_format($dues['total_owed'], 2); ?> ج.م</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <?php if (current_user_can('sm_manage_finance')): ?>
        <div style="margin-top: 25px; background: #fffaf0; border: 1px solid #feebc8; padding: 20px; border-radius: 8px;">
            <h5 style="margin: 0 0 15px 0; color: #744210;">تسجيل دفعة جديدة</h5>
            <form id="record-payment-form">
                <input type="hidden" name="member_id" value="<?php echo $member_id; ?>">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                    <div>
                        <label class="sm-label" style="font-size:11px;">المبلغ:</label>
                        <input type="number" name="amount" class="sm-input" value="<?php echo $dues['balance']; ?>" step="0.01" required>
                    </div>
                    <div>
                        <label class="sm-label" style="font-size:11px;">التاريخ:</label>
                        <input type="date" name="payment_date" class="sm-input" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div>
                        <label class="sm-label" style="font-size:11px;">النوع:</label>
                        <select name="payment_type" class="sm-select">
                            <option value="membership">اشتراك عضوية</option>
                            <option value="license">ترخيص مزاولة</option>
                            <option value="facility">ترخيص منشأة</option>
                            <option value="penalty">غرامة</option>
                            <option value="other">أخرى</option>
                        </select>
                    </div>
                    <div>
                        <label class="sm-label" style="font-size:11px;">السنة (للعضوية):</label>
                        <input type="number" name="target_year" class="sm-input" value="<?php echo date('Y'); ?>">
                    </div>
                    <div>
                        <label class="sm-label" style="font-size:11px;">كود الفاتورة الورقية:</label>
                        <input type="text" name="paper_invoice_code" class="sm-input" placeholder="أدخل الكود يدوياً">
                    </div>
                    <div>
                        <label class="sm-label" style="font-size:11px;">تفاصيل العملية (بالعربية):</label>
                        <input type="text" name="details_ar" class="sm-input" placeholder="مثال: اشتراك عام 2024">
                    </div>
                </div>
                <div class="sm-form-group">
                    <label class="sm-label" style="font-size:11px;">ملاحظات إضافية:</label>
                    <textarea name="notes" class="sm-input" rows="2"></textarea>
                </div>
                <button type="button" onclick="smSubmitPayment(this)" class="sm-btn" style="background:#27ae60; height: 45px; font-weight: 700;">تأكيد استلام المبلغ وإصدار فاتورة</button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <div>
        <h4 style="border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 15px;">سجل المدفوعات السابقة</h4>
        <div style="max-height: 500px; overflow-y: auto;">
            <?php if (empty($history)): ?>
                <div style="text-align:center; padding: 30px; color: #718096; background: #f8fafc; border-radius: 8px;">لا يوجد سجل مدفوعات لهذا العضو.</div>
            <?php else: ?>
                <table class="sm-table" style="font-size: 12px;">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>النوع</th>
                            <th>المبلغ</th>
                            <th>الفاتورة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $p): ?>
                        <tr>
                            <td><?php echo $p->payment_date; ?></td>
                            <td><?php
                                $types = ['membership' => 'عضوية', 'license' => 'ترخيص', 'facility' => 'منشأة', 'penalty' => 'غرامة', 'other' => 'أخرى'];
                                echo $types[$p->payment_type] ?? $p->payment_type;
                            ?></td>
                            <td style="font-weight:700; color:#27ae60;"><?php echo number_format($p->amount, 2); ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin-ajax.php?action=sm_print_invoice&payment_id='.$p->id); ?>" target="_blank" class="sm-btn" style="height:24px; padding:0 8px; font-size:10px; width:auto; background:#111F35;">عرض الفاتورة</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
