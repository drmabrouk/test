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
            'working_schedule' => array(
                'staff' => array('mon', 'tue', 'wed', 'thu', 'fri'),
                'members' => array('mon', 'tue', 'wed', 'thu')
            ),
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

    public static function get_academic_structure() {
        $default = array(
            'active_grades' => array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12),
        );
        return wp_parse_args(get_option('sm_academic_structure', array()), $default);
    }

    public static function get_sections_from_db() {
        global $wpdb;
        $results = $wpdb->get_results("SELECT DISTINCT class_name, section FROM {$wpdb->prefix}sm_members WHERE section != '' ORDER BY class_name ASC, section ASC");

        $structure = array();
        foreach ($results as $row) {
            $grade_num = (int)str_replace('الصف ', '', $row->class_name);
            if (!isset($structure[$grade_num])) {
                $structure[$grade_num] = array();
            }
            if (!in_array($row->section, $structure[$grade_num])) {
                $structure[$grade_num][] = $row->section;
            }
        }
        foreach ($structure as $grade => $sections) {
            sort($structure[$grade]);
        }
        return $structure;
    }
}
