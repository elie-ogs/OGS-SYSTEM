<?php
if (!defined('ABSPATH')) exit;

class OLM_Scheduler {

    public static function init() {
        add_action('olm_daily_batch', [__CLASS__, 'run_daily_batch']);
    }
}
OLM_Scheduler::init();

class OLM_Cycle_Engine {

    public static function run_daily_batch() {
        global $wpdb;
        $prefix = OLM_PREFIX;
        $today = current_time('Y-m-d'); // site-local date

        // Select active loans due for cycle roll
        $loans = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$prefix}loans WHERE status='Active' AND next_interest_date IS NOT NULL AND next_interest_date <= %s",
            $today
        ), ARRAY_A);

        foreach ($loans as $loan) {
            self::process_loan_cycle($loan);
        }
    }

    private static function process_loan_cycle($loan) {
        global $wpdb;
        $prefix = OLM_PREFIX;

        // Cycle dates
        $cycle_start = $loan['next_interest_date'];
        $cycle_next  = date('Y-m-d', strtotime("+1 month", strtotime($cycle_start)));
        // Accurate days in cycle
        $cycle_days  = (new DateTime($cycle_start))->diff(new DateTime($cycle_next))->days;

        // Buckets before accruals
        $b = OLM_Models::compute_buckets($loan);
        $outstanding = $b['outstanding'];

        // Monthly interest on full outstanding
        $monthly_interest = round($outstanding * ((float)$loan['interest_rate'] / 100.0), 2);
        $newAI = round(((float)$loan['accrued_interest']) + $monthly_interest, 2);

        // Penalty decision (after grace cutoff, once per cycle, and only if Min_Payment_Met==false)
        $grace_cutoff = date('Y-m-d', strtotime($cycle_next . ' +' . (int)$loan['grace_days'] . ' days'));
        $today = current_time('Y-m-d');
        $should_penalize = false;
        if ($today > $grace_cutoff && intval($loan['min_payment_met']) === 0 && $loan['last_penalty_cycle_start'] !== $cycle_start) {
            $should_penalize = true;
        }

        $penalty_add = 0.00;
        $newLF = (float)$loan['late_fees_accrued'];
        $newLPS = $loan['last_penalty_cycle_start'];
        if ($should_penalize) {
            $min_due = OLM_Models::min_due($loan, $outstanding);
            $penalty_add = round($min_due * ((float)$loan['penalty_rate'] / 100.0), 2);
            $newLF = round($newLF + $penalty_add, 2);
            $newLPS = $cycle_start;
        }

        // Recompute outstanding after accruals
        $interest_left_after = max(0, $newAI - (float)$loan['total_interest_paid']);
        $fees_left_after     = max(0, $newLF - (float)$loan['total_fees_paid']);
        $principal_left      = max(0, (float)$loan['principal_amount'] - (float)$loan['total_principal_paid']);
        $out_after = round($principal_left + $interest_left_after + $fees_left_after, 2);

        // Create Monthly Charge
        $wpdb->insert($prefix.'monthly_charges', [
            'loan_id' => $loan['id'],
            'cycle_start' => $cycle_start,
            'cycle_end' => $cycle_next,
            'interest_amount' => $monthly_interest,
            'penalty_amount'  => $penalty_add,
            'total_charge'    => round($monthly_interest + $penalty_add, 2),
            'amount_paid'     => 0.00,
            'open_balance'    => round($monthly_interest + $penalty_add, 2),
            'status'          => 'Open',
            'amount_applied_this_cycle' => 0.00,
            'created_at' => current_time('mysql')
        ]);

        // Roll dates & flags on loan
        $payment_due_next = $loan['payment_due_date'] ? date('Y-m-d', strtotime("+1 month", strtotime($loan['payment_due_date']))) : null;

        $wpdb->update($prefix.'loans', [
            'accrued_interest' => $newAI,
            'late_fees_accrued' => $newLF,
            'interest_cycle_days' => $cycle_days,
            'next_interest_date' => $cycle_next,
            'payment_due_date' => $payment_due_next,
            'min_payment_met' => 0,
            'last_penalty_cycle_start' => $newLPS,
            'outstanding_balance' => $out_after,
            'updated_at' => current_time('mysql')
        ], ['id' => $loan['id']]);
    }
}
