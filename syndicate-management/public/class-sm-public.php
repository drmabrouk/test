<?php

class SM_Public {
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function hide_admin_bar_for_non_admins($show) {
        if (!current_user_can('manage_options') || current_user_can('إدارة_النظام')) {
            if (!current_user_can('administrator')) {
                return false;
            }
        }
        return $show;
    }

    public function restrict_admin_access() {
        if (is_user_logged_in()) {
            $status = get_user_meta(get_current_user_id(), 'sm_account_status', true);
            if ($status === 'restricted') {
                wp_logout();
                wp_redirect(home_url('/sm-login?login=failed'));
                exit;
            }
        }

        if (is_admin() && !defined('DOING_AJAX') && !current_user_can('manage_options')) {
            wp_redirect(home_url('/sm-admin'));
            exit;
        }
    }

    public function enqueue_styles() {
        wp_enqueue_media();
        wp_enqueue_script('jquery');
        wp_enqueue_style('dashicons');
        wp_enqueue_style('google-font-rubik', 'https://fonts.googleapis.com/css2?family=Rubik:wght@300;400;500;700;800;900&display=swap', array(), null);
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.1', true);
        wp_enqueue_script('html5-qrcode', 'https://unpkg.com/html5-qrcode', array(), '2.3.8', true);
        wp_enqueue_style($this->plugin_name, SM_PLUGIN_URL . 'assets/css/sm-public.css', array('dashicons'), $this->version, 'all');

        $appearance = SM_Settings::get_appearance();
        $custom_css = "
            :root {
                --sm-primary-color: {$appearance['primary_color']};
                --sm-secondary-color: {$appearance['secondary_color']};
                --sm-accent-color: {$appearance['accent_color']};
                --sm-dark-color: {$appearance['dark_color']};
                --sm-radius: {$appearance['border_radius']};
            }
            .sm-content-wrapper, .sm-admin-dashboard, .sm-container,
            .sm-content-wrapper *:not(.dashicons), .sm-admin-dashboard *:not(.dashicons), .sm-container *:not(.dashicons) {
                font-family: 'Rubik', sans-serif !important;
            }
            .sm-admin-dashboard { font-size: {$appearance['font_size']}; }
        ";
        wp_add_inline_style($this->plugin_name, $custom_css);
    }

    public function register_shortcodes() {
        add_shortcode('sm_login', array($this, 'shortcode_login'));
        add_shortcode('sm_admin', array($this, 'shortcode_admin_dashboard'));
    }

    public function shortcode_login() {
        if (is_user_logged_in()) {
            wp_redirect(home_url('/sm-admin'));
            exit;
        }
        $syndicate = SM_Settings::get_syndicate_info();
        $output = '<div class="sm-login-wrapper" style="max-width: 450px; margin: 60px auto; padding: 40px; background: #fff; border-radius: 20px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); border: 1px solid #e2e8f0;" dir="rtl">';

        // Logo & Name
        $output .= '<div style="text-align: center; margin-bottom: 35px;">';
        if (!empty($syndicate['syndicate_logo'])) {
            $output .= '<img src="'.esc_url($syndicate['syndicate_logo']).'" style="max-height: 80px; margin-bottom: 15px;">';
        }
        $output .= '<h2 style="margin: 0; font-weight: 900; color: #111F35; font-size: 1.6em;">'.esc_html($syndicate['syndicate_name']).'</h2>';
        $output .= '<p style="margin-top: 5px; color: #718096; font-size: 0.9em;">نظام إدارة السلوك والنشاط النقابي</p>';
        $output .= '</div>';

        if (isset($_GET['login']) && $_GET['login'] == 'failed') {
            $output .= '<div style="background: #fff5f5; color: #c53030; padding: 12px; border-radius: 8px; border: 1px solid #feb2b2; margin-bottom: 20px; font-size: 0.9em; text-align: center;">خطأ في اسم المستخدم أو كلمة المرور.</div>';
        }

        $args = array(
            'echo' => false,
            'redirect' => home_url('/sm-admin'),
            'form_id' => 'sm_login_form',
            'label_username' => 'اسم المستخدم أو الكود',
            'label_password' => 'كلمة المرور',
            'label_remember' => 'تذكرني على هذا الجهاز',
            'label_log_in' => 'دخول النظام الآمن',
            'remember' => true
        );
        $output .= wp_login_form($args);

        // Notice
        $output .= '<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #edf2f7; text-align: center;">';
        $output .= '<p style="font-size: 0.85em; color: #718096; line-height: 1.6;">في حال نسيان بيانات الدخول، يرجى التواصل مع إدارة النقابة لإعادة تعيين كلمة المرور الخاصة بك.</p>';
        $output .= '</div>';

        $output .= '</div>';
        return $output;
    }


    public function shortcode_admin_dashboard() {
        if (!is_user_logged_in()) {
            return $this->shortcode_login();
        }

        $user = wp_get_current_user();
        $roles = (array) $user->roles;
        $active_tab = isset($_GET['sm_tab']) ? sanitize_text_field($_GET['sm_tab']) : 'summary';

        // Data Preparation based on tab
        $is_admin = in_array('administrator', $roles) || current_user_can('manage_options');
        $is_sys_admin = in_array('sm_system_admin', $roles);
        $is_officer = in_array('sm_officer', $roles);
        $is_syndicate_member = in_array('sm_syndicate_member', $roles);
        $is_member = in_array('sm_member', $roles);
        $is_parent = in_array('sm_parent', $roles);

        // Security / Capability check for tabs
        if ($active_tab === 'members' && !current_user_can('إدارة_الأعضاء')) $active_tab = 'summary';
        if (($active_tab === 'staff' || $active_tab === 'staffs') && !current_user_can('إدارة_المستخدمين')) $active_tab = 'summary';
        if ($active_tab === 'staff-reports' && !current_user_can('إدارة_المخالفات')) $active_tab = 'summary';
        if ($active_tab === 'printing' && !current_user_can('طباعة_التقارير')) $active_tab = 'summary';
        if ($active_tab === 'global-settings' && !current_user_can('إدارة_النظام')) $active_tab = 'summary';

        // Fetch data based on tab
        switch ($active_tab) {
            case 'summary':
                if ($is_member) {
                    $member = SM_DB::get_member_by_parent($user->ID);
                    $member_id = $member ? $member->id : 0;
                    $stats = SM_DB::get_member_stats($member_id);

                    // Find assigned supervisor
                    $supervisor = null;
                    if ($member) {
                        $supervisors = get_users(array('role' => 'sm_syndicate_member'));
                        foreach ($supervisors as $s) {
                            $supervised = get_user_meta($s->ID, 'sm_supervised_classes', true);
                            if (is_array($supervised) && in_array($member->class_name . '|' . $member->section, $supervised)) {
                                $supervisor = $s;
                                break;
                            }
                        }
                    }
                } else {
                    $stats = SM_DB::get_statistics(($is_syndicate_member && !$is_admin) ? ['officer_id' => $user->ID] : []);
                }
                break;

            case 'members':
                $args = array();
                if (isset($_GET['member_search'])) $args['search'] = sanitize_text_field($_GET['member_search']);
                if (isset($_GET['grade_filter'])) $args['professional_grade'] = sanitize_text_field($_GET['grade_filter']);
                if (isset($_GET['spec_filter'])) $args['specialization'] = sanitize_text_field($_GET['spec_filter']);
                if (isset($_GET['status_filter'])) $args['membership_status'] = sanitize_text_field($_GET['status_filter']);
                $members = SM_DB::get_members($args);
                break;

            case 'stats':
                $filters = array();
                if ($is_parent || $is_member) {
                    $my_stu = SM_DB::get_members_by_parent($user->ID);
                    $filters['member_id'] = isset($_GET['member_id']) ? intval($_GET['member_id']) : ($my_stu[0]->id ?? 0);
                } else {
                    if (isset($_GET['member_filter'])) $filters['member_id'] = intval($_GET['member_filter']);
                    if ($is_syndicate_member && !$is_admin) $filters['officer_id'] = $user->ID;

                    if (isset($_GET['member_search'])) $filters['search'] = sanitize_text_field($_GET['member_search']);
                }
                if (isset($_GET['start_date'])) $filters['start_date'] = sanitize_text_field($_GET['start_date']);
                if (isset($_GET['end_date'])) $filters['end_date'] = sanitize_text_field($_GET['end_date']);
                if (isset($_GET['type_filter'])) $filters['type'] = sanitize_text_field($_GET['type_filter']);

                if (empty($_GET['member_search']) && empty($_GET['class_filter']) && empty($_GET['section_filter']) && empty($_GET['start_date']) && empty($_GET['end_date']) && empty($_GET['type_filter']) && !$is_parent) {
                    $filters['limit'] = 20;
                }

                $records = SM_DB::get_records($filters);
                break;

            case 'reports':
                $stats = SM_DB::get_statistics();
                $records = SM_DB::get_records();
                break;

            case 'staff-reports':
                $records = SM_DB::get_records(array('status' => 'pending'));
                break;
        }

        ob_start();
        include SM_PLUGIN_DIR . 'templates/public-admin-panel.php';
        return ob_get_clean();
    }

