<?php if (!defined('ABSPATH')) exit; ?>
<div class="sm-form-container" dir="rtl">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
        <h3 class="sm-form-title" style="margin:0; border:none; padding:0; font-size: 1.2em; font-weight: 800; color: var(--sm-primary-color);">بيانات المخالفة الجديدة</h3>
        <div id="barcode-scanner-section" style="display: flex; align-items: center; gap: 15px;">
            <div style="display: flex; align-items: center; gap: 10px; background: #f1f5f9; padding: 5px 12px; border-radius: 8px; border: 1px solid #e2e8f0;">
                <label class="sm-label" style="margin:0; font-size: 12px; font-weight: 800; color: #475569;">التاريخ:</label>
                <input type="date" form="violation-form" name="custom_date" class="sm-input" value="<?php echo date('Y-m-d'); ?>" required style="padding: 4px 8px; font-size: 12px; width: auto; border: none; background: transparent;">
            </div>
            <button id="start-scanner" type="button" class="sm-btn" style="width: auto; padding: 10px 20px; background: var(--sm-dark-color); font-size: 13px; font-weight: 700;"><span class="dashicons dashicons-barcode" style="vertical-align: middle; margin-left: 5px;"></span> استخدام الماسح الضوئي</button>
        </div>
    </div>

    <div id="reader" style="width: 100%; max-width: 400px; margin: 0 auto 20px auto; display: none; border-radius: 8px; overflow: hidden; border: 2px solid var(--sm-primary-color);"></div>
    
    <div id="member-intelligence-panel" style="display:none; background: #fdfdfd; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 30px; border-right: 4px solid var(--sm-primary-color);">
        <h4 style="margin-top:0; color:var(--sm-primary-color);">تحليل سلوك العضو الذكي</h4>
        <div id="intel-content" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px;">
        </div>
        <div id="intel-history" style="margin-top: 15px; font-size: 0.85em; color: #666; border-top: 1px dashed #eee; padding-top: 10px;">
        </div>
    </div>

    <div id="sm-ajax-response" style="display:none; margin-bottom: 25px;"></div>

    <form method="post" id="violation-form">
        <?php wp_nonce_field('sm_record_action', 'sm_nonce'); ?>
        
        <div class="sm-form-group" style="position:relative;">
            <label class="sm-label">البحث عن الأعضاء (يمكنك اختيار أكثر من عضو):</label>
            <div style="display:flex; gap:10px;">
                <input type="text" id="member_unified_search" class="sm-input" placeholder="اكتب اسم العضو أو الكود..." autocomplete="off">
            </div>
            <div id="search_results_dropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:white; border:1px solid var(--sm-border-color); border-radius:0 0 8px 8px; z-index:1000; box-shadow:0 10px 15px -3px rgba(0,0,0,0.1); max-height:250px; overflow-y:auto;">
            </div>
            <div id="selected_members_container" style="display:flex; flex-wrap:wrap; gap:10px; margin-top:10px;">
            </div>
            <input type="hidden" name="member_ids" id="selected_member_ids" required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; background: #f8fafc; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 25px;">
            <div class="sm-form-group" style="margin-bottom:0;">
                <label class="sm-label">نوع المخالفة:</label>
                <select name="type" class="sm-select" required>
                    <option value="">-- اختر النوع --</option>
                    <?php foreach (SM_Settings::get_violation_types() as $k => $v): ?>
                        <option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($v); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sm-form-group" style="margin-bottom:0;">
                <label class="sm-label">الحدة:</label>
                <select name="severity" id="violation_severity" class="sm-select" onchange="updateSuggestions(this.value)" required>
                    <?php foreach (SM_Settings::get_severities() as $k => $v): ?>
                        <option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($v); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <div class="sm-form-group">
                <label class="sm-label">النقاط المستحقة:</label>
                <input type="number" name="points" id="violation_points" class="sm-input" value="0">
            </div>
        </div>

        <div class="sm-form-group">
            <label class="sm-label">الإجراء المتخذ (اقتراحات):</label>
            <input type="text" name="action_taken" id="action_taken" class="sm-input" placeholder="مثال: تنبيه شفوي، استدعاء ولي أمر...">
            <div id="action-suggestions" style="display:flex; gap:10px; margin-top:8px; flex-wrap:wrap;">
            </div>
        </div>

        <div class="sm-form-group">
            <label class="sm-label">التفاصيل:</label>
            <textarea name="details" class="sm-textarea" placeholder="اشرح الموقف بالتفصيل..." rows="3"></textarea>
        </div>

        <button type="submit" id="submit-btn" class="sm-btn" style="width: 100%; height: 50px; font-weight: 800; font-size: 1.1em; border-radius: 10px;">حفظ وتسجيل المخالفة الآن</button>
    </form>
