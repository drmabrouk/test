<?php
if (!defined('ABSPATH')) exit;
$user = wp_get_current_user();
if (!current_user_can('طباعة_التقارير')) wp_die('Unauthorized');

$member_id = intval($_GET['member_id']);
$member = SM_DB::get_member_by_id($member_id);
if (!$member || empty($member->facility_number)) wp_die('Facility data not found');

$syndicate = SM_Settings::get_syndicate_info();
$appearance = SM_Settings::get_appearance();
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>ترخيص منشأة رياضية - <?php echo esc_html($member->facility_name); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Rubik', sans-serif; margin: 0; padding: 0; background: #fff; }
        .license-page { width: 297mm; height: 210mm; padding: 20mm; margin: 0 auto; box-sizing: border-box; border: 20px solid <?php echo $appearance['dark_color']; ?>; position: relative; }
        .inner-border { border: 5px solid <?php echo $appearance['primary_color']; ?>; height: 100%; padding: 15mm; box-sizing: border-box; display: flex; flex-direction: column; justify-content: space-between; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .title-box { text-align: center; flex: 1; }
        .title { font-size: 48px; font-weight: 900; color: <?php echo $appearance['primary_color']; ?>; margin: 10px 0; }
        .content { font-size: 24px; line-height: 1.8; text-align: center; margin-top: 20px; }
        .field { font-weight: 900; border-bottom: 2px solid #ccc; padding: 0 15px; color: #000; }
        .footer { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 20px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print" style="position: fixed; top: 20px; right: 20px; z-index: 1000;">
        <button onclick="window.print()" style="padding: 15px 30px; background: #27ae60; color: white; border: none; cursor: pointer; border-radius: 8px; font-weight: bold;">طباعة الشهادة</button>
    </div>

    <div class="license-page">
        <div class="inner-border">
            <div class="header">
                <div style="text-align: right; width: 200px;">
                    <p style="font-weight: 700; margin: 0;"><?php echo esc_html($syndicate['syndicate_name']); ?></p>
                    <p style="font-size: 14px; margin: 5px 0;">قسم شؤون المنشآت</p>
                </div>
                <div class="title-box">
                    <?php if ($syndicate['syndicate_logo']): ?>
                        <img src="<?php echo esc_url($syndicate['syndicate_logo']); ?>" style="max-height: 80px;">
                    <?php endif; ?>
                    <div class="title">شهادة ترخيص منشأة</div>
                    <div style="font-size: 20px; font-weight: 700;">فئة ( <?php echo esc_html($member->facility_category); ?> )</div>
                </div>
                <div style="text-align: left; width: 200px;">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?php echo urlencode(admin_url('admin-ajax.php?action=sm_print_facility&member_id='.$member_id)); ?>">
                </div>
            </div>

            <div class="content">
                تشهد النقابة بأن المنشأة المسماة: <span class="field"><?php echo esc_html($member->facility_name); ?></span><br>
                والتي يملكها السيد/ <span class="field"><?php echo esc_html($member->name); ?></span><br>
                والواقعة في: <span class="field"><?php echo esc_html($member->facility_address ?: '---'); ?></span><br>
                قد رخصت بموجب القانون لمزاولة النشاط الرياضي والبدني تحت رقم: <span class="field"><?php echo esc_html($member->facility_number); ?></span><br>
                وينتهي هذا الترخيص في: <span class="field"><?php echo esc_html($member->facility_license_expiration_date); ?></span>
            </div>

            <div class="footer">
                <div style="text-align: center;">
                    <p>ختم النقابة</p>
                    <div style="width: 150px; height: 150px; border: 2px dashed #ccc; border-radius: 50%; margin: 10px auto;"></div>
                </div>
                <div style="text-align: center; margin-bottom: 20px;">
                    <p style="font-weight: 700;">مسؤول النقابة</p>
                    <p style="font-size: 20px; margin-top: 30px;"><?php echo esc_html($syndicate['syndicate_officer_name']); ?></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
