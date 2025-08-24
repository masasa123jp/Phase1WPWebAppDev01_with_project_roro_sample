(function($){
  $(function(){
    // 二重クリック防止
    $('.roro-auth-wrap .roro-btn').on('click', function(){
      var $btn = $(this);
      if ($btn.data('busy')) return false;
      $btn.data('busy', true);
      setTimeout(function(){ $btn.data('busy', false); }, 3000);
    });
  });
})(jQuery);
