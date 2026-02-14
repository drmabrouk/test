<?php

class SM_DB {

    public static function get_members($args = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sm_members';
        $query = "SELECT * FROM $table_name WHERE 1=1";
        $params = array();

        if (isset($args['professional_grade']) && !empty($args['professional_grade'])) {
            $query .= " AND professional_grade = %s";
            $params[] = $args['professional_grade'];
        }

        if (isset($args['specialization']) && !empty($args['specialization'])) {
            $query .= " AND specialization = %s";
            $params[] = $args['specialization'];
        }

        if (isset($args['membership_status']) && !empty($args['membership_status'])) {
            $query .= " AND membership_status = %s";
            $params[] = $args['membership_status'];
        }

        if (isset($args['search']) && !empty($args['search'])) {
            $query .= " AND (name LIKE %s OR national_id LIKE %s OR membership_number LIKE %s)";
            $params[] = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = '%' . $wpdb->esc_like($args['search']) . '%';
        }

        $query .= " ORDER BY sort_order ASC, name ASC";

        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($query, $params));
        }
        return $wpdb->get_results($query);
    }

    public static function get_member_by_id($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_members WHERE id = %d", $id));
    }

    public static function get_member_by_national_id($national_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_members WHERE national_id = %s", $national_id));
    }

    public static function get_member_by_parent($parent_user_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_members WHERE parent_user_id = %d", $parent_user_id));
    }

    public static function get_members_by_parent($parent_user_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_members WHERE parent_user_id = %d", $parent_user_id));
    }

    public static function add_member($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sm_members';

        $national_id = sanitize_text_field($data['national_id'] ?? '');
        if (!preg_match('/^[0-9]{14}$/', $national_id)) {
            return new WP_Error('invalid_national_id', 'الرقم القومي يجب أن يتكون من 14 رقم بالضبط وبدون حروف.');
        }

        // Check if national_id already exists
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE national_id = %s", $national_id));
        if ($exists) {
            return new WP_Error('duplicate_national_id', 'الرقم القومي مسجل مسبقاً.');
        }

        $name = sanitize_text_field($data['name'] ?? '');
        $email = sanitize_email($data['email'] ?? '');

        // Auto-create WordPress User for the Member
        $parent_user_id = null;
        $temp_pass = '';
        for($i=0; $i<10; $i++) $temp_pass .= rand(0,9);

        if (!function_exists('wp_insert_user')) {
            require_once(ABSPATH . 'wp-includes/user.php');
        }

        $wp_user_id = wp_insert_user(array(
            'user_login' => $national_id,
            'user_email' => $email ?: $national_id . '@syndicate.local',
            'display_name' => $name,
            'user_pass' => $temp_pass,
            'role' => 'sm_member'
        ));

        if (!is_wp_error($wp_user_id)) {
            $parent_user_id = $wp_user_id;
            update_user_meta($wp_user_id, 'sm_temp_pass', $temp_pass);
        } else {
            return $wp_user_id; // Return WP_Error
        }

        $insert_data = array(
            'national_id' => $national_id,
            'name' => $name,
            'gender' => sanitize_text_field($data['gender'] ?? 'male'),
            'professional_grade' => sanitize_text_field($data['professional_grade'] ?? ''),
            'specialization' => sanitize_text_field($data['specialization'] ?? ''),
            'academic_degree' => sanitize_text_field($data['academic_degree'] ?? ''),
            'membership_number' => sanitize_text_field($data['membership_number'] ?? ''),
            'membership_start_date' => sanitize_text_field($data['membership_start_date'] ?? null),
            'membership_expiration_date' => sanitize_text_field($data['membership_expiration_date'] ?? null),
            'membership_status' => sanitize_text_field($data['membership_status'] ?? ''),
            'license_number' => sanitize_text_field($data['license_number'] ?? ''),
            'license_issue_date' => sanitize_text_field($data['license_issue_date'] ?? null),
            'license_expiration_date' => sanitize_text_field($data['license_expiration_date'] ?? null),
            'facility_number' => sanitize_text_field($data['facility_number'] ?? ''),
            'facility_name' => sanitize_text_field($data['facility_name'] ?? ''),
            'facility_license_issue_date' => sanitize_text_field($data['facility_license_issue_date'] ?? null),
            'facility_license_expiration_date' => sanitize_text_field($data['facility_license_expiration_date'] ?? null),
            'facility_address' => sanitize_textarea_field($data['facility_address'] ?? ''),
            'sub_syndicate' => sanitize_text_field($data['sub_syndicate'] ?? ''),
            'email' => $email,
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'alt_phone' => sanitize_text_field($data['alt_phone'] ?? ''),
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'parent_user_id' => $parent_user_id,
            'registration_date' => current_time('Y-m-d'),
            'sort_order' => self::get_next_sort_order()
        );

        $wpdb->insert($table_name, $insert_data);
        $id = $wpdb->insert_id;

        if ($id) {
            SM_Logger::log('إضافة عضو جديد', "تمت إضافة العضو: $name بنجاح (الرقم القومي: $national_id)");
        }

        return $id;
    }

    public static function update_member($id, $data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sm_members';

        $update_data = array();
        $fields = [
            'national_id', 'name', 'gender', 'professional_grade', 'specialization',
            'academic_degree', 'membership_number', 'membership_start_date',
            'membership_expiration_date', 'membership_status', 'license_number',
            'license_issue_date', 'license_expiration_date', 'facility_number',
            'facility_name', 'facility_license_issue_date', 'facility_license_expiration_date',
            'facility_address', 'sub_syndicate', 'email', 'phone', 'alt_phone', 'notes'
        ];

        foreach ($fields as $f) {
            if (isset($data[$f])) {
                if (in_array($f, ['facility_address', 'notes'])) {
                    $update_data[$f] = sanitize_textarea_field($data[$f]);
                } elseif ($f === 'email') {
                    $update_data[$f] = sanitize_email($data[$f]);
                } else {
                    $update_data[$f] = sanitize_text_field($data[$f]);
                }
            }
        }

        if (isset($data['parent_user_id'])) $update_data['parent_user_id'] = intval($data['parent_user_id']);
        if (isset($data['registration_date'])) $update_data['registration_date'] = sanitize_text_field($data['registration_date']);
        if (isset($data['sort_order'])) $update_data['sort_order'] = intval($data['sort_order']);

        return $wpdb->update($table_name, $update_data, array('id' => $id));
    }

    public static function update_member_photo($id, $photo_url) {
        global $wpdb;
        return $wpdb->update($wpdb->prefix . 'sm_members', array('photo_url' => $photo_url), array('id' => $id));
    }

    public static function delete_member($id) {
        global $wpdb;

        $member = self::get_member_by_id($id);
        if ($member) {
            SM_Logger::log('حذف عضو (مع إمكانية الاستعادة)', 'ROLLBACK_DATA:' . json_encode(['table' => 'members', 'data' => (array)$member]));
            if ($member->parent_user_id) {
                if (!function_exists('wp_delete_user')) {
                    require_once(ABSPATH . 'wp-admin/includes/user.php');
                }
                wp_delete_user($member->parent_user_id);
            }
        }

        $wpdb->delete($wpdb->prefix . 'sm_records', array('member_id' => $id));
        return $wpdb->delete($wpdb->prefix . 'sm_members', array('id' => $id));
    }

    public static function member_exists($national_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}sm_members WHERE national_id = %s",
            $national_id
        ));
    }

    public static function get_next_sort_order() {
        global $wpdb;
        $max = $wpdb->get_var("SELECT MAX(sort_order) FROM {$wpdb->prefix}sm_members");
        return ($max ? intval($max) : 0) + 1;
    }

    public static function get_records($filters = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sm_records';
        $member_table = $wpdb->prefix . 'sm_members';
        $query = "SELECT r.*, s.name as member_name, s.national_id, s.membership_number
                  FROM $table_name r
                  JOIN $member_table s ON r.member_id = s.id
                  WHERE 1=1";
        $params = array();

        if (isset($filters['member_id'])) {
            $query .= " AND r.member_id = %d";
            $params[] = $filters['member_id'];
        }

        if (isset($filters['status'])) {
            $query .= " AND r.status = %s";
            $params[] = $filters['status'];
        }

        if (isset($filters['type']) && !empty($filters['type'])) {
            $query .= " AND r.type = %s";
            $params[] = $filters['type'];
        }

        if (isset($filters['search'])) {
            $query .= " AND (s.name LIKE %s OR s.national_id LIKE %s OR s.membership_number LIKE %s)";
            $params[] = '%' . $wpdb->esc_like($filters['search']) . '%';
            $params[] = '%' . $wpdb->esc_like($filters['search']) . '%';
            $params[] = '%' . $wpdb->esc_like($filters['search']) . '%';
        }

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query .= " AND r.created_at BETWEEN %s AND %s";
            $params[] = $filters['start_date'] . ' 00:00:00';
            $params[] = $filters['end_date'] . ' 23:59:59';
        }

        $query .= " ORDER BY r.created_at DESC";

        if (isset($filters['limit'])) {
            $query .= " LIMIT %d";
            $params[] = $filters['limit'];
        }

        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($query, $params));
        }
        return $wpdb->get_results($query);
    }

    public static function get_record_by_id($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_records WHERE id = %d", $id));
    }

    public static function add_record($data, $skip_log = false) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sm_records';

        $insert_data = array(
            'member_id' => intval($data['member_id']),
            'officer_id' => get_current_user_id(),
            'type' => sanitize_text_field($data['type']),
            'severity' => sanitize_text_field($data['severity'] ?? 'low'),
            'degree' => sanitize_text_field($data['degree'] ?? 'first'),
            'points' => intval($data['points'] ?? 0),
            'details' => sanitize_textarea_field($data['details']),
            'action_taken' => sanitize_textarea_field($data['action_taken'] ?? ''),
            'status' => 'pending',
            'created_at' => current_time('mysql')
        );

        $wpdb->insert($table_name, $insert_data);
        $id = $wpdb->insert_id;

        if ($id && !$skip_log) {
            $member = self::get_member_by_id($data['member_id']);
            SM_Logger::log('تسجيل مخالفة', "تم تسجيل مخالفة للعضو: {$member->name} النوع: {$data['type']}");
        }

        return $id;
    }

    public static function update_record($id, $data) {
        global $wpdb;
        $update_data = array();
        if (isset($data['type'])) $update_data['type'] = sanitize_text_field($data['type']);
        if (isset($data['severity'])) $update_data['severity'] = sanitize_text_field($data['severity']);
        if (isset($data['points'])) $update_data['points'] = intval($data['points']);
        if (isset($data['details'])) $update_data['details'] = sanitize_textarea_field($data['details']);
        if (isset($data['action_taken'])) $update_data['action_taken'] = sanitize_textarea_field($data['action_taken']);
        if (isset($data['status'])) $update_data['status'] = sanitize_text_field($data['status']);

        return $wpdb->update($wpdb->prefix . 'sm_records', $update_data, array('id' => $id));
    }

    public static function update_record_status($id, $status) {
        global $wpdb;
        return $wpdb->update($wpdb->prefix . 'sm_records', array('status' => $status), array('id' => $id));
    }

    public static function delete_record($id) {
        global $wpdb;

        $record = self::get_record_by_id($id);
        if ($record) {
             SM_Logger::log('حذف مخالفة (مع إمكانية الاستعادة)', 'ROLLBACK_DATA:' . json_encode(['table' => 'records', 'data' => (array)$record]));
        }

        return $wpdb->delete($wpdb->prefix . 'sm_records', array('id' => $id));
    }

    public static function send_message($sender_id, $receiver_id, $message, $member_id = null) {
        global $wpdb;
        return $wpdb->insert($wpdb->prefix . 'sm_messages', array(
            'sender_id' => $sender_id,
            'receiver_id' => $receiver_id,
            'member_id' => $member_id,
            'message' => $message,
            'created_at' => current_time('mysql')
        ));
    }

    public static function get_conversation_messages($user1, $user2) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT m.*, u.display_name as sender_name
             FROM {$wpdb->prefix}sm_messages m
             JOIN {$wpdb->prefix}users u ON m.sender_id = u.ID
             WHERE (sender_id = %d AND receiver_id = %d)
                OR (sender_id = %d AND receiver_id = %d)
             ORDER BY created_at ASC",
            $user1, $user2, $user2, $user1
        ));
    }

    public static function get_statistics($filters = array()) {
        global $wpdb;
        $stats = array();

        $where = " WHERE 1=1";
        $params = array();

        if (isset($filters['officer_id'])) {
            $where .= " AND (officer_id = %d)";
            $params[] = $filters['officer_id'];
        }

        $stats['total_members'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sm_members");

        if (function_exists('get_users')) {
            $stats['total_officers'] = count(get_users(array('role' => 'sm_syndicate_member'))) + count(get_users(array('role' => 'sm_officer')));
        } else {
            $stats['total_officers'] = 0;
        }

        $query_today = "SELECT COUNT(*) FROM {$wpdb->prefix}sm_records " . $where . " AND DATE(created_at) = CURDATE()";
        $stats['violations_today'] = !empty($params) ? $wpdb->get_var($wpdb->prepare($query_today, $params)) : $wpdb->get_var($query_today);

        $query_actions = "SELECT COUNT(*) FROM {$wpdb->prefix}sm_records " . $where . " AND action_taken != ''";
        $stats['total_actions'] = !empty($params) ? $wpdb->get_var($wpdb->prepare($query_actions, $params)) : $wpdb->get_var($query_actions);

        $query_total = "SELECT COUNT(*) FROM {$wpdb->prefix}sm_records" . $where;
        $stats['total_records'] = !empty($params) ? $wpdb->get_var($wpdb->prepare($query_total, $params)) : $wpdb->get_var($query_total);

        $query_points = "SELECT SUM(points) FROM {$wpdb->prefix}sm_records" . $where;
        $stats['total_points'] = !empty($params) ? $wpdb->get_var($wpdb->prepare($query_points, $params)) : $wpdb->get_var($query_points);

        $query_pending = "SELECT COUNT(*) FROM {$wpdb->prefix}sm_records" . $where . " AND status = 'pending'";
        $stats['pending_reports'] = !empty($params) ? $wpdb->get_var($wpdb->prepare($query_pending, $params)) : $wpdb->get_var($query_pending);

        // Trends (last 30 days)
        $query_trends = "SELECT DATE(created_at) as date, COUNT(*) as count FROM {$wpdb->prefix}sm_records " . $where . " AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY date ASC";
        $stats['trends'] = !empty($params) ? $wpdb->get_results($wpdb->prepare($query_trends, $params)) : $wpdb->get_results($query_trends);

        // By Type
        $query_type = "SELECT type, COUNT(*) as count FROM {$wpdb->prefix}sm_records " . $where . " GROUP BY type";
        $stats['by_type'] = !empty($params) ? $wpdb->get_results($wpdb->prepare($query_type, $params)) : $wpdb->get_results($query_type);

        // By Severity
        $query_sev = "SELECT severity, COUNT(*) as count FROM {$wpdb->prefix}sm_records " . $where . " GROUP BY severity";
        $stats['by_severity'] = !empty($params) ? $wpdb->get_results($wpdb->prepare($query_sev, $params)) : $wpdb->get_results($query_sev);

        // Top Members
        $member_table = $wpdb->prefix . 'sm_members';
        $query_top = "SELECT s.name, COUNT(r.id) as count FROM {$wpdb->prefix}sm_records r JOIN $member_table s ON r.member_id = s.id " . str_replace('officer_id', 'r.officer_id', $where) . " GROUP BY r.member_id ORDER BY count DESC LIMIT 5";
        $stats['top_members'] = !empty($params) ? $wpdb->get_results($wpdb->prepare($query_top, $params)) : $wpdb->get_results($query_top);

        // By Degree
        $query_deg = "SELECT degree, COUNT(*) as count FROM {$wpdb->prefix}sm_records " . $where . " GROUP BY degree";
        $stats['by_degree'] = !empty($params) ? $wpdb->get_results($wpdb->prepare($query_deg, $params)) : $wpdb->get_results($query_deg);

        return $stats;
    }

    public static function get_member_stats($member_id) {
        global $wpdb;
        $stats = array();
        $stats['total'] = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}sm_records WHERE member_id = %d", $member_id));
        $stats['points'] = $wpdb->get_var($wpdb->prepare("SELECT SUM(points) FROM {$wpdb->prefix}sm_records WHERE member_id = %d", $member_id));
        $stats['by_type'] = $wpdb->get_results($wpdb->prepare("SELECT type, COUNT(*) as count FROM {$wpdb->prefix}sm_records WHERE member_id = %d GROUP BY type", $member_id));
        $stats['high_severity_count'] = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}sm_records WHERE member_id = %d AND severity = 'high'", $member_id));
        $stats['last_action'] = $wpdb->get_var($wpdb->prepare("SELECT action_taken FROM {$wpdb->prefix}sm_records WHERE member_id = %d ORDER BY created_at DESC LIMIT 1", $member_id));
        $stats['frequent_type'] = $wpdb->get_var($wpdb->prepare("SELECT type FROM {$wpdb->prefix}sm_records WHERE member_id = %d GROUP BY type ORDER BY COUNT(*) DESC LIMIT 1", $member_id));

        $stats['case_file'] = ($stats['points'] >= 15 || $stats['high_severity_count'] >= 2);

        return $stats;
    }

    public static function delete_all_data() {
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}sm_members");
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}sm_records");
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}sm_messages");
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}sm_logs");
        SM_Logger::log('مسح شامل للبيانات', 'تم تنفيذ أمر مسح كافة بيانات النظام');
    }

    public static function get_backup_data() {
        global $wpdb;
        $data = array();
        $tables = array('members', 'records', 'messages');
        foreach ($tables as $t) {
            $data[$t] = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sm_$t", ARRAY_A);
        }
        return json_encode($data);
    }

    public static function restore_backup($json) {
        global $wpdb;
        $data = json_decode($json, true);
        if (!$data) return false;

        foreach ($data as $table => $rows) {
            $table_name = $wpdb->prefix . 'sm_' . $table;
            $wpdb->query("TRUNCATE TABLE $table_name");
            foreach ($rows as $row) {
                $wpdb->insert($table_name, $row);
            }
        }
        return true;
    }

    public static function get_pending_reports_count() {
        global $wpdb;
        return $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sm_records WHERE status = 'pending'");
    }


    public static function add_survey($title, $questions, $recipients, $user_id) {
        global $wpdb;
        $wpdb->insert("{$wpdb->prefix}sm_surveys", array(
            'title' => $title,
            'questions' => json_encode($questions),
            'recipients' => $recipients,
            'status' => 'active',
            'created_by' => $user_id,
            'created_at' => current_time('mysql')
        ));
        return $wpdb->insert_id;
    }

    public static function get_surveys($role) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_surveys WHERE (recipients = %s OR recipients = 'all') AND status = 'active' ORDER BY created_at DESC", $role));
    }

    public static function save_survey_response($survey_id, $user_id, $responses) {
        global $wpdb;
        return $wpdb->insert("{$wpdb->prefix}sm_survey_responses", array(
            'survey_id' => $survey_id,
            'user_id' => $user_id,
            'responses' => json_encode($responses),
            'created_at' => current_time('mysql')
        ));
    }

    public static function get_survey($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_surveys WHERE id = %d", $id));
    }

    public static function get_survey_results($survey_id) {
        global $wpdb;
        $survey = self::get_survey($survey_id);
        if (!$survey) return array();

        $questions = json_decode($survey->questions, true);
        $responses = $wpdb->get_results($wpdb->prepare("SELECT responses FROM {$wpdb->prefix}sm_survey_responses WHERE survey_id = %d", $survey_id));

        $results = array();
        foreach ($questions as $index => $q) {
            $results[$index] = array('question' => $q, 'answers' => array());
            foreach ($responses as $r) {
                $res_data = json_decode($r->responses, true);
                $ans = $res_data[$index] ?? 'No Answer';
                $results[$index]['answers'][$ans] = ($results[$index]['answers'][$ans] ?? 0) + 1;
            }
        }
        return $results;
    }

    public static function get_survey_responses($survey_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_survey_responses WHERE survey_id = %d", $survey_id));
    }
}
