<?php

class SM_Activation {
    private static $option_name = 'sm_activation_data_secure';
    private static $secret_key = 'IRS_SYNDICATE_SECURE_KEY_1996'; // Internal salt
    // Hashed activation password: 691101
    private static $activation_pass_hash = '$2y$10$VVoYf2rTaCTY/VQ5dAA.YeE92hSN7.HPeS7UUMhbCHpiqu/.TbSly';

    /**
     * Encrypts data for storage or transport.
     */
    private static function encrypt($data) {
        if (!function_exists('openssl_encrypt')) return base64_encode($data);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', self::$secret_key, 0, $iv);
        return base64_encode($encrypted . '::' . $iv);
    }

    /**
     * Decrypts data.
     */
    private static function decrypt($data) {
        if (empty($data)) return false;
        if (!function_exists('openssl_decrypt')) return base64_decode($data);
        $parts = explode('::', base64_decode($data), 2);
        if (count($parts) !== 2) return $parts[0]; // Fallback if no IV
        return openssl_decrypt($parts[0], 'aes-256-cbc', self::$secret_key, 0, $parts[1]);
    }

    /**
     * Checks if the plugin is currently active.
     */
    public static function is_active() {
        $data = self::get_activation_data();
        if (!$data) return false;

        $current_time = current_time('timestamp');
        $start_time = strtotime($data['start_date']);
        $end_time = strtotime($data['end_date']);

        if (!$start_time || !$end_time) return false;

        return ($current_time >= $start_time && $current_time <= $end_time);
    }

    /**
     * Retrieves decrypted activation data from DB.
     */
    public static function get_activation_data() {
        $secure_data = get_option(self::$option_name);
        if (!$secure_data) return false;

        $decrypted = self::decrypt($secure_data);
        if (!$decrypted) return false;

        return json_decode($decrypted, true);
    }


    /**
     * Activates the plugin for a specified duration.
     */
    public static function activate($duration_years = 1, $cost = 0) {
        $start_date = current_time('Y-m-d');
        $end_date = date('Y-m-d', strtotime($start_date . " + $duration_years year"));

        $data = [
            'start_date' => $start_date,
            'end_date' => $end_date,
            'cost' => (float)$cost,
            'activated_at' => current_time('mysql'),
            'duration' => $duration_years
        ];

        $json = json_encode($data);
        $encrypted = self::encrypt($json);

        // Anti-tamper Checksum
        update_option('sm_activation_checksum', wp_hash($json . self::$secret_key));

        // Update Activation Log
        $logs = self::get_activation_logs();
        $logs[] = $data;
        update_option('sm_activation_logs_secure', self::encrypt(json_encode($logs)));

        // Update Current Activation
        return update_option(self::$option_name, $encrypted);
    }

    /**
     * Verifies the special developer password.
     */
    public static function verify_activation_password($password) {
        return password_verify($password, self::$activation_pass_hash);
    }

    /**
     * Sends a 15-digit OTP to website.developer@email.com
     */
    public static function send_activation_otp() {
        $otp = '';
        for ($i = 0; $i < 15; $i++) {
            $otp .= mt_rand(0, 9);
        }

        set_transient('sm_activation_otp_' . get_current_user_id(), $otp, 600); // 10 min

        $to = 'website.developer@email.com';
        $subject = 'System Activation OTP - Syndicate Management';
        $message = "Your 15-digit activation OTP is: " . $otp . "\r\n\r\n" . "This code is valid for 10 minutes.";

        return wp_mail($to, $subject, $message);
    }

    /**
     * Verifies the 15-digit OTP.
     */
    public static function verify_activation_otp($otp) {
        $saved = get_transient('sm_activation_otp_' . get_current_user_id());
        return ($saved && $saved === $otp);
    }

    /**
     * Retrieves the activation logs.
     */
    public static function get_activation_logs() {
        $secure_logs = get_option('sm_activation_logs_secure');
        if (!$secure_logs) return [];

        $decrypted = self::decrypt($secure_logs);
        return $decrypted ? json_decode($decrypted, true) : [];
    }

}
