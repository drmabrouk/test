<?php

class SM_Activator {

    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Migration: Rename old tables if they exist
        self::migrate_tables();
        self::migrate_settings();

        $sql = "";

        // Members Table
        $table_name = $wpdb->prefix . 'sm_members';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            national_id varchar(14) NOT NULL,
            member_code tinytext,
            name tinytext NOT NULL,
            gender enum('male', 'female') DEFAULT 'male',
            professional_grade tinytext,
            specialization tinytext,
            academic_degree enum('bachelor', 'master', 'doctorate'),
            governorate tinytext,
            membership_number tinytext,
            membership_start_date date,
            membership_expiration_date date,
            membership_status tinytext,
            license_number tinytext,
            license_issue_date date,
            license_expiration_date date,
            facility_number tinytext,
            facility_name tinytext,
            facility_license_issue_date date,
            facility_license_expiration_date date,
            facility_address text,
            sub_syndicate tinytext,
            facility_category enum('A', 'B', 'C') DEFAULT 'C',
            last_paid_membership_year int DEFAULT 0,
            last_paid_license_year int DEFAULT 0,
            email tinytext,
            phone tinytext,
            alt_phone tinytext,
            notes text,
            photo_url text,
            wp_user_id bigint(20),
            officer_id bigint(20),
            registration_date date,
            sort_order int DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY national_id (national_id),
            KEY wp_user_id (wp_user_id),
            KEY officer_id (officer_id)
        ) $charset_collate;\n";


        // Messages Table
        $table_name = $wpdb->prefix . 'sm_messages';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            sender_id bigint(20) NOT NULL,
            receiver_id bigint(20) NOT NULL,
            member_id mediumint(9),
            message text NOT NULL,
            is_read tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY sender_id (sender_id),
            KEY receiver_id (receiver_id),
            KEY member_id (member_id)
        ) $charset_collate;\n";

        // Logs Table
        $table_name = $wpdb->prefix . 'sm_logs';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20),
            action tinytext NOT NULL,
            details text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id)
        ) $charset_collate;\n";

        // Surveys Table
        $table_name = $wpdb->prefix . 'sm_surveys';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title tinytext NOT NULL,
            questions text NOT NULL,
            recipients tinytext NOT NULL,
            status enum('active', 'completed', 'cancelled') DEFAULT 'active',
            created_by bigint(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY created_by (created_by)
        ) $charset_collate;\n";

        // Survey Responses Table
        $table_name = $wpdb->prefix . 'sm_survey_responses';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            survey_id mediumint(9) NOT NULL,
            user_id bigint(20) NOT NULL,
            responses text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY survey_id (survey_id),
            KEY user_id (user_id)
        ) $charset_collate;\n";

        // Payments Table
        $table_name = $wpdb->prefix . 'sm_payments';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            member_id mediumint(9) NOT NULL,
            amount decimal(10,2) NOT NULL,
            payment_type enum('membership', 'license', 'facility', 'other') NOT NULL,
            payment_date date NOT NULL,
            target_year int,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY member_id (member_id)
        ) $charset_collate;\n";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        self::setup_roles();
    }

    private static function migrate_settings() {
        $old_info = get_option('sm_syndicate_info');
        if ($old_info && !get_option('sm_syndicate_info')) {
            // Rename syndicate fields to syndicate fields
            if (isset($old_info['syndicate_name'])) {
                $old_info['syndicate_name'] = $old_info['syndicate_name'];
            }
            if (isset($old_info['syndicate_logo'])) {
                $old_info['syndicate_logo'] = $old_info['syndicate_logo'];
            }
            if (isset($old_info['syndicate_officer_name'])) {
                $old_info['syndicate_officer_name'] = $old_info['syndicate_officer_name'];
            }
            update_option('sm_syndicate_info', $old_info);
        }
    }

    private static function migrate_tables() {
        global $wpdb;
        // Migration from School version (sm_students -> sm_members)
        $mappings = array(
            'sm_students' => 'sm_members'
        );
        foreach ($mappings as $old => $new) {
            $old_table = $wpdb->prefix . $old;
            $new_table = $wpdb->prefix . $new;
            if ($wpdb->get_var("SHOW TABLES LIKE '$old_table'") && !$wpdb->get_var("SHOW TABLES LIKE '$new_table'")) {
                $wpdb->query("RENAME TABLE $old_table TO $new_table");
            }
        }

        $members_table = $wpdb->prefix . 'sm_members';
        if ($wpdb->get_var("SHOW TABLES LIKE '$members_table'")) {
            // Ensure column names are updated from legacy 'student' to 'member'
            $column_renames = [
                'student_code' => 'member_code'
            ];
            foreach ($column_renames as $old_col => $new_col) {
                $col_exists = $wpdb->get_results("SHOW COLUMNS FROM $members_table LIKE '$old_col'");
                if (!empty($col_exists)) {
                    $wpdb->query("ALTER TABLE $members_table CHANGE $old_col $new_col tinytext");
                }
            }
        }

        // Rename old column names to new ones in all relevant tables
        $tables_to_fix = array('sm_messages', 'sm_members');
        foreach ($tables_to_fix as $table) {
            $full_table = $wpdb->prefix . $table;
            if ($wpdb->get_var("SHOW TABLES LIKE '$full_table'")) {
                // Fix Member ID to Member ID
                $col_member = $wpdb->get_results("SHOW COLUMNS FROM $full_table LIKE 'member_id'");
                if (!empty($col_member)) {
                    $wpdb->query("ALTER TABLE $full_table CHANGE member_id member_id mediumint(9)");
                }

                // Fix Officer ID / Syndicate Member ID to Officer ID
                $col_officer = $wpdb->get_results("SHOW COLUMNS FROM $full_table LIKE 'officer_id'");
                if (!empty($col_officer)) {
                    $wpdb->query("ALTER TABLE $full_table CHANGE officer_id officer_id bigint(20)");
                }

                $col_syndicate_member = $wpdb->get_results("SHOW COLUMNS FROM $full_table LIKE 'syndicate_member_id'");
                if (!empty($col_syndicate_member)) {
                    $wpdb->query("ALTER TABLE $full_table CHANGE syndicate_member_id officer_id bigint(20)");
                }
            }
        }
    }

    private static function setup_roles() {
        // Clear all existing custom roles first to ensure no traces remain
        $roles_to_clean = array(
            'sm_system_admin', 'sm_officer', 'sm_syndicate_member', 'sm_member', 'sm_parent',
            'sm_syndicate_admin', 'syndicate_admin', 'sm_school_admin', 'school_admin',
            'discipline_officer', 'sm_principal', 'sm_supervisor', 'sm_teacher',
            'sm_coordinator', 'sm_clinic', 'sm_student'
        );
        foreach ($roles_to_clean as $role_slug) {
            remove_role($role_slug);
        }

        // 1. System Manager (مدير النظام)
        add_role('sm_system_admin', 'مدير النظام', array(
            'read' => true,
            'manage_options' => true,
            'sm_manage_system' => true,
            'sm_manage_users' => true,
            'sm_manage_members' => true,
            'sm_manage_finance' => true,
            'sm_manage_licenses' => true,
            'sm_print_reports' => true,
            'sm_full_access' => true
        ));

        // 2. Syndicate Administrator (مسؤول نقابة)
        add_role('sm_syndicate_admin', 'مسؤول نقابة', array(
            'read' => true,
            'sm_manage_users' => true,
            'sm_manage_members' => true,
            'sm_manage_finance' => true,
            'sm_manage_licenses' => true,
            'sm_print_reports' => true
        ));

        // 3. Syndicate Member (عضو نقابة)
        add_role('sm_syndicate_member', 'عضو نقابة', array(
            'read' => true,
            'sm_manage_members' => true,
            'sm_print_reports' => true
        ));

        // 4. Member (End-user) (عضو)
        add_role('sm_member', 'عضو', array(
            'read' => true
        ));

        self::migrate_user_roles();
    }

    private static function migrate_user_roles() {
        $role_migration = array(
            'sm_system_admin'       => 'sm_system_admin',
            'sm_officer'            => 'sm_syndicate_admin',
            'sm_syndicate_admin'    => 'sm_syndicate_admin',
            'sm_syndicate_member'   => 'sm_syndicate_member',
            'sm_member'             => 'sm_member',
            'sm_parent'             => 'sm_member',
            'sm_principal'          => 'sm_syndicate_admin',
            'school_admin'          => 'sm_syndicate_admin',
            'sm_school_admin'       => 'sm_syndicate_admin',
            'sm_student'            => 'sm_member'
        );

        foreach ($role_migration as $old => $new) {
            $users = get_users(array('role' => $old));
            if (!empty($users)) {
                foreach ($users as $user) {
                    $user->add_role($new);
                    $user->remove_role($old);
                }
            }
        }
    }
}
