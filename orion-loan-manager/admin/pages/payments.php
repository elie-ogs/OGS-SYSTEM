<?php
if (!defined('ABSPATH')) exit;
global $wpdb; $prefix = OLM_PREFIX;

$borrowers = $wpdb->get_results("SELECT id, borrower_name FROM {$prefix}borrowers ORDER BY borrower_name ASC", ARRAY_A);
$loans = $wpdb->get_results("SELECT id, loan_id FROM {$prefix}loans ORDER BY id DESC", ARRAY_A);
$items = $wpdb->get_results("SELECT p.*, l.loan_id, b.borrower_name FROM {$prefix}payments p LEFT JOIN {$prefix}loans l ON l.id=p.loan_id LEFT JOIN {$prefix}borrowers b ON b.id=p.borrower_id ORDER BY p.id DESC LIMIT 300", ARRAY_A);

?>
<div class="wrap">
  <h1>Payments</h1>
  <div class="olm-flex">
    <div class="olm-form">
      <h2>Add Payment</h2>
      <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <?php wp_nonce_field('olm_save_payment'); ?>
        <input type="hidden" name="action" value="olm_save_payment">
        <table class="form-table">
          <tr><th>Payment ID</th><td><input name="payment_id" type="number" required></td></tr>
          <tr><th>Loan</th><td>
            <select name="loan_id" required>
              <option value="">— Select —</option>
              <?php foreach ($loans as $l): ?>
                <option value="<?php echo (int)$l['id']; ?>">#<?php echo esc_html($l['loan_id']); ?></option>
              <?php endforeach; ?>
            </select>
          </td></tr>
          <tr><th>Borrower (optional)</th><td>
            <select name="borrower_id">
              <option value="">—</option>
              <?php foreach ($borrowers as $b): ?>
                <option value="<?php echo (int)$b['id']; ?>"><?php echo esc_html($b['borrower_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </td></tr>
          <tr><th>Payment Date</th><td><input type="date" name="payment_date" required></td></tr>
          <tr><th>Amount Paid</th><td><input type="number" step="0.01" name="amount_paid" required></td></tr>
          <tr><th>Method</th><td><input type="text" name="payment_method"></td></tr>
          <tr><th>Notes</th><td><textarea name="notes" rows="3"></textarea></td></tr>
        </table>
        <?php submit_button('Record Payment'); ?>
      </form>
    </div>
    <div class="olm-list">
      <h2>Recent Payments</h2>
      <table class="widefat striped">
        <thead><tr><th>Payment ID</th><th>Loan</th><th>Borrower</th><th>Date</th><th>Amount</th><th>To Interest</th><th>To Fees</th><th>To Principal</th></tr></thead>
        <tbody>
        <?php foreach ($items as $r): ?>
          <tr>
            <td><?php echo esc_html($r['payment_id']); ?></td>
            <td>#<?php echo esc_html($r['loan_id']); ?></td>
            <td><?php echo esc_html($r['borrower_name']); ?></td>
            <td><?php echo esc_html($r['payment_date']); ?></td>
            <td><?php echo esc_html(number_format($r['amount_paid'],2)); ?></td>
            <td><?php echo esc_html(number_format($r['to_interest'],2)); ?></td>
            <td><?php echo esc_html(number_format($r['to_fees'],2)); ?></td>
            <td><?php echo esc_html(number_format($r['to_principal'],2)); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
