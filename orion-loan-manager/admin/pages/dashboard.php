<?php
if (!defined('ABSPATH')) exit;
global $wpdb; $prefix = OLM_PREFIX;

$total_loans = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$prefix}loans");
$active_loans = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$prefix}loans WHERE status='Active'");
$outstanding = (float)$wpdb->get_var("SELECT SUM(outstanding_balance) FROM {$prefix}loans");

?>
<div class="wrap">
  <h1>Loan Manager — Dashboard</h1>
  <div class="olm-cards">
    <div class="card"><div class="k">Total Loans</div><div class="v"><?php echo esc_html($total_loans); ?></div></div>
    <div class="card"><div class="k">Active Loans</div><div class="v"><?php echo esc_html($active_loans); ?></div></div>
    <div class="card"><div class="k">Outstanding</div><div class="v"><?php echo esc_html(number_format($outstanding,2)); ?></div></div>
  </div>

  <p>Daily batch runs near local midnight and creates Monthly Charges, applies penalty once per cycle after grace if minimum not met, and rolls loan cycle dates.</p>
</div>