</div>

<script>
(function() {
<?php $suggested = SM_Settings::get_suggested_actions(); ?>
const severityActions = {
    'low': <?php echo json_encode(explode("\n", str_replace("\r", "", $suggested['low']))); ?>,
    'medium': <?php echo json_encode(explode("\n", str_replace("\r", "", $suggested['medium']))); ?>,
    'high': <?php echo json_encode(explode("\n", str_replace("\r", "", $suggested['high']))); ?>
};

window.updateSuggestions = function(sev) {
    const container = document.getElementById('action-suggestions');
    if (!container) return;
    container.innerHTML = '';
    if (severityActions[sev]) {
        severityActions[sev].forEach(act => {
            const btn = document.createElement('span');
            btn.innerText = act;
            btn.style = "cursor:pointer; background:#edf2f7; padding:4px 10px; border-radius:4px; font-size:12px; border:1px solid #cbd5e0;";
            btn.onclick = () => {
                const input = document.getElementById('action_taken');
                if (input) input.value = act;
            };
            container.appendChild(btn);
        });
    }
}

updateSuggestions('low');

let searchTimer;
document.addEventListener('click', function(e) {
    const searchInput = document.getElementById('member_unified_search');
    if (searchInput && !searchInput.contains(e.target)) {
        const dropdown = document.getElementById('search_results_dropdown');
        if (dropdown) dropdown.style.display = 'none';
    }
});

document.getElementById('member_unified_search').addEventListener('input', function() {
    const query = this.value;
    clearTimeout(searchTimer);
    if (query.length < 2) {
        document.getElementById('search_results_dropdown').style.display = 'none';
        return;
    }

    searchTimer = setTimeout(() => {
        const formData = new FormData();
        formData.append('action', 'sm_search_members');
        formData.append('query', query);

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                const results = res.data;
                const dropdown = document.getElementById('search_results_dropdown');
                dropdown.innerHTML = '';
                if (results.length === 0) {
                    dropdown.innerHTML = '<div style="padding:10px; color:#666; text-align:center;">لم يتم العثور على نتائج.</div>';
                } else {
                    results.forEach(s => {
                        const div = document.createElement('div');
                        div.style = "padding:12px 15px; border-bottom:1px solid #eee; cursor:pointer; display:flex; align-items:center; gap:10px; transition: background 0.2s;";
                        div.onmouseover = () => div.style.background = '#f8fafc';
                        div.onmouseout = () => div.style.background = '#fff';
                        div.innerHTML = `
                            ${s.photo_url ? `<img src="${s.photo_url}" style="width:30px; height:30px; border-radius:50%; object-fit:cover;">` : '<span class="dashicons dashicons-admin-users"></span>'}
                            <div>
                                <div style="font-weight:700;">${s.name}</div>
                                <div style="font-size:11px; color:#666;">كود: ${s.member_code} | فصل: ${s.class_name} ${s.section || ''}</div>
                            </div>
                        `;
                        div.onclick = () => selectMember(s);
                        dropdown.appendChild(div);
                    });
                }
                dropdown.style.display = 'block';
            }
        });
    }, 300);
});

let selectedMembers = [];

function selectMember(s) {
    if (selectedMembers.find(x => x.id === s.id)) return;
    selectedMembers.push(s);
    renderSelectedMembers();
    document.getElementById('member_unified_search').value = '';
    document.getElementById('search_results_dropdown').style.display = 'none';
    if (selectedMembers.length === 1) fetchIntelligence(s.id);
    else document.getElementById('member-intelligence-panel').style.display = 'none';
}

function renderSelectedMembers() {
    const container = document.getElementById('selected_members_container');
    container.innerHTML = '';
    const ids = [];
    selectedMembers.forEach(s => {
        ids.push(s.id);
        const tag = document.createElement('div');
        tag.style = "background:#f0f7ff; padding:5px 12px; border-radius:20px; border:1px solid #c3dafe; display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; color:var(--sm-primary-color);";
        tag.innerHTML = `<span>${s.name}</span><span onclick="removeMember(${s.id})" style="cursor:pointer; color:#e53e3e;">✖</span>`;
        container.appendChild(tag);
    });
    document.getElementById('selected_member_ids').value = ids.join(',');
}

