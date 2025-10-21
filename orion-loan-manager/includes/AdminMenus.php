<?php
if (!defined('ABSPATH')) exit;

class OLM_Admin_Menus {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menus']);
        add_action('admin_post_olm_save_borrower', [__CLASS__, 'handle_save_borrower']);
        add_action('admin_post_olm_save_loan', [__CLASS__, 'handle_save_loan']);
        add_action('admin_post_olm_save_payment', [__CLASS__, 'handle_save_payment']);
    }

    public static function register_menus() {
        $cap = 'manage_options';
        add_menu_page(
            'Loan Manager',
            'Loan Manager',
            $cap,
            'orion-loan-manager',
            [__CLASS__, 'dashboard_page'],
            'dashicons-chart-line',
            25
        );

        add_submenu_page('orion-loan-manager', 'Borrowers', 'Borrowers', $cap, 'olm-borrowers', [__CLASS__, 'borrowers_page']);
        add_submenu_page('orion-loan-manager', 'Loans', 'Loans', $cap, 'olm-loans', [__CLASS__, 'loans_page']);
        add_submenu_page('orion-loan-manager', 'Monthly Charges', 'Monthly Charges', $cap, 'olm-charges', [__CLASS__, 'charges_page']);
        add_submenu_page('orion-loan-manager', 'Payments', 'Payments', $cap, 'olm-payments', [__CLASS__, 'payments_page']);
        add_submenu_page('orion-loan-manager', 'Settings', 'Settings', $cap, 'olm-settings', [__CLASS__, 'settings_page']);
    }

    public static function dashboard_page() { include __DIR__ . '/../admin/pages/dashboard.php'; }
    public static function borrowers_page() { include __DIR__ . '/../admin/pages/borrowers.php'; }
    public static function loans_page() { include __DIR__ . '/../admin/pages/loans.php'; }
    public static function charges_page() { include __DIR__ . '/../admin/pages/charges.php'; }
    public static function payments_page() { include __DIR__ . '/../admin/pages/payments.php'; }
    public static function settings_page() { include __DIR__ . '/../admin/pages/settings.php'; }

    // ---- Handlers ----
    public static function handle_save_borrower() {
        if (!current_user_can('manage_options')) wp_die('Denied');
        check_admin_referer('olm_save_borrower');

        global $wpdb;
        $prefix = OLM_PREFIX;
        $data = [
            'borrower_name' => sanitize_text_field($_POST['borrower_name'] ?? ''),
            'books_customer_id' => sanitize_text_field($_POST['books_customer_id'] ?? ''),
            'email' => sanitize_text_field($_POST['email'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'notes' => wp_kses_post($_POST['notes'] ?? ''),
            'updated_at' => current_time('mysql')
        ];

        if (empty($_POST['id'])) {
            $wpdb->insert($prefix.'borrowers', array_merge($data, ['created_at'=>current_time('mysql')]));
        } else {
            $wpdb->update($prefix.'borrowers', $data, ['id'=>intval($_POST['id'])]);
        }
        wp_redirect(admin_url('admin.php?page=olm-borrowers&saved=1'));
        exit;
    }

    public static function handle_save_loan() {
        if (!current_user_can('manage_options')) wp_die('Denied');
        check_admin_referer('olm_save_loan');
        global $wpdb; $prefix = OLM_PREFIX;

        $loan_id = intval($_POST['loan_id'] ?? 0);
        $row = [
            'loan_id' => $loan_id,
            'borrower_id' => intval($_POST['borrower_id'] ?? 0),
            'status' => sanitize_text_field($_POST['status'] ?? 'Active'),
            'principal_amount' => round((float)($_POST['principal_amount'] ?? 0),2),
            'interest_rate' => round((float)($_POST['interest_rate'] ?? 0),4),
            'loan_term_months' => intval($_POST['loan_term_months'] ?? 0),
            'start_date' => sanitize_text_field($_POST['start_date'] ?? ''),
            'end_date' => sanitize_text_field($_POST['end_date'] ?? null),
            'payment_frequency' => 'Monthly',
            'payment_due_date' => sanitize_text_field($_POST['payment_due_date'] ?? ''),
            'grace_days' => intval($_POST['grace_days'] ?? 15),
            'next_interest_date' => sanitize_text_field($_POST['next_interest_date'] ?? ''),
            'interest_cycle_days' => intval($_POST['interest_cycle_days'] ?? 0),
            'min_payment_percent' => round((float)($_POST['min_payment_percent'] ?? 5),4),
            'min_payment_floor' => round((float)($_POST['min_payment_floor'] ?? 100),2),
            'min_payment_met' => 0,
            'penalty_rate' => round((float)($_POST['penalty_rate'] ?? 1),4),
            'last_penalty_cycle_start' => null,
            'updated_at' => current_time('mysql')
        ];

        $is_new = empty($_POST['id']);
        if ($is_new) {
            // Initialize first cycle interest and defaults (per spec)
            $row['accrued_interest'] = 0.00;
            $row['late_fees_accrued'] = 0.00;
            $row['total_principal_paid'] = 0.00;
            $row['total_interest_paid'] = 0.00;
            $row['total_fees_paid'] = 0.00;
            $row['total_paid'] = 0.00;
            $row['outstanding_balance'] = $row['principal_amount'];
            $row['created_at'] = current_time('mysql');

            $wpdb->insert($prefix.'loans', $row);
            $id = $wpdb->insert_id;

            // Apply first month interest immediately
            $loan = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$prefix}loans WHERE id=%d", $id), ARRAY_A);

            // Compute first month cycle_next based on start_date
            $cycle_start = $loan['start_date'];
            $cycle_next  = date('Y-m-d', strtotime("+1 month", strtotime($cycle_start)));
            $cycle_days  = (new DateTime($cycle_start))->diff(new DateTime($cycle_next))->days;

            $b = OLM_Models::compute_buckets($loan);
            $monthly_interest = round($b['outstanding'] * ((float)$loan['interest_rate'] / 100.0), 2);
            $newAI = round(((float)$loan['accrued_interest']) + $monthly_interest, 2);

            $wpdb->update($prefix.'loans', [
                'accrued_interest' => $newAI,
                'interest_cycle_days' => $cycle_days,
                'next_interest_date' => $cycle_next,
                'payment_due_date' => $cycle_next,
                'outstanding_balance' => round($b['outstanding'] + $monthly_interest, 2),
                'updated_at' => current_time('mysql')
            ], ['id' => $id]);

        } else {
            $wpdb->update($prefix.'loans', $row, ['id'=>intval($_POST['id'])]);
        }

        wp_redirect(admin_url('admin.php?page=olm-loans&saved=1'));
        exit;
    }

    public static function handle_save_payment() {
        if (!current_user_can('manage_options')) wp_die('Denied');
        check_admin_referer('olm_save_payment');
        global $wpdb; $prefix = OLM_PREFIX;

        $row = [
            'payment_id' => intval($_POST['payment_id'] ?? 0),
            'loan_id' => intval($_POST['loan_id'] ?? 0),
            'borrower_id' => intval($_POST['borrower_id'] ?? 0),
            'payment_date' => sanitize_text_field($_POST['payment_date'] ?? ''),
            'amount_paid' => round((float)($_POST['amount_paid'] ?? 0),2),
            'payment_type' => sanitize_text_field($_POST['payment_type'] ?? ''),
            'payment_method' => sanitize_text_field($_POST['payment_method'] ?? ''),
            'notes' => wp_kses_post($_POST['notes'] ?? ''),
            'created_at' => current_time('mysql')
        ];

        $wpdb->insert($prefix.'payments', $row);
        $pid = $wpdb->insert_id;

        // Allocation
        OLM_Payments::apply_payment($pid);

        wp_redirect(admin_url('admin.php?page=olm-payments&saved=1'));
        exit;
    }
}
OLM_Admin_Menus::init();