    public function login_failed($username) {
        SM_Logger::log('فشل تسجيل الدخول', "محاولة دخول فاشلة للمستخدم: $username");
        $referrer = wp_get_referer();
        if ($referrer && !strstr($referrer, 'wp-login') && !strstr($referrer, 'wp-admin')) {
            wp_redirect(add_query_arg('login', 'failed', $referrer));
            exit;
        }
    }

    public function log_successful_login($user_login, $user) {
        SM_Logger::log('تسجيل دخول ناجح', "المستخدم: $user_login (ID: {$user->ID})");
    }

    public function handle_print() {
        $user = wp_get_current_user();
        $member_id = isset($_GET['member_id']) ? intval($_GET['member_id']) : 0;

        if (in_array('sm_parent', (array) $user->roles)) {
            $my_members = SM_DB::get_members_by_parent($user->ID);
            $is_mine = false;
            foreach ($my_members as $ms) {
                if ($ms->id == $member_id) $is_mine = true;
            }
            if (!$is_mine) wp_die('Unauthorized');
        } elseif (!current_user_can('طباعة_التقارير')) {
            wp_die('Unauthorized');
        }

        $type = sanitize_text_field($_GET['print_type']);
        $member_id = isset($_GET['member_id']) ? intval($_GET['member_id']) : 0;

        if ($type === 'id_card') {
            if ($member_id) {
                $members = array(SM_DB::get_member_by_id($member_id));
            } else {
                $filters = array();
                if (!empty($_GET['grade_filter'])) {
                    $filters['professional_grade'] = sanitize_text_field($_GET['grade_filter']);
                }
                $members = SM_DB::get_members($filters);
            }
            include SM_PLUGIN_DIR . 'templates/print-id-cards.php';
        } elseif ($type === 'disciplinary_report') {
            if (!$member_id) wp_die('Member ID missing');
            $member = SM_DB::get_member_by_id($member_id);
            $records = SM_DB::get_records(array('member_id' => $member_id));
            $stats = SM_DB::get_member_stats($member_id);
            include SM_PLUGIN_DIR . 'templates/print-member-report.php';
        } elseif ($type === 'single_violation') {
            $record_id = isset($_GET['record_id']) ? intval($_GET['record_id']) : 0;
            if (!$record_id) wp_die('Record ID missing');
            $record = SM_DB::get_record_by_id($record_id);
            if (!$record) wp_die('Record not found');

            if (in_array('sm_parent', (array) $user->roles)) {
                $member = SM_DB::get_member_by_parent($user->ID);
                if (!$member || $record->member_id != $member->id) wp_die('Unauthorized');
            }

            include SM_PLUGIN_DIR . 'templates/print-single-violation.php';
        } elseif ($type === 'general_log') {
            $filters = array(
                'start_date' => $_GET['start_date'] ?? '',
                'end_date' => $_GET['end_date'] ?? ''
            );
            $records = SM_DB::get_records($filters);
            include SM_PLUGIN_DIR . 'templates/print-general-log.php';
        } elseif ($type === 'member_credentials') {
            $filters = array();
            if (!empty($_GET['grade_filter'])) {
                $filters['professional_grade'] = sanitize_text_field($_GET['grade_filter']);
            }
            $members = SM_DB::get_members($filters);
            include SM_PLUGIN_DIR . 'templates/print-member-credentials.php';
        } elseif ($type === 'member_credentials_card') {
            include SM_PLUGIN_DIR . 'templates/print-member-credentials-card.php';
        } elseif ($type === 'violation_report') {
            $filters = array();
            if (!empty($_GET['search'])) $filters['search'] = sanitize_text_field($_GET['search']);
            if (!empty($_GET['grade_filter'])) $filters['professional_grade'] = sanitize_text_field($_GET['grade_filter']);
            if (!empty($_GET['type_filter'])) $filters['type'] = sanitize_text_field($_GET['type_filter']);

            $range = $_GET['range'] ?? '';
            if ($range === 'today') {
                $filters['start_date'] = current_time('Y-m-d');
                $filters['end_date'] = current_time('Y-m-d');
            } elseif ($range === 'week') {
                $filters['start_date'] = date('Y-m-d', strtotime('-7 days'));
                $filters['end_date'] = current_time('Y-m-d');
            } elseif ($range === 'month') {
                $filters['start_date'] = date('Y-m-d', strtotime('-30 days'));
                $filters['end_date'] = current_time('Y-m-d');
            }

            $records = SM_DB::get_records($filters);
            include SM_PLUGIN_DIR . 'templates/print-violation-report.php';
        }
        exit;
    }

    public function ajax_get_member() {
        if (!is_user_logged_in() || !current_user_can('تسجيل_مخالفة')) wp_send_json_error('Unauthorized');
        $national_id = sanitize_text_field($_POST['national_id'] ?? '');
        $member = SM_DB::get_member_by_national_id($national_id);
        if ($member) {
            wp_send_json_success($member);
        } else {
            wp_send_json_error('Member not found');
        }
    }

    public function ajax_search_members() {
        if (!is_user_logged_in() || !current_user_can('تسجيل_مخالفة')) wp_send_json_error('Unauthorized');
        $query = sanitize_text_field($_POST['query']);
        if (strlen($query) < 2) wp_send_json_success(array());

        $args = array('search' => $query);
        $members = SM_DB::get_members($args);
        wp_send_json_success($members);
    }

    public function ajax_get_member_intelligence() {
        if (!is_user_logged_in() || !current_user_can('تسجيل_مخالفة')) wp_send_json_error('Unauthorized');
        $member_id = intval($_POST['member_id']);
        if (!$member_id) wp_send_json_error('Invalid ID');

        $stats = SM_DB::get_member_stats($member_id);
        $records = SM_DB::get_records(array('member_id' => $member_id));
        $latest = array_slice($records, 0, 3);
        $member = SM_DB::get_member_by_id($member_id);

        wp_send_json_success(array(
            'stats' => $stats,
            'recent' => $latest,
            'labels' => SM_Settings::get_violation_types(),
            'photo_url' => $member ? $member->photo_url : ''
        ));
    }

    public function ajax_refresh_dashboard() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');

        $user_id = get_current_user_id();
        $stats = SM_DB::get_statistics();
        $records = SM_DB::get_records();
        $logs = SM_Logger::get_logs(50);

