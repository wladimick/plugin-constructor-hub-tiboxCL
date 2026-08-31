(function () {
  'use strict';

  document.querySelectorAll('[data-hub-header]').forEach(function (root) {
    var bar = root.querySelector('[data-hub-header-bar]');
    var hamburger = root.querySelector('[data-hub-hamburger]');
    var mobileNav = root.querySelector('[data-hub-mobile-nav]');

    if (bar) {
      var onScroll = function () {
        var scrolled = window.scrollY > 40;
        bar.classList.toggle('is-scrolled', scrolled);
      };
      window.addEventListener('scroll', onScroll, { passive: true });
      onScroll();
    }

    if (hamburger && mobileNav) {
      hamburger.addEventListener('click', function () {
        var isOpen = mobileNav.classList.contains('is-open');
        mobileNav.hidden = isOpen;
        mobileNav.classList.toggle('is-open', !isOpen);
        hamburger.setAttribute('aria-expanded', String(!isOpen));
      });
    }

    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      if (mobileNav && mobileNav.classList.contains('is-open')) {
        mobileNav.hidden = true;
        mobileNav.classList.remove('is-open');
        if (hamburger) hamburger.setAttribute('aria-expanded', 'false');
      }
    });
  });
})();
