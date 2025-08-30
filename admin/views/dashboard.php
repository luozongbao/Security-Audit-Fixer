<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
  <h1>Security Audit — Dashboard</h1>
  <p>Run a quick scan to find common security issues.</p>
  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('saf_run_scan'); ?>
    <input type="hidden" name="action" value="saf_run_scan" />
    <button class="button button-primary">Run Scan</button>
  </form>
</div>