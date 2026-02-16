<?php

class Syndicate_Management {
    protected $loader;
    protected $plugin_name;
    protected $version;

    public function __construct() {
        $this->plugin_name = 'syndicate-management';
        $this->version = SM_VERSION;
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    private function load_dependencies() {
        require_once SM_PLUGIN_DIR . 'includes/class-sm-loader.php';
        require_once SM_PLUGIN_DIR . 'includes/class-sm-activation.php';
        require_once SM_PLUGIN_DIR . 'includes/class-sm-db.php';
        require_once SM_PLUGIN_DIR . 'includes/class-sm-settings.php';
        require_once SM_PLUGIN_DIR . 'includes/class-sm-finance.php';
        require_once SM_PLUGIN_DIR . 'includes/class-sm-logger.php';
        require_once SM_PLUGIN_DIR . 'includes/class-sm-notifications.php';
        require_once SM_PLUGIN_DIR . 'admin/class-sm-admin.php';
        require_once SM_PLUGIN_DIR . 'public/class-sm-public.php';
        $this->loader = new SM_Loader();
    }

    private function define_admin_hooks() {
        $plugin_admin = new SM_Admin($this->get_plugin_name(), $this->get_version());
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_menu_pages');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
    }

    private function define_public_hooks() {
        $plugin_public = new SM_Public($this->get_plugin_name(), $this->get_version());
        $this->loader->add_filter('show_admin_bar', $plugin_public, 'hide_admin_bar_for_non_admins');
        $this->loader->add_action('admin_init', $plugin_public, 'restrict_admin_access');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('init', $plugin_public, 'register_shortcodes');
        $this->loader->add_action('template_redirect', $plugin_public, 'handle_form_submission');
        $this->loader->add_action('wp_login_failed', $plugin_public, 'login_failed');
        $this->loader->add_action('wp_login', $plugin_public, 'log_successful_login', 10, 2);
        $this->loader->add_action('wp_ajax_sm_get_member', $plugin_public, 'ajax_get_member');
        $this->loader->add_action('wp_ajax_sm_search_members', $plugin_public, 'ajax_search_members');
        $this->loader->add_action('wp_ajax_sm_refresh_dashboard', $plugin_public, 'ajax_refresh_dashboard');
        $this->loader->add_action('wp_ajax_sm_update_member_photo', $plugin_public, 'ajax_update_member_photo');
        $this->loader->add_action('wp_ajax_sm_send_message_ajax', $plugin_public, 'ajax_send_message');
        $this->loader->add_action('wp_ajax_sm_get_conversation_ajax', $plugin_public, 'ajax_get_conversation');
        $this->loader->add_action('wp_ajax_sm_mark_read', $plugin_public, 'ajax_mark_read');
        $this->loader->add_action('wp_ajax_sm_print', $plugin_public, 'handle_print');
        $this->loader->add_action('wp_ajax_sm_add_member_ajax', $plugin_public, 'ajax_add_member');
        $this->loader->add_action('wp_ajax_sm_update_member_ajax', $plugin_public, 'ajax_update_member');
        $this->loader->add_action('wp_ajax_sm_delete_member_ajax', $plugin_public, 'ajax_delete_member');
        $this->loader->add_action('wp_ajax_sm_get_counts_ajax', $plugin_public, 'ajax_get_counts');
        $this->loader->add_action('wp_ajax_sm_add_staff_ajax', $plugin_public, 'ajax_add_staff');
        $this->loader->add_action('wp_ajax_sm_update_staff_ajax', $plugin_public, 'ajax_update_staff');
        $this->loader->add_action('wp_ajax_sm_delete_staff_ajax', $plugin_public, 'ajax_delete_staff');
        $this->loader->add_action('wp_ajax_sm_bulk_delete_users_ajax', $plugin_public, 'ajax_bulk_delete_users');
        $this->loader->add_action('wp_ajax_sm_add_survey', $plugin_public, 'ajax_add_survey');
        $this->loader->add_action('wp_ajax_sm_cancel_survey', $plugin_public, 'ajax_cancel_survey');
        $this->loader->add_action('wp_ajax_sm_submit_survey_response', $plugin_public, 'ajax_submit_survey_response');
        $this->loader->add_action('wp_ajax_sm_get_survey_results', $plugin_public, 'ajax_get_survey_results');
        $this->loader->add_action('wp_ajax_sm_export_survey_results', $plugin_public, 'ajax_export_survey_results');
        $this->loader->add_action('wp_ajax_sm_record_payment_ajax', $plugin_public, 'ajax_record_payment');
        $this->loader->add_action('wp_ajax_sm_delete_transaction_ajax', $plugin_public, 'ajax_delete_transaction');
        $this->loader->add_action('wp_ajax_sm_delete_gov_data_ajax', $plugin_public, 'ajax_delete_gov_data');
        $this->loader->add_action('wp_ajax_sm_merge_gov_data_ajax', $plugin_public, 'ajax_merge_gov_data');
        $this->loader->add_action('wp_ajax_sm_reset_system_ajax', $plugin_public, 'ajax_reset_system');
        $this->loader->add_action('wp_ajax_sm_get_member_finance_html', $plugin_public, 'ajax_get_member_finance_html');
        $this->loader->add_action('wp_ajax_sm_update_license_ajax', $plugin_public, 'ajax_update_license');
        $this->loader->add_action('wp_ajax_sm_print_license', $plugin_public, 'ajax_print_license');
        $this->loader->add_action('wp_ajax_sm_update_facility_ajax', $plugin_public, 'ajax_update_facility');
        $this->loader->add_action('wp_ajax_sm_print_facility', $plugin_public, 'ajax_print_facility');
        $this->loader->add_action('wp_ajax_sm_print_invoice', $plugin_public, 'ajax_print_invoice');
        $this->loader->add_action('wp_ajax_sm_submit_update_request_ajax', $plugin_public, 'ajax_submit_update_request_ajax');
        $this->loader->add_action('wp_ajax_sm_process_update_request_ajax', $plugin_public, 'ajax_process_update_request_ajax');
        $this->loader->add_action('wp_ajax_nopriv_sm_forgot_password_otp', $plugin_public, 'ajax_forgot_password_otp');
        $this->loader->add_action('wp_ajax_nopriv_sm_reset_password_otp', $plugin_public, 'ajax_reset_password_otp');
        $this->loader->add_action('wp_ajax_nopriv_sm_activate_account_step1', $plugin_public, 'ajax_activate_account_step1');
        $this->loader->add_action('wp_ajax_nopriv_sm_activate_account_final', $plugin_public, 'ajax_activate_account_final');
    }

    public function run() {
        $this->check_version_updates();

        // GLOBAL ACTIVATION CHECK
        if (!SM_Activation::is_active()) {
            if (is_admin()) {
                $page = isset($_GET['page']) ? $_GET['page'] : '';

                // Block sensitive AJAX actions if not active (keep core dashboard ones if needed)
                if (defined('DOING_AJAX') && DOING_AJAX && isset($_REQUEST['action']) && strpos($_REQUEST['action'], 'sm_') === 0) {
                    $allowed_ajax = ['sm_get_counts_ajax', 'sm_refresh_dashboard'];
                    if (!in_array($_REQUEST['action'], $allowed_ajax)) {
                        wp_send_json_error('النظام متوقف. انتهت صلاحية الترخيص.');
                    }
                }

                // Global Inactivity Notice
                $notice_callback = function() use ($page) {
                    if ($page === 'sm-activation' && current_user_can('sm_full_access')) return;

                    echo '<div class="sm-inactive-overlay-notice" style="background: #9b1c1c; color: #fff; padding: 25px; text-align: center; border-bottom: 5px solid #111F35; position: sticky; top: 0; z-index: 99999; box-shadow: 0 4px 10px rgba(0,0,0,0.3);">';
                    echo '<p style="margin: 0; font-weight: 900; font-size: 22px; text-transform: uppercase;">⚠️ يرجى التواصل مع المطور لتجديد النظام. النظام متوقف مؤقتاً.</p>';
                    echo '<div style="font-size: 13px; margin-top: 5px; opacity: 0.8;">Contact Developer for Renewal - System Temporarily Inactive</div>';
                    echo '</div>';
                };
                add_action('admin_notices', $notice_callback);
                add_action('admin_footer', $notice_callback);

                // Restriction logic: Only Dashboard and Activation remain functional
                if (strpos($page, 'sm-') === 0 && !in_array($page, ['sm-dashboard', 'sm-activation'])) {
                    // Just show the notice and keep the content blocked?
                    // Let's redirect to dashboard if not on dashboard or activation
                    wp_safe_redirect(admin_url('admin.php?page=sm-dashboard'));
                    exit;
                }
            } else {
                // Disable shortcodes on public side if not active
                add_shortcode('sm_login', function() { return '<div style="padding:40px; text-align:center; background:#fff5f5; color:#c53030; border-radius:12px; border:2px solid #feb2b2; font-weight:800;">⚠️ يرجى التواصل مع المطور لتجديد النظام. النظام متوقف مؤقتاً.</div>'; });
                add_shortcode('sm_admin', function() { return ''; });
            }
        }

        $this->loader->run();
    }

    private function check_version_updates() {
        $db_version = get_option('sm_plugin_version', '1.0.0');
        if (version_compare($db_version, SM_VERSION, '<')) {
            require_once SM_PLUGIN_DIR . 'includes/class-sm-activator.php';
            SM_Activator::activate();
            update_option('sm_plugin_version', SM_VERSION);
        }
    }

    public function get_plugin_name() {
        return $this->plugin_name;
    }

    public function get_version() {
        return $this->version;
    }
}
