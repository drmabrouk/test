<?php

class SM_Activation {
    private static $fixed_code = '10111996';
    private static $option_name = 'sm_activation_data_secure';
    private static $secret_key = 'IRS_SYNDICATE_SECURE_KEY_1996'; // Internal salt
    // Hashed activation password: 10111996
    private static $activation_pass_hash = '$2y$10$.Atnihww9QRkgPxR8luOJuUHQylXHDrKoYakbsqI..sxwk4Kv7DI2';

    /**
     * Encrypts data for storage or transport.
     */
    private static function encrypt($data) {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', self::$secret_key, 0, $iv);
        return base64_encode($encrypted . '::' . $iv);
    }

    /**
     * Decrypts data.
     */
    private static function decrypt($data) {
        if (empty($data)) return false;
        $parts = explode('::', base64_decode($data), 2);
        if (count($parts) !== 2) return false;
        return openssl_decrypt($parts[0], 'aes-256-cbc', self::$secret_key, 0, $parts[1]);
    }

    /**
     * Checks if the plugin is currently active.
     */
    public static function is_active() {
        $data = self::get_activation_data();
        if (!$data) return false;

        $current_time = time();
        $start_time = strtotime($data['start_date']);
        $end_time = strtotime($data['end_date']);

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
     * Verifies a serial provided by the user.
     * Expected format: YYYYMMDD10111996 (Plain numeric string)
     */
    public static function verify_serial($serial) {
        if (empty($serial)) return false;

        // Check length and fixed code suffix
        if (strlen($serial) !== 16) return false;
        if (substr($serial, 8) !== self::$fixed_code) return false;

        $date_str = substr($serial, 0, 8);
        $year = substr($date_str, 0, 4);
        $month = substr($date_str, 4, 2);
        $day = substr($date_str, 6, 2);

        if (!checkdate((int)$month, (int)$day, (int)$year)) return false;

        return $year . '-' . $month . '-' . $day;
    }

    /**
     * Activates the plugin using an encrypted serial.
     */
    public static function activate($encrypted_serial, $cost = 0) {
        $start_date = self::verify_serial($encrypted_serial);
        if (!$start_date) return new WP_Error('invalid_serial', 'كود التفعيل غير صحيح أو تالف.');

        $end_date = date('Y-m-d', strtotime($start_date . ' +1 year'));

        $data = [
            'start_date' => $start_date,
            'end_date' => $end_date,
            'cost' => (float)$cost,
            'activated_at' => current_time('mysql'),
            'serial_hash' => wp_hash($encrypted_serial)
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
     * Sends a 15-digit OTP to mabrouk@dr.com
     */
    public static function send_activation_otp() {
        $otp = '';
        for ($i = 0; $i < 15; $i++) {
            $otp .= mt_rand(0, 9);
        }

        set_transient('sm_activation_otp_' . get_current_user_id(), $otp, 600); // 10 min

        $to = 'mabrouk@dr.com';
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

    /**
     * Helper to generate a serial (For developer use).
     */
    public static function generate_serial($date_yyyy_mm_dd) {
        $plain = str_replace('-', '', $date_yyyy_mm_dd) . self::$fixed_code;
        return self::encrypt($plain);
    }
}
