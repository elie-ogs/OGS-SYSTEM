<?php
if (!defined('ABSPATH')) exit;

class OLM_Models {
    public static function maybe_install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $prefix  = OLM_PREFIX;

        // Borrowers
        $sql1 = "CREATE TABLE {$prefix}borrowers (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            borrower_name VARCHAR(190) NOT NULL,
            books_customer_id VARCHAR(64) NULL,
            email VARCHAR(190) NULL,
            phone VARCHAR(64) NULL,
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            PRIMARY KEY (id)
        ) {$charset};";

        // Loans
        $sql2 = "CREATE TABLE {$prefix}loans (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            loan_id BIGINT UNSIGNED NOT NULL,
            borrower_id BIGINT UNSIGNED NOT NULL,
            status ENUM('Active','Closed','ChargedOff') NOT NULL DEFAULT 'Active',

            principal_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
            interest_rate DECIMAL(9,4) NOT NULL DEFAULT 0.0000, -- percent per month
            loan_term_months INT NULL,
            start_date DATE NOT NULL,
            end_date DATE NULL,

            payment_frequency ENUM('Monthly') NOT NULL DEFAULT 'Monthly',
            payment_due_date DATE NULL,
            grace_days INT NOT NULL DEFAULT 15,
            next_interest_date DATE NULL,
            interest_cycle_days INT NULL,

            min_payment_percent DECIMAL(9,4) NOT NULL DEFAULT 5.0000,
            min_payment_floor DECIMAL(18,2) NOT NULL DEFAULT 100.00,
            min_payment_met TINYINT(1) NOT NULL DEFAULT 0,

            penalty_rate DECIMAL(9,4) NOT NULL DEFAULT 1.0000,
            last_penalty_cycle_start DATE NULL,

            accrued_interest DECIMAL(18,2) NOT NULL DEFAULT 0.00,
            late_fees_accrued DECIMAL(18,2) NOT NULL DEFAULT 0.00,
            total_principal_paid DECIMAL(18,2) NOT NULL DEFAULT 0.00,
            total_interest_paid DECIMAL(18,2) NOT NULL DEFAULT 0.00,
            total_fees_paid DECIMAL(18,2) NOT NULL DEFAULT 0.00,
            total_paid DECIMAL(18,2) NOT NULL DEFAULT 0.00,
            outstanding_balance DECIMAL(18,2) NOT NULL DEFAULT 0.00,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            UNIQUE KEY loan_id (loan_id),
            KEY borrower_id (borrower_id),
            PRIMARY KEY (id)
        ) {$charset};";

        // Monthly Charges
        $sql3 = "CREATE TABLE {$prefix}monthly_charges (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            loan_id BIGINT UNSIGNED NOT NULL,
            cycle_start DATE NOT NULL,
            cycle_end DATE NOT NULL,
            interest_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
            penalty_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
            total_charge DECIMAL(18,2) NOT NULL DEFAULT 0.00,
            amount_paid DECIMAL(18,2) NOT NULL DEFAULT 0.00,
            open_balance DECIMAL(18,2) NOT NULL DEFAULT 0.00,
            status ENUM('Open','Partial','Paid') NOT NULL DEFAULT 'Open',
            amount_applied_this_cycle DECIMAL(18,2) NOT NULL DEFAULT 0.00,
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            KEY loan_id (loan_id),
            KEY cycle_start (cycle_start),
            PRIMARY KEY (id)
        ) {$charset};";

        // Payments
        $sql4 = "CREATE TABLE {$prefix}payments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            payment_id BIGINT UNSIGNED NOT NULL,
            loan_id BIGINT UNSIGNED NOT NULL,
            borrower_id BIGINT UNSIGNED NULL,
            payment_date DATE NOT NULL,
            amount_paid DECIMAL(18,2) NOT NULL DEFAULT 0.00,
            payment_type VARCHAR(64) NULL,
            payment_method VARCHAR(64) NULL,
            notes TEXT NULL,
            to_interest DECIMAL(18,2) NOT NULL DEFAULT 0.00,
            to_fees DECIMAL(18,2) NOT NULL DEFAULT 0.00,
            to_principal DECIMAL(18,2) NOT NULL DEFAULT 0.00,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            UNIQUE KEY payment_id (payment_id),
            KEY loan_id (loan_id),
            PRIMARY KEY (id)
        ) {$charset};";

        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
        dbDelta($sql4);

        add_option('olm_db_version', OLM_DB_VERSION);
    }

    // ---- Helpers (business rules) ----

    public static function compute_buckets($loan) {
        // Expects associative array of loan row
        $principal_left = max(0, (float)$loan['principal_amount'] - (float)$loan['total_principal_paid']);
        $interest_left  = max(0, (float)$loan['accrued_interest'] - (float)$loan['total_interest_paid']);
        $fees_left      = max(0, (float)$loan['late_fees_accrued'] - (float)$loan['total_fees_paid']);
        $outstanding    = $principal_left + $interest_left + $fees_left;
        return compact('principal_left','interest_left','fees_left','outstanding');
    }

    public static function min_due($loan, $outstanding=null) {
        if ($outstanding === null) {
            $b = self::compute_buckets($loan);
            $outstanding = $b['outstanding'];
        }
        $pct = (float)$loan['min_payment_percent'];
        $floor = (float)$loan['min_payment_floor'];
        $candidate = $outstanding * ($pct/100.0);
        return max($candidate, $floor);
    }
}
