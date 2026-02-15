<?php

class SM_Finance {

    public static function calculate_member_dues($member_id) {
        $member = SM_DB::get_member_by_id($member_id);
        if (!$member) return array('total' => 0, 'breakdown' => []);

        $settings = SM_Settings::get_finance_settings();
        $current_year = (int)date('Y');
        $current_date = date('Y-m-d');

        $total_owed = 0;
        $breakdown = [];

        // 1. Membership Dues
        $start_year = $member->membership_start_date ? (int)date('Y', strtotime($member->membership_start_date)) : $current_year;
        $last_paid_year = (int)$member->last_paid_membership_year;

        for ($year = $start_year; $year <= $current_year; $year++) {
            if ($year > $last_paid_year) {
                // Initial registration fee is 480, renewal is 280
                $base_fee = ($year === $start_year) ? 480 : 280;
                $penalty = 0;

                // Penalty starts April 1st of the year
                $penalty_date = $year . '-04-01';
                if ($current_date >= $penalty_date) {
                    // Initial penalty for the year
                    $penalty += 50;

                    // Additional 50 for each subsequent year of delay
                    if ($current_year > $year) {
                        // If current date is >= April 1st of the current year, add penalties for previous years
                        $years_over = $current_year - $year;
                        // The user says "another 50 EGP late fee is added on April 1 each subsequent year"
                        // So for 2023 due, if it's April 1 2024, it gets another 50.
                        if ($current_date >= $current_year . '-04-01') {
                             $penalty += $years_over * 50;
                        } else {
                             $penalty += ($years_over - 1) * 50;
                        }
                    }
                }

                $year_total = $base_fee + $penalty;
                $total_owed += $year_total;
                $breakdown[] = [
                    'item' => "اشتراك عضوية لعام $year",
                    'amount' => $base_fee,
                    'penalty' => $penalty,
                    'total' => $year_total
                ];
            }
        }

        // 2. Professional Practice License Dues
        if (!empty($member->license_expiration_date)) {
            $expiry = $member->license_expiration_date;
            if ($current_date > $expiry) {
                // Check if it's new or renewal (usually renewal if it expired)
                $base_fee = $settings['license_renewal'];

                // Penalty starts 1 month after expiry
                $penalty_start = date('Y-m-d', strtotime($expiry . ' +1 month'));
                $penalty = 0;

                if ($current_date >= $penalty_start) {
                    $penalty += $settings['license_penalty'];

                    // Extra penalty for each full year that has passed since expiration
                    $d1 = new DateTime($expiry);
                    $d2 = new DateTime($current_date);
                    $diff = $d1->diff($d2);
                    if ($diff->y > 0) {
                        $penalty += $diff->y * $settings['license_penalty'];
                    }
                }

                $license_total = $base_fee + $penalty;
                $total_owed += $license_total;
                $breakdown[] = [
                    'item' => "تجديد ترخيص مزاولة المهنة",
                    'amount' => $base_fee,
                    'penalty' => $penalty,
                    'total' => $license_total
                ];
            }
        }

        // 3. Facility License Dues
        if (!empty($member->facility_category)) {
            $cat = $member->facility_category;
            $fee = 0;
            switch($cat) {
                case 'A': $fee = $settings['facility_a']; break;
                case 'B': $fee = $settings['facility_b']; break;
                case 'C': $fee = $settings['facility_c']; break;
            }

            // Check if facility license is expired
            if (!empty($member->facility_license_expiration_date) && $current_date > $member->facility_license_expiration_date) {
                $total_owed += $fee;
                $breakdown[] = [
                    'item' => "رسوم ترخيص منشأة (فئة $cat)",
                    'amount' => $fee,
                    'penalty' => 0,
                    'total' => $fee
                ];
            }
        }

        // Subtract existing payments from total
        $total_paid = self::get_total_paid($member_id);
        $final_balance = $total_owed - $total_paid;

        return [
            'total_owed' => $total_owed,
            'total_paid' => $total_paid,
            'balance' => $final_balance,
            'breakdown' => $breakdown
        ];
    }

