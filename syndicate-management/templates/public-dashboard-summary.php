<?php
if (!defined('ABSPATH')) exit;
if (in_array('sm_member', (array)wp_get_current_user()->roles)) {
    echo '<p>يرجى التوجه إلى لوحة المعلومات الخاصة بك.</p>';
    return;
}

// Check for active surveys for current user role
$user_role = !empty(wp_get_current_user()->roles) ? wp_get_current_user()->roles[0] : '';
$active_surveys = SM_DB::get_surveys($user_role);

foreach ($active_surveys as $survey):
    // Check if already responded
    global $wpdb;
    $responded = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sm_survey_responses WHERE survey_id = %d AND user_id = %d", $survey->id, get_current_user_id()));
    if ($responded) continue;
?>
<div class="sm-survey-card" style="background: #fffdf2; border: 2px solid #fef3c7; border-radius: 12px; padding: 25px; margin-bottom: 30px; position: relative; overflow: hidden;">
    <div style="position: absolute; top: 0; right: 0; background: #fbbf24; color: #78350f; font-size: 10px; font-weight: 800; padding: 4px 15px; border-radius: 0 0 0 12px;">استطلاع رأي هام</div>
    <h3 style="margin: 0 0 10px 0; color: #92400e;"><?php echo esc_html($survey->title); ?></h3>
    <p style="margin: 0 0 20px 0; font-size: 14px; color: #b45309;">يرجى المشاركة في هذا الاستطلاع القصير للمساهمة في تحسين جودة العملية المهنية.</p>

    <button class="sm-btn" style="background: #d97706; width: auto;" onclick="smOpenSurveyModal(<?php echo $survey->id; ?>)">المشاركة الآن</button>
</div>

<!-- Survey Participation Modal -->
<div id="survey-participation-modal-<?php echo $survey->id; ?>" class="sm-modal-overlay">
    <div class="sm-modal-content" style="max-width: 700px;">
        <div class="sm-modal-header">
            <h3><?php echo esc_html($survey->title); ?></h3>
            <button class="sm-modal-close" onclick="this.closest('.sm-modal-overlay').style.display='none'">&times;</button>
        </div>
        <div class="sm-modal-body" style="padding: 30px;">
            <div id="survey-questions-list-<?php echo $survey->id; ?>">
                <?php
                $questions = json_decode($survey->questions, true);
                foreach ($questions as $index => $q):
                ?>
                <div class="survey-question-block" style="margin-bottom: 25px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
                    <div style="font-weight: 800; margin-bottom: 15px; color: var(--sm-dark-color);"><?php echo ($index+1) . '. ' . esc_html($q); ?></div>
                    <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                        <label style="font-size: 13px; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="survey_q_<?php echo $survey->id; ?>_<?php echo $index; ?>" value="ممتاز" required> ممتاز
                        </label>
                        <label style="font-size: 13px; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="survey_q_<?php echo $survey->id; ?>_<?php echo $index; ?>" value="جيد جداً"> جيد جداً
                        </label>
                        <label style="font-size: 13px; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="survey_q_<?php echo $survey->id; ?>_<?php echo $index; ?>" value="جيد"> جيد
                        </label>
                        <label style="font-size: 13px; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="survey_q_<?php echo $survey->id; ?>_<?php echo $index; ?>" value="مقبول"> مقبول
                        </label>
                        <label style="font-size: 13px; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="survey_q_<?php echo $survey->id; ?>_<?php echo $index; ?>" value="غير راض"> غير راض
                        </label>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <button class="sm-btn" style="height: 45px; margin-top: 20px;" onclick="smSubmitSurveyResponse(<?php echo $survey->id; ?>, <?php echo count($questions); ?>)">إرسال الردود</button>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script>
function smOpenSurveyModal(id) {
    document.getElementById('survey-participation-modal-' + id).style.display = 'flex';
}

function smSubmitSurveyResponse(surveyId, questionsCount) {
    const responses = [];
    for (let i = 0; i < questionsCount; i++) {
        const selected = document.querySelector(`input[name="survey_q_${surveyId}_${i}"]:checked`);
        if (!selected) {
            smShowNotification('يرجى الإجابة على جميع الأسئلة', true);
            return;
        }
        responses.push(selected.value);
    }

    const formData = new FormData();
    formData.append('action', 'sm_submit_survey_response');
    formData.append('survey_id', surveyId);
    formData.append('responses', JSON.stringify(responses));
    formData.append('nonce', '<?php echo wp_create_nonce("sm_survey_action"); ?>');

    fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            smShowNotification('تم إرسال ردودك بنجاح. شكراً لمشاركتك!');
            location.reload();
        } else {
            smShowNotification('فشل إرسال الردود: ' + res.data, true);
        }
    });
}
</script>

<div class="sm-card-grid" style="margin-bottom: 40px;">
    <div class="sm-stat-card">
        <div style="font-size: 0.85em; color: var(--sm-text-gray); margin-bottom: 10px; font-weight: 700;">إجمالي الأعضاء</div>
        <div style="font-size: 2.5em; font-weight: 900; color: var(--sm-primary-color);"><?php echo esc_html($stats['total_members'] ?? 0); ?></div>
    </div>
    <div class="sm-stat-card">
        <div style="font-size: 0.85em; color: var(--sm-text-gray); margin-bottom: 10px; font-weight: 700;">إجمالي أعضاء النقابة</div>
        <div style="font-size: 2.5em; font-weight: 900; color: var(--sm-secondary-color);"><?php echo esc_html($stats['total_officers'] ?? 0); ?></div>
    </div>
</div>





<script>
function smDownloadChart(chartId, fileName) {
    const canvas = document.getElementById(chartId);
    if (!canvas) return;
    const link = document.createElement('a');
    link.download = fileName + '.png';
    link.href = canvas.toDataURL('image/png');
    link.click();
}

(function() {
    window.smCharts = window.smCharts || {};

    const initSummaryCharts = function() {
        if (typeof Chart === 'undefined') {
            setTimeout(initSummaryCharts, 200);
            return;
        }

        const chartOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } };
        const severityLabels = <?php echo json_encode(SM_Settings::get_severities()); ?>;

        const createOrUpdateChart = (id, config) => {
            if (window.smCharts[id]) {
                window.smCharts[id].destroy();
            }
            const el = document.getElementById(id);
            if (el) {
                window.smCharts[id] = new Chart(el.getContext('2d'), config);
            }
        };


    };

    if (document.readyState === 'complete') initSummaryCharts();
    else window.addEventListener('load', initSummaryCharts);
})();
</script>
