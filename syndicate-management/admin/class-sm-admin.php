<?php

class SM_Admin {
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function add_menu_pages() {
        add_menu_page(
            'إدارة النقابة',
            'إدارة النقابة',
            'read', // Allow all roles to see top level
            'sm-dashboard',
            array($this, 'display_dashboard'),
            'dashicons-welcome-learn-more',
            6
        );

        add_submenu_page(
            'sm-dashboard',
            'لوحة التحكم',
            'لوحة التحكم',
            'read',
            'sm-dashboard',
            array($this, 'display_dashboard')
        );


        add_submenu_page(
            'sm-dashboard',
            'إدارة الأعضاء',
            'إدارة الأعضاء',
            'sm_manage_members',
            'sm-members',
            array($this, 'display_members')
        );

        add_submenu_page(
            'sm-dashboard',
            'أعضاء النقابة',
            'أعضاء النقابة',
            'sm_manage_users',
            'sm-staff',
            array($this, 'display_staff_page')
        );

        add_submenu_page(
            'sm-dashboard',
            'إعدادات النظام',
            'إعدادات النظام',
            'sm_manage_system',
            'sm-settings',
            array($this, 'display_settings')
        );
    }

    public function enqueue_styles() {
        wp_enqueue_style('google-font-rubik', 'https://fonts.googleapis.com/css2?family=Rubik:wght@300;400;500;700;800;900&display=swap', array(), null);
        wp_enqueue_style($this->plugin_name, SM_PLUGIN_URL . 'assets/css/sm-admin.css', array(), $this->version, 'all');

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
            .sm-content-wrapper { font-size: {$appearance['font_size']}; }
        ";
        wp_add_inline_style($this->plugin_name, $custom_css);
    }

    public function display_dashboard() {
        $_GET['sm_tab'] = 'summary';
        $this->display_settings();
    }


    public function display_settings() {
        if (isset($_POST['sm_save_settings_unified'])) {
            check_admin_referer('sm_admin_action', 'sm_admin_nonce');
            SM_Settings::save_syndicate_info(array(
                'syndicate_name' => sanitize_text_field($_POST['syndicate_name']),
                'syndicate_officer_name' => sanitize_text_field($_POST['syndicate_officer_name']),
                'phone' => sanitize_text_field($_POST['syndicate_phone']),
                'email' => sanitize_email($_POST['syndicate_email']),
                'syndicate_logo' => esc_url_raw($_POST['syndicate_logo']),
                'address' => sanitize_text_field($_POST['syndicate_address'])
            ));
            echo '<div class="updated"><p>تم حفظ بيانات النقابة بنجاح.</p></div>';
        }

        if (isset($_POST['sm_save_appearance'])) {
            check_admin_referer('sm_admin_action', 'sm_admin_nonce');
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
            echo '<div class="updated"><p>تم حفظ إعدادات التصميم بنجاح.</p></div>';
        }


        if (isset($_POST['sm_save_professional_options'])) {
            check_admin_referer('sm_admin_action', 'sm_admin_nonce');
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
            echo '<div class="updated"><p>تم حفظ الخيارات المهنية بنجاح.</p></div>';
        }

        $member_filters = array();
        $stats = SM_DB::get_statistics();
        $members = SM_DB::get_members();
        include SM_PLUGIN_DIR . 'templates/public-admin-panel.php';
    }

    public function display_staff_page() {
        $_GET['sm_tab'] = 'staff';
        $this->display_settings();
    }


    public function display_members() {
        $_GET['sm_tab'] = 'members';
        $this->display_settings();
    }

}