        global $wpdb;
        $unread_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}sm_messages WHERE receiver_id = %d AND is_read = 0", $user_id));

        wp_send_json_success(array(
            'stats' => $stats,
            'records' => $records,
            'logs' => $logs,
            'unread_messages' => intval($unread_count),
            'violation_labels' => SM_Settings::get_violation_types(),
            'severity_labels' => SM_Settings::get_severities()
        ));
    }

    public function ajax_save_record() {
        if (!is_user_logged_in() || !current_user_can('تسجيل_مخالفة')) wp_send_json_error('Unauthorized');
        if (!wp_verify_nonce($_POST['sm_nonce'], 'sm_record_action')) wp_send_json_error('Security check failed');

        $member_ids = array_filter(array_map('intval', explode(',', $_POST['member_ids'])));
        $last_record_id = 0;
        $count = 0;

        foreach ($member_ids as $sid) {
            $data = $_POST;
            $data['member_id'] = $sid;
            $rid = SM_DB::add_record($data, true);
            if ($rid) {
                $last_record_id = $rid;
                $count++;
                SM_Notifications::send_violation_alert($rid);
            }
        }

        if ($count > 0) {
            SM_Logger::log('تسجيل مخالفة جماعية', "تم تسجيل مخالفة لعدد ($count) من الأعضاء بنجاح.");
        }

        if ($last_record_id) {
            wp_send_json_success(array(
                'record_id' => $last_record_id,
                'print_url' => admin_url('admin-ajax.php?action=sm_print&print_type=single_violation&record_id=' . $last_record_id)
            ));
        } else {
            wp_send_json_error('Failed to save records');
        }
    }

    public function ajax_update_member_photo() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        if (!wp_verify_nonce($_POST['sm_photo_nonce'], 'sm_photo_action')) wp_send_json_error('Security check failed');

        $user_id = get_current_user_id();
        $member_id = intval($_POST['member_id']);

        if (!current_user_can('إدارة_الأعضاء')) {
            $my_children = SM_DB::get_members_by_parent($user_id);
            $is_mine = false;
            foreach ($my_children as $child) {
                if ($child->id == $member_id) $is_mine = true;
            }
            if (!$is_mine) wp_send_json_error('Permission denied');
        }

        if (empty($_FILES['member_photo'])) wp_send_json_error('No file provided');

        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $attachment_id = media_handle_upload('member_photo', 0);
        if (is_wp_error($attachment_id)) {
            wp_send_json_error($attachment_id->get_error_message());
        }

        $photo_url = wp_get_attachment_url($attachment_id);
        $member_id = intval($_POST['member_id']);

        SM_DB::update_member_photo($member_id, $photo_url);
        wp_send_json_success(array('photo_url' => $photo_url));
    }

    public function ajax_send_message() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        if (!wp_verify_nonce($_POST['sm_message_nonce'], 'sm_message_action')) wp_send_json_error('Security check failed');

        $sender_id = get_current_user_id();
        $receiver_id = intval($_POST['receiver_id']);
        $message = sanitize_textarea_field($_POST['message']);
        $member_id = isset($_POST['member_id']) ? intval($_POST['member_id']) : null;

        if (SM_DB::send_message($sender_id, $receiver_id, $message, $member_id)) {
            wp_send_json_success('Message sent');
        } else {
            wp_send_json_error('Failed to send message');
        }
    }

    public function ajax_get_conversation() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        if (!wp_verify_nonce($_POST['nonce'], 'sm_message_action')) wp_send_json_error('Security check');

        $my_id = get_current_user_id();
        $other_id = intval($_POST['other_user_id']);

        $messages = SM_DB::get_conversation_messages($my_id, $other_id);
        wp_send_json_success($messages);
    }

    public function ajax_mark_read() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        if (!wp_verify_nonce($_POST['nonce'], 'sm_message_action')) wp_send_json_error('Security check');

        $my_id = get_current_user_id();
        $other_id = intval($_POST['other_user_id']);

        global $wpdb;
        $wpdb->update(
            "{$wpdb->prefix}sm_messages",
            array('is_read' => 1),
            array('receiver_id' => $my_id, 'sender_id' => $other_id)
        );
        wp_send_json_success();
    }


    public function ajax_update_record_status() {
        if (!is_user_logged_in() || !current_user_can('إدارة_المخالفات')) wp_send_json_error('Unauthorized');
        if (!wp_verify_nonce($_POST['nonce'], 'sm_record_action')) wp_send_json_error('Security check');

        $record_id = intval($_POST['record_id']);
        $status = sanitize_text_field($_POST['status']);

        if (SM_DB::update_record_status($record_id, $status)) {
            wp_send_json_success('Status updated');
        } else {
            wp_send_json_error('Failed to update status');
        }
    }

    public function ajax_send_group_message() {
        if (!is_user_logged_in() || !current_user_can('إدارة_المستخدمين')) wp_send_json_error('Unauthorized');
        if (!wp_verify_nonce($_POST['nonce'], 'sm_message_action')) wp_send_json_error('Security check');

        $role = sanitize_text_field($_POST['target_role']);
        $subject = "رسالة جماعية من إدارة النقابة";
        $message = sanitize_textarea_field($_POST['message']);

        SM_Notifications::send_group_notification($role, $subject, $message);
        wp_send_json_success('Group messages sent');
    }

    public function ajax_add_member() {
        if (!current_user_can('إدارة_الأعضاء')) wp_send_json_error('Unauthorized');
        if (!wp_verify_nonce($_POST['sm_nonce'], 'sm_add_member')) wp_send_json_error('Security check failed');

        $res = SM_DB::add_member($_POST);

        if (is_wp_error($res)) {
            wp_send_json_error($res->get_error_message());
        } elseif ($res) {
            wp_send_json_success($res);
        } else {
            wp_send_json_error('فشل في إضافة العضو. يرجى التحقق من البيانات والمحاولة مرة أخرى.');
        }
    }

    public function ajax_update_member() {
        if (!current_user_can('إدارة_الأعضاء')) wp_send_json_error('Unauthorized');
        if (!wp_verify_nonce($_POST['sm_nonce'], 'sm_add_member')) wp_send_json_error('Security check failed');

        if (SM_DB::update_member(intval($_POST['member_id']), $_POST)) {
            wp_send_json_success('Updated');
        } else {
            wp_send_json_error('Failed to update');
        }
    }

    public function ajax_delete_member() {
        if (!current_user_can('إدارة_الأعضاء')) wp_send_json_error('Unauthorized');
        if (!wp_verify_nonce($_POST['nonce'], 'sm_delete_member')) wp_send_json_error('Security check failed');

        $member_id = intval($_POST['member_id']);
        $member = SM_DB::get_member_by_id($member_id);

        if ($member && SM_DB::delete_member($member_id)) {
            SM_Logger::log('حذف عضو', "تم حذف العضو: {$member->name} (كود: {$member->member_code})");
            wp_send_json_success('Deleted');
        } else {
            wp_send_json_error('Failed to delete');
        }
    }

    public function ajax_delete_record() {
        if (!current_user_can('إدارة_المخالفات')) wp_send_json_error('Unauthorized');
        if (!wp_verify_nonce($_POST['nonce'], 'sm_record_action')) wp_send_json_error('Security check failed');

        $record_id = intval($_POST['record_id']);
        $record = SM_DB::get_record_by_id($record_id);

        if ($record && SM_DB::delete_record($record_id)) {
            SM_Logger::log('حذف مخالفة', "تم حذف مخالفة ID: $record_id للعضو ID: {$record->member_id}");
            wp_send_json_success('Deleted');
        } else {
            wp_send_json_error('Failed to delete');
        }
    }

    public function ajax_get_counts() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        wp_send_json_success(array(
            'pending_reports' => intval(SM_DB::get_pending_reports_count())
        ));
    }

    public function ajax_add_parent() {
        if (!current_user_can('إدارة_المستخدمين')) wp_send_json_error('Unauthorized');
        if (!wp_verify_nonce($_POST['sm_nonce'], 'sm_user_action')) wp_send_json_error('Security check failed');

        $username = sanitize_user($_POST['user_login']);
        $email = sanitize_email($_POST['user_email']);
        if (empty($email)) $email = $username . '@parent.local';

        $user_id = wp_insert_user(array(
            'user_login' => $username,
            'user_email' => $email,
            'display_name' => sanitize_text_field($_POST['display_name']),
            'user_pass' => $_POST['user_pass'],
            'role' => 'sm_parent'
        ));

        if (is_wp_error($user_id)) wp_send_json_error($user_id->get_error_message());
        else {
            SM_Logger::log('إضافة ولي أمر', "تم إنشاء حساب ولي أمر جديد: {$_POST['display_name']}");
            wp_send_json_success($user_id);
        }
    }

    public function ajax_add_user() {
        if (!current_user_can('إدارة_المستخدمين')) wp_send_json_error('Unauthorized');
        if (!wp_verify_nonce($_POST['sm_nonce'], 'sm_user_action')) wp_send_json_error('Security check failed');

        $username = sanitize_user($_POST['user_login']);
        $email = $username . '@syndicate-system.local';

        $user_data = array(
            'user_login' => $username,
            'user_email' => $email,
            'display_name' => sanitize_text_field($_POST['display_name']),
            'user_pass' => $_POST['user_pass'],
            'role' => sanitize_text_field($_POST['user_role'])
        );
        $user_id = wp_insert_user($user_data);
        if (is_wp_error($user_id)) wp_send_json_error($user_id->get_error_message());
        else {
            if (!empty($_POST['specialization'])) {
                update_user_meta($user_id, 'sm_specialization', sanitize_text_field($_POST['specialization']));
            }
            SM_Logger::log('إضافة مستخدم جديد', "تم إنشاء مستخدم باسم: {$_POST['display_name']} ورتبة: {$_POST['user_role']}");
            wp_send_json_success($user_id);
        }
    }

    public function ajax_update_generic_user() {
        if (!current_user_can('إدارة_المستخدمين')) wp_send_json_error('Unauthorized');
        if (!wp_verify_nonce($_POST['sm_nonce'], 'sm_user_action')) wp_send_json_error('Security check failed');

        $user_id = intval($_POST['edit_user_id']);
        $user_data = array(
            'ID' => $user_id,
            'display_name' => sanitize_text_field($_POST['display_name'])
        );
        if (!empty($_POST['user_pass'])) {
            $user_data['user_pass'] = $_POST['user_pass'];
        }
        $result = wp_update_user($user_data);
        if (is_wp_error($result)) wp_send_json_error($result->get_error_message());

        if (!empty($_POST['specialization'])) {
            update_user_meta($user_id, 'sm_specialization', sanitize_text_field($_POST['specialization']));
        }

        $u = new WP_User($user_id);
        $u->set_role(sanitize_text_field($_POST['user_role']));

        SM_Logger::log('تعديل بيانات مستخدم', "تم تحديث بيانات المستخدم: {$_POST['display_name']} (ID: $user_id)");
        wp_send_json_success('Updated');
    }

    public function ajax_add_staff() {
        if (!current_user_can('إدارة_المستخدمين')) wp_send_json_error('Unauthorized');
        if (!wp_verify_nonce($_POST['sm_nonce'], 'sm_syndicate_member_action')) wp_send_json_error('Security check failed');

        $pass = $_POST['user_pass'];
        if (empty($pass)) {
            $pass = '';
            for($i=0; $i<10; $i++) $pass .= rand(0,9);
        }

        $username = sanitize_user($_POST['user_login']);
        $email = $username . '@syndicate-system.local';

        $user_data = array(
            'user_login' => $username,
            'user_email' => $email,
            'display_name' => sanitize_text_field($_POST['display_name']),
            'user_pass' => $pass,
            'role' => sanitize_text_field($_POST['role'] ?: 'sm_syndicate_member')
        );
        $user_id = wp_insert_user($user_data);
        if (is_wp_error($user_id)) wp_send_json_error($user_id->get_error_message());

        update_user_meta($user_id, 'sm_temp_pass', $pass);
        update_user_meta($user_id, 'sm_syndicate_member_id', sanitize_text_field($_POST['officer_id']));
        update_user_meta($user_id, 'sm_phone', sanitize_text_field($_POST['phone']));

        if (!empty($_POST['specialization'])) {
            update_user_meta($user_id, 'sm_specialization', sanitize_text_field($_POST['specialization']));
        }

        if (isset($_POST['assigned'])) {
            $assigned = array_map('sanitize_text_field', $_POST['assigned']);
            if ($_POST['role'] === 'sm_syndicate_member') {
                update_user_meta($user_id, 'sm_assigned_sections', $assigned);
                update_user_meta($user_id, 'sm_supervised_classes', $assigned);
            }
        }

        wp_send_json_success($user_id);
    }

    public function ajax_update_profile() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        if (!wp_verify_nonce($_POST['nonce'], 'sm_profile_action')) wp_send_json_error('Security check failed');

        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        $is_restricted = in_array('sm_member', (array)$user->roles) || in_array('sm_parent', (array)$user->roles);

        $user_data = array(
            'ID' => $user_id
        );

        if (!$is_restricted) {
            $user_data['display_name'] = sanitize_text_field($_POST['display_name']);
            $user_data['user_email'] = sanitize_email($_POST['user_email']);
        }

        if (!empty($_POST['user_pass'])) {
            $user_data['user_pass'] = $_POST['user_pass'];
            update_user_meta($user_id, 'sm_temp_pass', $_POST['user_pass']);
        }

        if (count($user_data) <= 1) {
            wp_send_json_error('No data to update');
        }

        $result = wp_update_user($user_data);
        if (is_wp_error($result)) wp_send_json_error($result->get_error_message());
        else wp_send_json_success('Profile updated');
    }

    public function ajax_bulk_delete() {
        if (!current_user_can('إدارة_النظام')) wp_send_json_error('Unauthorized');
        if (!wp_verify_nonce($_POST['nonce'], 'sm_admin_action')) wp_send_json_error('Security check failed');

        global $wpdb;
        $type = sanitize_text_field($_POST['delete_type']);
        $count = 0;

        switch ($type) {
            case 'members':
                $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}sm_members");
                $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}sm_records");
                SM_Logger::log('مسح كافة الأعضاء والسجلات', 'إجراء جماعي');
                break;
            case 'staffs':
                $staffs = get_users(array('role' => 'sm_syndicate_member'));
                foreach ($staffs as $t) {
                    wp_delete_user($t->ID);
                    $count++;
                }
                SM_Logger::log('مسح كافة أعضاء النقابة', 'إجراء جماعي');
                break;
            case 'parents':
                $parents = get_users(array('role' => 'sm_parent'));
                foreach ($parents as $p) {
                    wp_delete_user($p->ID);
                    $count++;
                }
                SM_Logger::log('مسح كافة أولياء الأمور', 'إجراء جماعي');
                break;
            case 'records':
                $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}sm_records");
                SM_Logger::log('مسح كافة المخالفات', 'إجراء جماعي');
                break;
        }

        wp_send_json_success('تم مسح البيانات بنجاح');
    }

    public function ajax_delete_log() {
        if (!current_user_can('إدارة_النظام')) wp_send_json_error('Unauthorized');
        if (!wp_verify_nonce($_POST['nonce'], 'sm_admin_action')) wp_send_json_error('Security check failed');

        global $wpdb;
        $log_id = intval($_POST['log_id']);
        $result = $wpdb->delete("{$wpdb->prefix}sm_logs", array('id' => $log_id));

        if ($result) wp_send_json_success();
        else wp_send_json_error('Failed to delete log');
    }

    public function ajax_delete_all_logs() {
        if (!current_user_can('إدارة_النظام')) wp_send_json_error('Unauthorized');
        if (!wp_verify_nonce($_POST['nonce'], 'sm_admin_action')) wp_send_json_error('Security check failed');

        global $wpdb;
        $result = $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}sm_logs");

        if ($result !== false) {
            SM_Logger::log('مسح كافة النشاطات', 'قام المستخدم بمسح سجل النشاطات بالكامل');
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to delete logs');
        }
    }

    public function ajax_rollback_log() {
        if (!current_user_can('إدارة_النظام')) wp_send_json_error('Unauthorized');
        if (!wp_verify_nonce($_POST['nonce'], 'sm_admin_action')) wp_send_json_error('Security check failed');

        $log_id = intval($_POST['log_id']);
        global $wpdb;
        $log = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_logs WHERE id = %d", $log_id));

        if (!$log || strpos($log->details, 'ROLLBACK_DATA:') !== 0) {
            wp_send_json_error('لا يمكن استعادة هذه العملية');
        }

        $json = substr($log->details, strlen('ROLLBACK_DATA:'));
        $data_obj = json_decode($json, true);

        if (!$data_obj || !isset($data_obj['table']) || !isset($data_obj['data'])) {
            wp_send_json_error('بيانات الاستعادة تالفة');
        }

        $table = $data_obj['table'];
        $data = $data_obj['data'];

        $table_name = $wpdb->prefix . 'sm_' . $table;

        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE id = %d", $data['id']));
        if ($exists) {
            wp_send_json_error('البيانات موجودة بالفعل أو تم استخدام المعرف');
        }

        $result = $wpdb->insert($table_name, $data);

        if ($result) {
            $wpdb->delete("{$wpdb->prefix}sm_logs", array('id' => $log_id));
            SM_Logger::log('استعادة عملية محذوفة', "الجدول: $table، المعرف الأصلي: {$data['id']}");
            wp_send_json_success('تمت الاستعادة بنجاح');
        } else {
            wp_send_json_error('فشلت عملية الاستعادة في قاعدة البيانات');
        }
    }

    public function ajax_initialize_system() {
        if (!current_user_can('إدارة_النظام')) wp_send_json_error('Unauthorized');
        if (!wp_verify_nonce($_POST['nonce'], 'sm_admin_action')) wp_send_json_error('Security check failed');

        if ($_POST['confirm_code'] !== '1011996') {
            wp_send_json_error('كود التأكيد غير صحيح');
        }

        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/user.php');

        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}sm_members");
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}sm_records");
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}sm_messages");
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}sm_logs");

        $staffs = get_users(array('role' => 'sm_syndicate_member'));
        foreach ($staffs as $t) wp_delete_user($t->ID);

        $parents = get_users(array('role' => 'sm_parent'));
        foreach ($parents as $p) wp_delete_user($p->ID);

        SM_Logger::log('تهيأة النظام بالكامل', 'تم مسح كافة البيانات والجداول');
        wp_send_json_success('تمت تهيأة النظام بالكامل بنجاح');
    }

    public function ajax_update_staff() {
        if (!current_user_can('إدارة_المستخدمين')) wp_send_json_error('Unauthorized');
        if (!wp_verify_nonce($_POST['sm_nonce'], 'sm_syndicate_member_action')) wp_send_json_error('Security check failed');

        $user_id = intval($_POST['edit_officer_id']);
        $user_data = array(
            'ID' => $user_id,
            'display_name' => sanitize_text_field($_POST['display_name'])
        );
        if (!empty($_POST['user_pass'])) {
            $user_data['user_pass'] = $_POST['user_pass'];
            update_user_meta($user_id, 'sm_temp_pass', $_POST['user_pass']);
        }
        $result = wp_update_user($user_data);
        if (is_wp_error($result)) wp_send_json_error($result->get_error_message());

        $u = new WP_User($user_id);
        $role = sanitize_text_field($_POST['role']);
        $u->set_role($role);

        update_user_meta($user_id, 'sm_syndicate_member_id', sanitize_text_field($_POST['officer_id']));
        update_user_meta($user_id, 'sm_phone', sanitize_text_field($_POST['phone']));
        update_user_meta($user_id, 'sm_account_status', sanitize_text_field($_POST['account_status']));

        if (!empty($_POST['specialization'])) {
            update_user_meta($user_id, 'sm_specialization', sanitize_text_field($_POST['specialization']));
        }

        delete_user_meta($user_id, 'sm_assigned_sections');
        delete_user_meta($user_id, 'sm_supervised_classes');

        if (isset($_POST['assigned'])) {
            $assigned = array_map('sanitize_text_field', $_POST['assigned']);
            if ($role === 'sm_syndicate_member') {
                update_user_meta($user_id, 'sm_assigned_sections', $assigned);
                update_user_meta($user_id, 'sm_supervised_classes', $assigned);
            }
        }

        wp_send_json_success('Updated');
    }


    public function ajax_bulk_delete_users() {
        if (!current_user_can('إدارة_المستخدمين')) wp_send_json_error('Unauthorized');
        if (!wp_verify_nonce($_POST['nonce'], 'sm_syndicate_member_action')) wp_send_json_error('Security check');

        $ids = array_map('intval', explode(',', $_POST['user_ids']));
        require_once(ABSPATH . 'wp-admin/includes/user.php');

        $count = 0;
        foreach ($ids as $id) {
            if ($id != get_current_user_id()) {
                if (wp_delete_user($id)) $count++;
            }
        }
        SM_Logger::log('حذف مستخدمين (جماعي)', "تم حذف عدد ($count) مستخدم من النظام.");
        wp_send_json_success();
    }

    public function ajax_bulk_delete_members() {
        if (!current_user_can('إدارة_الأعضاء')) wp_send_json_error('Unauthorized');
        if (!wp_verify_nonce($_POST['nonce'], 'sm_delete_member')) wp_send_json_error('Security');

        $ids = array_map('intval', explode(',', $_POST['member_ids']));
        $count = 0;
        foreach ($ids as $id) {
            if (SM_DB::delete_member($id)) $count++;
        }
        SM_Logger::log('حذف أعضاء (جماعي)', "تم حذف عدد ($count) عضو من النظام.");
        wp_send_json_success($count);
    }

    public function ajax_add_survey() {
        if (!current_user_can('manage_options') && !in_array('sm_officer', (array)wp_get_current_user()->roles) && !in_array('sm_syndicate_member', (array)wp_get_current_user()->roles)) {
            wp_send_json_error('Unauthorized');
        }
        if (!wp_verify_nonce($_POST['nonce'], 'sm_admin_action')) wp_send_json_error('Security');

        $title = sanitize_text_field($_POST['title']);
        $questions = json_decode(stripslashes($_POST['questions']), true);
        $recipients = sanitize_text_field($_POST['recipients']);

        $survey_id = SM_DB::add_survey($title, $questions, $recipients, get_current_user_id());
        if ($survey_id) wp_send_json_success($survey_id);
        else wp_send_json_error('Failed to add survey');
    }

    public function ajax_cancel_survey() {
        if (!current_user_can('manage_options') && !in_array('sm_officer', (array)wp_get_current_user()->roles) && !in_array('sm_syndicate_member', (array)wp_get_current_user()->roles)) {
            wp_send_json_error('Unauthorized');
        }
        if (!wp_verify_nonce($_POST['nonce'], 'sm_admin_action')) wp_send_json_error('Security');

        $survey_id = intval($_POST['id']);
        global $wpdb;
        $wpdb->update("{$wpdb->prefix}sm_surveys", array('status' => 'cancelled'), array('id' => $survey_id));
        wp_send_json_success();
    }

    public function ajax_submit_survey_response() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        if (!wp_verify_nonce($_POST['nonce'], 'sm_survey_action')) wp_send_json_error('Security check');

        $survey_id = intval($_POST['survey_id']);
        $responses = json_decode(stripslashes($_POST['responses']), true);
        $user_id = get_current_user_id();

        if (SM_DB::save_survey_response($survey_id, $user_id, $responses)) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to save response');
        }
    }

    public function ajax_get_survey_results() {
        if (!current_user_can('manage_options') && !in_array('sm_officer', (array)wp_get_current_user()->roles) && !in_array('sm_syndicate_member', (array)wp_get_current_user()->roles)) {
            wp_send_json_error('Unauthorized');
        }
        $survey_id = intval($_GET['id']);
        $results = SM_DB::get_survey_results($survey_id);
        wp_send_json_success($results);
    }

    public function ajax_export_survey_results() {
         if (!current_user_can('manage_options') && !in_array('sm_officer', (array)wp_get_current_user()->roles) && !in_array('sm_syndicate_member', (array)wp_get_current_user()->roles)) {
            wp_send_json_error('Unauthorized');
        }
        $survey_id = intval($_GET['id']);
        $survey = SM_DB::get_survey($survey_id);
        if (!$survey) wp_send_json_error('Survey not found');

        $responses = SM_DB::get_survey_responses($survey_id);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=survey_results_'.$survey_id.'.csv');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        $questions = json_decode($survey->questions, true);
        $header = array('المجيب', 'الدور');
        foreach($questions as $q) $header[] = $q;
        fputcsv($output, $header);

        foreach ($responses as $r) {
            $user = get_userdata($r->user_id);
            $role = !empty($user->roles) ? $user->roles[0] : '';
            $row = array($user->display_name, $role);
            $res_data = json_decode($r->responses, true);
            foreach($questions as $index => $q) {
                $row[] = $res_data[$index] ?? '';
            }
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }

    public function ajax_export_violations_csv() {
        if (!is_user_logged_in() || !current_user_can('إدارة_المخالفات')) wp_send_json_error('Unauthorized');
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'sm_export_action')) wp_send_json_error('Security check failed');

        global $wpdb;
        $range = sanitize_text_field($_GET['range']);
        $start_date = '';
        $end_date = current_time('Y-m-d') . ' 23:59:59';
        $member_code = $_GET['member_code'] ?? '';

        if ($range !== 'all') {
            switch ($range) {
                case 'today': $start_date = current_time('Y-m-d') . ' 00:00:00'; break;
                case 'week': $start_date = date('Y-m-d', strtotime('-7 days')) . ' 00:00:00'; break;
                case 'month': $start_date = date('Y-m-d', strtotime('-30 days')) . ' 00:00:00'; break;
            }
        }

        $query = "SELECT r.*, s.name as member_name, s.class_name, s.section, s.member_code
                  FROM {$wpdb->prefix}sm_records r
                  JOIN {$wpdb->prefix}sm_members s ON r.member_id = s.id
                  WHERE 1=1";

        $params = array();
        if ($start_date) {
            $query .= " AND r.created_at BETWEEN %s AND %s";
            $params[] = $start_date;
            $params[] = $end_date;
        }

        if ($member_code) {
            $query .= " AND s.member_code = %s";
            $params[] = $member_code;
        }

        $query .= " ORDER BY r.created_at DESC";

        $records = empty($params) ? $wpdb->get_results($query) : $wpdb->get_results($wpdb->prepare($query, $params));

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=violations_'.$range.'_'.date('Y-m-d').'.csv');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($output, array('التاريخ', 'اسم العضو', 'كود العضو', 'الصف', 'الشعبة', 'النوع', 'الحدة', 'الدرجة', 'النقاط', 'التفاصيل', 'الإجراء المتخذ'));

        foreach ($records as $r) {
            fputcsv($output, array(
                $r->created_at,
                $r->member_name,
                $r->member_code,
                $r->class_name,
                $r->section,
                $r->type,
                $r->severity,
                $r->degree,
                $r->points,
                $r->details,
                $r->action_taken
            ));
        }
        fclose($output);
        exit;
    }

    public function handle_form_submission() {
        if (isset($_POST['sm_send_call_in']) && wp_verify_nonce($_POST['sm_nonce'], 'sm_message_action')) {
            if (current_user_can('إدارة_أولياء_الأمور')) {
                $receiver_id = intval($_POST['receiver_id']);
                $message = "🔴 طلب استدعاء رسمي: " . sanitize_textarea_field($_POST['message']);
                SM_DB::send_message(get_current_user_id(), $receiver_id, $message);
                wp_redirect(add_query_arg('sm_admin_msg', 'settings_saved', $_SERVER['REQUEST_URI']));
                exit;
            }
        }

        if (isset($_POST['sm_update_generic_user']) && wp_verify_nonce($_POST['sm_nonce'], 'sm_user_action')) {
            if (current_user_can('إدارة_المستخدمين')) {
                $user_id = intval($_POST['edit_user_id']);
                $user_data = array(
                    'ID' => $user_id,
                    'user_email' => sanitize_email($_POST['user_email']),
                    'display_name' => sanitize_text_field($_POST['display_name'])
                );
                if (!empty($_POST['user_pass'])) {
                    $user_data['user_pass'] = $_POST['user_pass'];
                }
                wp_update_user($user_data);

                $u = new WP_User($user_id);
                $u->set_role(sanitize_text_field($_POST['user_role']));

                wp_redirect(add_query_arg('sm_admin_msg', 'settings_saved', $_SERVER['REQUEST_URI']));
                exit;
            }
        }

        if (isset($_POST['sm_save_record']) && wp_verify_nonce($_POST['sm_nonce'], 'sm_record_action')) {
            $record_id = SM_DB::add_record($_POST);
            if ($record_id) {
                SM_Notifications::send_violation_alert($record_id);
                $url = add_query_arg(array('sm_msg' => 'success', 'last_id' => $record_id), $_SERVER['REQUEST_URI']);
                wp_redirect($url);
                exit;
            }
        }

        if (isset($_POST['sm_add_user']) && wp_verify_nonce($_POST['sm_nonce'], 'sm_user_action')) {
            if (current_user_can('إدارة_المستخدمين')) {
                $user_data = array(
                    'user_login' => sanitize_user($_POST['user_login']),
                    'user_email' => sanitize_email($_POST['user_email']),
                    'display_name' => sanitize_text_field($_POST['display_name']),
                    'user_pass' => $_POST['user_pass'],
                    'role' => sanitize_text_field($_POST['user_role'])
                );
                wp_insert_user($user_data);
                wp_redirect(add_query_arg('sm_admin_msg', 'settings_saved', $_SERVER['REQUEST_URI']));
                exit;
            }
        }

        if (isset($_POST['sm_delete_user']) && wp_verify_nonce($_POST['sm_nonce'], 'sm_user_action')) {
            if (current_user_can('إدارة_المستخدمين')) {
                require_once(ABSPATH . 'wp-admin/includes/user.php');
                wp_delete_user(intval($_POST['delete_user_id']));
                wp_redirect(add_query_arg('sm_admin_msg', 'settings_saved', $_SERVER['REQUEST_URI']));
                exit;
            }
        }

        if (isset($_POST['sm_add_staff']) && wp_verify_nonce($_POST['sm_nonce'], 'sm_syndicate_member_action')) {
            if (current_user_can('إدارة_المستخدمين')) {
                $user_data = array(
                    'user_login' => sanitize_user($_POST['user_login']),
                    'user_email' => sanitize_email($_POST['user_email']),
                    'display_name' => sanitize_text_field($_POST['display_name']),
                    'user_pass' => $_POST['user_pass'],
                    'role' => 'sm_syndicate_member'
                );
                $user_id = wp_insert_user($user_data);
                if (!is_wp_error($user_id)) {
                    update_user_meta($user_id, 'sm_syndicate_member_id', sanitize_text_field($_POST['officer_id']));
                    update_user_meta($user_id, 'sm_job_title', sanitize_text_field($_POST['job_title']));
                    update_user_meta($user_id, 'sm_phone', sanitize_text_field($_POST['phone']));
                    wp_redirect(add_query_arg('sm_admin_msg', 'settings_saved', $_SERVER['REQUEST_URI']));
                    exit;
                }
            }
        }

        if (isset($_POST['sm_update_staff']) && wp_verify_nonce($_POST['sm_nonce'], 'sm_syndicate_member_action')) {
            if (current_user_can('إدارة_المستخدمين')) {
                $user_id = intval($_POST['edit_officer_id']);
                $user_data = array(
                    'ID' => $user_id,
                    'user_email' => sanitize_email($_POST['user_email']),
                    'display_name' => sanitize_text_field($_POST['display_name'])
                );
                if (!empty($_POST['user_pass'])) {
                    $user_data['user_pass'] = $_POST['user_pass'];
                }
                wp_update_user($user_data);
                update_user_meta($user_id, 'sm_syndicate_member_id', sanitize_text_field($_POST['officer_id']));
                update_user_meta($user_id, 'sm_job_title', sanitize_text_field($_POST['job_title']));
                update_user_meta($user_id, 'sm_phone', sanitize_text_field($_POST['phone']));
                wp_redirect(add_query_arg('sm_admin_msg', 'settings_saved', $_SERVER['REQUEST_URI']));
                exit;
            }
        }

        if (isset($_POST['sm_delete_staff']) && wp_verify_nonce($_POST['sm_nonce'], 'sm_syndicate_member_action')) {
            if (current_user_can('إدارة_المستخدمين')) {
                require_once(ABSPATH . 'wp-admin/includes/user.php');
                wp_delete_user(intval($_POST['delete_officer_id']));
                wp_redirect(add_query_arg('sm_admin_msg', 'settings_saved', $_SERVER['REQUEST_URI']));
                exit;
            }
        }

        if (isset($_POST['sm_update_record']) && wp_verify_nonce($_POST['sm_nonce'], 'sm_record_action')) {
            if (current_user_can('إدارة_المخالفات')) {
                SM_DB::update_record(intval($_POST['record_id']), $_POST);
                wp_redirect(add_query_arg('sm_admin_msg', 'settings_saved', $_SERVER['REQUEST_URI']));
                exit;
            }
        }

        if (isset($_POST['add_member']) && wp_verify_nonce($_POST['sm_nonce'], 'sm_add_member')) {
            if (current_user_can('إدارة_الأعضاء')) {
                SM_DB::add_member($_POST);
                wp_redirect(add_query_arg('sm_admin_msg', 'member_added', $_SERVER['REQUEST_URI']));
                exit;
            }
        }

        if (isset($_POST['delete_member']) && wp_verify_nonce($_POST['sm_nonce'], 'sm_add_member')) {
            if (current_user_can('إدارة_الأعضاء')) {
                SM_DB::delete_member($_POST['delete_member_id']);
                wp_redirect(add_query_arg('sm_admin_msg', 'member_deleted', $_SERVER['REQUEST_URI']));
                exit;
            }
        }

        if (isset($_POST['sm_update_member']) && wp_verify_nonce($_POST['sm_nonce'], 'sm_add_member')) {
            if (current_user_can('إدارة_الأعضاء')) {
                SM_DB::update_member(intval($_POST['member_id']), $_POST);
                wp_redirect(add_query_arg('sm_admin_msg', 'settings_saved', $_SERVER['REQUEST_URI']));
                exit;
            }
        }

        if (isset($_POST['sm_download_backup']) && wp_verify_nonce($_POST['sm_admin_nonce'], 'sm_admin_action')) {
            if (current_user_can('إدارة_النظام')) {
                if (ob_get_length()) ob_clean();
                SM_Settings::record_backup_download();
                $data = SM_DB::get_backup_data();
                header('Content-Type: application/json; charset=utf-8');
                header('Content-Disposition: attachment; filename="sm_backup_'.date('Y-m-d').'.json"');
                header('Pragma: no-cache');
                header('Expires: 0');
                echo $data;
                exit;
            }
        }

        if (isset($_POST['sm_restore_backup']) && wp_verify_nonce($_POST['sm_admin_nonce'], 'sm_admin_action')) {
            if (current_user_can('إدارة_النظام') && !empty($_FILES['backup_file']['tmp_name'])) {
                $json = file_get_contents($_FILES['backup_file']['tmp_name']);
                if (SM_DB::restore_backup($json)) {
                    SM_Settings::record_backup_import();
                    wp_redirect(add_query_arg('sm_admin_msg', 'restored', $_SERVER['REQUEST_URI']));
                    exit;
                }
            }
        }

        if (isset($_POST['sm_save_settings_unified']) && wp_verify_nonce($_POST['sm_admin_nonce'], 'sm_admin_action')) {
            if (current_user_can('إدارة_النظام')) {
                SM_Settings::save_syndicate_info(array(
                    'syndicate_name' => sanitize_text_field($_POST['syndicate_name']),
                    'syndicate_principal_name' => sanitize_text_field($_POST['syndicate_principal_name']),
                    'syndicate_logo' => esc_url_raw($_POST['syndicate_logo']),
                    'address' => sanitize_text_field($_POST['syndicate_address']),
                    'email' => sanitize_email($_POST['syndicate_email']),
                    'phone' => sanitize_text_field($_POST['syndicate_phone'])
                ));
                SM_Logger::log('تحديث بيانات السلطة', "تم تحديث بيانات النقابة والمدير: {$_POST['syndicate_name']}");
                SM_Settings::save_retention_settings(array(
                    'message_retention_days' => intval($_POST['message_retention_days'])
                ));
                wp_redirect(add_query_arg('sm_admin_msg', 'settings_saved', $_SERVER['REQUEST_URI']));
                exit;
            }
        }

        if (isset($_POST['sm_save_professional_options']) && wp_verify_nonce($_POST['sm_admin_nonce'], 'sm_admin_action')) {
            if (current_user_can('إدارة_النظام')) {
                $grades_raw = explode("\n", str_replace("\r", "", $_POST['professional_grades']));
                $grades = array();
                foreach ($grades_raw as $line) {
                    $parts = explode("|", $line);
                    if (count($parts) == 2) {
                        $grades[trim($parts[0])] = trim($parts[1]);
                    }
                }
                if (!empty($grades)) SM_Settings::save_professional_grades($grades);

                $specs_raw = explode("\n", str_replace("\r", "", $_POST['specializations']));
                $specs = array();
                foreach ($specs_raw as $line) {
                    $parts = explode("|", $line);
                    if (count($parts) == 2) {
                        $specs[trim($parts[0])] = trim($parts[1]);
                    }
                }
                if (!empty($specs)) SM_Settings::save_specializations($specs);

                SM_Logger::log('تحديث الخيارات المهنية', "تم تحديث قائمة الدرجات والتخصصات.");
                wp_redirect(add_query_arg('sm_admin_msg', 'settings_saved', $_SERVER['REQUEST_URI']));
                exit;
            }
        }

        if (isset($_POST['sm_save_appearance']) && wp_verify_nonce($_POST['sm_admin_nonce'], 'sm_admin_action')) {
            if (current_user_can('إدارة_النظام')) {
                SM_Settings::save_appearance(array(
                    'primary_color' => sanitize_hex_color($_POST['primary_color']),
                    'secondary_color' => sanitize_hex_color($_POST['secondary_color']),
                    'accent_color' => sanitize_hex_color($_POST['accent_color']),
                    'dark_color' => sanitize_hex_color($_POST['dark_color']),
                    'font_size' => sanitize_text_field($_POST['font_size']),
                    'border_radius' => sanitize_text_field($_POST['border_radius']),
                    'table_style' => sanitize_text_field($_POST['table_style']),
                    'button_style' => sanitize_text_field($_POST['button_style'])
                ));
                SM_Logger::log('تحديث تصميم النظام', "تم تغيير إعدادات الألوان والمظهر العام.");
                wp_redirect(add_query_arg('sm_admin_msg', 'settings_saved', $_SERVER['REQUEST_URI']));
                exit;
            }
        }

        if (isset($_POST['sm_save_violation_settings']) && wp_verify_nonce($_POST['sm_admin_nonce'], 'sm_admin_action')) {
            if (current_user_can('إدارة_النظام')) {
                SM_Logger::log('تحديث إعدادات المخالفات', "تم تحديث أنواع المخالفات والإجراءات المقترحة.");
                $types_raw = explode("\n", str_replace("\r", "", $_POST['violation_types']));
                $types = array();
                foreach ($types_raw as $line) {
                    $parts = explode("|", $line);
                    if (count($parts) == 2) {
                        $types[trim($parts[0])] = trim($parts[1]);
                    }
                }
                if (!empty($types)) {
                    SM_Settings::save_violation_types($types);
                }
                SM_Settings::save_suggested_actions(array(
                    'low' => sanitize_textarea_field($_POST['suggested_low']),
                    'medium' => sanitize_textarea_field($_POST['suggested_medium']),
                    'high' => sanitize_textarea_field($_POST['suggested_high'])
                ));
                wp_redirect(add_query_arg('sm_admin_msg', 'settings_saved', $_SERVER['REQUEST_URI']));
                exit;
            }
        }

        if (isset($_POST['sm_save_print_templates']) && wp_verify_nonce($_POST['sm_admin_nonce'], 'sm_admin_action')) {
            if (current_user_can('إدارة_النظام')) {
                update_option('sm_print_settings', array(
                    'header' => $_POST['print_header'],
                    'footer' => $_POST['print_footer'],
                    'custom_css' => $_POST['print_css']
                ));
                wp_redirect(add_query_arg('sm_admin_msg', 'settings_saved', $_SERVER['REQUEST_URI']));
                exit;
            }
        }

        if (isset($_POST['sm_save_notif']) && wp_verify_nonce($_POST['sm_admin_nonce'], 'sm_admin_action')) {
            if (current_user_can('إدارة_النظام')) {
                SM_Settings::save_notifications(array(
                    'email_subject' => sanitize_text_field($_POST['email_subject']),
                    'email_template' => sanitize_textarea_field($_POST['email_template']),
                    'whatsapp_template' => sanitize_textarea_field($_POST['whatsapp_template']),
                    'internal_template' => sanitize_textarea_field($_POST['internal_template'])
                ));
                wp_redirect(add_query_arg('sm_admin_msg', 'settings_saved', $_SERVER['REQUEST_URI']));
                exit;
            }
        }

        if (isset($_POST['sm_full_reset']) && wp_verify_nonce($_POST['sm_admin_nonce'], 'sm_admin_action')) {
            if (current_user_can('إدارة_النظام')) {
                if ($_POST['reset_password'] === '1011996') {
                    SM_DB::delete_all_data();
                    wp_redirect(add_query_arg('sm_admin_msg', 'demo_deleted', $_SERVER['REQUEST_URI']));
                    exit;
                } else {
                    wp_redirect(add_query_arg('sm_admin_msg', 'error', $_SERVER['REQUEST_URI']));
                    exit;
                }
            }
        }

        if (isset($_POST['sm_import_csv']) && wp_verify_nonce($_POST['sm_admin_nonce'], 'sm_admin_action')) {
            if (current_user_can('إدارة_الأعضاء') && !empty($_FILES['csv_file']['tmp_name'])) {
                @set_time_limit(0);
                ini_set('auto_detect_line_endings', true);

                $results = array(
                    'total'   => 0,
                    'success' => 0,
                    'warning' => 0,
                    'error'   => 0,
                    'details' => array()
                );

                $handle = fopen($_FILES['csv_file']['tmp_name'], "r");
                $bom = fread($handle, 3);
                if ($bom != "\xEF\xBB\xBF") rewind($handle);
                $first_line = fgets($handle);
                rewind($handle);
                $bom = fread($handle, 3);
                if ($bom != "\xEF\xBB\xBF") rewind($handle);

                $delimiters = [',', ';', "\t", '|'];
                $delimiter = ',';
                $max_count = -1;
                foreach ($delimiters as $d) {
                    $count = substr_count($first_line, $d);
                    if ($count > $max_count) {
                        $max_count = $count;
                        $delimiter = $d;
                    }
                }
                fgetcsv($handle, 0, $delimiter);

                $rows = array();
                $row_index = 2;
                while (($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
                    $rows[] = array('data' => $data, 'index' => $row_index++);
                }
                fclose($handle);

                $next_sort_order = SM_DB::get_next_sort_order();

                foreach ($rows as $row_obj) {
                    $data = $row_obj['data'];
                    $row_index = $row_obj['index'];
                    $results['total']++;

                    foreach ($data as $k => $v) {
                        $encoding = mb_detect_encoding($v, array('UTF-8', 'ISO-8859-6', 'ISO-8859-1'), true);
                        if ($encoding && $encoding != 'UTF-8') {
                            $data[$k] = mb_convert_encoding($v, 'UTF-8', $encoding);
                        }
                    }

                    $national_id = isset($data[0]) ? trim($data[0]) : '';
                    $name        = isset($data[1]) ? trim($data[1]) : '';
                    $grade       = isset($data[2]) ? trim($data[2]) : '';
                    $spec        = isset($data[3]) ? trim($data[3]) : '';
                    $email       = isset($data[4]) ? trim($data[4]) : '';
                    $phone       = isset($data[5]) ? trim($data[5]) : '';

                    if (empty($national_id) || empty($name)) {
                        $results['error']++;
                        $results['details'][] = array('type' => 'error', 'msg' => "بيانات مفقودة في السطر " . $row_index);
                        continue;
                    }

                    $existing_id = SM_DB::member_exists($national_id);

                    $member_data = array(
                        'national_id' => $national_id,
                        'name' => $name,
                        'professional_grade' => $grade,
                        'specialization' => $spec,
                        'email' => $email,
                        'phone' => $phone
                    );

                    if ($existing_id) {
                        SM_DB::update_member($existing_id, $member_data);
                        $results['success']++;
                    } else {
                        $res = SM_DB::add_member($member_data);
                        if (!is_wp_error($res) && $res) $results['success']++;
                        else $results['error']++;
                    }
                }

                SM_Logger::log('استيراد أعضاء (جماعي)', "تم استيراد {$results['success']} عضو بنجاح.");
                set_transient('sm_import_results_' . get_current_user_id(), $results, HOUR_IN_SECONDS);
                wp_redirect(add_query_arg('sm_admin_msg', 'import_completed', $_SERVER['REQUEST_URI']));
                exit;
            }
        }

        if (isset($_POST['sm_import_staffs_csv']) && wp_verify_nonce($_POST['sm_admin_nonce'], 'sm_admin_action')) {
            if (current_user_can('إدارة_المستخدمين') && !empty($_FILES['csv_file']['tmp_name'])) {
                $handle = fopen($_FILES['csv_file']['tmp_name'], "r");
                fgetcsv($handle);
                $count = 0;
                while (($data = fgetcsv($handle)) !== FALSE) {
                    if (count($data) >= 3) {
                        $user_id = wp_insert_user(array(
                            'user_login' => $data[0],
                            'user_email' => $data[1],
                            'display_name' => $data[2],
                            'user_pass' => isset($data[6]) ? $data[6] : wp_generate_password(),
                            'role' => 'sm_syndicate_member'
                        ));
                        if (!is_wp_error($user_id)) {
                            $count++;
                            update_user_meta($user_id, 'sm_syndicate_member_id', isset($data[3]) ? $data[3] : '');
                            update_user_meta($user_id, 'sm_job_title', isset($data[4]) ? $data[4] : '');
                            update_user_meta($user_id, 'sm_phone', isset($data[5]) ? $data[5] : '');
                        }
                    }
                }
                fclose($handle);
                SM_Logger::log('استيراد أعضاء النقابة (جماعي)', "تم استيراد ($count) عضو نقابة بنجاح.");
                wp_redirect(add_query_arg('sm_admin_msg', 'csv_imported', $_SERVER['REQUEST_URI']));
                exit;
            }
        }

        if (isset($_POST['sm_import_violations_csv']) && wp_verify_nonce($_POST['sm_admin_nonce'], 'sm_admin_action')) {
            if (current_user_can('إدارة_المخالفات')) {
                $handle = fopen($_FILES['csv_file']['tmp_name'], "r");
                fgetcsv($handle);
                $count = 0;
                while (($data = fgetcsv($handle)) !== FALSE) {
                    if (count($data) >= 4) {
                        $member = SM_DB::get_member_by_national_id($data[0]);
                        if ($member) {
                            $rid = SM_DB::add_record(array(
                                'member_id' => $member->id,
                                'type' => $data[1],
                                'severity' => $data[2],
                                'details' => $data[3],
                                'action_taken' => isset($data[4]) ? $data[4] : '',
                                'reward_penalty' => isset($data[5]) ? $data[5] : ''
                            ), true);
                            if ($rid) {
                                $count++;
                                SM_Notifications::send_violation_alert($rid);
                            }
                        }
                    }
                }
                fclose($handle);
                SM_Logger::log('استيراد مخالفات (جماعي)', "تم استيراد ($count) مخالفة بنجاح.");
                wp_redirect(add_query_arg('sm_admin_msg', 'csv_imported', $_SERVER['REQUEST_URI']));
                exit;
            }
        }
    }
}
