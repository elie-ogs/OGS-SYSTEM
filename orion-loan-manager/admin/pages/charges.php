<?php
if (!defined('ABSPATH')) exit;
global $wpdb; $prefix = OLM_PREFIX;

$items = $wpdb->get_results("SELECT c.*, l.loan_id FROM {$prefix}monthly_charges c LEFT JOIN {$prefix}loans l ON l.id=c.loan_id ORDER BY c.cycle_start DESC, c.id DESC LIMIT 500", ARRAY_A);

?>
<div class="wrap">
  <h1>Monthly Charges (system-generated)</h1>
  <table class="widefat striped">
    <thead>
      <tr><th>Loan</th><th>Cycle</th><th>Interest</th><th>Penalty</th><th>Total</th><th>Paid</th><th>Open</th><th>Status</th></tr>
    </thead>
    <tbody>
      <?php foreach ($items as $r): ?>
        <tr>
          <td>#<?php echo esc_html($r['loan_id']); ?></td>
          <td><?php echo esc_html($r['cycle_start']); ?> → <?php echo esc_html($r['cycle_end']); ?></td>
          <td><?php echo esc_html(number_format($r['interest_amount'],2)); ?></td>
          <td><?php echo esc_html(number_format($r['penalty_amount'],2)); ?></td>
          <td><?php echo esc_html(number_format($r['total_charge'],2)); ?></td>
          <td><?php echo esc_html(number_format($r['amount_paid'],2)); ?></td>
          <td><?php echo esc_html(number_format($r['open_balance'],2)); ?></td>
          <td><?php echo esc_html($r['status']); ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
