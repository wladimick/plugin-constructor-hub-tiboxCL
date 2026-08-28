(function () {
  'use strict';

  document.querySelectorAll('[data-hub-footer]').forEach(function (root) {
    var prefsLink = root.querySelector('[data-hub-cookie-prefs]');
    if (prefsLink) {
      prefsLink.addEventListener('click', function (e) {
        e.preventDefault();
        document.dispatchEvent(new CustomEvent('hub:cookie-preferences-open'));
      });
    }
  });
})();
