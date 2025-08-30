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
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                  <?php wp_nonce_field('saf_apply_fix'); ?>
                  <input type="hidden" name="action" value="saf_apply_fix" />
                  <input type="hidden" name="fix_key" value="<?php echo esc_attr($i['fix_key']); ?>" />
                  <button class="button button-primary">Apply Fix</button>
                </form>
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