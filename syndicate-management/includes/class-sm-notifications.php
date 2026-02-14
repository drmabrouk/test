<?php

class SM_Notifications {

    public static function send_group_notification($role, $subject, $message) {
        $users = get_users(array('role' => $role));
        $emails = array_map(function($u) { return $u->user_email; }, $users);
        if (!empty($emails)) {
            wp_mail($emails, $subject, $message);
        }
    }

}
