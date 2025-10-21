<?php
if (!defined('ABSPATH')) exit;

class OLM_Payments {

    public static function apply_payment($payment_id) {
        global $wpdb;
        $prefix = OLM_PREFIX;

        // Fetch payment
        $payment = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$prefix}payments WHERE id=%d", $payment_id), ARRAY_A);
        if (!$payment) return false;

        $loan = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$prefix}loans WHERE id=%d", $payment['loan_id']), ARRAY_A);
        if (!$loan) return false;

        $amt = (float)$payment['amount_paid'];
        $toI = 0.0; $toF = 0.0; $toP = 0.0;

        // Fetch open charges oldest first
        $charges = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$prefix}monthly_charges WHERE loan_id=%d AND open_balance > 0 ORDER BY cycle_start ASC",
            $loan['id']
        ), ARRAY_A);

        foreach ($charges as $c) {
            if ($amt <= 0) break;

            $interest_resid = max(0.0, (float)$c['interest_amount'] - self::paid_to_interest($c));
            if ($interest_resid > 0 && $amt > 0) {
                $use = min($amt, $interest_resid);
                $amt -= $use;
                $toI += $use;
                $c['amount_paid'] += $use;
            }

            $penalty_resid = max(0.0, (float)$c['penalty_amount'] - self::paid_to_penalty($c));
            if ($penalty_resid > 0 && $amt > 0) {
                $use = min($amt, $penalty_resid);
                $amt -= $use;
                $toF += $use;
                $c['amount_paid'] += $use;
            }

            $c['open_balance'] = round(((float)$c['total_charge']) - ((float)$c['amount_paid']), 2);
            $c['status'] = ($c['open_balance'] <= 0.00001 ? 'Paid' : 'Partial');

            // If this is the latest charge, track amount applied this cycle
            $latest_cycle = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(cycle_start) FROM {$prefix}monthly_charges WHERE loan_id=%d",
                $loan['id']
            ));
            if ($latest_cycle && $c['cycle_start'] === $latest_cycle) {
                $delta = 0.0;
                // We can approximate delta as toI/toF just applied to this c; to keep it simple we recompute amounts applied to this cycle by comparing before/after
                // For now, increment by the portion of payment applied to this charge in this loop iteration.
                $applied_now = 0.0; // Hard to track precisely without sub-splits; but we can infer:
                // Use difference from previous values: we don't have them. Instead set equal to min(payment remaining before, c['open_balance'] + applied). 
                // Simpler: add any amount that went to this c (interest + fees) in this iteration.
                // We tracked toI/toF cumulative; not split per charge. We'll compute applied_now using current $c['total_charge'] - $c['open_balance'] - prev_paid.
                // To avoid complexity, skip increment here; we will compute minimum due check based on latest charge total_paid relative to min_due via Amount_Applied_This_Cycle field.
                // We'll compute applied_to_latest afterwards outside the loop.
            }

            // Persist charge
            $wpdb->update($prefix.'monthly_charges', [
                'amount_paid' => round($c['amount_paid'], 2),
                'open_balance' => round($c['open_balance'], 2),
                'status' => $c['status'],
                'updated_at' => current_time('mysql')
            ], ['id' => $c['id']]);
        }

        // Remaining goes to principal
        if ($amt > 0) {
            $toP += $amt;
            $amt = 0.0;
        }

        // Update loan totals & outstanding
        $loan['total_interest_paid']  = round(((float)$loan['total_interest_paid']) + $toI, 2);
        $loan['total_fees_paid']      = round(((float)$loan['total_fees_paid']) + $toF, 2);
        $loan['total_principal_paid'] = round(((float)$loan['total_principal_paid']) + $toP, 2);
        $loan['total_paid']           = round(((float)$loan['total_paid']) + (float)$payment['amount_paid'], 2);

        // Recompute outstanding
        $b = OLM_Models::compute_buckets($loan);
        $loan['outstanding_balance'] = round($b['outstanding'], 2);

        // Mark Min_Payment_Met if applicable (before grace cutoff and applied to latest charge >= minimum due)
        $latest = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}monthly_charges WHERE loan_id=%d ORDER BY cycle_start DESC LIMIT 1",
            $loan['id']
        ), ARRAY_A);

        if ($latest) {
            $cycle_next = $latest['cycle_end'];
            $grace_cutoff = date('Y-m-d', strtotime($cycle_next . ' +' . (int)$loan['grace_days'] . ' days'));
            $today = current_time('Y-m-d');

            if ($today <= $grace_cutoff) {
                // Compute how much has been applied to the latest charge so far: total_charge - open_balance
                $applied_to_latest = round(((float)$latest['total_charge']) - ((float)$latest['open_balance']), 2);
                $min_due = OLM_Models::min_due($loan, $loan['outstanding_balance']);
                if ($applied_to_latest + 0.00001 >= $min_due) {
                    $loan['min_payment_met'] = 1;
                }
            }
        }

        // Persist loan
        $wpdb->update($prefix.'loans', [
            'total_interest_paid' => $loan['total_interest_paid'],
            'total_fees_paid' => $loan['total_fees_paid'],
            'total_principal_paid' => $loan['total_principal_paid'],
            'total_paid' => $loan['total_paid'],
            'outstanding_balance' => $loan['outstanding_balance'],
            'min_payment_met' => isset($loan['min_payment_met']) ? $loan['min_payment_met'] : $loan['min_payment_met'],
            'updated_at' => current_time('mysql')
        ], ['id' => $loan['id']]);

        // Update payment splits
        $wpdb->update($prefix.'payments', [
            'to_interest' => round($toI, 2),
            'to_fees'     => round($toF, 2),
            'to_principal'=> round($toP, 2),
            'updated_at'  => current_time('mysql')
        ], ['id' => $payment['id']]);

        return true;
    }

    private static function paid_to_interest($charge) {
        // We don't track split per charge; approximate as min(amount_paid, interest_amount) if penalty exists; otherwise all to interest up to interest_amount then penalty.
        $paid = (float)$charge['amount_paid'];
        $interest_cap = (float)$charge['interest_amount'];
        return min($paid, $interest_cap);
    }

    private static function paid_to_penalty($charge) {
        $paid = (float)$charge['amount_paid'];
        $interest_cap = (float)$charge['interest_amount'];
        $penalty_cap  = (float)$charge['penalty_amount'];
        $remaining_after_interest = max(0.0, $paid - $interest_cap);
        return min($remaining_after_interest, $penalty_cap);
    }
}
