<?php if (!defined('ABSPATH')) exit; ?>
<?php
$is_admin = current_user_can('إدارة_الأعضاء');
$import_results = get_transient('sm_import_results_' . get_current_user_id());
if ($import_results) {
    delete_transient('sm_import_results_' . get_current_user_id());
}
?>
<div class="sm-content-wrapper" dir="rtl">
    <?php if ($import_results): ?>
        <div style="background: #fff; border-radius: 12px; border: 1px solid var(--sm-border-color); margin-bottom: 30px; overflow: hidden; box-shadow: var(--sm-shadow);">
            <div style="background: var(--sm-bg-light); padding: 15px 25px; border-bottom: 1px solid var(--sm-border-color); display: flex; justify-content: space-between; align-items: center;">
                <h4 style="margin:0; color: var(--sm-dark-color); font-weight: 800;">تقرير استيراد الأعضاء الأخير</h4>
                <span style="font-size: 12px; color: #718096;">إجمالي السجلات المعالجة: <?php echo $import_results['total']; ?></span>
            </div>
            <div style="padding: 25px;">
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 25px;">
                    <div style="background: #f0fff4; padding: 15px; border-radius: 8px; border: 1px solid #c6f6d5; text-align: center;">
                        <div style="font-size: 20px; font-weight: 800; color: #2f855a;"><?php echo $import_results['success']; ?></div>
                        <div style="font-size: 12px; color: #38a169;">تم الاستيراد بنجاح</div>
                    </div>
                    <div style="background: #fffaf0; padding: 15px; border-radius: 8px; border: 1px solid #feebc8; text-align: center;">
                        <div style="font-size: 20px; font-weight: 800; color: #c05621;"><?php echo $import_results['warning']; ?></div>
                        <div style="font-size: 12px; color: #dd6b20;">تنبيهات (بيانات ناقصة)</div>
                    </div>
                    <div style="background: #fff5f5; padding: 15px; border-radius: 8px; border: 1px solid #fed7d7; text-align: center;">
                        <div style="font-size: 20px; font-weight: 800; color: #c53030;"><?php echo $import_results['error']; ?></div>
                        <div style="font-size: 12px; color: #e53e3e;">أخطاء (فشل الاستيراد)</div>
                    </div>
                </div>

                <?php if (!empty($import_results['details'])): ?>
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; max-height: 250px; overflow-y: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 12px; text-align: right;">
                            <thead>
                                <tr style="background: #edf2f7; position: sticky; top: 0;">
                                    <th style="padding: 10px 15px; border-bottom: 1px solid #cbd5e0; width: 80px;">النوع</th>
                                    <th style="padding: 10px 15px; border-bottom: 1px solid #cbd5e0;">التفاصيل والسبب</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($import_results['details'] as $detail): ?>
                                    <tr>
                                        <td style="padding: 10px 15px; border-bottom: 1px solid #e2e8f0;">
                                            <?php if ($detail['type'] == 'error'): ?>
                                                <span style="color: #e53e3e; font-weight: 700;">خطأ</span>
                                            <?php else: ?>
                                                <span style="color: #dd6b20; font-weight: 700;">تنبيه</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 10px 15px; border-bottom: 1px solid #e2e8f0; color: #4a5568;"><?php echo esc_html($detail['msg']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div style="background: white; padding: 30px; border: 1px solid var(--sm-border-color); border-radius: var(--sm-radius); margin-bottom: 30px; box-shadow: var(--sm-shadow);">
        <form method="get" style="display: grid; grid-template-columns: 1.5fr 1fr 1fr 1fr auto; gap: 15px; align-items: end;">
            <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page'] ?? ''); ?>">
            <input type="hidden" name="sm_tab" value="members">

            <div class="sm-form-group" style="margin-bottom:0;">
                <label class="sm-label">بحث:</label>
                <input type="text" name="member_search" class="sm-input" value="<?php echo esc_attr(isset($_GET['member_search']) ? $_GET['member_search'] : ''); ?>" placeholder="الاسم، الرقم القومي، رقم العضوية...">
            </div>

            <div class="sm-form-group" style="margin-bottom:0;">
                <label class="sm-label">الدرجة الوظيفية:</label>
                <select name="grade_filter" class="sm-select">
                    <option value="">كل الدرجات</option>
                    <?php foreach (SM_Settings::get_professional_grades() as $k => $v): ?>
                        <option value="<?php echo esc_attr($k); ?>" <?php selected(isset($_GET['grade_filter']) && $_GET['grade_filter'] == $k); ?>><?php echo esc_html($v); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sm-form-group" style="margin-bottom:0;">
                <label class="sm-label">التخصص:</label>
                <select name="spec_filter" class="sm-select">
                    <option value="">كل التخصصات</option>
                    <?php foreach (SM_Settings::get_specializations() as $k => $v): ?>
                        <option value="<?php echo esc_attr($k); ?>" <?php selected(isset($_GET['spec_filter']) && $_GET['spec_filter'] == $k); ?>><?php echo esc_html($v); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="sm-btn">بحث</button>
                <a href="<?php echo add_query_arg('sm_tab', 'members', remove_query_arg(['member_search', 'grade_filter', 'spec_filter', 'status_filter'])); ?>" class="sm-btn sm-btn-outline" style="text-decoration:none;">إعادة ضبط</a>
            </div>
        </form>
    </div>

    <?php if ($is_admin): ?>
    <div style="display: flex; gap: 15px; margin-bottom: 30px; flex-wrap: wrap; align-items: center;">
        <button onclick="document.getElementById('add-single-member-modal').style.display='flex'" class="sm-btn">+ إضافة عضو جديد</button>
        <button onclick="document.getElementById('csv-import-form').style.display='block'" class="sm-btn sm-btn-secondary">استيراد أعضاء (Excel)</button>
        <a href="data:text/csv;charset=utf-8,<?php echo rawurlencode("الاسم الكامل,الصف,الشعبة,الجنسية,البريد,الهاتف\nأحمد محمد,الصف 12,أ,إماراتي,parent@example.com,0501234567"); ?>" download="member_template.csv" class="sm-btn sm-btn-outline" style="text-decoration:none;">تحميل نموذج CSV</a>
        <a href="<?php echo admin_url('admin-ajax.php?action=sm_print&print_type=id_card'); ?>" target="_blank" class="sm-btn sm-btn-accent" style="background: #27ae60; text-decoration:none;">طباعة كافة البطاقات</a>
    </div>
    <?php endif; ?>

    <div id="csv-import-form" style="display:none; background: #f8fafc; padding: 30px; border: 2px dashed #cbd5e0; border-radius: 12px; margin-bottom: 30px;">
        <h3 style="margin-top:0; color:var(--sm-secondary-color);">دليل استيراد الأعضاء (Excel Mapping)</h3>

        <div style="background:#fff; padding:20px; border-radius:8px; border:1px solid #e2e8f0; margin-bottom:20px;">
            <p style="font-size:14px; font-weight:700; margin-bottom:15px; color: var(--sm-dark-color);">يتم استيراد البيانات وفقاً لخريطة الأعمدة التالية:</p>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px;">
                <div style="background: #f1f5f9; padding: 10px; border-radius: 6px; text-align: center;">
                    <div style="font-size: 11px; color: #64748b;">العمود A</div>
                    <div style="font-weight: 800;">الاسم الكامل للعضو</div>
                </div>
                <div style="background: #f1f5f9; padding: 10px; border-radius: 6px; text-align: center;">
                    <div style="font-size: 11px; color: #64748b;">العمود B</div>
                    <div style="font-weight: 800;">الصف الدراسي</div>
                </div>
                <div style="background: #f1f5f9; padding: 10px; border-radius: 6px; text-align: center;">
                    <div style="font-size: 11px; color: #64748b;">العمود C</div>
                    <div style="font-weight: 800;">الشعبة</div>
                </div>
                <div style="background: #f1f5f9; padding: 10px; border-radius: 6px; text-align: center;">
                    <div style="font-size: 11px; color: #64748b;">العمود D</div>
                    <div style="font-weight: 800;">الجنسية</div>
                </div>
                <div style="background: #f1f5f9; padding: 10px; border-radius: 6px; text-align: center;">
                    <div style="font-size: 11px; color: #64748b;">العمود E</div>
                    <div style="font-weight: 800;">بريد ولي الأمر</div>
                </div>
                <div style="background: #f1f5f9; padding: 10px; border-radius: 6px; text-align: center;">
                    <div style="font-size: 11px; color: #64748b;">العمود F</div>
                    <div style="font-weight: 800;">هاتف ولي الأمر</div>
                </div>
            </div>
            <p style="font-size:12px; color:#718096; line-height: 1.6;">يرجى التأكد من أن ملف الإكسل يحتوي على كافة سجلات الأعضاء وأن البيانات مرتبة بدقة في الأعمدة المذكورة أعلاه (A, B, C) لضمان نجاح عملية الاستيراد.</p>
        </div>

        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('sm_admin_action', 'sm_admin_nonce'); ?>
            <div class="sm-form-group">
                <label class="sm-label">اختر ملف CSV للملفات:</label>
                <input type="file" name="csv_file" accept=".csv" required>
            </div>
            <div style="display:flex; gap:10px; margin-top:20px;">
                <button type="submit" name="sm_import_csv" class="sm-btn" style="width:auto; background:#27ae60;">بدء عملية الاستيراد</button>
                <button type="button" onclick="this.parentElement.parentElement.parentElement.style.display='none'" class="sm-btn" style="width:auto; background:var(--sm-text-gray);">إلغاء</button>
            </div>
        </form>
    </div>

    <div style="display: flex; gap: 10px; margin-bottom: 15px; align-items: center; background: #f8fafc; padding: 10px 20px; border-radius: 8px; border: 1px solid #e2e8f0;">
        <span style="font-size: 13px; font-weight: 700; color: #4a5568;">الإجراءات الجماعية:</span>
        <button onclick="bulkDeleteSelected()" class="sm-btn" style="background: #e53e3e; font-size: 11px; padding: 5px 15px; width: auto;">حذف المحدد</button>
    </div>

    <div class="sm-table-container">
        <table class="sm-table">
            <thead>
                <tr>
                    <th style="width: 40px;"><input type="checkbox" id="select-all-members" onclick="toggleAllMembers(this)"></th>
                    <th>الرقم القومي</th>
                    <th>الاسم</th>
                    <th>الدرجة الوظيفية</th>
                    <th>التخصص</th>
                    <th>رقم العضوية</th>
                    <th>الحالة</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($members)): ?>
                    <tr>
                        <td colspan="8" style="padding: 60px; text-align: center; color: var(--sm-text-gray);">
                            <span class="dashicons dashicons-search" style="font-size: 40px; width:40px; height:40px; margin-bottom:10px;"></span>
                            <p>لا يوجد أعضاء يطابقون معايير البحث الحالية.</p>
                        </td>
                    </tr>
                <?php else:
                    $grades = SM_Settings::get_professional_grades();
                    $specs = SM_Settings::get_specializations();
                    $statuses = SM_Settings::get_membership_statuses();
                    foreach ($members as $member): ?>
                        <tr id="stu-row-<?php echo $member->id; ?>">
                            <td><input type="checkbox" class="member-checkbox" value="<?php echo $member->id; ?>"></td>
                            <td style="font-family: 'Rubik', sans-serif; font-weight: 700; color: var(--sm-primary-color);"><?php echo esc_html($member->national_id); ?></td>
                            <td style="font-weight: 800; color: var(--sm-dark-color);"><?php echo esc_html($member->name); ?></td>
                            <td><?php echo esc_html($grades[$member->professional_grade] ?? $member->professional_grade); ?></td>
                            <td><?php echo esc_html($specs[$member->specialization] ?? $member->specialization); ?></td>
                            <td><?php echo esc_html($member->membership_number); ?></td>
                            <td><span class="sm-badge sm-badge-low"><?php echo esc_html($statuses[$member->membership_status] ?? $member->membership_status); ?></span></td>
                            <td>
                                <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                    <button onclick='viewSmMember(<?php echo json_encode(array(
                                        "id" => $member->id,
                                        "name" => $member->name,
                                        "national_id" => $member->national_id,
                                        "photo" => $member->photo_url
                                    )); ?>)' class="sm-btn sm-btn-outline" style="padding: 5px 12px; font-size: 12px; height: 32px; min-width: 80px;">
                                        <span class="dashicons dashicons-visibility"></span> سجل
                                    </button>

                                    <?php if ($is_admin):
                                        $temp_pass = get_user_meta($member->parent_user_id, 'sm_temp_pass', true);
                                    ?>
                                        <button onclick='showMemberCreds("<?php echo esc_js($member->national_id); ?>", "<?php echo esc_js($temp_pass ?: '********'); ?>", "<?php echo esc_js($member->name); ?>", "<?php echo $member->id; ?>")' class="sm-btn sm-btn-outline" style="padding: 5px; min-width: 32px;" title="بيانات الدخول"><span class="dashicons dashicons-lock"></span></button>

                                        <button onclick='editSmMember(<?php echo json_encode($member); ?>)' class="sm-btn sm-btn-outline" style="padding: 5px; min-width: 32px;" title="تعديل"><span class="dashicons dashicons-edit"></span></button>

                                        <a href="<?php echo admin_url('admin-ajax.php?action=sm_print&print_type=id_card&member_id=' . $member->id); ?>" target="_blank" class="sm-btn sm-btn-outline" style="padding: 5px; min-width: 32px;" title="بطاقة العضوية"><span class="dashicons dashicons-id"></span></a>

                                        <button onclick="confirmDeleteMember(<?php echo $member->id; ?>, '<?php echo esc_js($member->name); ?>')" class="sm-btn sm-btn-outline" style="padding: 5px; min-width: 32px; color: #e53e3e;" title="حذف العضو"><span class="dashicons dashicons-trash"></span></button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <style>
    .sm-member-row:hover {
        border-color: var(--sm-primary-color);
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05);
        transform: translateX(-5px);
    }
    .sm-action-btn-row {
        padding: 8px 15px;
        border-radius: 8px;
        border: none;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s;
    }
    .sm-action-btn-row:hover {
        opacity: 0.8;
        transform: translateY(-1px);
    }
    .sm-icon-btn-row {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: #f7fafc;
        border: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #4a5568;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
    }
    .sm-icon-btn-row:hover {
        background: #edf2f7;
        color: var(--sm-primary-color);
    }
    </style>

    <?php if ($is_admin): ?>
    <div id="add-single-member-modal" class="sm-modal-overlay">
        <div class="sm-modal-content" style="max-width: 900px;">
            <div class="sm-modal-header">
                <h3>تسجيل عضو جديد في النظام</h3>
                <button class="sm-modal-close" onclick="document.getElementById('add-single-member-modal').style.display='none'">&times;</button>
            </div>
            <form id="add-member-form">
                <?php wp_nonce_field('sm_add_member', 'sm_nonce'); ?>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; background:#f8fafc; padding:20px; border-radius:12px; border:1px solid #edf2f7;">
                    <div class="sm-form-group">
                        <label class="sm-label">الرقم القومي (14 رقم):</label>
                        <input name="national_id" type="text" class="sm-input" required maxlength="14" pattern="[0-9]{14}">
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">الاسم الكامل:</label>
                        <input name="name" type="text" class="sm-input" required>
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">الجنس:</label>
                        <select name="gender" class="sm-select">
                            <option value="male">ذكر</option>
                            <option value="female">أنثى</option>
                        </select>
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">الدرجة الوظيفية:</label>
                        <select name="professional_grade" class="sm-select">
                            <?php foreach (SM_Settings::get_professional_grades() as $k => $v) echo "<option value='$k'>$v</option>"; ?>
                        </select>
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">التخصص المهني:</label>
                        <select name="specialization" class="sm-select">
                            <?php foreach (SM_Settings::get_specializations() as $k => $v) echo "<option value='$k'>$v</option>"; ?>
                        </select>
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">الدرجة العلمية:</label>
                        <select name="academic_degree" class="sm-select">
                            <?php foreach (SM_Settings::get_academic_degrees() as $k => $v) echo "<option value='$k'>$v</option>"; ?>
                        </select>
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">رقم العضوية:</label>
                        <input name="membership_number" type="text" class="sm-input">
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">تاريخ بدء العضوية:</label>
                        <input name="membership_start_date" type="date" class="sm-input">
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">تاريخ انتهاء العضوية:</label>
                        <input name="membership_expiration_date" type="date" class="sm-input">
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">حالة العضوية:</label>
                        <select name="membership_status" class="sm-select">
                            <?php foreach (SM_Settings::get_membership_statuses() as $k => $v) echo "<option value='$k'>$v</option>"; ?>
                        </select>
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">رقم ترخيص المزاولة:</label>
                        <input name="license_number" type="text" class="sm-input">
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">تاريخ صدور الترخيص:</label>
                        <input name="license_issue_date" type="date" class="sm-input">
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">تاريخ انتهاء الترخيص:</label>
                        <input name="license_expiration_date" type="date" class="sm-input">
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">رقم المنشأة:</label>
                        <input name="facility_number" type="text" class="sm-input">
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">اسم المنشأة:</label>
                        <input name="facility_name" type="text" class="sm-input">
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">تاريخ ترخيص المنشأة:</label>
                        <input name="facility_license_issue_date" type="date" class="sm-input">
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">تاريخ انتهاء المنشأة:</label>
                        <input name="facility_license_expiration_date" type="date" class="sm-input">
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">النقابة الفرعية:</label>
                        <input name="sub_syndicate" type="text" class="sm-input">
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">البريد الإلكتروني:</label>
                        <input name="email" type="email" class="sm-input">
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">رقم الهاتف:</label>
                        <input name="phone" type="text" class="sm-input">
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">هاتف بديل:</label>
                        <input name="alt_phone" type="text" class="sm-input">
                    </div>
                    <div class="sm-form-group" style="grid-column: span 3;">
                        <label class="sm-label">عنوان المنشأة:</label>
                        <input name="facility_address" type="text" class="sm-input">
                    </div>
                    <div class="sm-form-group" style="grid-column: span 3;">
                        <label class="sm-label">ملاحظات:</label>
                        <textarea name="notes" class="sm-textarea" rows="2"></textarea>
                    </div>
                </div>
                <div style="text-align:left; margin-top:20px;">
                    <button type="submit" class="sm-btn" style="width:200px;">إضافة العضو</button>
                </div>
            </form>
        </div>
    </div>

    <div id="edit-member-modal" class="sm-modal-overlay">
        <div class="sm-modal-content" style="max-width: 900px;">
            <div class="sm-modal-header">
                <h3>تعديل بيانات العضو</h3>
                <button class="sm-modal-close" onclick="document.getElementById('edit-member-modal').style.display='none'">&times;</button>
            </div>
            <form id="edit-member-form">
                <?php wp_nonce_field('sm_add_member', 'sm_nonce'); ?>
                <input type="hidden" name="member_id" id="edit_stu_id">

                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; background:#f8fafc; padding:20px; border-radius:12px; border:1px solid #edf2f7;">
                    <div class="sm-form-group">
                        <label class="sm-label">الرقم القومي:</label>
                        <input name="national_id" id="edit_national_id" type="text" class="sm-input" required maxlength="14" pattern="[0-9]{14}">
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">الاسم الكامل:</label>
                        <input name="name" id="edit_name" type="text" class="sm-input" required>
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">الجنس:</label>
                        <select name="gender" id="edit_gender" class="sm-select">
                            <option value="male">ذكر</option>
                            <option value="female">أنثى</option>
                        </select>
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">الدرجة الوظيفية:</label>
                        <select name="professional_grade" id="edit_professional_grade" class="sm-select">
                            <?php foreach (SM_Settings::get_professional_grades() as $k => $v) echo "<option value='$k'>$v</option>"; ?>
                        </select>
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">التخصص المهني:</label>
                        <select name="specialization" id="edit_specialization" class="sm-select">
                            <?php foreach (SM_Settings::get_specializations() as $k => $v) echo "<option value='$k'>$v</option>"; ?>
                        </select>
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">الدرجة العلمية:</label>
                        <select name="academic_degree" id="edit_academic_degree" class="sm-select">
                            <?php foreach (SM_Settings::get_academic_degrees() as $k => $v) echo "<option value='$k'>$v</option>"; ?>
                        </select>
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">رقم العضوية:</label>
                        <input name="membership_number" id="edit_membership_number" type="text" class="sm-input">
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">تاريخ بدء العضوية:</label>
                        <input name="membership_start_date" id="edit_membership_start_date" type="date" class="sm-input">
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">تاريخ انتهاء العضوية:</label>
                        <input name="membership_expiration_date" id="edit_membership_expiration_date" type="date" class="sm-input">
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">حالة العضوية:</label>
                        <select name="membership_status" id="edit_membership_status" class="sm-select">
                            <?php foreach (SM_Settings::get_membership_statuses() as $k => $v) echo "<option value='$k'>$v</option>"; ?>
                        </select>
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">رقم ترخيص المزاولة:</label>
                        <input name="license_number" id="edit_license_number" type="text" class="sm-input">
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">تاريخ صدور الترخيص:</label>
                        <input name="license_issue_date" id="edit_license_issue_date" type="date" class="sm-input">
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">تاريخ انتهاء الترخيص:</label>
                        <input name="license_expiration_date" id="edit_license_expiration_date" type="date" class="sm-input">
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">رقم المنشأة:</label>
                        <input name="facility_number" id="edit_facility_number" type="text" class="sm-input">
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">اسم المنشأة:</label>
                        <input name="facility_name" id="edit_facility_name" type="text" class="sm-input">
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">تاريخ ترخيص المنشأة:</label>
                        <input name="facility_license_issue_date" id="edit_facility_license_issue_date" type="date" class="sm-input">
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">تاريخ انتهاء المنشأة:</label>
                        <input name="facility_license_expiration_date" id="edit_facility_license_expiration_date" type="date" class="sm-input">
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">النقابة الفرعية:</label>
                        <input name="sub_syndicate" id="edit_sub_syndicate" type="text" class="sm-input">
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">البريد الإلكتروني:</label>
                        <input name="email" id="edit_email" type="email" class="sm-input">
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">رقم الهاتف:</label>
                        <input name="phone" id="edit_phone" type="text" class="sm-input">
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">هاتف بديل:</label>
                        <input name="alt_phone" id="edit_alt_phone" type="text" class="sm-input">
                    </div>
                    <div class="sm-form-group" style="grid-column: span 3;">
                        <label class="sm-label">عنوان المنشأة:</label>
                        <input name="facility_address" id="edit_facility_address" type="text" class="sm-input">
                    </div>
                    <div class="sm-form-group" style="grid-column: span 3;">
                        <label class="sm-label">ملاحظات:</label>
                        <textarea name="notes" id="edit_notes" class="sm-textarea" rows="2"></textarea>
                    </div>
                </div>

                <div style="display:flex; gap:15px; margin-top:20px; justify-content: flex-end;">
                    <button type="submit" class="sm-btn" style="width:200px;">تحديث البيانات</button>
                    <button type="button" onclick="document.getElementById('edit-member-modal').style.display='none'" class="sm-btn" style="background:#cbd5e0; color:#2d3748; width:100px;">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Delete Confirmation Modal -->
    <div id="delete-member-modal" class="sm-modal-overlay">
        <div class="sm-modal-content" style="max-width: 400px; text-align: center;">
            <div class="sm-modal-header">
                <h3>تأكيد الحذف</h3>
                <button class="sm-modal-close" onclick="document.getElementById('delete-member-modal').style.display='none'">&times;</button>
            </div>
            <div style="color: #e53e3e; font-size: 50px; margin-bottom: 15px;"><span class="dashicons dashicons-warning" style="font-size: 50px; width:50px; height:50px;"></span></div>
            <p id="delete-confirm-msg">هل أنت متأكد من حذف العضو وسجلاته بالكامل؟</p>
            <form method="post" id="delete-member-form">
                <?php wp_nonce_field('sm_add_member', 'sm_nonce'); ?>
                <input type="hidden" name="delete_member_id" id="confirm_delete_stu_id">
                <div style="display: flex; gap: 10px; margin-top: 25px;">
                    <button type="submit" name="delete_member" class="sm-btn" style="background: #e53e3e;">تأكيد الحذف النهائي</button>
                    <button type="button" onclick="document.getElementById('delete-member-modal').style.display='none'" class="sm-btn" style="background: #cbd5e0; color: #2d3748;">تراجع</button>
                </div>
            </form>
        </div>
    </div>

    <div id="view-member-modal" class="sm-modal-overlay">
        <div class="sm-modal-content" style="max-width: 950px; background: #fdfdfd;">
            <div class="sm-modal-header" style="border-bottom: 3px solid var(--sm-primary-color); padding-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                    <h3 style="margin:0; font-size: 1.5em; font-weight: 900; color: #111F35;">السجل الانضباطي الشامل</h3>
                    <div style="display: flex; gap: 10px;">
                        <button id="print-full-record-btn" class="sm-btn" style="background: #27ae60; width: auto; font-size: 13px; font-weight: 700; height: 40px; display: flex; align-items: center; gap: 8px;">
                            <span class="dashicons dashicons-printer"></span> طباعة السجل الكامل PDF
                        </button>
                        <button class="sm-modal-close" style="position:static; margin:0;" onclick="document.getElementById('view-member-modal').style.display='none'">&times;</button>
                    </div>
                </div>
            </div>
            <div id="stu_details_content" style="padding: 20px 0; max-height: 70vh; overflow-y: auto;"></div>
            <div style="margin-top: 20px; text-align: left; border-top: 1px solid #eee; padding-top: 20px;">
                <button type="button" onclick="document.getElementById('view-member-modal').style.display='none'" class="sm-btn" style="width:auto; background:var(--sm-text-gray); height: 40px;">إغلاق النافذة</button>
            </div>
        </div>
    </div>

    <!-- Member Credentials Modal -->
    <div id="member-creds-modal" class="sm-modal-overlay">
        <div class="sm-modal-content" style="max-width: 400px; text-align: center;">
            <div class="sm-modal-header">
                <h3>بيانات دخول العضو</h3>
                <button class="sm-modal-close" onclick="document.getElementById('member-creds-modal').style.display='none'">&times;</button>
            </div>
            <div style="padding: 20px; background: #f8fafc; border-radius: 12px; margin-top: 15px; border: 1px solid #edf2f7;">
                <div style="font-weight: 800; color: var(--sm-dark-color); margin-bottom: 15px; font-size: 1.1em;" id="cred-stu-name"></div>

                <div style="margin-bottom: 15px;">
                    <div style="font-size: 11px; color: #718096; margin-bottom: 5px;">اسم المستخدم (كود العضو):</div>
                    <div style="font-family: monospace; font-size: 1.3em; font-weight: 900; color: var(--sm-primary-color); background: #fff; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0;" id="cred-username"></div>
                </div>

                <div style="margin-bottom: 10px;">
                    <div style="font-size: 11px; color: #718096; margin-bottom: 5px;">كلمة المرور المؤقتة:</div>
                    <div style="font-family: monospace; font-size: 1.3em; font-weight: 900; color: var(--sm-dark-color); background: #fff; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0;" id="cred-password"></div>
                </div>
            </div>

            <div style="margin-top: 25px; display: flex; gap: 10px;">
                <a href="#" id="cred-download-link" target="_blank" class="sm-btn" style="background: #3182ce; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px; flex: 1;">
                    <span class="dashicons dashicons-download"></span> تحميل البطاقة
                </a>
                <button onclick="document.getElementById('member-creds-modal').style.display='none'" class="sm-btn sm-btn-outline" style="flex: 1;">إغلاق</button>
            </div>
        </div>
    </div>

    <script>
    (function() {
        // Edit Handlers
        window.editSmMember = function(s) {
            document.getElementById('edit_stu_id').value = s.id;
            document.getElementById('edit_national_id').value = s.national_id || '';
            document.getElementById('edit_name').value = s.name || '';
            document.getElementById('edit_gender').value = s.gender || 'male';
            document.getElementById('edit_professional_grade').value = s.professional_grade || '';
            document.getElementById('edit_specialization').value = s.specialization || '';
            document.getElementById('edit_academic_degree').value = s.academic_degree || '';
            document.getElementById('edit_membership_number').value = s.membership_number || '';
            document.getElementById('edit_membership_start_date').value = s.membership_start_date || '';
            document.getElementById('edit_membership_expiration_date').value = s.membership_expiration_date || '';
            document.getElementById('edit_membership_status').value = s.membership_status || '';
            document.getElementById('edit_license_number').value = s.license_number || '';
            document.getElementById('edit_license_issue_date').value = s.license_issue_date || '';
            document.getElementById('edit_license_expiration_date').value = s.license_expiration_date || '';
            document.getElementById('edit_facility_number').value = s.facility_number || '';
            document.getElementById('edit_facility_name').value = s.facility_name || '';
            document.getElementById('edit_facility_license_issue_date').value = s.facility_license_issue_date || '';
            document.getElementById('edit_facility_license_expiration_date').value = s.facility_license_expiration_date || '';
            document.getElementById('edit_facility_address').value = s.facility_address || '';
            document.getElementById('edit_sub_syndicate').value = s.sub_syndicate || '';
            document.getElementById('edit_email').value = s.email || '';
            document.getElementById('edit_phone').value = s.phone || '';
            document.getElementById('edit_alt_phone').value = s.alt_phone || '';
            document.getElementById('edit_notes').value = s.notes || '';

            document.getElementById('edit-member-modal').style.display = 'flex';
        };

        // Show Credentials
        window.showMemberCreds = function(user, pass, name, id) {
            document.getElementById('cred-username').innerText = user;
            document.getElementById('cred-password').innerText = pass;
            document.getElementById('cred-stu-name').innerText = name;
            document.getElementById('cred-download-link').href = '<?php echo admin_url('admin-ajax.php?action=sm_print&print_type=member_credentials_card&member_id='); ?>' + id;
            document.getElementById('member-creds-modal').style.display = 'flex';
        };

        // Handle View Record
        window.viewSmMember = function(member) {
            const modal = document.getElementById('view-member-modal');
            const content = document.getElementById('stu_details_content');
            const printBtn = document.getElementById('print-full-record-btn');
            if (!modal || !content) return;

            content.innerHTML = '<div style="text-align:center; padding:50px;"><p style="font-weight:700; color:#718096;">جاري جلب الملف الانضباطي وتنسيقه...</p></div>';
            modal.style.display = 'flex';

            printBtn.onclick = function() {
                window.open('<?php echo admin_url('admin-ajax.php'); ?>?action=sm_print&print_type=disciplinary_report&member_id=' + member.id, '_blank');
            };

            fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=sm_print&print_type=disciplinary_report&member_id=' + member.id)
                .then(r => r.text())
                .then(html => {
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    // Remove print buttons from the content
                    doc.querySelectorAll('.no-print').forEach(el => el.remove());
                    // Enhance style for modal display
                    const styles = doc.querySelectorAll('style');
                    content.innerHTML = doc.body.innerHTML;

                    // Re-apply styles scoped to content if needed or just rely on existing report styling
                    // The report is already RTL and has fonts.
                });
        };

        // Handle Add Member AJAX
        const addForm = document.getElementById('add-member-form');
        if (addForm) {
            addForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('action', 'sm_add_member_ajax');

                fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        smShowNotification('تمت إضافة العضو بنجاح');
                        setTimeout(() => location.reload(), 500);
                    } else {
                        smShowNotification('خطأ: ' + res.data, true);
                    }
                })
                .catch(err => {
                    smShowNotification('حدث خطأ أثناء الاتصال بالخادم', true);
                });
            });
        }

        // Handle Edit Member AJAX
        const editForm = document.getElementById('edit-member-form');
        if (editForm) {
            editForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('action', 'sm_update_member_ajax');

                fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        smShowNotification('تم تحديث بيانات العضو');
                        setTimeout(() => location.reload(), 500);
                    } else {
                        smShowNotification('خطأ: ' + res.data, true);
                    }
                })
                .catch(err => {
                    smShowNotification('حدث خطأ أثناء الاتصال بالخادم', true);
                });
            });
        }

        // Handle Delete
        window.confirmDeleteMember = function(id, name) {
            document.getElementById('confirm_delete_stu_id').value = id;
            document.getElementById('delete-confirm-msg').innerText = `هل أنت متأكد من حذف العضو "${name}" وكافة سجلاته؟`;
            document.getElementById('delete-member-modal').style.display = 'flex';
        };

        const deleteForm = document.getElementById('delete-member-form');
        if (deleteForm) {
            deleteForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('action', 'sm_delete_member_ajax');
                formData.append('nonce', '<?php echo wp_create_nonce("sm_delete_member"); ?>');
                formData.append('member_id', document.getElementById('confirm_delete_stu_id').value);

                fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        smShowNotification('تم حذف العضو');
                        setTimeout(() => location.reload(), 500);
                    } else {
                        smShowNotification('خطأ: ' + res.data, true);
                    }
                })
                .catch(err => {
                    smShowNotification('حدث خطأ أثناء الاتصال بالخادم', true);
                });
            });
        }

        window.toggleAllMembers = function(master) {
            document.querySelectorAll('.member-checkbox').forEach(cb => cb.checked = master.checked);
        };

        window.bulkDeleteSelected = function() {
            const selected = Array.from(document.querySelectorAll('.member-checkbox:checked')).map(cb => cb.value);
            if (selected.length === 0) { alert('يرجى اختيار أعضاء أولاً'); return; }
            if (!confirm(`هل أنت متأكد من حذف ${selected.length} عضو نهائياً؟`)) return;

            const formData = new FormData();
            formData.append('action', 'sm_bulk_delete_members_ajax');
            formData.append('member_ids', selected.join(','));
            formData.append('nonce', '<?php echo wp_create_nonce("sm_delete_member"); ?>');

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    smShowNotification(`تم حذف ${selected.length} عضو بنجاح`);
                    setTimeout(() => location.reload(), 500);
                }
            });
        };
    })();
    </script>
</div>
