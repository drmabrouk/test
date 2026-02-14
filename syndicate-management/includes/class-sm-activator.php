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
            name tinytext NOT NULL,
            gender enum('male', 'female') DEFAULT 'male',
            professional_grade tinytext,
            specialization tinytext,
            academic_degree enum('bachelor', 'master', 'doctorate'),
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
            parent_user_id bigint(20),
            officer_id bigint(20),
            registration_date date,
            sort_order int DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY national_id (national_id),
            KEY parent_user_id (parent_user_id),
            KEY officer_id (officer_id)
        ) $charset_collate;\n";

        // Records Table
        $table_name = $wpdb->prefix . 'sm_records';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            member_id mediumint(9) NOT NULL,
            officer_id bigint(20),
            type tinytext NOT NULL,
            violation_code tinytext,
            severity tinytext,
            degree tinytext,
            classification tinytext,
            points int DEFAULT 0,
            recurrence_count int DEFAULT 1,
            details text,
            action_taken text,
            status tinytext DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY member_id (member_id),
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
            payment_type enum('membership', 'license', 'facility', 'penalty', 'other') NOT NULL,
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
        $old_info = get_option('sm_school_info');
        if ($old_info && !get_option('sm_syndicate_info')) {
            // Rename school_name to syndicate_name and school_logo to syndicate_logo
            if (isset($old_info['school_name'])) {
                $old_info['syndicate_name'] = $old_info['school_name'];
                unset($old_info['school_name']);
            }
            if (isset($old_info['school_logo'])) {
                $old_info['syndicate_logo'] = $old_info['school_logo'];
                unset($old_info['school_logo']);
            }
            if (isset($old_info['school_principal_name'])) {
                $old_info['syndicate_principal_name'] = $old_info['school_principal_name'];
                unset($old_info['school_principal_name']);
            }
            update_option('sm_syndicate_info', $old_info);
        }
    }

    private static function migrate_tables() {
        global $wpdb;
        $mappings = array(
            'sm_students' => 'sm_members'
        );
        foreach ($mappings as $old => $new) {
            $old_table = $wpdb->prefix . $old;
            $new_table = $wpdb->prefix . $new;
            if ($wpdb->get_var("SHOW TABLES LIKE '$old_table'") && !$wpdb->get_var("SHOW TABLES LIKE '$new_table'")) {
                $wpdb->query("RENAME TABLE $old_table TO $new_table");

                if ($new === 'sm_members') {
                    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $new_table LIKE 'student_code'");
                    if (!empty($column_exists)) {
                        $wpdb->query("ALTER TABLE $new_table CHANGE student_code member_code tinytext");
                    }
                }
            }
        }

        // Rename student_id to member_id and teacher_id to officer_id in other tables for data integrity
        $tables_to_fix = array('sm_records', 'sm_messages', 'sm_members');
        foreach ($tables_to_fix as $table) {
            $full_table = $wpdb->prefix . $table;
            if ($wpdb->get_var("SHOW TABLES LIKE '$full_table'")) {
                // Fix Student ID
                $col_student = $wpdb->get_results("SHOW COLUMNS FROM $full_table LIKE 'student_id'");
                if (!empty($col_student)) {
                    $wpdb->query("ALTER TABLE $full_table CHANGE student_id member_id mediumint(9)");
                }

                // Fix Teacher ID / Supervisor ID
                $col_teacher = $wpdb->get_results("SHOW COLUMNS FROM $full_table LIKE 'teacher_id'");
                if (!empty($col_teacher)) {
                    $wpdb->query("ALTER TABLE $full_table CHANGE teacher_id officer_id bigint(20)");
                }

                $col_supervisor = $wpdb->get_results("SHOW COLUMNS FROM $full_table LIKE 'supervisor_id'");
                if (!empty($col_supervisor)) {
                    $wpdb->query("ALTER TABLE $full_table CHANGE supervisor_id officer_id bigint(20)");
                }
            }
        }
    }

    private static function setup_roles() {
        // System Admin
        add_role('sm_system_admin', 'مدير النظام (النقابة)', array(
            'read' => true,
            'إدارة_النظام' => true,
            'إدارة_المستخدمين' => true,
            'إدارة_الأعضاء' => true,
            'إدارة_المخالفات' => true,
            'طباعة_التقارير' => true,
            'تسجيل_مخالفة' => true,
            'إدارة_أولياء_الأمور' => true
        ));

        // Syndicate Officer (Formerly Principal)
        add_role('sm_officer', 'مسؤول النقابة', array(
            'read' => true,
            'إدارة_الأعضاء' => true,
            'إدارة_المخالفات' => true,
            'طباعة_التقارير' => true,
            'تسجيل_مخالفة' => true,
            'إدارة_أولياء_الأمور' => true
        ));

        // Syndicate Member (Formerly Supervisor/Teacher/Coordinator)
        add_role('sm_syndicate_member', 'عضو النقابة', array(
            'read' => true,
            'تسجيل_مخالفة' => true,
            'إدارة_المخالفات' => true,
            'إدارة_الأعضاء' => true
        ));

        // Member (Formerly Student)
        add_role('sm_member', 'عضو', array(
            'read' => true
        ));

        // Parent
        add_role('sm_parent', 'ولي أمر', array(
            'read' => true
        ));

        self::migrate_user_roles();
    }

    private static function migrate_user_roles() {
        $role_migration = array(
            'school_admin'          => 'sm_officer',
            'sm_school_admin'       => 'sm_officer',
            'discipline_officer'    => 'sm_officer',
            'sm_principal'          => 'sm_officer',
            'sm_supervisor'         => 'sm_syndicate_member',
            'sm_teacher'            => 'sm_syndicate_member',
            'sm_coordinator'        => 'sm_syndicate_member',
            'sm_clinic'             => 'sm_officer'
        );

        foreach ($role_migration as $old => $new) {
            $users = get_users(array('role' => $old));
            foreach ($users as $user) {
                $user->remove_role($old);
                $user->add_role($new);
            }
        }

        remove_role('sm_clinic');
        remove_role('school_admin');
        remove_role('discipline_officer');
    }
}
