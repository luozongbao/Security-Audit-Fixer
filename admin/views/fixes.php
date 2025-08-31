<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
  <h1>Fixes</h1>
  <?php if (isset($_GET['applied'])): ?>
    <div class="notice <?php echo $_GET['applied'] ? 'notice-success' : 'notice-error'; ?>">
      <p><?php echo $_GET['applied'] ? 'Fix applied.' : 'Failed to apply fix.'; ?></p>
    </div>
  <?php endif; ?>

  <p>Common fixes you can apply:</p>
  <?php
  $fixes = [
    'disable_file_edit' => 'Disable Theme/Plugin File Editor',
    'disable_debug' => 'Disable WP_DEBUG',
    'add_htaccess_no_indexes' => 'Disable Directory Indexing (.htaccess)',
    'disable_xmlrpc' => 'Disable XML-RPC',
    'force_ssl_admin' => 'Force SSL for Admin',
    'enable_strong_password_policy' => 'Enforce Strong Passwords',
    'harden_wpconfig_perms' => 'Harden wp-config.php Permissions',
    'harden_htaccess_perms' => 'Harden .htaccess Permissions',
    'add_basic_security_headers' => 'Add Basic Security Headers',
    'update_all_plugins' => 'Update All Plugins',
    'update_all_themes' => 'Update All Themes',
  ];
  foreach ($fixes as $key => $label): ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <?php wp_nonce_field('saf_apply_fix'); ?>
      <input type="hidden" name="action" value="saf_apply_fix" />
      <input type="hidden" name="fix_key" value="<?php echo esc_attr($key); ?>" />
      <button class="button"><?php echo esc_html($label); ?></button>
    </form>
  <?php endforeach; ?>

<!-- Fix All Exposed Files button (if not already added) -->
<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin: 12px 0;">
  <?php wp_nonce_field('saf_apply_fix'); ?>
  <input type="hidden" name="action" value="saf_apply_fix" />
  <input type="hidden" name="fix_key" value="saf_fix_all_exposed" />
  <button class="button button-secondary">Fix All Exposed Files</button>
</form>









