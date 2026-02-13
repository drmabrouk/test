<?php

class SM_DB {

    public static function get_members($args = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sm_members';
        $query = "SELECT * FROM $table_name WHERE 1=1";
        $params = array();

        if (isset($args['officer_id'])) {
            $user_id = intval($args['officer_id']);
            $user = get_userdata($user_id);
            if ($user) {
                $is_officer = in_array('sm_officer', (array) $user->roles);
                $is_syndicate_member = in_array('sm_syndicate_member', (array) $user->roles);

                if ($is_officer) {
                    // Officers see all
                } elseif ($is_syndicate_member) {
                    $assigned_sections = get_user_meta($user_id, 'sm_assigned_sections', true) ?: array();
                    $supervised_classes = get_user_meta($user_id, 'sm_supervised_classes', true) ?: array();
                    $all_sections = array_unique(array_merge($assigned_sections, $supervised_classes));

                    if (empty($all_sections)) {
                        return array(); // No access
                    }

                    $section_conditions = array();
                    foreach ($all_sections as $sec) {
                        $parts = explode('|', $sec);
                        if (count($parts) == 2) {
                            $section_conditions[] = "(class_name = %s AND section = %s)";
                            $params[] = $parts[0];
                            $params[] = $parts[1];
                        }
                    }
                    if (!empty($section_conditions)) {
                        $query .= " AND (" . implode(' OR ', $section_conditions) . ")";
                    }
                }
            }
        }

        if (isset($args['class_name']) && !empty($args['class_name'])) {
            $query .= " AND class_name = %s";
            $params[] = $args['class_name'];
        }

        if (isset($args['section']) && !empty($args['section'])) {
            $query .= " AND section = %s";
            $params[] = $args['section'];
        }

        if (isset($args['search']) && !empty($args['search'])) {
            $query .= " AND (name LIKE %s OR member_code LIKE %s)";
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

    public static function get_member_by_code($code) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_members WHERE member_code = %s", $code));
    }

    public static function get_member_by_parent($parent_user_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_members WHERE parent_user_id = %d", $parent_user_id));
    }

    public static function get_members_by_parent($parent_user_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_members WHERE parent_user_id = %d", $parent_user_id));
    }

    public static function add_member($name, $class, $email = '', $code = '', $parent_user_id = null, $officer_id = null, $section = '', $extra = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sm_members';

        if (empty($code)) {
            $last_id = $wpdb->get_var("SELECT id FROM $table_name ORDER BY id DESC LIMIT 1");
            $next_num = ($last_id ? intval($last_id) : 0) + 1;
            $code = 'MB' . str_pad($next_num, 5, '0', STR_PAD_LEFT);
        }

        $data = array(
            'name' => $name,
            'email' => $email,
            'member_code' => $code,
            'class_name' => $class,
            'section' => $section,
            'parent_user_id' => $parent_user_id,
            'officer_id' => $officer_id,
            'guardian_phone' => $extra['guardian_phone'] ?? '',
            'nationality' => $extra['nationality'] ?? '',
            'registration_date' => $extra['registration_date'] ?? current_time('Y-m-d'),
            'sort_order' => $extra['sort_order'] ?? 0
        );

        $wpdb->insert($table_name, $data);
        $id = $wpdb->insert_id;

        if ($id) {
            SM_Logger::log('إضافة عضو جديد', "تمت إضافة العضو: $name بنجاح (الكود: $code)");
        }

        return $id;
    }

    public static function update_member($id, $data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sm_members';

        $update_data = array();
        if (isset($data['name'])) $update_data['name'] = sanitize_text_field($data['name']);
        if (isset($data['email'])) $update_data['email'] = sanitize_email($data['email']);
        if (isset($data['parent_email'])) $update_data['email'] = sanitize_email($data['parent_email']);
        if (isset($data['member_code'])) $update_data['member_code'] = sanitize_text_field($data['member_code']);
        if (isset($data['class_name'])) $update_data['class_name'] = sanitize_text_field($data['class_name']);
        if (isset($data['section'])) $update_data['section'] = sanitize_text_field($data['section']);
        if (isset($data['parent_user_id'])) $update_data['parent_user_id'] = intval($data['parent_user_id']);
        if (isset($data['officer_id'])) $update_data['officer_id'] = intval($data['officer_id']);
        if (isset($data['guardian_phone'])) $update_data['guardian_phone'] = sanitize_text_field($data['guardian_phone']);
        if (isset($data['nationality'])) $update_data['nationality'] = sanitize_text_field($data['nationality']);
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

        // Log for rollback before deletion
        $member = self::get_member_by_id($id);
        if ($member) {
            SM_Logger::log('حذف عضو (مع إمكانية الاستعادة)', 'ROLLBACK_DATA:' . json_encode(['table' => 'members', 'data' => (array)$member]));
        }

        $wpdb->delete($wpdb->prefix . 'sm_records', array('member_id' => $id));
        $wpdb->delete($wpdb->prefix . 'sm_attendance', array('member_id' => $id));
        $wpdb->delete($wpdb->prefix . 'sm_grades', array('member_id' => $id));
        $wpdb->delete($wpdb->prefix . 'sm_clinic', array('member_id' => $id));
        return $wpdb->delete($wpdb->prefix . 'sm_members', array('id' => $id));
    }

    public static function member_exists($name, $class, $section) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}sm_members WHERE name = %s AND class_name = %s AND section = %s",
            $name, $class, $section
        ));
    }

    public static function get_next_sort_order() {
        global $wpdb;
        $max = $wpdb->get_var("SELECT MAX(sort_order) FROM {$wpdb->prefix}sm_members");
        return ($max ? intval($max) : 0) + 1;
    }

    // Records (Violations)
    public static function get_records($filters = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sm_records';
        $stu_table = $wpdb->prefix . 'sm_members';
        $query = "SELECT r.*, s.name as member_name, s.class_name, s.section, s.member_code
                  FROM $table_name r
                  JOIN $stu_table s ON r.member_id = s.id
                  WHERE 1=1";
        $params = array();

        if (isset($filters['member_id'])) {
            $query .= " AND r.member_id = %d";
            $params[] = $filters['member_id'];
        }

        if (isset($filters['officer_id'])) {
            $user_id = intval($filters['officer_id']);
            $user = get_userdata($user_id);
            if ($user && !in_array('administrator', (array)$user->roles) && !current_user_can('manage_options')) {
                if (in_array('sm_officer', (array)$user->roles)) {
                    // Principal sees all
                } else {
                    $assigned_sections = get_user_meta($user_id, 'sm_assigned_sections', true) ?: array();
                    $supervised_classes = get_user_meta($user_id, 'sm_supervised_classes', true) ?: array();
                    $all_sections = array_unique(array_merge($assigned_sections, $supervised_classes));

                    if (empty($all_sections)) {
                        $query .= " AND r.officer_id = %d";
                        $params[] = $user_id;
                    } else {
                        $sec_cond = array();
                        foreach ($all_sections as $sec) {
                            $parts = explode('|', $sec);
                            if (count($parts) == 2) {
                                $sec_cond[] = "(s.class_name = %s AND s.section = %s)";
                                $params[] = $parts[0];
                                $params[] = $parts[1];
                            }
                        }
                        $query .= " AND (r.officer_id = %d OR (" . implode(' OR ', $sec_cond) . "))";
                        $params[] = $user_id;
                    }
                }
            }
        }

        if (isset($filters['status'])) {
            $query .= " AND r.status = %s";
            $params[] = $filters['status'];
        }

        if (isset($filters['type']) && !empty($filters['type'])) {
            $query .= " AND r.type = %s";
            $params[] = $filters['type'];
        }

        if (isset($filters['class_name'])) {
            $query .= " AND s.class_name = %s";
            $params[] = $filters['class_name'];
        }

        if (isset($filters['section'])) {
            $query .= " AND s.section = %s";
            $params[] = $filters['section'];
        }

        if (isset($filters['search'])) {
            $query .= " AND (s.name LIKE %s OR s.member_code LIKE %s)";
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

    // Messages
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

    // Statistics
    public static function get_statistics($filters = array()) {
        global $wpdb;
        $stats = array();

        $where = " WHERE 1=1";
        $params = array();

        if (isset($filters['officer_id'])) {
            $user_id = intval($filters['officer_id']);
            $user = get_userdata($user_id);
            if ($user && !in_array('administrator', (array)$user->roles) && !current_user_can('manage_options')) {
                if (in_array('sm_officer', (array)$user->roles)) {
                    // Principal sees all
                } else {
                    $assigned_sections = get_user_meta($user_id, 'sm_assigned_sections', true) ?: array();
                    $supervised_classes = get_user_meta($user_id, 'sm_supervised_classes', true) ?: array();
                    $all_sections = array_unique(array_merge($assigned_sections, $supervised_classes));

                    if (empty($all_sections)) {
                        $where .= " AND (officer_id = %d)";
                        $params[] = $user_id;
                    } else {
                        $stu_table = $wpdb->prefix . 'sm_members';
                        $sec_cond = array();
                        foreach ($all_sections as $sec) {
                            $parts = explode('|', $sec);
                            if (count($parts) == 2) {
                                $sec_cond[] = "(id IN (SELECT id FROM $stu_table WHERE class_name = %s AND section = %s))";
                                $params[] = $parts[0];
                                $params[] = $parts[1];
                            }
                        }
                        $where .= " AND (officer_id = %d OR (" . implode(' OR ', $sec_cond) . "))";
                        $params[] = $user_id;
                    }
                }
            }
        }

        $stats['total_members'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sm_members" . str_replace('officer_id', 'officer_id', $where)); // members count doesn't have officer_id in table yet, might need join
        // Actually for simplicity let's re-run counts with proper joins if needed.

        $stats['total_records'] = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}sm_records" . $where, $params));
        $stats['total_points'] = $wpdb->get_var($wpdb->prepare("SELECT SUM(points) FROM {$wpdb->prefix}sm_records" . $where, $params));
        $stats['pending_reports'] = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}sm_records" . $where . " AND status = 'pending'", $params));

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

        // Critical: Case File Status
        $stats['case_file'] = ($stats['points'] >= 15 || $stats['high_severity_count'] >= 2);

        return $stats;
    }

    // Confiscated Items
    public static function add_confiscated_item($data) {
        global $wpdb;
        return $wpdb->insert($wpdb->prefix . 'sm_confiscated_items', array(
            'member_id' => intval($data['member_id']),
            'item_name' => sanitize_text_field($data['item_name']),
            'officer_id' => get_current_user_id(),
            'status' => 'confiscated',
            'confiscated_at' => current_time('mysql')
        ));
    }

    public static function get_confiscated_items() {
        global $wpdb;
        return $wpdb->get_results("SELECT c.*, s.name as member_name FROM {$wpdb->prefix}sm_confiscated_items c JOIN {$wpdb->prefix}sm_members s ON c.member_id = s.id ORDER BY confiscated_at DESC");
    }

    public static function update_confiscated_item_status($id, $status) {
        global $wpdb;
        return $wpdb->update($wpdb->prefix . 'sm_confiscated_items', array('status' => $status), array('id' => $id));
    }

    public static function delete_confiscated_item($id) {
        global $wpdb;
        return $wpdb->delete($wpdb->prefix . 'sm_confiscated_items', array('id' => $id));
    }

    // Attendance
    public static function save_attendance($member_id, $status, $date, $officer_id) {
        global $wpdb;
        return $wpdb->replace($wpdb->prefix . 'sm_attendance', array(
            'member_id' => $member_id,
            'date' => $date,
            'status' => $status,
            'officer_id' => $officer_id,
            'created_at' => current_time('mysql')
        ));
    }

    public static function get_members_attendance($class_name, $section, $date) {
        global $wpdb;
        $query = "SELECT s.id, s.name, s.member_code, a.status
                  FROM {$wpdb->prefix}sm_members s
                  LEFT JOIN {$wpdb->prefix}sm_attendance a ON s.id = a.member_id AND a.date = %s
                  WHERE s.class_name = %s AND s.section = %s
                  ORDER BY s.sort_order ASC, s.name ASC";
        return $wpdb->get_results($wpdb->prepare($query, $date, $class_name, $section));
    }

    public static function get_attendance_summary($date) {
        global $wpdb;
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);
        $is_admin = in_array('administrator', (array)$user->roles) || current_user_can('manage_options');
        $is_officer = in_array('sm_officer', (array)$user->roles);
        $is_syndicate_member = in_array('sm_syndicate_member', (array)$user->roles);

        $query = "SELECT s.class_name, s.section,
                  COUNT(s.id) as total,
                  SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present,
                  SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent,
                  SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late,
                  SUM(CASE WHEN a.status = 'excused' THEN 1 ELSE 0 END) as excused
                  FROM {$wpdb->prefix}sm_members s
                  LEFT JOIN {$wpdb->prefix}sm_attendance a ON s.id = a.member_id AND a.date = %s
                  WHERE 1=1";

        $params = array($date);

        if (!$is_admin && !$is_officer && $is_syndicate_member) {
            $assigned_sections = get_user_meta($user_id, 'sm_assigned_sections', true) ?: array();
            $supervised_classes = get_user_meta($user_id, 'sm_supervised_classes', true) ?: array();
            $all_sections = array_unique(array_merge($assigned_sections, $supervised_classes));

            if (!empty($all_sections)) {
                $sec_cond = array();
                foreach ($all_sections as $sec) {
                    $parts = explode('|', $sec);
                    if (count($parts) == 2) {
                        $sec_cond[] = "(s.class_name = %s AND s.section = %s)";
                        $params[] = $parts[0];
                        $params[] = $parts[1];
                    }
                }
                $query .= " AND (" . implode(' OR ', $sec_cond) . ")";
            } else {
                return array();
            }
        }

        $query .= " GROUP BY s.class_name, s.section ORDER BY s.class_name ASC, s.section ASC";

        return $wpdb->get_results($wpdb->prepare($query, $params));
    }

    // Generic Data Handling
    public static function delete_all_data() {
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}sm_members");
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}sm_records");
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}sm_messages");
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}sm_confiscated_items");
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}sm_logs");
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}sm_attendance");
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}sm_clinic");
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}sm_grades");
        SM_Logger::log('مسح شامل للبيانات', 'تم تنفيذ أمر مسح كافة بيانات النظام');
    }

    public static function get_backup_data() {
        global $wpdb;
        $data = array();
        $tables = array('members', 'records', 'messages', 'confiscated_items', 'attendance', 'clinic', 'grades');
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

    public static function get_expired_items_count() {
        global $wpdb;
        // Logic for expired confiscated items (e.g., > 30 days)
        return $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}sm_confiscated_items WHERE status = 'confiscated' AND confiscated_at < %s", date('Y-m-d H:i:s', strtotime('-30 days'))));
    }

    // Timetable
    public static function get_timetable($class_name, $section) {
        global $wpdb;
        $query = "SELECT t.*, s.name as subject_name, u.display_name as officer_name
                  FROM {$wpdb->prefix}sm_timetable t
                  LEFT JOIN {$wpdb->prefix}sm_subjects s ON t.subject_id = s.id
                  LEFT JOIN {$wpdb->prefix}users u ON t.officer_id = u.ID
                  WHERE t.class_name = %s AND t.section = %s";
        return $wpdb->get_results($wpdb->prepare($query, $class_name, $section));
    }

    public static function update_timetable($class_name, $section, $day, $period, $subject_id, $officer_id) {
        global $wpdb;
        $table = "{$wpdb->prefix}sm_timetable";
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE class_name = %s AND section = %s AND day = %s AND period = %d", $class_name, $section, $day, $period));

        $data = array(
            'class_name' => $class_name,
            'section' => $section,
            'day' => $day,
            'period' => $period,
            'subject_id' => $subject_id,
            'officer_id' => $officer_id
        );

        if ($exists) {
            return $wpdb->update($table, $data, array('id' => $exists));
        } else {
            return $wpdb->insert($table, $data);
        }
    }

    // Subjects
    public static function get_subjects($grade_id = null) {
        global $wpdb;
        $query = "SELECT * FROM {$wpdb->prefix}sm_subjects";
        $subjects = $wpdb->get_results($query);
        if ($grade_id) {
            $filtered = array();
            foreach ($subjects as $s) {
                $ids = explode(',', $s->grade_ids);
                if (in_array($grade_id, $ids)) $filtered[] = $s;
            }
            return $filtered;
        }
        return $subjects;
    }

    public static function add_subject($name, $grade_ids) {
        global $wpdb;
        return $wpdb->insert("{$wpdb->prefix}sm_subjects", array(
            'name' => $name,
            'grade_ids' => implode(',', $grade_ids)
        ));
    }

    public static function delete_subject($id) {
        global $wpdb;
        return $wpdb->delete("{$wpdb->prefix}sm_subjects", array('id' => $id));
    }

    // Assignments
    public static function add_assignment($data) {
        global $wpdb;
        return $wpdb->insert("{$wpdb->prefix}sm_assignments", array(
            'sender_id' => $data['sender_id'],
            'receiver_id' => $data['receiver_id'],
            'title' => $data['title'],
            'description' => $data['description'],
            'file_url' => $data['file_url'],
            'type' => $data['type'],
            'created_at' => current_time('mysql')
        ));
    }

    public static function get_assignments($user_id) {
        global $wpdb;
        $query = "SELECT a.*, u.display_name as sender_name
                  FROM {$wpdb->prefix}sm_assignments a
                  JOIN {$wpdb->prefix}users u ON a.sender_id = u.ID
                  WHERE a.receiver_id = %d AND a.type = 'assignment'
                  ORDER BY a.created_at DESC";
        $res = $wpdb->get_results($wpdb->prepare($query, $user_id));
        foreach ($res as $r) {
            $r->specialization = get_user_meta($r->sender_id, 'sm_specialization', true);
        }
        return $res;
    }

    public static function get_staff_by_section($grade_num, $section) {
        return get_users(array(
            'role' => 'sm_syndicate_member',
            'meta_query' => array(
                array(
                    'key' => 'sm_assigned_sections',
                    'value' => 'الصف ' . $grade_num . '|' . $section,
                    'compare' => 'LIKE'
                )
            )
        ));
    }

    // Surveys
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