    public static function get_total_paid($member_id) {
        global $wpdb;
        $sum = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM {$wpdb->prefix}sm_payments WHERE member_id = %d",
            $member_id
        ));
        return (float)$sum;
    }

    public static function get_payment_history($member_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sm_payments WHERE member_id = %d ORDER BY payment_date DESC",
            $member_id
        ));
    }

    public static function record_payment($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'sm_payments';
        $current_user_id = get_current_user_id();

        $digital_code = 'DINV-' . strtoupper(wp_generate_password(8, false)) . '-' . time();
        $paper_code = sanitize_text_field($data['paper_invoice_code'] ?? '');
        $details_ar = sanitize_text_field($data['details_ar'] ?? '');

        $insert = $wpdb->insert($table, [
            'member_id' => intval($data['member_id']),
            'amount' => floatval($data['amount']),
            'payment_type' => sanitize_text_field($data['payment_type']),
            'payment_date' => sanitize_text_field($data['payment_date']),
            'target_year' => isset($data['target_year']) ? intval($data['target_year']) : null,
            'digital_invoice_code' => $digital_code,
            'paper_invoice_code' => $paper_code,
            'details_ar' => $details_ar,
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'created_by' => $current_user_id,
            'created_at' => current_time('mysql')
        ]);

        if ($insert) {
            $payment_id = $wpdb->insert_id;
            $member = SM_DB::get_member_by_id($data['member_id']);

            if ($data['payment_type'] === 'membership' && !empty($data['target_year'])) {
                // Update member's last paid year if this payment is for a later year
                if ($member && intval($data['target_year']) > intval($member->last_paid_membership_year)) {
                    SM_DB::update_member($member->id, ['last_paid_membership_year' => intval($data['target_year'])]);
                }
            }

            if ($data['payment_type'] === 'license' && !empty($data['target_year'])) {
                if ($member && intval($data['target_year']) > intval($member->last_paid_license_year)) {
                    SM_DB::update_member($member->id, ['last_paid_license_year' => intval($data['target_year'])]);
                }
            }

            // Log the financial transaction in Arabic as requested
            $log_details = "تحصيل مبلغ " . $data['amount'] . " ج.م مقابل " . $details_ar . " للعضو: " . $member->name;
            SM_Logger::log('عملية مالية', $log_details);

            // Trigger Invoice Delivery (Email & Account)
            self::deliver_invoice($payment_id);
        }

        return $insert;
    }

    public static function deliver_invoice($payment_id) {
        global $wpdb;
        $payment = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_payments WHERE id = %d", $payment_id));
        if (!$payment) return;

        $member = SM_DB::get_member_by_id($payment->member_id);
        if (!$member || empty($member->email)) return;

        $syndicate = SM_Settings::get_syndicate_info();
        $invoice_url = admin_url('admin-ajax.php?action=sm_print_invoice&payment_id=' . $payment_id);

        $subject = "فاتورة سداد إلكترونية - " . $syndicate['syndicate_name'];
        $message = "عزيزي العضو " . $member->name . ",\n\n";
        $message .= "تم استلام مبلغ " . $payment->amount . " ج.م بنجاح.\n";
        $message .= "نوع العملية: " . $payment->payment_type . "\n";
        $message .= "يمكنك استعراض وتحميل الفاتورة الرسمية من الرابط التالي:\n";
        $message .= $invoice_url . "\n\n";
        $message .= "شكراً لتعاونكم.\n";
        $message .= $syndicate['syndicate_name'];

        wp_mail($member->email, $subject, $message);
    }

    public static function get_member_status($member_id) {
        $member = SM_DB::get_member_by_id($member_id);
        if (!$member) return 'unknown';

        $current_year = (int)date('Y');
        $current_date = date('Y-m-d');
        $last_paid = (int)$member->last_paid_membership_year;

        if ($last_paid >= $current_year) {
            return 'نشط (مسدد لعام ' . $current_year . ')';
        }

        if ($current_date <= $current_year . '-03-31') {
            return 'في فترة السماح (يجب التجديد لعام ' . $current_year . ')';
        }

        return 'منتهي (متأخر عن سداد عام ' . $current_year . ')';
    }

    public static function get_financial_stats() {
        global $wpdb;
        $user = wp_get_current_user();
        $is_syndicate_admin = in_array('sm_syndicate_admin', (array)$user->roles);
        $my_gov = get_user_meta($user->ID, 'sm_governorate', true);

        $args = array();
        $members = SM_DB::get_members($args);

        $total_owed = 0;
        $total_paid = 0;
        $total_penalty = 0;

        foreach ($members as $member) {
            $dues = self::calculate_member_dues($member->id);
            $total_owed += $dues['total_owed'];
            $total_paid += $dues['total_paid'];

            foreach ($dues['breakdown'] as $item) {
                $total_penalty += $item['penalty'];
            }
        }

        return [
            'total_owed' => $total_owed,
            'total_paid' => $total_paid,
            'total_balance' => $total_owed - $total_paid,
            'total_penalty' => $total_penalty
        ];
    }
}
