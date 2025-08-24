(function($){
  $(function(){

    // 追加フォーム：species変更に合わせて <select> 候補を絞り込み
    function filterBreedOptions(species) {
      $('#roro-breed-select option').each(function(){
        var sp = $(this).attr('data-sp');
        if (!sp) return; // placeholder は残す
        $(this).toggle(sp === species);
      });
      $('#roro-breed-select').val(''); // クリア
    }
    $('#roro-pet-species').on('change', function(){
      filterBreedOptions($(this).val());
    });
    filterBreedOptions($('#roro-pet-species').val());

    // 削除確認
    $('.roro-delete-form').on('submit', function(e){
      var ok = window.confirm((window.RORO_PROFILE_LOC && RORO_PROFILE_LOC.confirm_delete) || 'Delete this pet?');
      if (!ok) e.preventDefault();
    });

    // プロフィール画像プレビュー（簡易）
    $('input[name="avatar"]').on('change', function(e){
      if (!this.files || !this.files[0]) return;
      var file = this.files[0];
      if (!file.type.match(/^image\//)) return;
      var reader = new FileReader();
      reader.onload = function(ev){
        var $img = $('#roro-avatar-preview');
        if ($img.is('img')) {
          $img.attr('src', ev.target.result);
        } else {
          $img.replaceWith($('<img id="roro-avatar-preview"/>').attr('src', ev.target.result));
        }
      };
      reader.readAsDataURL(file);
    });

  });
})(jQuery);
