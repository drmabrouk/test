<?php

class SM_Settings {
    public static function get_violation_types() {
        $default = array(
            'behavior' => 'سلوك',
            'lateness' => 'تأخر',
            'absence' => 'غياب',
            'other' => 'أخرى'
        );
        return get_option('sm_violation_types', $default);
    }

    public static function get_severities() {
        return array(
            'low' => 'منخفضة',
            'medium' => 'متوسطة',
            'high' => 'خطيرة'
        );
    }

    public static function save_violation_types($types) {
        update_option('sm_violation_types', $types);
    }

    public static function get_appearance() {
        $default = array(
            'primary_color' => '#F63049',
            'secondary_color' => '#D02752',
            'accent_color' => '#8A244B',
            'dark_color' => '#111F35',
            'font_size' => '15px',
            'border_radius' => '12px',
            'table_style' => 'modern',
            'button_style' => 'flat'
        );
        return wp_parse_args(get_option('sm_appearance', array()), $default);
    }

    public static function save_appearance($data) {
        update_option('sm_appearance', $data);
    }

    public static function get_notifications() {
        $default = array(
            'email_subject' => 'تنبيه بخصوص سلوك العضو: {member_name}',
            'email_template' => "تم تسجيل ملاحظة بخصوص العضو: {member_name}\nنوع المخالفة: {type}\nالحدة: {severity}\nالتفاصيل: {details}\nالإجراء المتخذ: {action_taken}",
            'whatsapp_template' => "تنبيه من النقابة: تم تسجيل ملاحظة سلوكية بحق العضو {member_name}. نوع الملاحظة: {type}. تفاصيل: {details}. الإجراء: {action_taken}",
            'internal_template' => "إشعار نظام: تم تسجيل مخالفة {type} للعضو {member_name}. الرجاء مراجعة سجل العضو."
        );
        return get_option('sm_notification_settings', $default);
    }

    public static function save_notifications($data) {
        update_option('sm_notification_settings', $data);
    }

    public static function get_syndicate_info() {
        $default = array(
            'syndicate_name' => 'نقابتي النموذجية',
            'syndicate_principal_name' => 'أحمد علي',
            'syndicate_logo' => '',
            'address' => 'الرياض، المملكة العربية السعودية',
            'email' => 'info@syndicate.edu',
            'phone' => '0123456789',
            'map_link' => '',
            'extra_details' => ''
        );
        return get_option('sm_syndicate_info', $default);
    }

    public static function save_syndicate_info($data) {
        update_option('sm_syndicate_info', $data);
    }

    public static function format_grade_name($grade, $section = '', $format = 'full') {
        if (empty($grade)) return '---';
        $grade_num = str_replace('الصف ', '', $grade);
        if ($format === 'short') {
            return trim($grade_num . ' ' . $section);
        }
        $output = 'الصف ' . $grade_num;
        if (!empty($section)) {
            $output .= ' شعبة ' . $section;
        }
        return $output;
    }

    public static function get_retention_settings() {
        $default = array(
            'message_retention_days' => 90
        );
        return get_option('sm_retention_settings', $default);
    }

    public static function save_retention_settings($data) {
        update_option('sm_retention_settings', $data);
    }

    public static function record_backup_download() {
        update_option('sm_last_backup_download', current_time('mysql'));
    }

    public static function record_backup_import() {
        update_option('sm_last_backup_import', current_time('mysql'));
    }

    public static function get_last_backup_info() {
        return array(
            'export' => get_option('sm_last_backup_download', 'لم يتم التصدير مسبقاً'),
            'import' => get_option('sm_last_backup_import', 'لم يتم الاستيراد مسبقاً')
        );
    }

    public static function get_suggested_actions() {
        $default = array(
            'low' => "تنبيه شفوي\nتسجيل ملاحظة\nنصيحة تربوية",
            'medium' => "إنذار خطي\nاستدعاء ولي أمر\nحسم درجات سلوك",
            'high' => "فصل مؤقت\nمجلس انضباط\nتعهد خطي شديد"
        );
        return get_option('sm_suggested_actions', $default);
    }

    public static function save_suggested_actions($actions) {
        update_option('sm_suggested_actions', $actions);
    }

    public static function get_professional_grades() {
        $default = array(
            'assistant_specialist' => 'أخصائي مساعد',
            'specialist' => 'أخصائي',
            'consultant' => 'استشاري',
            'expert' => 'خبير'
        );
        return get_option('sm_professional_grades', $default);
    }

    public static function save_professional_grades($grades) {
        update_option('sm_professional_grades', $grades);
    }

    public static function get_specializations() {
        $default = array(
            'injuries' => 'إصابات وتأهيل',
            'massage' => 'تدليك رياضي',
            'nutrition' => 'تغذية رياضية',
            'special_needs' => 'تأهيل ذوي الاحتياجات الخاصة'
        );
        return get_option('sm_specializations', $default);
    }

    public static function save_specializations($specs) {
        update_option('sm_specializations', $specs);
    }

    public static function get_academic_degrees() {
        return array(
            'bachelor' => 'بكالوريوس',
            'master' => 'ماجستير',
            'doctorate' => 'دكتوراه'
        );
    }

    public static function get_membership_statuses() {
        return array(
            'active' => 'نشط',
            'inactive' => 'غير نشط',
            'pending' => 'قيد الانتظار',
            'expired' => 'منتهي'
        );
    }

    public static function get_finance_settings() {
        $default = array(
            'membership_new' => 480,
            'membership_renewal' => 280,
            'membership_penalty' => 50,
            'license_new' => 2500,
            'license_renewal' => 1000,
            'license_penalty' => 500,
            'facility_a' => 9000,
            'facility_b' => 6000,
            'facility_c' => 3000
        );
        return get_option('sm_finance_settings', $default);
    }

    public static function save_finance_settings($settings) {
        update_option('sm_finance_settings', $settings);
    }
}
