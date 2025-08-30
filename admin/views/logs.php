<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
  <h1>Logs</h1>
  <pre style="max-height: 400px; overflow:auto; background:#f7f7f7; padding:12px;">
<?php
$file = WP_CONTENT_DIR . '/uploads/saf.log';
if (file_exists($file)) {
    echo esc_html(substr(file_get_contents($file), -10000)); // last ~10KB
} else {
    echo 'No logs yet.';
}
?>
  </pre>
</div>