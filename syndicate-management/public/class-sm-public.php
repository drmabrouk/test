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

    private function can_manage_user($target_user_id) {
        if (current_user_can('sm_full_access') || current_user_can('manage_options')) return true;

        $current_user = wp_get_current_user();
        $target_user = get_userdata($target_user_id);
        if (!$target_user) return false;

        // Syndicate Admins can only manage Syndicate Members
        if (in_array('sm_syndicate_admin', (array)$current_user->roles)) {
            // Cannot manage System Admins
            if (in_array('sm_system_admin', (array)$target_user->roles)) return false;
            // Cannot manage other Syndicate Admins
            if (in_array('sm_syndicate_admin', (array)$target_user->roles)) return false;

            // Must be in the same governorate
            $my_gov = get_user_meta($current_user->ID, 'sm_governorate', true);
            $target_gov = get_user_meta($target_user_id, 'sm_governorate', true);
            if ($my_gov && $target_gov && $my_gov !== $target_gov) return false;

            return true;
        }

        return false;
    }

    private function can_access_member($member_id) {
        if (current_user_can('sm_full_access') || current_user_can('manage_options')) return true;

        $member = SM_DB::get_member_by_id($member_id);
        if (!$member) return false;

        $user = wp_get_current_user();

        // Members can access their own record
        if (in_array('sm_member', (array)$user->roles) && $member->wp_user_id == $user->ID) {
            return true;
        }

        // Syndicate Admins check governorate
        if (in_array('sm_syndicate_admin', (array)$user->roles)) {
            $my_gov = get_user_meta($user->ID, 'sm_governorate', true);
            if ($my_gov && $member->governorate !== $my_gov) {
                return false;
            }
            return true;
        }

        // Syndicate Members check governorate
        if (in_array('sm_syndicate_member', (array)$user->roles)) {
             $my_gov = get_user_meta($user->ID, 'sm_governorate', true);
             if ($my_gov && $member->governorate !== $my_gov) {
                 return false;
             }
             return true;
        }

        return false;
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
        add_filter('authenticate', array($this, 'custom_authenticate'), 20, 3);
        add_filter('auth_cookie_expiration', array($this, 'custom_auth_cookie_expiration'), 10, 3);
    }

    public function custom_auth_cookie_expiration($expiration, $user_id, $remember) {
        if ($remember) {
            return 30 * DAY_IN_SECONDS; // 30 days
        }
        return $expiration;
    }

    public function custom_authenticate($user, $username, $password) {
        if (empty($username) || empty($password)) return $user;

        // If already authenticated by standard means, return
        if ($user instanceof WP_User) return $user;

        // 1. Check for Syndicate Admin/Member ID Code (meta)
        $code_query = new WP_User_Query(array(
            'meta_query' => array(
                array('key' => 'sm_syndicateMemberIdAttr', 'value' => $username)
            ),
            'number' => 1
        ));
        $found = $code_query->get_results();
        if (!empty($found)) {
            $u = $found[0];
            if (wp_check_password($password, $u->user_pass, $u->ID)) return $u;
        }

        // 2. Check for National ID in sm_members table (if user_login is different)
        global $wpdb;
        $member_wp_id = $wpdb->get_var($wpdb->prepare("SELECT wp_user_id FROM {$wpdb->prefix}sm_members WHERE national_id = %s", $username));
        if ($member_wp_id) {
            $u = get_userdata($member_wp_id);
            if ($u && wp_check_password($password, $u->user_pass, $u->ID)) return $u;
        }

        return $user;
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

        $output .= '<div style="margin-top: 25px; text-align: center; font-size: 0.9em; display: flex; flex-direction: column; gap: 12px;">';
        $output .= '<a href="javascript:smToggleRecovery()" style="color: var(--sm-primary-color); text-decoration: none; font-weight: 600;">نسيت كلمة المرور؟</a>';
        $output .= '<a href="javascript:smToggleActivation()" style="color: #64748b; text-decoration: none;">تفعيل حساب عضو قديم</a>';
        $output .= '</div>';

        // Recovery Modal
        $output .= '<div id="sm-recovery-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:10000; justify-content:center; align-items:center; padding:20px;">';
        $output .= '<div style="background:white; width:100%; max-width:400px; padding:30px; border-radius:16px; position:relative;">';
        $output .= '<button onclick="smToggleRecovery()" style="position:absolute; top:15px; left:15px; border:none; background:none; font-size:20px; cursor:pointer;">&times;</button>';
        $output .= '<h3 style="margin-top:0; margin-bottom:20px; text-align:center;">استعادة كلمة المرور</h3>';
        $output .= '<div id="recovery-step-1">';
        $output .= '<p style="font-size:13px; color:#64748b; margin-bottom:15px;">أدخل الرقم القومي لإرسال رمز التحقق إلى بريدك الإلكتروني.</p>';
        $output .= '<input type="text" id="rec_national_id" class="sm-input" placeholder="الرقم القومي (14 رقم)" style="margin-bottom:15px; width:100%;">';
        $output .= '<button onclick="smRequestOTP()" class="sm-btn" style="width:100%;">إرسال رمز التحقق</button>';
        $output .= '</div>';
        $output .= '<div id="recovery-step-2" style="display:none;">';
        $output .= '<p style="font-size:13px; color:#64748b; margin-bottom:15px;">تم إرسال الرمز. أدخل الرمز وكلمة المرور الجديدة.</p>';
        $output .= '<input type="text" id="rec_otp" class="sm-input" placeholder="رمز التحقق (6 أرقام)" style="margin-bottom:10px; width:100%;">';
        $output .= '<input type="password" id="rec_new_pass" class="sm-input" placeholder="كلمة المرور الجديدة (10+ أحرف وأرقام)" style="margin-bottom:15px; width:100%;">';
        $output .= '<button onclick="smResetPassword()" class="sm-btn" style="width:100%;">تغيير كلمة المرور</button>';
        $output .= '</div>';
        $output .= '</div></div>';

        // Activation Modal
        $output .= '<div id="sm-activation-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:10000; justify-content:center; align-items:center; padding:20px;">';
        $output .= '<div style="background:white; width:100%; max-width:450px; padding:30px; border-radius:16px; position:relative;">';
        $output .= '<button onclick="smToggleActivation()" style="position:absolute; top:15px; left:15px; border:none; background:none; font-size:20px; cursor:pointer;">&times;</button>';
        $output .= '<h3 style="margin-top:0; margin-bottom:20px; text-align:center;">تفعيل حساب العضوية</h3>';
        $output .= '<div id="activation-step-1">';
        $output .= '<input type="text" id="act_national_id" class="sm-input" placeholder="الرقم القومي" style="margin-bottom:10px; width:100%;">';
        $output .= '<input type="text" id="act_phone" class="sm-input" placeholder="رقم الهاتف المسجل" style="margin-bottom:10px; width:100%;">';
        $output .= '<input type="text" id="act_mem_no" class="sm-input" placeholder="رقم العضوية" style="margin-bottom:15px; width:100%;">';
        $output .= '<button onclick="smActivateStep1()" class="sm-btn" style="width:100%;">التحقق من البيانات</button>';
        $output .= '</div>';
        $output .= '<div id="activation-step-2" style="display:none;">';
        $output .= '<input type="email" id="act_email" class="sm-input" placeholder="البريد الإلكتروني الجديد" style="margin-bottom:10px; width:100%;">';
        $output .= '<input type="password" id="act_pass" class="sm-input" placeholder="كلمة المرور الجديدة (10+ أحرف وأرقام)" style="margin-bottom:15px; width:100%;">';
        $output .= '<button onclick="smActivateFinal()" class="sm-btn" style="width:100%;">تفعيل الحساب الآن</button>';
        $output .= '</div>';
        $output .= '</div></div>';

        $output .= '<script>
        function smToggleRecovery() {
            const m = document.getElementById("sm-recovery-modal");
            m.style.display = m.style.display === "none" ? "flex" : "none";
        }
        function smToggleActivation() {
            const m = document.getElementById("sm-activation-modal");
            m.style.display = m.style.display === "none" ? "flex" : "none";
        }
        function smRequestOTP() {
            const nid = document.getElementById("rec_national_id").value;
            const fd = new FormData(); fd.append("action", "sm_forgot_password_otp"); fd.append("national_id", nid);
            fetch("'.admin_url('admin-ajax.php').'", {method:"POST", body:fd}).then(r=>r.json()).then(res=>{
                if(res.success) {
                    document.getElementById("recovery-step-1").style.display="none";
                    document.getElementById("recovery-step-2").style.display="block";
                } else alert(res.data);
            });
        }
        function smResetPassword() {
            const nid = document.getElementById("rec_national_id").value;
            const otp = document.getElementById("rec_otp").value;
            const pass = document.getElementById("rec_new_pass").value;
            const fd = new FormData(); fd.append("action", "sm_reset_password_otp");
            fd.append("national_id", nid); fd.append("otp", otp); fd.append("new_password", pass);
            fetch("'.admin_url('admin-ajax.php').'", {method:"POST", body:fd}).then(r=>r.json()).then(res=>{
                if(res.success) { alert(res.data); location.reload(); } else alert(res.data);
            });
        }
        function smActivateStep1() {
            const nid = document.getElementById("act_national_id").value;
            const ph = document.getElementById("act_phone").value;
            const mem = document.getElementById("act_mem_no").value;
            const fd = new FormData(); fd.append("action", "sm_activate_account_step1");
            fd.append("national_id", nid); fd.append("phone", ph); fd.append("membership_number", mem);
            fetch("'.admin_url('admin-ajax.php').'", {method:"POST", body:fd}).then(r=>r.json()).then(res=>{
                if(res.success) {
                    document.getElementById("activation-step-1").style.display="none";
                    document.getElementById("activation-step-2").style.display="block";
                } else alert(res.data);
            });
        }
        function smActivateFinal() {
            const nid = document.getElementById("act_national_id").value;
            const ph = document.getElementById("act_phone").value;
            const mem = document.getElementById("act_mem_no").value;
            const email = document.getElementById("act_email").value;
            const pass = document.getElementById("act_pass").value;
            const fd = new FormData(); fd.append("action", "sm_activate_account_final");
            fd.append("national_id", nid); fd.append("phone", ph); fd.append("membership_number", mem);
            fd.append("email", email); fd.append("password", pass);
            fetch("'.admin_url('admin-ajax.php').'", {method:"POST", body:fd}).then(r=>r.json()).then(res=>{
                if(res.success) { alert(res.data); location.reload(); } else alert(res.data);
            });
        }
        </script>';

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
        if (!current_user_can('sm_manage_members')) wp_send_json_error('Unauthorized');
        $national_id = sanitize_text_field($_POST['national_id'] ?? '');
        $member = SM_DB::get_member_by_national_id($national_id);
        if ($member) {
            if (!$this->can_access_member($member->id)) wp_send_json_error('Access denied');
            wp_send_json_success($member);
        } else {
            wp_send_json_error('Member not found');
        }
    }

    public function ajax_search_members() {
        if (!current_user_can('sm_manage_members')) wp_send_json_error('Unauthorized');
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

        $member_id = intval($_POST['member_id']);
        if (!$this->can_access_member($member_id)) wp_send_json_error('Access denied');

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

        if (!empty($_POST['user_pass'])) {
            $pass = $_POST['user_pass'];
        } else {
            $digits = '';
            for ($i = 0; $i < 10; $i++) {
                $digits .= mt_rand(0, 9);
            }
            $pass = 'IRS' . $digits;
        }
        $username = sanitize_user($_POST['user_login']);
        $email = sanitize_email($_POST['user_email']) ?: $username . '@irseg.org';
        $role = sanitize_text_field($_POST['role']);

        // Prevent role escalation
        if ($role === 'sm_system_admin' && !current_user_can('sm_full_access') && !current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions to assign this role');
        }

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

        $gov = sanitize_text_field($_POST['governorate'] ?? '');
        if (in_array('sm_syndicate_admin', (array)wp_get_current_user()->roles)) {
            $gov = get_user_meta(get_current_user_id(), 'sm_governorate', true);
        }
        update_user_meta($user_id, 'sm_governorate', $gov);
        SM_Logger::log('إضافة مستخدم', "الاسم: {$_POST['display_name']} الرتبة: $role");
        wp_send_json_success($user_id);
    }

    public function ajax_delete_staff() {
        if (!current_user_can('sm_manage_users') && !current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        if (!wp_verify_nonce($_POST['nonce'], 'sm_syndicateMemberAction')) wp_send_json_error('Security check failed');

        $user_id = intval($_POST['user_id']);
        if ($user_id === get_current_user_id()) wp_send_json_error('Cannot delete yourself');
        if (!$this->can_manage_user($user_id)) wp_send_json_error('Access denied');

        wp_delete_user($user_id);
        wp_send_json_success('Deleted');
    }

    public function ajax_update_staff() {
        if (!current_user_can('sm_manage_users') && !current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        if (!wp_verify_nonce($_POST['sm_nonce'], 'sm_syndicateMemberAction')) wp_send_json_error('Security check failed');

        $user_id = intval($_POST['edit_officer_id']);
        if (!$this->can_manage_user($user_id)) wp_send_json_error('Access denied');

        $role = sanitize_text_field($_POST['role']);

        // Prevent role escalation
        if ($role === 'sm_system_admin' && !current_user_can('sm_full_access') && !current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions to assign this role');
        }

        $user_data = array('ID' => $user_id, 'display_name' => sanitize_text_field($_POST['display_name']), 'user_email' => sanitize_email($_POST['user_email']));
        if (!empty($_POST['user_pass'])) {
            $user_data['user_pass'] = $_POST['user_pass'];
            update_user_meta($user_id, 'sm_temp_pass', $_POST['user_pass']);
        }
        wp_update_user($user_data);

        $u = new WP_User($user_id);
        $u->set_role($role);

        update_user_meta($user_id, 'sm_syndicateMemberIdAttr', sanitize_text_field($_POST['officer_id']));
        update_user_meta($user_id, 'sm_phone', sanitize_text_field($_POST['phone']));

        if (!in_array('sm_syndicate_admin', (array)wp_get_current_user()->roles)) {
            if (isset($_POST['governorate'])) {
                update_user_meta($user_id, 'sm_governorate', sanitize_text_field($_POST['governorate']));
            }
        }

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

        $member_id = intval($_POST['member_id']);
        if (!$this->can_access_member($member_id)) wp_send_json_error('Access denied');

        SM_DB::update_member($member_id, $_POST);
        wp_send_json_success('Updated');
    }

    public function ajax_delete_member() {
        if (!current_user_can('sm_manage_members')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_delete_member', 'nonce');

        $member_id = intval($_POST['member_id']);
        if (!$this->can_access_member($member_id)) wp_send_json_error('Access denied');

        SM_DB::delete_member($member_id);
        wp_send_json_success('Deleted');
    }

    public function ajax_update_license() {
        if (!current_user_can('sm_manage_licenses')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_add_member', 'nonce');
        $member_id = intval($_POST['member_id']);
        if (!$this->can_access_member($member_id)) wp_send_json_error('Access denied');
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
        if (!$this->can_access_member($member_id)) wp_send_json_error('Access denied');
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
        $member_id = intval($_POST['member_id']);
        if (!$this->can_access_member($member_id)) wp_send_json_error('Access denied');
        if (SM_Finance::record_payment($_POST)) wp_send_json_success();
        else wp_send_json_error('Failed to record payment');
    }

    public function ajax_delete_transaction() {
        if (!current_user_can('sm_full_access') && !current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_admin_action', 'nonce');

        global $wpdb;
        $id = intval($_POST['transaction_id']);
        $wpdb->delete("{$wpdb->prefix}sm_payments", ['id' => $id]);
        SM_Logger::log('حذف عملية مالية', "تم حذف العملية رقم #$id بواسطة مدير النظام");
        wp_send_json_success();
    }

    public function ajax_delete_gov_data() {
        if (!current_user_can('manage_options') && !current_user_can('sm_full_access')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_admin_action', 'nonce');

        global $wpdb;
        $gov = sanitize_text_field($_POST['governorate']);
        if (!$gov) wp_send_json_error('محافظة غير محددة');

        // 1. Get member IDs for this gov
        $member_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sm_members WHERE governorate = %s", $gov));
        if (empty($member_ids)) wp_send_json_success('لا توجد بيانات لهذه المحافظة');

        // 2. Delete WP Users
        $wp_user_ids = $wpdb->get_col($wpdb->prepare("SELECT wp_user_id FROM {$wpdb->prefix}sm_members WHERE governorate = %s AND wp_user_id IS NOT NULL", $gov));
        if (!empty($wp_user_ids)) {
            require_once(ABSPATH . 'wp-admin/includes/user.php');
            foreach ($wp_user_ids as $uid) wp_delete_user($uid);
        }

        // 3. Delete payments
        $ids_str = implode(',', array_map('intval', $member_ids));
        $wpdb->query("DELETE FROM {$wpdb->prefix}sm_payments WHERE member_id IN ($ids_str)");

        // 4. Delete members
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}sm_members WHERE governorate = %s", $gov));

        SM_Logger::log('حذف بيانات محافظة', "تم مسح كافة بيانات محافظة: $gov");
        wp_send_json_success();
    }

    public function ajax_merge_gov_data() {
        if (!current_user_can('manage_options') && !current_user_can('sm_full_access')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_admin_action', 'nonce');

        $gov = sanitize_text_field($_POST['governorate']);
        if (empty($_FILES['backup_file']['tmp_name'])) wp_send_json_error('الملف غير موجود');

        $json = file_get_contents($_FILES['backup_file']['tmp_name']);
        $data = json_decode($json, true);
        if (!$data || !isset($data['members'])) wp_send_json_error('تنسيق الملف غير صحيح');

        $success = 0; $skipped = 0;
        foreach ($data['members'] as $row) {
            // Only merge members belonging to the TARGET governorate if specified in the row,
            // OR force them to the target governorate.
            // Requirement says "data for a single governorate only"
            if ($row['governorate'] !== $gov) {
                $skipped++;
                continue;
            }

            if (SM_DB::member_exists($row['national_id'])) {
                $skipped++;
                continue;
            }

            // Clean data for insertion
            unset($row['id']);

            // Re-create WP User if needed
            $digits = ''; for ($i = 0; $i < 10; $i++) $digits .= mt_rand(0, 9);
            $temp_pass = 'IRS' . $digits;
            $wp_user_id = wp_insert_user([
                'user_login' => $row['national_id'],
                'user_email' => $row['email'] ?: $row['national_id'] . '@irseg.org',
                'display_name' => $row['name'],
                'user_pass' => $temp_pass,
                'role' => 'sm_syndicate_member'
            ]);

            if (!is_wp_error($wp_user_id)) {
                $row['wp_user_id'] = $wp_user_id;
                update_user_meta($wp_user_id, 'sm_temp_pass', $temp_pass);
                update_user_meta($wp_user_id, 'sm_governorate', $gov);
            }

            global $wpdb;
            if ($wpdb->insert("{$wpdb->prefix}sm_members", $row)) $success++;
            else $skipped++;
        }

        SM_Logger::log('دمج بيانات محافظة', "تم دمج $success عضواً لمحافظة $gov (تخطى $skipped)");
        wp_send_json_success("تم بنجاح دمج $success عضواً وتجاهل $skipped عضواً مسجلين مسبقاً.");
    }

    public function ajax_reset_system() {
        if (!current_user_can('manage_options') && !current_user_can('sm_full_access')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_admin_action', 'nonce');

        global $wpdb;
        $tables = [
            'sm_members', 'sm_payments', 'sm_logs', 'sm_messages',
            'sm_surveys', 'sm_survey_responses', 'sm_update_requests'
        ];

        // 1. Delete WordPress Users associated with members
        $member_wp_ids = $wpdb->get_col("SELECT wp_user_id FROM {$wpdb->prefix}sm_members WHERE wp_user_id IS NOT NULL");
        if (!empty($member_wp_ids)) {
            require_once(ABSPATH . 'wp-admin/includes/user.php');
            foreach ($member_wp_ids as $uid) {
                wp_delete_user($uid);
            }
        }

        // 2. Truncate Tables
        foreach ($tables as $t) {
            $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}$t");
        }

        // 3. Reset sequences
        delete_option('sm_invoice_sequence_' . date('Y'));

        SM_Logger::log('إعادة تهيئة النظام', "تم مسح كافة البيانات وتصفير النظام بالكامل");
        wp_send_json_success();
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
        if (isset($_POST['sm_import_members_csv'])) {
            $this->handle_member_csv_import();
        }
        if (isset($_POST['sm_import_staffs_csv'])) {
            $this->handle_staff_csv_import();
        }
    }

    private function handle_member_csv_import() {
        if (!current_user_can('sm_manage_members')) return;
        check_admin_referer('sm_admin_action', 'sm_admin_nonce');

        if (empty($_FILES['member_csv_file']['tmp_name'])) return;

        $handle = fopen($_FILES['member_csv_file']['tmp_name'], 'r');
        if (!$handle) return;

        $results = ['total' => 0, 'success' => 0, 'warning' => 0, 'error' => 0];

        // Skip header
        fgetcsv($handle);

        while (($data = fgetcsv($handle)) !== FALSE) {
            $results['total']++;
            if (count($data) < 2) { $results['error']++; continue; }

            $member_data = [
                'national_id' => sanitize_text_field($data[0]),
                'name' => sanitize_text_field($data[1]),
                'professional_grade' => sanitize_text_field($data[2] ?? ''),
                'specialization' => sanitize_text_field($data[3] ?? ''),
                'governorate' => sanitize_text_field($data[4] ?? ''),
                'phone' => sanitize_text_field($data[5] ?? ''),
                'email' => sanitize_email($data[6] ?? '')
            ];

            $res = SM_DB::add_member($member_data);
            if (is_wp_error($res)) {
                $results['error']++;
            } else {
                $results['success']++;
            }
        }
        fclose($handle);

        set_transient('sm_import_results_' . get_current_user_id(), $results, 3600);
        wp_redirect(add_query_arg('sm_tab', 'members', wp_get_referer()));
        exit;
    }

    private function handle_staff_csv_import() {
        if (!current_user_can('sm_manage_users')) return;
        check_admin_referer('sm_admin_action', 'sm_admin_nonce');

        if (empty($_FILES['csv_file']['tmp_name'])) return;

        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if (!$handle) return;

        // Skip header
        fgetcsv($handle);

        while (($data = fgetcsv($handle)) !== FALSE) {
            if (count($data) < 4) continue;

            $username = sanitize_user($data[0]);
            $email = sanitize_email($data[1]);
            $name = sanitize_text_field($data[2]);
            $officer_id = sanitize_text_field($data[3]);
            $role_label = sanitize_text_field($data[4] ?? 'عضو نقابة');
            $phone = sanitize_text_field($data[5] ?? '');
            if (!empty($data[6])) {
                $pass = $data[6];
            } else {
                $digits = '';
                for ($i = 0; $i < 10; $i++) {
                    $digits .= mt_rand(0, 9);
                }
                $pass = 'IRS' . $digits;
            }

            $role = 'sm_syndicate_member';
            if (strpos($role_label, 'مدير') !== false) $role = 'sm_system_admin';
            elseif (strpos($role_label, 'مسؤول') !== false) $role = 'sm_syndicate_admin';

            $user_id = wp_insert_user([
                'user_login' => $username,
                'user_email' => $email ?: $username . '@irseg.org',
                'display_name' => $name,
                'user_pass' => $pass,
                'role' => $role
            ]);

            if (!is_wp_error($user_id)) {
                update_user_meta($user_id, 'sm_temp_pass', $pass);
                update_user_meta($user_id, 'sm_syndicateMemberIdAttr', $officer_id);
                update_user_meta($user_id, 'sm_phone', $phone);
            }
        }
        fclose($handle);

        wp_redirect(add_query_arg('sm_tab', 'staff', wp_get_referer()));
        exit;
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
            if (!$this->can_manage_user($id)) continue;
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
        if (!$this->can_access_member($member_id)) wp_send_json_error('Access denied');

        $dues = SM_Finance::calculate_member_dues($member_id);
        $history = SM_Finance::get_payment_history($member_id);
        ob_start();
        include SM_PLUGIN_DIR . 'templates/modal-finance-details.php';
        $html = ob_get_clean();
        wp_send_json_success(['html' => $html]);
    }

    public function ajax_print_license() {
        if (!current_user_can('sm_print_reports')) wp_die('Unauthorized');
        $member_id = intval($_GET['member_id'] ?? 0);
        if (!$this->can_access_member($member_id)) wp_die('Access denied');
        include SM_PLUGIN_DIR . 'templates/print-practice-license.php';
        exit;
    }

    public function ajax_print_facility() {
        if (!current_user_can('sm_print_reports')) wp_die('Unauthorized');
        $member_id = intval($_GET['member_id'] ?? 0);
        if (!$this->can_access_member($member_id)) wp_die('Access denied');
        include SM_PLUGIN_DIR . 'templates/print-facility-license.php';
        exit;
    }

    public function ajax_print_invoice() {
        if (!current_user_can('sm_manage_finance')) {
            // Check if member is viewing their own invoice
            $payment_id = intval($_GET['payment_id'] ?? 0);
            global $wpdb;
            $pmt = $wpdb->get_row($wpdb->prepare("SELECT member_id FROM {$wpdb->prefix}sm_payments WHERE id = %d", $payment_id));
            if (!$pmt || !$this->can_access_member($pmt->member_id)) wp_die('Unauthorized');
        }
        include SM_PLUGIN_DIR . 'templates/print-invoice.php';
        exit;
    }

    public function handle_print() {
        if (!current_user_can('sm_print_reports')) wp_die('Unauthorized');

        $type = sanitize_text_field($_GET['print_type'] ?? '');
        $member_id = intval($_GET['member_id'] ?? 0);

        if ($member_id && !$this->can_access_member($member_id)) wp_die('Access denied');

        switch($type) {
            case 'id_card':
                include SM_PLUGIN_DIR . 'templates/print-id-cards.php';
                break;
            case 'credentials':
                include SM_PLUGIN_DIR . 'templates/print-member-credentials.php';
                break;
            default:
                wp_die('Invalid print type');
        }
        exit;
    }

    public function ajax_submit_update_request_ajax() {
        if (!is_user_logged_in()) wp_send_json_error('يجب تسجيل الدخول');
        check_ajax_referer('sm_update_request', 'nonce');

        $member_id = intval($_POST['member_id']);
        if (!$this->can_access_member($member_id)) wp_send_json_error('لا تملك صلاحية تعديل هذا العضو');

        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'national_id' => sanitize_text_field($_POST['national_id']),
            'professional_grade' => sanitize_text_field($_POST['professional_grade']),
            'specialization' => sanitize_text_field($_POST['specialization']),
            'governorate' => sanitize_text_field($_POST['governorate']),
            'phone' => sanitize_text_field($_POST['phone']),
            'email' => sanitize_email($_POST['email']),
            'notes' => sanitize_textarea_field($_POST['notes'])
        );

        $res = SM_DB::add_update_request($member_id, $data);
        if ($res) {
            wp_send_json_success();
        } else {
            wp_send_json_error('فشل في إرسال الطلب');
        }
    }

    public function ajax_process_update_request_ajax() {
        if (!current_user_can('sm_manage_members')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_update_request', 'nonce');

        $request_id = intval($_POST['request_id']);
        $status = sanitize_text_field($_POST['status']); // 'approved' or 'rejected'

        if (SM_DB::process_update_request($request_id, $status)) {
            wp_send_json_success();
        } else {
            wp_send_json_error('فشل في معالجة الطلب');
        }
    }

    public function ajax_forgot_password_otp() {
        $national_id = sanitize_text_field($_POST['national_id'] ?? '');
        $member = SM_DB::get_member_by_national_id($national_id);
        if (!$member || !$member->wp_user_id) {
            wp_send_json_error('الرقم القومي غير مسجل في النظام');
        }

        $user = get_userdata($member->wp_user_id);
        $otp = sprintf("%06d", mt_rand(1, 999999));

        update_user_meta($user->ID, 'sm_recovery_otp', $otp);
        update_user_meta($user->ID, 'sm_recovery_otp_time', time());
        update_user_meta($user->ID, 'sm_recovery_otp_used', 0);

        $syndicate = SM_Settings::get_syndicate_info();
        $subject = "رمز استعادة كلمة المرور - " . $syndicate['syndicate_name'];
        $message = "عزيزي العضو " . $member->name . ",\n\n";
        $message .= "رمز التحقق الخاص بك هو: " . $otp . "\n";
        $message .= "هذا الرمز صالح لمدة 10 دقائق فقط ولمرة واحدة.\n\n";
        $message .= "إذا لم تطلب هذا الرمز، يرجى تجاهل هذه الرسالة.\n";

        wp_mail($member->email, $subject, $message);

        wp_send_json_success('تم إرسال رمز التحقق إلى بريدك الإلكتروني المسجل');
    }

    public function ajax_reset_password_otp() {
        $national_id = sanitize_text_field($_POST['national_id'] ?? '');
        $otp = sanitize_text_field($_POST['otp'] ?? '');
        $new_pass = $_POST['new_password'] ?? '';

        $member = SM_DB::get_member_by_national_id($national_id);
        if (!$member || !$member->wp_user_id) wp_send_json_error('بيانات غير صحيحة');

        $user_id = $member->wp_user_id;
        $saved_otp = get_user_meta($user_id, 'sm_recovery_otp', true);
        $otp_time = get_user_meta($user_id, 'sm_recovery_otp_time', true);
        $otp_used = get_user_meta($user_id, 'sm_recovery_otp_used', true);

        if ($otp_used || $saved_otp !== $otp || (time() - $otp_time) > 600) {
            update_user_meta($user_id, 'sm_recovery_otp_used', 1); // Mark as attempt made
            wp_send_json_error('رمز التحقق غير صحيح أو منتهي الصلاحية');
        }

        if (strlen($new_pass) < 10 || !preg_match('/^[a-zA-Z0-9]+$/', $new_pass)) {
            wp_send_json_error('كلمة المرور يجب أن تكون 10 أحرف على الأقل وتتكون من حروف وأرقام فقط بدون رموز');
        }

        wp_set_password($new_pass, $user_id);
        update_user_meta($user_id, 'sm_recovery_otp_used', 1);

        wp_send_json_success('تمت إعادة تعيين كلمة المرور بنجاح. يمكنك الآن تسجيل الدخول');
    }

    public function ajax_activate_account_step1() {
        $national_id = sanitize_text_field($_POST['national_id'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $membership_number = sanitize_text_field($_POST['membership_number'] ?? '');

        $member = SM_DB::get_member_by_national_id($national_id);
        if (!$member) wp_send_json_error('الرقم القومي غير موجود');

        if ($member->phone !== $phone || $member->membership_number !== $membership_number) {
            wp_send_json_error('البيانات المدخلة لا تطابق سجلات العضوية');
        }

        wp_send_json_success('تم التحقق بنجاح. يرجى إكمال بيانات الحساب');
    }

    public function ajax_activate_account_final() {
        $national_id = sanitize_text_field($_POST['national_id'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $membership_number = sanitize_text_field($_POST['membership_number'] ?? '');
        $new_email = sanitize_email($_POST['email'] ?? '');
        $new_pass = $_POST['password'] ?? '';

        $member = SM_DB::get_member_by_national_id($national_id);
        if (!$member || $member->phone !== $phone || $member->membership_number !== $membership_number) {
            wp_send_json_error('فشل التحقق من الهوية');
        }

        if (strlen($new_pass) < 10 || !preg_match('/^[a-zA-Z0-9]+$/', $new_pass)) {
            wp_send_json_error('كلمة المرور يجب أن تكون 10 أحرف على الأقل وتتكون من حروف وأرقام فقط');
        }

        if (!is_email($new_email)) wp_send_json_error('بريد إلكتروني غير صحيح');

        // Update member record
        SM_DB::update_member($member->id, ['email' => $new_email]);

        // Update WP User
        if ($member->wp_user_id) {
            wp_update_user([
                'ID' => $member->wp_user_id,
                'user_email' => $new_email,
                'user_pass' => $new_pass
            ]);
            delete_user_meta($member->wp_user_id, 'sm_temp_pass');
        }

        wp_send_json_success('تم تفعيل الحساب بنجاح. يمكنك الآن تسجيل الدخول');
    }
}
