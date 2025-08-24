(function(){
  document.addEventListener('click', function(ev){
    if (ev.target && ev.target.classList.contains('roro-mag-toggle')) {
      var content = ev.target.previousElementSibling;
      if (!content) return;
      var collapsed = content.getAttribute('data-collapsed') === 'true';
      content.setAttribute('data-collapsed', collapsed ? 'false' : 'true');
      ev.target.textContent = collapsed ? (window.ROROMAG_I18N && ROROMAG_I18N.read_less ? ROROMAG_I18N.read_less : 'Read less') :
                                          (window.ROROMAG_I18N && ROROMAG_I18N.read_more ? ROROMAG_I18N.read_more : 'Read more');
    }
  });
})();
