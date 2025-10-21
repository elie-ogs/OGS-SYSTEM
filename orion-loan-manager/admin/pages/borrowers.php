<?php
if (!defined('ABSPATH')) exit;
global $wpdb; $prefix = OLM_PREFIX;

$items = $wpdb->get_results("SELECT * FROM {$prefix}borrowers ORDER BY id DESC", ARRAY_A);
$edit = null;
if (!empty($_GET['edit'])) {
    $edit = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$prefix}borrowers WHERE id=%d", intval($_GET['edit'])), ARRAY_A);
}
?>
<div class="wrap">
  <h1>Borrowers</h1>
  <div class="olm-flex">
    <div class="olm-form">
      <h2><?php echo $edit ? 'Edit Borrower' : 'Add Borrower'; ?></h2>
      <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <?php wp_nonce_field('olm_save_borrower'); ?>
        <input type="hidden" name="action" value="olm_save_borrower">
        <input type="hidden" name="id" value="<?php echo esc_attr($edit['id'] ?? ''); ?>">
        <table class="form-table">
          <tr><th>Name</th><td><input class="regular-text" name="borrower_name" value="<?php echo esc_attr($edit['borrower_name'] ?? ''); ?>" required></td></tr>
          <tr><th>Books Customer ID</th><td><input class="regular-text" name="books_customer_id" value="<?php echo esc_attr($edit['books_customer_id'] ?? ''); ?>"></td></tr>
          <tr><th>Email</th><td><input class="regular-text" name="email" value="<?php echo esc_attr($edit['email'] ?? ''); ?>"></td></tr>
          <tr><th>Phone</th><td><input class="regular-text" name="phone" value="<?php echo esc_attr($edit['phone'] ?? ''); ?>"></td></tr>
          <tr><th>Notes</th><td><textarea name="notes" rows="4"><?php echo esc_textarea($edit['notes'] ?? ''); ?></textarea></td></tr>
        </table>
        <?php submit_button($edit ? 'Update Borrower' : 'Add Borrower'); ?>
      </form>
    </div>
    <div class="olm-list">
      <h2>All Borrowers</h2>
      <table class="widefat striped">
        <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($items as $r): ?>
          <tr>
            <td><?php echo esc_html($r['id']); ?></td>
            <td><?php echo esc_html($r['borrower_name']); ?></td>
            <td><?php echo esc_html($r['email']); ?></td>
            <td><?php echo esc_html($r['phone']); ?></td>
            <td><a class="button" href="<?php echo admin_url('admin.php?page=olm-borrowers&edit='.(int)$r['id']); ?>">Edit</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
