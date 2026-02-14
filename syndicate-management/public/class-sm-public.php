<?php

class SM_Public {
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function hide_admin_bar_for_non_admins($show) {
        if (!current_user_can('administrator')) {
            return false;
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
        $output = '<div class="sm-login-container" style="display: flex; justify-content: center; align-items: center; min-height: 80vh; padding: 20px;">';
        $output .= '<div class="sm-login-box" style="width: 100%; max-width: 400px; background: #ffffff; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); overflow: hidden; border: 1px solid #f0f0f0;" dir="rtl">';

        $output .= '<div style="background: #111F35; padding: 40px 20px; text-align: center;">';
        if (!empty($syndicate['syndicate_logo'])) {
            $output .= '<img src="'.esc_url($syndicate['syndicate_logo']).'" style="max-height: 90px; margin-bottom: 20px; filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2));">';
        }
        $output .= '<h2 style="margin: 0; font-weight: 800; color: #ffffff; font-size: 1.4em; letter-spacing: -0.5px;">'.esc_html($syndicate['syndicate_name']).'</h2>';
        $output .= '</div>';

        $output .= '<div style="padding: 40px 30px;">';
        if (isset($_GET['login']) && $_GET['login'] == 'failed') {
            $output .= '<div style="background: #fff5f5; color: #c53030; padding: 12px; border-radius: 8px; border: 1px solid #feb2b2; margin-bottom: 25px; font-size: 0.85em; text-align: center; font-weight: 600;">⚠️ خطأ في اسم المستخدم أو كلمة المرور</div>';
        }

        $output .= '<style>
            #sm_login_form p { margin-bottom: 20px; }
            #sm_login_form label { display: none; }
            #sm_login_form input[type="text"], #sm_login_form input[type="password"] {
                width: 100%; padding: 14px 20px; border: 1px solid #e2e8f0; border-radius: 10px;
                background: #f8fafc; font-size: 15px; transition: 0.3s; font-family: "Rubik", sans-serif;
            }
            #sm_login_form input:focus { border-color: var(--sm-primary-color); outline: none; background: #fff; box-shadow: 0 0 0 3px rgba(246, 48, 73, 0.1); }
            #sm_login_form .login-remember { display: flex; align-items: center; gap: 8px; font-size: 0.85em; color: #64748b; }
            #sm_login_form input[type="submit"] {
                width: 100%; padding: 14px; background: #111F35; color: #fff; border: none;
                border-radius: 10px; font-weight: 700; font-size: 16px; cursor: pointer; transition: 0.3s;
                margin-top: 10px;
            }
            #sm_login_form input[type="submit"]:hover { background: var(--sm-primary-color); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(246, 48, 73, 0.2); }
        </style>';

        $args = array(
            'echo' => false,
            'redirect' => home_url('/sm-admin'),
            'form_id' => 'sm_login_form',
            'label_username' => 'اسم المستخدم',
            'label_password' => 'كلمة المرور',
            'label_remember' => 'تذكرني على هذا الجهاز',
            'label_log_in' => 'تسجيل الدخول للنظام',
            'remember' => true
        );
        $form = wp_login_form($args);

        // Inject placeholders
        $form = str_replace('name="log"', 'name="log" placeholder="اسم المستخدم أو الرقم القومي"', $form);
        $form = str_replace('name="pwd"', 'name="pwd" placeholder="كلمة المرور الخاصة بك"', $form);

