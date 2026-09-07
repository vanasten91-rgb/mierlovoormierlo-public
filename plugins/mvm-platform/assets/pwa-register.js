(function () {
  'use strict';

  const config = window.MvMPWA || {};
  if (!('serviceWorker' in navigator) || !window.isSecureContext || !config.serviceWorker) {
    return;
  }

  window.addEventListener('load', function () {
    navigator.serviceWorker.register(String(config.serviceWorker), {
      scope: '/',
      updateViaCache: 'none'
    }).then(function (registration) {
      registration.update().catch(function () {
        // Update checks are best-effort; normal browsing must remain unaffected.
      });
    }).catch(function () {
      // PWA enhancement is optional; a registration failure must never break the site.
    });
  }, { once: true });
}());
