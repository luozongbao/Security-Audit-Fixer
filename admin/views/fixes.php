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

  <?php if (get_user_by('login', 'admin')): ?>
    <button id="saf-rename-admin-btn" class="button button-primary">Rename "admin" User</button>
  <?php endif; ?>


  <!-- Modal markup -->
  <div id="saf-rename-admin-modal" class="saf-modal" style="display:none;">
    <div class="saf-modal-content">
      <h2>Rename "admin" User</h2>
      <p>Choose a new username (cannot be “admin” and must be unique).</p>
      <form id="saf-rename-admin-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('saf_apply_fix'); ?>
        <input type="hidden" name="action" value="saf_apply_fix" />
        <input type="hidden" name="fix_key" value="rename_admin_user" />
        <label for="saf-new-username"><strong>New username</strong></label><br/>
        <input type="text" id="saf-new-username" name="new_username" required placeholder="e.g., siteadmin123" style="width: 300px;" />
        <div id="saf-rename-error" style="color:#b32d2e; margin-top:6px; display:none;"></div>
        <div style="margin-top:12px;">
          <button type="submit" class="button button-primary">Confirm Rename</button>
          <button type="button" id="saf-rename-cancel" class="button">Cancel</button>
        </div>
      </form>
    </div>

  <!-- Change prefix modal -->
  <div id="saf-change-prefix-modal" class="saf-modal" style="display:none;">
    <div class="saf-modal-content">
      <h2>Change Table Prefix</h2>
      <p>Enter a new table prefix. It must start with a letter, include only letters/numbers/underscores, and end with an underscore. Example: site123_</p>
      <form id="saf-change-prefix-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('saf_apply_fix'); ?>
        <input type="hidden" name="action" value="saf_apply_fix" />
        <input type="hidden" name="fix_key" value="change_table_prefix" />
        <label for="saf-new-prefix"><strong>New prefix</strong></label><br/>
        <input type="text" id="saf-new-prefix" name="new_prefix" required placeholder="e.g., site123_" style="width: 300px;" />
        <div id="saf-prefix-error" style="color:#b32d2e; margin-top:6px; display:none;"></div>
        <div style="margin-top:12px;">
          <button type="submit" class="button button-primary">Confirm Change</button>
          <button type="button" id="saf-prefix-cancel" class="button">Cancel</button>
        </div>
      </form>
    </div>
    <div class="saf-modal-backdrop"></div>
  </div>
</div>