<?php

class SM_Notifications {
    public static function send_violation_alert($record_id) {
        global $wpdb;
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT r.*, s.name as member_name, s.parent_email FROM {$wpdb->prefix}sm_records r JOIN {$wpdb->prefix}sm_members s ON r.member_id = s.id WHERE r.id = %d",
            $record_id
        ));

        if (!$record || empty($record->parent_email)) return;

        $settings = SM_Settings::get_notifications();
        
        $placeholders = array(
            '{member_name}' => $record->member_name,
            '{type}' => self::get_label($record->type),
            '{severity}' => self::get_label($record->severity),
            '{details}' => $record->details,
            '{action_taken}' => $record->action_taken
        );

        $subject = strtr($settings['email_subject'], $placeholders);
        $message = strtr($settings['email_template'], $placeholders);

        wp_mail($record->parent_email, $subject, $message);

        // Advanced Recurring Pattern Check
        self::check_recurring_behavior($record->member_id);
    }

    public static function check_recurring_behavior($member_id) {
        global $wpdb;
        // Count violations in last 7 days
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sm_records WHERE member_id = %d AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            $member_id
        ));

        if ($count >= 3) {
            $member = SM_DB::get_member_by_id($member_id);
            $admins = get_users(array('role' => 'syndicate_admin'));
            $emails = array_map(function($u) { return $u->user_email; }, $admins);
            
            $subject = "تنبيه: سلوك متكرر للعضو " . $member->name;
            $message = "تم رصد $count مخالفات للعضو خلال الأسبوع الأخير. يرجى مراجعة ملف العضو واتخاذ الإجراء اللازم.";
            
            wp_mail($emails, $subject, $message);
            // In a real scenario, integrate WhatsApp API here
        }
    }

    public static function send_group_notification($role, $subject, $message) {
        $users = get_users(array('role' => $role));
        $emails = array_map(function($u) { return $u->user_email; }, $users);
        if (!empty($emails)) {
            wp_mail($emails, $subject, $message);
        }
    }

    private static function get_label($key) {
        $labels = array(
            'behavior' => 'سلوك',
            'lateness' => 'تأخر',
            'absence' => 'غياب',
            'low' => 'منخفضة',
            'medium' => 'متوسطة',
            'high' => 'خطيرة'
        );
        return isset($labels[$key]) ? $labels[$key] : $key;
    }
}
