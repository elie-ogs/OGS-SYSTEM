<?php
if (!defined('ABSPATH')) exit;
global $wpdb; $prefix = OLM_PREFIX;

$borrowers = $wpdb->get_results("SELECT id, borrower_name FROM {$prefix}borrowers ORDER BY borrower_name ASC", ARRAY_A);
$items = $wpdb->get_results("SELECT l.*, b.borrower_name FROM {$prefix}loans l LEFT JOIN {$prefix}borrowers b ON b.id=l.borrower_id ORDER BY l.id DESC", ARRAY_A);
$edit = null;
if (!empty($_GET['edit'])) {
    $edit = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$prefix}loans WHERE id=%d", intval($_GET['edit'])), ARRAY_A);
}
?>
<div class="wrap">
  <h1>Loans</h1>
  <div class="olm-flex">
    <div class="olm-form">
      <h2><?php echo $edit ? 'Edit Loan' : 'Add Loan'; ?></h2>
      <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <?php wp_nonce_field('olm_save_loan'); ?>
        <input type="hidden" name="action" value="olm_save_loan">
        <input type="hidden" name="id" value="<?php echo esc_attr($edit['id'] ?? ''); ?>">
        <table class="form-table">
          <tr><th>Loan ID</th><td><input class="regular-text" name="loan_id" value="<?php echo esc_attr($edit['loan_id'] ?? ''); ?>" required></td></tr>
          <tr><th>Borrower</th><td>
            <select name="borrower_id" required>
              <option value="">— Select —</option>
              <?php foreach ($borrowers as $b): ?>
                <option value="<?php echo (int)$b['id']; ?>" <?php selected(($edit['borrower_id'] ?? '') == $b['id']); ?>>
                  <?php echo esc_html($b['borrower_name']); ?>
                </option>
              <?php endforeach; ?>
            </select></td></tr>
          <tr><th>Status</th><td>
            <select name="status">
              <?php foreach (['Active','Closed','ChargedOff'] as $s): ?>
                <option <?php selected(($edit['status'] ?? 'Active') == $s); ?>><?php echo esc_html($s); ?></option>
              <?php endforeach; ?>
            </select>
          </td></tr>
          <tr><th>Principal Amount</th><td><input name="principal_amount" type="number" step="0.01" value="<?php echo esc_attr($edit['principal_amount'] ?? ''); ?>" required></td></tr>
          <tr><th>Interest Rate (% monthly)</th><td><input name="interest_rate" type="number" step="0.0001" value="<?php echo esc_attr($edit['interest_rate'] ?? ''); ?>" required></td></tr>
          <tr><th>Term (months)</th><td><input name="loan_term_months" type="number" value="<?php echo esc_attr($edit['loan_term_months'] ?? ''); ?>"></td></tr>
          <tr><th>Start Date</th><td><input name="start_date" type="date" value="<?php echo esc_attr($edit['start_date'] ?? ''); ?>" required></td></tr>
          <tr><th>Payment Due Date</th><td><input name="payment_due_date" type="date" value="<?php echo esc_attr($edit['payment_due_date'] ?? ''); ?>"></td></tr>
          <tr><th>Next Interest Date</th><td><input name="next_interest_date" type="date" value="<?php echo esc_attr($edit['next_interest_date'] ?? ''); ?>"></td></tr>
          <tr><th>Grace Days</th><td><input name="grace_days" type="number" value="<?php echo esc_attr($edit['grace_days'] ?? 15); ?>"></td></tr>
          <tr><th>Min Payment %</th><td><input name="min_payment_percent" type="number" step="0.0001" value="<?php echo esc_attr($edit['min_payment_percent'] ?? 5); ?>"></td></tr>
          <tr><th>Min Payment Floor</th><td><input name="min_payment_floor" type="number" step="0.01" value="<?php echo esc_attr($edit['min_payment_floor'] ?? 100); ?>"></td></tr>
          <tr><th>Penalty Rate %</th><td><input name="penalty_rate" type="number" step="0.0001" value="<?php echo esc_attr($edit['penalty_rate'] ?? 1); ?>"></td></tr>
        </table>
        <?php submit_button($edit ? 'Update Loan' : 'Create Loan'); ?>
      </form>
    </div>
    <div class="olm-list">
      <h2>All Loans</h2>
      <table class="widefat striped">
        <thead><tr><th>ID</th><th>Loan ID</th><th>Borrower</th><th>Status</th><th>Outstanding</th><th>Next Interest</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($items as $r): ?>
          <tr>
            <td><?php echo esc_html($r['id']); ?></td>
            <td><?php echo esc_html($r['loan_id']); ?></td>
            <td><?php echo esc_html($r['borrower_name']); ?></td>
            <td><?php echo esc_html($r['status']); ?></td>
            <td><?php echo esc_html(number_format($r['outstanding_balance'],2)); ?></td>
            <td><?php echo esc_html($r['next_interest_date']); ?></td>
            <td><a class="button" href="<?php echo admin_url('admin.php?page=olm-loans&edit='.(int)$r['id']); ?>">Edit</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