function removeMember(id) {
    selectedMembers = selectedMembers.filter(x => x.id !== id);
    renderSelectedMembers();
    if (selectedMembers.length === 1) fetchIntelligence(selectedMembers[0].id);
    else document.getElementById('member-intelligence-panel').style.display = 'none';
}

function clearMemberSelection() {
    selectedMembers = [];
    renderSelectedMembers();
    document.getElementById('member-intelligence-panel').style.display = 'none';
}

document.getElementById('start-scanner').addEventListener('click', function() {
    const reader = document.getElementById('reader');
    reader.style.display = 'block';
    const html5QrCode = new Html5Qrcode("reader");
    html5QrCode.start({ facingMode: "environment" }, { fps: 15, qrbox: 250 }, onScanSuccess);

    function onScanSuccess(decodedText) {
        html5QrCode.stop().then(() => {
            reader.style.display = 'none';
            const formData = new FormData();
            formData.append('action', 'sm_get_member');
            formData.append('code', decodedText);
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                if (res.success) selectMember(res.data);
                else alert('عذراً، كود غير معروف: ' + decodedText);
            });
        });
    }
});

function fetchIntelligence(memberId) {
    if (!memberId) {
        document.getElementById('member-intelligence-panel').style.display = 'none';
        return;
    }
    const formData = new FormData();
    formData.append('action', 'sm_get_member_intelligence');
    formData.append('member_id', memberId);
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            const data = res.data;
            document.getElementById('member-intelligence-panel').style.display = 'block';
            let photoHtml = data.photo_url ? `<img src="${data.photo_url}" style="width:60px; height:60px; border-radius:50%; object-fit:cover; margin-bottom:10px; border:2px solid var(--sm-primary-color);">` : '';
            let intelHtml = `
                <div style="grid-column: 1 / -1; display:flex; align-items:center; gap:15px; margin-bottom:10px; border-bottom:1px solid #eee; padding-bottom:10px;">
                    ${photoHtml}<h4 style="margin:0;">تحليل بيانات العضو</h4>
                </div>
                <div><strong>إجمالي المخالفات:</strong> <span style="color:red;">${data.stats.total}</span></div>
                <div><strong>النوع الأكثر تكراراً:</strong> <span>${data.labels[data.stats.frequent_type] || 'لا يوجد'}</span></div>
                <div><strong>آخر إجراء متخذ:</strong> <span>${data.stats.last_action || 'لا يوجد'}</span></div>
            `;
            document.getElementById('intel-content').innerHTML = intelHtml;
            let historyHtml = '<strong>آخر 3 ملاحظات:</strong> ';
            if (data.recent.length === 0) historyHtml += 'لا يوجد سجل سابق.';
            data.recent.forEach(r => {
                historyHtml += `<span style="margin-left:15px;">• ${r.created_at.split(' ')[0]}: ${data.labels[r.type]} (${r.severity})</span>`;
            });
            document.getElementById('intel-history').innerHTML = historyHtml;
        }
    });
}

document.getElementById('violation-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('submit-btn');
    btn.innerText = 'جاري الحفظ...';
    btn.disabled = true;
    const formData = new FormData(this);
    formData.append('action', 'sm_save_record_ajax');
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            const overlay = document.createElement('div');
            overlay.style = "position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); display:flex; align-items:center; justify-content:center; z-index:10002;";
            overlay.innerHTML = `<div style="background:white; padding:50px 80px; border-radius:20px; text-align:center;"><div style="font-size:60px; color:#38a169; margin-bottom:20px;">✅</div><h2 style="margin:0;">تم تسجيل المخالفة بنجاح</h2><p>يتم إرسال التنبيهات الآن وإغلاق النافذة...</p></div>`;
            document.body.appendChild(overlay);
            setTimeout(() => { overlay.remove(); location.reload(); }, 2000);
        } else {
            smShowNotification('خطأ: ' + (res.data || 'فشل في حفظ السجل'), true);
            btn.innerText = 'حفظ وتسجيل المخالفة الآن';
            btn.disabled = false;
        }
    });
});
})();
</script>
