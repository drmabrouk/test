<?php

class SM_Activator {

    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Migration: Rename old tables if they exist
        self::migrate_tables();

        // Members Table (formerly Students)
        $table_name = $wpdb->prefix . 'sm_members';
        $sql = "CREATE TABLE $table_name (
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
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Records Table
        $table_name = $wpdb->prefix . 'sm_records';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            member_id mediumint(9) NOT NULL,
            officer_id bigint(20),
            type tinytext NOT NULL,
            severity tinytext,
            degree tinytext,
            points int DEFAULT 0,
            details text,
            action_taken text,
            status tinytext DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

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
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Confiscated Items Table
        $table_name = $wpdb->prefix . 'sm_confiscated_items';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            member_id mediumint(9) NOT NULL,
            item_name tinytext NOT NULL,
            officer_id bigint(20),
            status tinytext DEFAULT 'confiscated',
            confiscated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Logs Table
        $table_name = $wpdb->prefix . 'sm_logs';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20),
            action tinytext NOT NULL,
            details text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Attendance Table
        $table_name = $wpdb->prefix . 'sm_attendance';
        $sql .= "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            member_id mediumint(9) NOT NULL,
            date date NOT NULL,
            status enum('present', 'absent', 'late', 'excused') NOT NULL,
            officer_id bigint(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY member_date (member_id, date)
        ) $charset_collate;";

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
            PRIMARY KEY (id)
        ) $charset_collate;";

        // Clinic Referrals Table
        $table_name = $wpdb->prefix . 'sm_clinic';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            member_id mediumint(9) NOT NULL,
            referrer_id bigint(20) NOT NULL,
            health_condition text,
            action_taken text,
            arrival_confirmed tinyint(1) DEFAULT 0,
            arrival_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        // Grades Table
        $table_name = $wpdb->prefix . 'sm_grades';
        $sql .= "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            member_id mediumint(9) NOT NULL,
            subject tinytext NOT NULL,
            term tinytext NOT NULL,
            grade_val tinytext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        // Timetable Table
        $table_name = $wpdb->prefix . 'sm_timetable';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            class_name tinytext NOT NULL,
            section tinytext NOT NULL,
            day enum('sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat') NOT NULL,
            period int NOT NULL,
            subject_id int,
            officer_id bigint(20),
            PRIMARY KEY (id)
        ) $charset_collate;";

        // Subjects Table
        $table_name = $wpdb->prefix . 'sm_subjects';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name tinytext NOT NULL,
            grade_ids text,
            PRIMARY KEY (id)
        ) $charset_collate;";

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
            PRIMARY KEY (id)
        ) $charset_collate;";

        // Survey Responses Table
        $table_name = $wpdb->prefix . 'sm_survey_responses';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            survey_id mediumint(9) NOT NULL,
            user_id bigint(20) NOT NULL,
            responses text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

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

                // If it's the students table, rename column student_code to member_code if it exists
                if ($new === 'sm_members') {
                    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $new_table LIKE 'student_code'");
                    if (!empty($column_exists)) {
                        $wpdb->query("ALTER TABLE $new_table CHANGE student_code member_code tinytext");
                    }
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
            'إدارة_أولياء_الأمور' => true,
            'إدارة_العيادة' => true
        ));

        // Syndicate Officer (Formerly Principal)
        add_role('sm_officer', 'مسؤول النقابة', array(
            'read' => true,
            'إدارة_الأعضاء' => true,
            'إدارة_المخالفات' => true,
            'طباعة_التقارير' => true,
            'تسجيل_مخالفة' => true,
            'إدارة_أولياء_الأمور' => true,
            'إدارة_العيادة' => true,
            'manage_grades' => true,
            'مراجعة_التحضير' => true
        ));

        // Syndicate Member (Formerly Supervisor/Teacher/Coordinator)
        add_role('sm_syndicate_member', 'عضو النقابة', array(
            'read' => true,
            'تسجيل_مخالفة' => true,
            'إدارة_المخالفات' => true, // Limited to their assigned members in code
            'manage_grades' => true,
            'إدارة_الأعضاء' => true // Limited view
        ));

        // Member (Formerly Student)
        add_role('sm_member', 'عضو', array(
            'read' => true
        ));

        // Parent
        add_role('sm_parent', 'ولي أمر', array(
            'read' => true
        ));

        // Clinic
        add_role('sm_clinic', 'العيادة', array(
            'read' => true,
            'إدارة_العيادة' => true
        ));

        // Migrate users from old roles to new roles
        self::migrate_user_roles();
    }

    private static function migrate_user_roles() {
        $legacy_roles = array(
            'school_admin', 'discipline_officer', 'sm_school_admin',
            'sm_principal', 'sm_supervisor', 'sm_teacher', 'sm_coordinator'
        );
        $role_migration = array(
            'school_admin'          => 'sm_officer',
            'sm_school_admin'       => 'sm_officer',
            'discipline_officer'    => 'sm_officer',
            'sm_principal'          => 'sm_officer',
            'sm_supervisor'         => 'sm_syndicate_member',
            'sm_teacher'            => 'sm_syndicate_member',
            'sm_coordinator'        => 'sm_syndicate_member'
        );

        foreach ($role_migration as $old => $new) {
            $users = get_users(array('role' => $old));
            foreach ($users as $user) {
                $user->remove_role($old);
                $user->add_role($new);
            }
        }
    }
}
