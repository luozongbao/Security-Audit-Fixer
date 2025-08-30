(function($){
  $(function(){
    var $modal = $('#saf-rename-admin-modal');
    var $btn = $('#saf-rename-admin-btn');
    var $cancel = $('#saf-rename-cancel');
    var $form = $('#saf-rename-admin-form');
    var $input = $('#saf-new-username');
    var $err = $('#saf-rename-error');

    function openModal(msg, preset) {
      $err.hide().text('');
      if (msg) { $err.text(msg).show(); }
      if (preset) { $input.val(preset); }
      $modal.show();
      $input.trigger('focus');
    }

    if ($btn.length) {
      $btn.on('click', function(e){
        e.preventDefault();
        openModal();
      });
    }

    if ($cancel.length) {
      $cancel.on('click', function(e){
        e.preventDefault();
        $modal.hide();
      });
    }

    if ($form.length) {
      $form.on('submit', function(e){
        var val = ($input.val() || '').trim();
        if (!val) {
          e.preventDefault();
          $err.text('Please enter a new username.').show();
          return;
        }
        if (val.toLowerCase() === 'admin') {
          e.preventDefault();
          $err.text('New username cannot be “admin”.').show();
          return;
        }
        var re = /^[A-Za-z0-9._\-@]+$/;
        if (!re.test(val)) {
          e.preventDefault();
          $err.text('Username can only contain letters, numbers, and . _ - @').show();
          return;
        }
      });
    }

    // If server sent error via query, show modal with message
    var params = new URLSearchParams(window.location.search);
    if (params.has('saf_rename_error')) {
      var msg = params.get('saf_rename_error');
      var preset = params.get('saf_new_username') || '';
      openModal(decodeURIComponent(msg), decodeURIComponent(preset));
      // Clean the URL (optional)
      if (window.history && history.replaceState) {
        params.delete('saf_rename_error'); params.delete('saf_new_username');
        history.replaceState(null, '', window.location.pathname + '?' + params.toString());
      }
    }
  });
})(jQuery);