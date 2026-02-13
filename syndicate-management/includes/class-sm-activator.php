<?php

class SM_Activator {

    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Migration: Rename old tables if they exist
        self::migrate_tables();

        $sql = "";

        // Members Table (formerly Students)
        $table_name = $wpdb->prefix . 'sm_members';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name tinytext NOT NULL,
            email tinytext,
            member_code tinytext,
            photo_url text,
            class_name tinytext,
            section tinytext,
            parent_user_id bigint(20),
            officer_id bigint(20),
            guardian_phone tinytext,
            nationality tinytext,
            registration_date date,
            sort_order int DEFAULT 0,
            PRIMARY KEY  (id),
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

        // Assignments / Lesson Plans Table
        $table_name = $wpdb->prefix . 'sm_assignments';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            sender_id bigint(20) NOT NULL,
            receiver_id bigint(20),
            title tinytext NOT NULL,
            description text,
            file_url text,
            type enum('assignment', 'lesson_plan') DEFAULT 'assignment',
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY sender_id (sender_id),
            KEY receiver_id (receiver_id)
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

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        self::setup_roles();
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
            'إدارة_أولياء_الأمور' => true,
            'مراجعة_التحضير' => true
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
