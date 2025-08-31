(function($){
  $(function(){
    // rename admin name...
    var $renameModal = $('#saf-rename-admin-modal');
    var $renameBtn = $('#saf-rename-admin-btn');
    var $renameCancel = $('#saf-rename-cancel');
    var $renameForm = $('#saf-rename-admin-form');
    var $renameInput = $('#saf-new-username');
    var $renameErr = $('#saf-rename-error');

    function openRenameModal(msg, preset) {
      $renameErr.hide().text('');
      if (msg) { $renameErr.text(msg).show(); }
      if (preset) { $renameInput.val(preset); }
      $renameModal.show();
      $renameInput.trigger('focus');
    }

    if ($renameBtn.length) {
      $renameBtn.on('click', function(e){ e.preventDefault(); openRenameModal(); });
    }
    if ($renameCancel.length) {
      $renameCancel.on('click', function(e){ e.preventDefault(); $renameModal.hide(); });
    }
    if ($renameForm.length) {
      $renameForm.on('submit', function(e){
        var val = ($renameInput.val() || '').trim();
        if (!val) { e.preventDefault(); $renameErr.text('Please enter a new username.').show(); return; }
        if (val.toLowerCase() === 'admin') { e.preventDefault(); $renameErr.text('New username cannot be “admin”.').show(); return; }
        var reUser = /^[A-Za-z0-9._\-@]+$/;
        if (!reUser.test(val)) { e.preventDefault(); $renameErr.text('Username can only contain letters, numbers, and . _ - @').show(); return; }
      });
    }

    // New: Change table prefix UI
    var $prefixModal = $('#saf-change-prefix-modal');
    var $prefixBtn = $('#saf-change-prefix-btn');
    var $prefixCancel = $('#saf-prefix-cancel');
    var $prefixForm = $('#saf-change-prefix-form');
    var $prefixInput = $('#saf-new-prefix');
    var $prefixErr = $('#saf-prefix-error');

    function openPrefixModal(msg, preset) {
      $prefixErr.hide().text('');
      if (msg) { $prefixErr.text(msg).show(); }
      if (preset) { $prefixInput.val(preset); }
      $prefixModal.show();
      $prefixInput.trigger('focus');
    }

    if ($prefixBtn.length) {
      $prefixBtn.on('click', function(e){ e.preventDefault(); openPrefixModal(); });
    }
    if ($prefixCancel.length) {
      $prefixCancel.on('click', function(e){ e.preventDefault(); $prefixModal.hide(); });
    }
    if ($prefixForm.length) {
      $prefixForm.on('submit', function(e){
        var val = ($prefixInput.val() || '').trim();
        if (!val) { e.preventDefault(); $prefixErr.text('Please enter a new table prefix.').show(); return; }
        if (val.toLowerCase() === 'wp_') { e.preventDefault(); $prefixErr.text('New table prefix cannot be "wp_".').show(); return; }
        // must start with letter, only letters/numbers/underscore, end with underscore
        var rePrefix = /^[A-Za-z][A-Za-z0-9_]*_$/;
        if (!rePrefix.test(val)) {
          e.preventDefault();
          $prefixErr.text('Prefix must start with a letter, contain only letters/numbers/underscores, and end with an underscore.').show();
          return;
        }
      });
    }


    // Server advisory modal
    var $advModal = $('#saf-server-advice-modal');
    var $advBody = $('#saf-server-advice-body');
    var $advClose = $('#saf-server-advice-close');

    function adviceText(fixKey) {
      var snippets = {
        'block_wp_config_htaccess': {
          title: 'Protect wp-config.php on Nginx',
          nginx: [
            'location = /wp-config.php {',
            '    deny all;',
            '}'
          ].join('\n'),
          apache: [
            '<Files wp-config.php>',
            '  Require all denied',
            '</Files>'
          ].join('\n')
        }
        // You can add more advisories if you plan server-rule fixes for other files
      };
      var s = snippets[fixKey] || null;
      if (!s) return 'Please add an equivalent deny rule on your web server for the requested file.';
      var msg = s.title + '\n\nNginx:\n' + s.nginx + '\n\nApache (.htaccess):\n' + s.apache;
      return msg;
    }

    function openAdvice(fixKey) {
      $advBody.text(adviceText(fixKey));
      $advModal.show();
    }

    if ($advClose.length) {
      $advClose.on('click', function(){ $advModal.hide(); });
    }

    // Read query param to trigger advisory
    var params = new URLSearchParams(window.location.search);
    if (params.has('saf_server_advice')) {
      var key = params.get('saf_server_advice');
      openAdvice(key);
      // Clean the URL
      if (window.history && history.replaceState) {
        params.delete('saf_server_advice');
        history.replaceState(null, '', window.location.pathname + (params.toString() ? '?' + params.toString() : ''));
      }
    }

    // Read server errors from query string to re-open modals
    var params = new URLSearchParams(window.location.search);
    if (params.has('saf_rename_error')) {
      var msg = params.get('saf_rename_error');
      var preset = params.get('saf_new_username') || '';
      openRenameModal(decodeURIComponent(msg), decodeURIComponent(preset));
    }
    if (params.has('saf_prefix_error')) {
      var msg2 = params.get('saf_prefix_error');
      var preset2 = params.get('saf_new_prefix') || '';
      openPrefixModal(decodeURIComponent(msg2), decodeURIComponent(preset2));
    }
    // Optional: clean URL
    if (window.history && history.replaceState && (params.has('saf_rename_error') || params.has('saf_prefix_error'))) {
      params.delete('saf_rename_error'); params.delete('saf_new_username');
      params.delete('saf_prefix_error'); params.delete('saf_new_prefix');
      var newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
      history.replaceState(null, '', newUrl);
    }
  });
})(jQuery);

(function($){
  $(function(){
    var $loginModal = $('#saf-login-slug-modal');
    $(document).on('click', '.saf-open-login-slug-modal', function(){
      $loginModal.show();
    });
    $(document).on('click', '.saf-close-login-slug-modal', function(){
      $loginModal.hide();
    });
  });
})(jQuery);
