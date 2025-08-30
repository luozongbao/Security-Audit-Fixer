<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
  <h1>Settings</h1>
  <?php if (isset($_GET['saved'])): ?>
    <div class="notice notice-success"><p>Settings saved.</p></div>
  <?php endif; ?>
  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('saf_save_settings'); ?>
    <input type="hidden" name="action" value="saf_save_settings" />

    <table class="form-table">
      <tr>
        <th><label>Daily Auto Scan</label></th>
        <td><input type="checkbox" name="auto_scan_daily" <?php checked(saf_bool(saf_get_option('auto_scan_daily', false))); ?> /></td>
      </tr>
      <tr>
        <th><label>Harden XML-RPC (recommend disable)</label></th>
        <td><input type="checkbox" name="harden_xmlrpc" <?php checked(saf_bool(saf_get_option('harden_xmlrpc', true))); ?> /></td>
      </tr>
      <tr>
        <th><label>Disable File Editor</label></th>
        <td><input type="checkbox" name="disable_file_edit" <?php checked(saf_bool(saf_get_option('disable_file_edit', true))); ?> /></td>
      </tr>
      <tr>
        <th><label>Enforce Strong Passwords</label></th>
        <td><input type="checkbox" name="enforce_strong_passwords" <?php checked(saf_bool(saf_get_option('enforce_strong_passwords', true))); ?> /></td>
      </tr>
    </table>

    <p><button class="button button-primary">Save Settings</button></p>
  </form>
</div>