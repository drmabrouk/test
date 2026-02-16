<?php

class SM_Finance {

    public static function calculate_member_dues($member_id) {
        $member = SM_DB::get_member_by_id($member_id);
        if (!$member) return array('total_owed' => 0, 'total_paid' => 0, 'balance' => 0, 'breakdown' => []);

        $settings = SM_Settings::get_finance_settings();
        $current_year = (int)date('Y');
        $current_date = date('Y-m-d');

        $total_owed = 0;
        $breakdown = [];

        // 1. Membership Dues
        // Registration date determines the first year
        $start_year = $member->membership_start_date ? (int)date('Y', strtotime($member->membership_start_date)) : $current_year;
        $last_paid_year = (int)$member->last_paid_membership_year;

        // If it's a new member (never paid), they owe registration fee for the start year
        // Membership is annual. If they registered in 2023, they owe for 2023.
        for ($year = $start_year; $year <= $current_year; $year++) {
            if ($year > $last_paid_year) {
                $base_fee = ($year === $start_year && $last_paid_year == 0) ? (float)$settings['membership_new'] : (float)$settings['membership_renewal'];
                $penalty = 0;

                // Penalty starts April 1st of the year FOLLOWING the membership year
                // BUT for the current year, if we are in Jan-Mar, it's a grace period (no penalty).
                // Actually, the renewal is for the NEXT year usually? No, "after December 31... during January, February, March... no fine... fine added starting April 1".
                // This implies the renewal for year X should be done by Dec 31 of X-1 or within grace period of X.

                // Let's assume:
                // Membership 2024 is due by Dec 31, 2023.
                // Grace period: Jan, Feb, Mar 2024.
                // Penalty starts: April 1, 2024.

                // If current year is 2024, and we are calculating for 2024:
                // If current_date >= 2024-04-01, add penalty.

                $penalty_date = $year . '-04-01';
                if ($current_date >= $penalty_date) {
                    $penalty += (float)$settings['membership_penalty'];

                    // Cumulative penalty for subsequent years of delay
                    // If it's 2025 and they still haven't paid for 2024:
                    // April 1, 2025 adds another penalty.
                    $subsequent_year = $year + 1;
                    while ($subsequent_year <= $current_year) {
                        if ($current_date >= $subsequent_year . '-04-01') {
                            $penalty += (float)$settings['membership_penalty'];
                        }
                        $subsequent_year++;
                    }
                }

                $year_total = $base_fee + $penalty;
                $total_owed += $year_total;
                $breakdown[] = [
                    'item' => ($year === $start_year) ? "رسوم انضمام وعضوية لعام $year" : "تجديد عضوية لعام $year",
                    'amount' => $base_fee,
                    'penalty' => $penalty,
                    'total' => $year_total
                ];
            }
        }

        // 2. Professional Practice License Dues
        // Only if they already have a license record
        if (!empty($member->license_number) && !empty($member->license_expiration_date)) {
            $expiry = $member->license_expiration_date;
            $has_paid_first = ((int)$member->last_paid_license_year > 0);

            if ($current_date > $expiry || !$has_paid_first) {
                $base_fee = $has_paid_first ? (float)$settings['license_renewal'] : (float)$settings['license_new'];
                $penalty = 0;

                // Penalty starts AFTER ONE YEAR from expiration
                $penalty_start_date = date('Y-m-d', strtotime($expiry . ' +1 year'));

                if ($current_date >= $penalty_start_date) {
                    $d1 = new DateTime($expiry);
                    $d2 = new DateTime($current_date);
                    $diff = $d1->diff($d2);
                    $years_delayed = $diff->y;

                    if ($years_delayed >= 1) {
                        $penalty = $years_delayed * (float)$settings['license_penalty'];
                    }
                }

                $license_total = $base_fee + $penalty;
                $total_owed += $license_total;
                $breakdown[] = [
                    'item' => "تجديد تصريح مزاولة المهنة",
                    'amount' => $base_fee,
                    'penalty' => $penalty,
                    'total' => $license_total
                ];
            }
        }

        // 3. Facility License Dues - Automatic renewal calculation REMOVED as requested.
        // It should only be applied if explicitly requested or handled via another mechanism.

        // Subtract existing payments from total
        $total_paid = self::get_total_paid($member_id);
        $final_balance = $total_owed - $total_paid;

        return [
            'total_owed' => (float)$total_owed,
            'total_paid' => (float)$total_paid,
            'balance' => (float)$final_balance,
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

        // Sequential Invoice Number: YYYY0000X
        $current_year = date('Y');
        $last_seq = (int)get_option('sm_invoice_sequence_' . $current_year, 0);
        $new_seq = $last_seq + 1;
        update_option('sm_invoice_sequence_' . $current_year, $new_seq);

        $digital_code = $current_year . str_pad($new_seq, 5, '0', STR_PAD_LEFT);

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

        $args = array('limit' => -1);
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
