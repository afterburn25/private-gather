(() => {
  const scriptUrl = document.currentScript?.src || new URL('assets/redesign.js', document.baseURI).href;
  const appRoot = new URL('../', scriptUrl);
  const drawer = document.querySelector('[data-pg-drawer]');
  const toggle = document.querySelector('[data-pg-menu-toggle]');

  if (drawer && toggle) {
    const close = () => {
      drawer.classList.remove('is-open');
      toggle.setAttribute('aria-expanded', 'false');
    };

    toggle.addEventListener('click', () => {
      const open = !drawer.classList.contains('is-open');
      drawer.classList.toggle('is-open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    drawer.querySelectorAll('a').forEach((link) => link.addEventListener('click', close));
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') close();
    });
  }

  document.querySelectorAll('[data-confirm]').forEach((element) => {
    element.addEventListener('click', (event) => {
      const message = element.getAttribute('data-confirm') || 'Are you sure?';
      if (!window.confirm(message)) event.preventDefault();
    });
  });

  let deferredInstallPrompt = null;
  const installButtons = [...document.querySelectorAll('[data-pg-install]')];
  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredInstallPrompt = event;
    installButtons.forEach((button) => { button.hidden = false; });
  });
  installButtons.forEach((button) => button.addEventListener('click', async () => {
    if (!deferredInstallPrompt) return;
    deferredInstallPrompt.prompt();
    await deferredInstallPrompt.userChoice.catch(() => null);
    deferredInstallPrompt = null;
    installButtons.forEach((item) => { item.hidden = true; });
  }));
  window.addEventListener('appinstalled', () => {
    deferredInstallPrompt = null;
    installButtons.forEach((button) => { button.hidden = true; });
  });

  if ('serviceWorker' in navigator && document.documentElement.hasAttribute('data-pwa')) {
    window.addEventListener('load', () => {
      const swUrl = new URL('service-worker.js', appRoot);
      navigator.serviceWorker.register(swUrl.href, { scope: appRoot.pathname }).catch(() => {});
    });
  }
})();
