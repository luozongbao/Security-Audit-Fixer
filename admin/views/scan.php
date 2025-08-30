<?php
if (!defined('ABSPATH')) exit;
global $wpdb;
$table = $wpdb->prefix . 'saf_scan_results';
$last = $wpdb->get_row("SELECT * FROM $table ORDER BY id DESC LIMIT 1");
$issues = [];
if ($last && !empty($last->issues)) {
    $issues = json_decode($last->issues, true) ?: [];
}
?>
<div class="wrap">
  <h1>Security Scan</h1>
  <?php if (isset($_GET['done'])): ?>
    <div class="notice notice-success"><p>Scan completed.</p></div>
  <?php endif; ?>

  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('saf_run_scan'); ?>
    <input type="hidden" name="action" value="saf_run_scan" />
    <button class="button">Run Full Scan</button>
  </form>

  <h2>Latest Results</h2>
  <?php if (!$last): ?>
    <p>No scans yet.</p>
  <?php else: ?>
    <p><strong>When:</strong> <?php echo esc_html($last->scan_time); ?></p>
    <p><strong>Summary:</strong> <?php echo esc_html($last->scan_summary); ?></p>
    <table class="widefat striped">
      <thead><tr><th>Issue</th><th>Severity</th><th>Details</th><th>Fix</th></tr></thead>
      <tbody>
        <?php foreach ($issues as $i): ?>
          <tr>
            <td><?php echo esc_html($i['title']); ?></td>
            <td><?php echo esc_html($i['severity']); ?></td>
            <td><?php echo esc_html($i['details']); ?></td>
            <td>
              <?php if (!empty($i['fix_key'])): ?>
                <?php if ($i['fix_key'] === 'change_table_prefix'): ?>
                  <button id="saf-change-prefix-btn" class="button button-primary">Change Table Prefix</button>
                <?php else: ?>
                  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                    <?php wp_nonce_field('saf_apply_fix'); ?>
                    <input type="hidden" name="action" value="saf_apply_fix" />
                    <input type="hidden" name="fix_key" value="<?php echo esc_attr($i['fix_key']); ?>" />
                    <button class="button button-primary">Apply Fix</button>
                  </form>
                <?php endif; ?>
              <?php else: ?>
                <em>N/A</em>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

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