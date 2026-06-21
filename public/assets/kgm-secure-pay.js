window.addEventListener('DOMContentLoaded', function () {
  var frame = document.getElementById('securepayframe');

  if (!frame || typeof window.iFrameResize !== 'function') {
    return;
  }

  window.iFrameResize({}, '#securepayframe');
});
