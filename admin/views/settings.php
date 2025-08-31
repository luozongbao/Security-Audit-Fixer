<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
  <h1>Settings</h1>
  <?php if (isset($_GET['saved'])): ?>
    <div class="notice notice-success"><p>Settings saved.</p></div>
  <?php endif; ?>

  <?php if (!empty($_GET['saf_login_slug_error'])): ?>
    <div class="notice notice-error"><p><?php echo esc_html(wp_unslash($_GET['saf_login_slug_error'])); ?></p></div>
  <?php endif; ?>
  <?php if (isset($_GET['applied'])): ?>
    <?php if ((int)$_GET['applied'] === 1): ?>
      <div class="notice notice-success"><p>Settings saved.</p></div>
    <?php else: ?>
      <div class="notice notice-error"><p>Failed to save settings.</p></div>
    <?php endif; ?>
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

<h3>Custom Login URL</h3>
<p>Hide default login endpoints from unauthenticated users and use a custom login path.</p>
<?php
$current_slug = saf_get_login_slug();
$custom_login_url = $current_slug ? home_url('/' . $current_slug . '/') : '';
?>
<table class="form-table">
  <tr>
    <th scope="row">Status</th>
    <td>
      <?php if ($current_slug): ?>
        <span style="color:#008000; font-weight:600;">Enabled</span>
        <div>Current URL: <code><?php echo esc_html($custom_login_url); ?></code></div>
      <?php else: ?>
        <span style="color:#a00; font-weight:600;">Disabled</span>
      <?php endif; ?>
      <div style="margin-top:8px; font-size:12px; color:#555;">
        Logged-in users access /wp-admin normally. Anonymous GETs to /wp-login.php and /wp-admin return 404. Your custom URL shows the login page.
      </div>
    </td>
  </tr>
</table>

<?php if (!empty($_GET['saf_login_slug_error'])): ?>
  <div class="notice notice-error"><p><?php echo esc_html(wp_unslash($_GET['saf_login_slug_error'])); ?></p></div>
<?php endif; ?>
<?php if (isset($_GET['applied'])): ?>
  <?php if ((int)$_GET['applied'] === 1): ?>
    <div class="notice notice-success"><p>Settings saved.</p></div>
  <?php else: ?>
    <div class="notice notice-error"><p>Failed to save settings.</p></div>
  <?php endif; ?>
<?php endif; ?>

<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:12px;">
  <?php wp_nonce_field('saf_apply_fix'); ?>
  <input type="hidden" name="action" value="saf_apply_fix" />
  <input type="hidden" name="fix_key" value="set_custom_login_url" />
  <label for="saf_login_slug_input"><strong>Set or update the login URL</strong></label><br/>
  <input id="saf_login_slug_input" type="text" name="login_slug" value="<?php echo esc_attr($current_slug); ?>" placeholder="my-secure-login" style="width:320px;" />
  <p class="description">Letters, numbers, dashes only; 3–64 characters.</p>
  <button class="button button-primary" type="submit">Save Login URL</button>
  <?php if ($current_slug): ?>
    <button class="button" type="submit" name="disable_login_slug" value="1"
      onclick="return confirm('Disable custom login URL? Default endpoints will be visible again (wp-login.php/wp-admin).');">
      Disable
    </button>
  <?php endif; ?>
</form>