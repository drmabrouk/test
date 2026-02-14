<?php if (!defined('ABSPATH')) exit; ?>
<div class="sm-printing-center" dir="rtl">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <h3 style="margin:0; border:none; padding:0;">مركز الطباعة والتقارير</h3>
        <div style="background: #f0f7ff; padding: 10px 20px; border-radius: 8px; border: 1px solid #c3dafe; font-size: 0.9em; color: var(--sm-primary-color); font-weight: 600;">
            إعدادات الطباعة: A4 عمودي
        </div>
    </div>

    <div style="display: flex; flex-direction: column; gap: 40px;">

        <!-- Section: Identity Cards -->
        <div>
            <h4 style="margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid var(--sm-primary-color); display: flex; align-items: center; gap: 10px; color: var(--sm-dark-color);">
                <span class="dashicons dashicons-id"></span> بطاقات الهوية التعريفية
            </h4>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                <!-- Member ID Cards (All) -->
                <div style="background: #fff; padding: 25px; border-radius: 15px; border: 1px solid var(--sm-border-color); display: flex; flex-direction: column; justify-content: space-between; box-shadow: var(--sm-shadow);">
                    <div>
                        <div style="width: 50px; height: 50px; background: #F8FAFC; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 20px; color: #3182CE;">
                            <span class="dashicons dashicons-groups" style="font-size: 28px; width: 28px; height: 28px;"></span>
                        </div>
                        <h4 style="margin: 0 0 10px 0; border: none; font-weight: 800; font-size: 15px;">بطاقات الأعضاء (الكل)</h4>
                <p style="font-size: 11px; color: #718096; line-height: 1.6; margin-bottom: 20px;">طباعة بطاقات التعريف لكافة الأعضاء في النظام أو حسب درجة محددة.</p>
                <div class="sm-form-group">
                    <select id="card_grade_filter" class="sm-select" style="font-size: 12px; padding: 8px;">
                        <option value="">كافة الدرجات</option>
                        <?php
                        foreach(SM_Settings::get_professional_grades() as $k => $v) echo '<option value="'.$k.'">'.$v.'</option>';
                        ?>
                    </select>
                </div>
            </div>
            <button onclick="printCards()" class="sm-btn" style="background: #3182CE; font-size: 12px;">طباعة البطاقات</button>
        </div>

                <!-- Specific Member ID Card -->
                <div style="background: #fff; padding: 25px; border-radius: 15px; border: 1px solid var(--sm-border-color); display: flex; flex-direction: column; justify-content: space-between; box-shadow: var(--sm-shadow);">
                    <div>
                        <div style="width: 50px; height: 50px; background: #FFF5F5; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 20px; color: #E53E3E;">
                            <span class="dashicons dashicons-id-alt" style="font-size: 28px; width: 28px; height: 28px;"></span>
                        </div>
                        <h4 style="margin: 0 0 10px 0; border: none; font-weight: 800; font-size: 15px;">بطاقة عضو محدد</h4>
                        <p style="font-size: 11px; color: #718096; line-height: 1.6; margin-bottom: 20px;">استخراج بطاقة تعريفية رسمية لعضو واحد فقط بالاسم والكود.</p>
                        <div class="sm-form-group">
                            <select id="specific_card_member_id" class="sm-select" style="font-size: 12px; padding: 8px;">
                                <?php
                                $members = SM_DB::get_members();
                                foreach($members as $s) echo '<option value="'.$s->id.'">'.$s->name.'</option>';
                                ?>
                            </select>
                        </div>
                    </div>
                    <button onclick="printSpecificCard()" class="sm-btn" style="background: #E53E3E; font-size: 12px;">توليد البطاقة</button>
                </div>
            </div>
        </div>


        <!-- Section: Administrative & Lists -->
        <div>
            <h4 style="margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #4A5568; display: flex; align-items: center; gap: 10px; color: var(--sm-dark-color);">
                <span class="dashicons dashicons-admin-generic"></span> القوائم والبيانات الإدارية
            </h4>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                <!-- Full Member List -->
                <div style="background: #fff; padding: 25px; border-radius: 15px; border: 1px solid var(--sm-border-color); display: flex; flex-direction: column; justify-content: space-between; box-shadow: var(--sm-shadow);">
                    <div>
                        <div style="width: 50px; height: 50px; background: #EBF8FF; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 20px; color: #2B6CB0;">
                            <span class="dashicons dashicons-editor-ul" style="font-size: 28px; width: 28px; height: 28px;"></span>
                        </div>
                        <h4 style="margin: 0 0 10px 0; border: none; font-weight: 800; font-size: 15px;">قائمة الأعضاء الكاملة</h4>
                        <p style="font-size: 11px; color: #718096; line-height: 1.6; margin-bottom: 20px;">طباعة كشف بجميع أعضاء النقابة مصنفين حسب الدرجة المهنية والتخصص.</p>
                    </div>
                    <button onclick="alert('قريباً: طباعة القائمة الكاملة')" class="sm-btn" style="background: #2B6CB0; font-size: 12px;">طباعة القائمة</button>
                </div>

                <!-- Member Login Credentials -->
                <div style="background: #fff; padding: 25px; border-radius: 15px; border: 1px solid var(--sm-border-color); display: flex; flex-direction: column; justify-content: space-between; box-shadow: var(--sm-shadow);">
                    <div>
                        <div style="width: 50px; height: 50px; background: #F7FAFC; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 20px; color: #4A5568;">
                            <span class="dashicons dashicons-lock" style="font-size: 28px; width: 28px; height: 28px;"></span>
                        </div>
                        <h4 style="margin: 0 0 10px 0; border: none; font-weight: 800; font-size: 15px;">بيانات دخول الأعضاء</h4>
                        <p style="font-size: 11px; color: #718096; line-height: 1.6; margin-bottom: 20px;">توليد كشف بأسماء الأعضاء مع اسم المستخدم (الرقم القومي) وكلمة المرور المؤقتة.</p>
                        <div class="sm-form-group">
                            <select id="creds_grade_filter" class="sm-select" style="font-size: 12px; padding: 8px;">
                                <option value="">كافة الدرجات</option>
                                <?php foreach(SM_Settings::get_professional_grades() as $k => $v) echo '<option value="'.$k.'">'.$v.'</option>'; ?>
                            </select>
                        </div>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <button onclick="printCredentials()" class="sm-btn" style="background: #4A5568; font-size: 11px; flex: 1;">كشف البيانات</button>
                        <button onclick="printCredentialsCard()" class="sm-btn" style="background: #8A244B; font-size: 11px; flex: 1;">بطاقات الدخول</button>
                    </div>
                </div>

                <!-- Single Member Login Data -->
                <div style="background: #fff; padding: 25px; border-radius: 15px; border: 1px solid var(--sm-border-color); display: flex; flex-direction: column; justify-content: space-between; box-shadow: var(--sm-shadow);">
                    <div>
                        <div style="width: 50px; height: 50px; background: #FFF5F7; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 20px; color: #D53F8C;">
                            <span class="dashicons dashicons-admin-users" style="font-size: 28px; width: 28px; height: 28px;"></span>
                        </div>
                        <h4 style="margin: 0 0 10px 0; border: none; font-weight: 800; font-size: 15px;">بيانات دخول عضو واحد</h4>
                        <p style="font-size: 11px; color: #718096; line-height: 1.6; margin-bottom: 20px;">استخراج بيانات الدخول (الاسم، المستخدم، كلمة المرور) لعضو واحد فقط.</p>
                        <div class="sm-form-group">
                            <select id="single_creds_member_id" class="sm-select" style="font-size: 12px; padding: 8px;">
                                <?php foreach($members as $s) echo '<option value="'.$s->id.'">'.$s->name.'</option>'; ?>
                            </select>
                        </div>
                    </div>
                    <button onclick="printSingleMemberCreds()" class="sm-btn" style="background: #D53F8C; font-size: 12px;">توليد بطاقة الدخول</button>
                </div>
            </div>
        </div>

        <!-- Excel Templates Section -->
        <div style="grid-column: 1 / -1; background: #f8fafc; padding: 30px; border-radius: 12px; border: 2px dashed #cbd5e1; margin-top: 20px;">
            <h4 style="margin-top:0; color:var(--sm-secondary-color); display:flex; align-items:center; gap:10px;">
                <span class="dashicons dashicons-media-spreadsheet"></span> نماذج إكسل جاهزة للاستخدام
            </h4>
            <p style="font-size: 0.9em; color: #64748b; margin-bottom: 20px;">قم بتحميل النماذج التالية، املأ البيانات، ثم ارفعها في الأقسام المخصصة لتسريع عملية إدخال البيانات.</p>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <a href="data:text/csv;charset=utf-8,<?php echo rawurlencode("الاسم,الدرجة المهنية,البريد,الكود\nاسم العضو,specialist,member@syndicate.local,MEM001"); ?>" download="members_template.csv" class="sm-btn" style="background:#fff; color:#2d3748; border:1px solid #cbd5e1; font-size:13px; text-decoration:none; display:flex; align-items:center; justify-content:center; gap:8px;">
                    <span class="dashicons dashicons-download"></span> نموذج الأعضاء
                </a>
                <a href="data:text/csv;charset=utf-8,<?php echo rawurlencode("الاسم,المستخدم,البريد,المعرف الوظيفي,الدور,الجوال,كلمة السر\nاسم المستخدم,staff_user,staff@syndicate.local,T100,sm_syndicate_member,0500000000,pass123"); ?>" download="staffs_template.csv" class="sm-btn" style="background:#fff; color:#2d3748; border:1px solid #cbd5e1; font-size:13px; text-decoration:none; display:flex; align-items:center; justify-content:center; gap:8px;">
                    <span class="dashicons dashicons-download"></span> نموذج مستخدمي النظام
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function printCards() {
    const gradeFilter = document.getElementById('card_grade_filter').value;
    window.open('<?php echo admin_url('admin-ajax.php?action=sm_print&print_type=id_card'); ?>&grade_filter=' + encodeURIComponent(gradeFilter), '_blank');
}

function printSpecificCard() {
    const memberId = document.getElementById('specific_card_member_id').value;
    window.open('<?php echo admin_url('admin-ajax.php?action=sm_print&print_type=id_card'); ?>&member_id=' + memberId, '_blank');
}


function printCredentials() {
    const gradeFilter = document.getElementById('creds_grade_filter').value;
    window.open('<?php echo admin_url('admin-ajax.php?action=sm_print&print_type=member_credentials'); ?>&grade_filter=' + encodeURIComponent(gradeFilter), '_blank');
}

function printCredentialsCard() {
    const gradeFilter = document.getElementById('creds_grade_filter').value;
    window.open('<?php echo admin_url('admin-ajax.php?action=sm_print&print_type=member_credentials_card'); ?>&grade_filter=' + encodeURIComponent(gradeFilter), '_blank');
}

function printSingleMemberCreds() {
    const memberId = document.getElementById('single_creds_member_id').value;
    window.open('<?php echo admin_url('admin-ajax.php?action=sm_print&print_type=member_credentials_card'); ?>&member_id=' + memberId, '_blank');
}
</script>