        $output .= $form;
        $output .= '</div>'; // End padding
        $output .= '</div>'; // End box
        $output .= '</div>'; // End container
        return $output;
    }

    public function shortcode_admin_dashboard() {
        if (!is_user_logged_in()) {
            return $this->shortcode_login();
        }

        $user = wp_get_current_user();
        $roles = (array) $user->roles;
        $active_tab = isset($_GET['sm_tab']) ? sanitize_text_field($_GET['sm_tab']) : 'summary';

        $is_admin = in_array('administrator', $roles) || current_user_can('sm_manage_system');
        $is_sys_admin = in_array('sm_system_admin', $roles);
        $is_syndicate_admin = in_array('sm_syndicate_admin', $roles);
        $is_syndicate_member = in_array('sm_syndicate_member', $roles);

        // Fetch data
        $stats = SM_DB::get_statistics();

        ob_start();
        include SM_PLUGIN_DIR . 'templates/public-admin-panel.php';
        return ob_get_clean();
    }

    public function login_failed($username) {
        $referrer = wp_get_referer();
        if ($referrer && !strstr($referrer, 'wp-login') && !strstr($referrer, 'wp-admin')) {
            wp_redirect(add_query_arg('login', 'failed', $referrer));
            exit;
        }
    }

    public function log_successful_login($user_login, $user) {
        SM_Logger::log('تسجيل دخول', "المستخدم: $user_login");
    }

    public function ajax_get_member() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        $national_id = sanitize_text_field($_POST['national_id'] ?? '');
        $member = SM_DB::get_member_by_national_id($national_id);
        if ($member) wp_send_json_success($member);
        else wp_send_json_error('Member not found');
    }

    public function ajax_search_members() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        $query = sanitize_text_field($_POST['query']);
        $members = SM_DB::get_members(array('search' => $query));
        wp_send_json_success($members);
    }

    public function ajax_refresh_dashboard() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        wp_send_json_success(array('stats' => SM_DB::get_statistics()));
    }

    public function ajax_update_member_photo() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_photo_action', 'sm_photo_nonce');

        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $attachment_id = media_handle_upload('member_photo', 0);
        if (is_wp_error($attachment_id)) wp_send_json_error($attachment_id->get_error_message());

        $photo_url = wp_get_attachment_url($attachment_id);
        $member_id = intval($_POST['member_id']);
        SM_DB::update_member_photo($member_id, $photo_url);
        wp_send_json_success(array('photo_url' => $photo_url));
    }

    public function ajax_add_staff() {
        if (!current_user_can('sm_manage_users') && !current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        if (!wp_verify_nonce($_POST['sm_nonce'], 'sm_syndicateMemberAction')) wp_send_json_error('Security check failed');

        $pass = $_POST['user_pass'] ?: wp_generate_password(12, false);
        $username = sanitize_user($_POST['user_login']);
        $email = sanitize_email($_POST['user_email']) ?: $username . '@irseg.org';
        $role = sanitize_text_field($_POST['role']);

        $user_id = wp_insert_user(array(
            'user_login' => $username,
            'user_email' => $email,
            'display_name' => sanitize_text_field($_POST['display_name']),
            'user_pass' => $pass,
            'role' => $role
        ));

        if (is_wp_error($user_id)) wp_send_json_error($user_id->get_error_message());

        update_user_meta($user_id, 'sm_temp_pass', $pass);
        update_user_meta($user_id, 'sm_syndicateMemberIdAttr', sanitize_text_field($_POST['officer_id']));
        update_user_meta($user_id, 'sm_phone', sanitize_text_field($_POST['phone']));
        SM_Logger::log('إضافة مستخدم', "الاسم: {$_POST['display_name']} الرتبة: $role");
        wp_send_json_success($user_id);
    }

    public function ajax_update_staff() {
        if (!current_user_can('sm_manage_users') && !current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        if (!wp_verify_nonce($_POST['sm_nonce'], 'sm_syndicateMemberAction')) wp_send_json_error('Security check failed');

        $user_id = intval($_POST['edit_officer_id']);
        $user_data = array('ID' => $user_id, 'display_name' => sanitize_text_field($_POST['display_name']), 'user_email' => sanitize_email($_POST['user_email']));
        if (!empty($_POST['user_pass'])) {
            $user_data['user_pass'] = $_POST['user_pass'];
            update_user_meta($user_id, 'sm_temp_pass', $_POST['user_pass']);
        }
        wp_update_user($user_data);

        $u = new WP_User($user_id);
        $u->set_role(sanitize_text_field($_POST['role']));

        update_user_meta($user_id, 'sm_syndicateMemberIdAttr', sanitize_text_field($_POST['officer_id']));
        update_user_meta($user_id, 'sm_phone', sanitize_text_field($_POST['phone']));
        update_user_meta($user_id, 'sm_account_status', sanitize_text_field($_POST['account_status']));
        SM_Logger::log('تحديث مستخدم', "الاسم: {$_POST['display_name']}");
        wp_send_json_success('Updated');
    }

    public function ajax_add_member() {
        if (!current_user_can('sm_manage_members')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_add_member', 'sm_nonce');
        $res = SM_DB::add_member($_POST);
        if (is_wp_error($res)) wp_send_json_error($res->get_error_message());
        else wp_send_json_success($res);
    }

    public function ajax_update_member() {
        if (!current_user_can('sm_manage_members')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_add_member', 'sm_nonce');
        SM_DB::update_member(intval($_POST['member_id']), $_POST);
        wp_send_json_success('Updated');
    }

    public function ajax_delete_member() {
        if (!current_user_can('sm_manage_members')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_delete_member', 'nonce');
        SM_DB::delete_member(intval($_POST['member_id']));
        wp_send_json_success('Deleted');
    }

    public function ajax_update_license() {
        if (!current_user_can('sm_manage_licenses')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_add_member', 'nonce');
        $member_id = intval($_POST['member_id']);
        SM_DB::update_member($member_id, [
            'license_number' => sanitize_text_field($_POST['license_number']),
            'license_issue_date' => sanitize_text_field($_POST['license_issue_date']),
            'license_expiration_date' => sanitize_text_field($_POST['license_expiration_date'])
        ]);
        SM_Logger::log('تحديث ترخيص مزاولة', "العضو ID: $member_id");
        wp_send_json_success();
    }

    public function ajax_update_facility() {
        if (!current_user_can('sm_manage_licenses')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_add_member', 'nonce');
        $member_id = intval($_POST['member_id']);
        SM_DB::update_member($member_id, [
            'facility_name' => sanitize_text_field($_POST['facility_name']),
            'facility_number' => sanitize_text_field($_POST['facility_number']),
            'facility_category' => sanitize_text_field($_POST['facility_category']),
            'facility_license_issue_date' => sanitize_text_field($_POST['facility_license_issue_date']),
            'facility_license_expiration_date' => sanitize_text_field($_POST['facility_license_expiration_date']),
            'facility_address' => sanitize_textarea_field($_POST['facility_address'])
        ]);
        SM_Logger::log('تحديث منشأة', "العضو ID: $member_id");
        wp_send_json_success();
    }

    public function ajax_record_payment() {
        if (!current_user_can('sm_manage_finance')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_finance_action', 'nonce');
        if (SM_Finance::record_payment($_POST)) wp_send_json_success();
        else wp_send_json_error('Failed to record payment');
    }

    public function ajax_add_survey() {
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_admin_action', 'nonce');
        $id = SM_DB::add_survey($_POST['title'], $_POST['questions'], $_POST['recipients'], get_current_user_id());
        wp_send_json_success($id);
    }

    public function ajax_cancel_survey() {
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_admin_action', 'nonce');
        global $wpdb;
        $wpdb->update("{$wpdb->prefix}sm_surveys", ['status' => 'cancelled'], ['id' => intval($_POST['id'])]);
        wp_send_json_success();
    }

    public function ajax_submit_survey_response() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_survey_action', 'nonce');
        SM_DB::save_survey_response(intval($_POST['survey_id']), get_current_user_id(), json_decode(stripslashes($_POST['responses']), true));
        wp_send_json_success();
    }

    public function ajax_get_survey_results() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        wp_send_json_success(SM_DB::get_survey_results(intval($_GET['id'])));
    }

    public function ajax_export_survey_results() {
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        $id = intval($_GET['id']);
        $results = SM_DB::get_survey_results($id);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="survey-'.$id.'.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Question', 'Answer', 'Count']);
        foreach ($results as $r) {
            foreach ($r['answers'] as $ans => $count) {
                fputcsv($out, [$r['question'], $ans, $count]);
            }
        }
        fclose($out);
        exit;
    }

    public function handle_form_submission() {
        // Placeholder for legacy form handling if needed
    }

    public function ajax_get_counts() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        $stats = SM_DB::get_statistics();
        wp_send_json_success([
            'pending_reports' => SM_DB::get_pending_reports_count()
        ]);
    }

    public function ajax_bulk_delete_users() {
        if (!current_user_can('sm_manage_users')) wp_send_json_error('Unauthorized');
        if (!wp_verify_nonce($_POST['nonce'], 'sm_syndicateMemberAction')) wp_send_json_error('Security check failed');

        $ids = explode(',', $_POST['user_ids']);
        foreach ($ids as $id) {
            $id = intval($id);
            if ($id === get_current_user_id()) continue;
            wp_delete_user($id);
        }
        wp_send_json_success();
    }

    public function ajax_send_message() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_message_action', 'sm_message_nonce');
        $sender_id = get_current_user_id();
        $receiver_id = intval($_POST['receiver_id']);
        $message = sanitize_textarea_field($_POST['message']);
        SM_DB::send_message($sender_id, $receiver_id, $message);
        wp_send_json_success();
    }

    public function ajax_get_conversation() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_message_action', 'nonce');
        $user1 = get_current_user_id();
        $user2 = intval($_POST['other_user_id']);
        wp_send_json_success(SM_DB::get_conversation_messages($user1, $user2));
    }

    public function ajax_mark_read() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_message_action', 'nonce');
        global $wpdb;
        $wpdb->update("{$wpdb->prefix}sm_messages", ['is_read' => 1], ['receiver_id' => get_current_user_id(), 'sender_id' => intval($_POST['other_user_id'])]);
        wp_send_json_success();
    }

    public function ajax_get_member_finance_html() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        $member_id = intval($_GET['member_id']);
        $dues = SM_Finance::calculate_member_dues($member_id);
        $history = SM_Finance::get_payment_history($member_id);
        ob_start();
        include SM_PLUGIN_DIR . 'templates/modal-finance-details.php';
        $html = ob_get_clean();
        wp_send_json_success(['html' => $html]);
    }

    public function ajax_print_license() { include SM_PLUGIN_DIR . 'templates/print-practice-license.php'; exit; }
    public function ajax_print_facility() { include SM_PLUGIN_DIR . 'templates/print-facility-license.php'; exit; }
    public function ajax_print_invoice() { include SM_PLUGIN_DIR . 'templates/print-invoice.php'; exit; }
    public function handle_print() { /* Basic print handling */ exit; }
}
