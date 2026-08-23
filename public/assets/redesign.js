(() => {
  const scriptUrl = document.currentScript?.src || new URL('assets/redesign.js', document.baseURI).href;
  const appRoot = new URL('../', scriptUrl);
  const compatHref = new URL('assets/redesign-compat.css', appRoot).href;
  if (!document.querySelector(`link[href="${compatHref}"]`)) {
    const compat = document.createElement('link'); compat.rel = 'stylesheet'; compat.href = compatHref; document.head.appendChild(compat);
  }

  const drawer = document.querySelector('[data-pg-drawer]');
  const toggle = document.querySelector('[data-pg-menu-toggle]');
  if (drawer && toggle) {
    const close = () => { drawer.classList.remove('is-open'); toggle.setAttribute('aria-expanded', 'false'); };
    toggle.addEventListener('click', () => { const open = !drawer.classList.contains('is-open'); drawer.classList.toggle('is-open', open); toggle.setAttribute('aria-expanded', open ? 'true' : 'false'); });
    drawer.querySelectorAll('a').forEach((link) => link.addEventListener('click', close));
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); });
  }

  document.querySelectorAll('[data-confirm]').forEach((element) => {
    element.addEventListener('click', (event) => { if (!window.confirm(element.getAttribute('data-confirm') || 'Are you sure?')) event.preventDefault(); });
  });

  let deferredInstallPrompt = null;
  const installButtons = [...document.querySelectorAll('[data-pg-install]')];
  window.addEventListener('beforeinstallprompt', (event) => { event.preventDefault(); deferredInstallPrompt = event; installButtons.forEach((button) => { button.hidden = false; }); });
  installButtons.forEach((button) => button.addEventListener('click', async () => {
    if (!deferredInstallPrompt) return;
    deferredInstallPrompt.prompt(); await deferredInstallPrompt.userChoice.catch(() => null); deferredInstallPrompt = null;
    installButtons.forEach((item) => { item.hidden = true; });
  }));
  window.addEventListener('appinstalled', () => { deferredInstallPrompt = null; installButtons.forEach((button) => { button.hidden = true; }); });

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const registerWorker = async () => {
    if (!('serviceWorker' in navigator) || !document.documentElement.hasAttribute('data-pwa')) return null;
    const swUrl = new URL('service-worker.js', appRoot);
    return navigator.serviceWorker.register(swUrl.href, { scope: appRoot.pathname });
  };
  if ('serviceWorker' in navigator && document.documentElement.hasAttribute('data-pwa')) {
    window.addEventListener('load', () => { registerWorker().catch(() => {}); });
  }

  const decodeVapidKey = (value) => {
    const padding = '='.repeat((4 - value.length % 4) % 4);
    const base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = atob(base64); return Uint8Array.from([...raw].map((char) => char.charCodeAt(0)));
  };
  const setPushStatus = (root, text, tone = '') => {
    const output = root?.querySelector('[data-push-status]'); if (!output) return;
    output.textContent = text; output.dataset.tone = tone; output.setAttribute('role', 'status');
  };

  document.querySelectorAll('[data-push-controls]').forEach((root) => {
    const enable = root.querySelector('[data-push-enable]');
    const disable = root.querySelector('[data-push-disable]');
    const publicKey = root.getAttribute('data-push-public-key') || '';
    const storeUrl = root.getAttribute('data-push-store') || '';
    const removeUrl = root.getAttribute('data-push-remove') || '';

    if (!('Notification' in window) || !('PushManager' in window) || !publicKey) {
      if (enable) enable.disabled = true;
      setPushStatus(root, publicKey ? 'Push notifications are not supported by this browser.' : 'Push delivery is not configured on this installation.');
      return;
    }

    enable?.addEventListener('click', async () => {
      enable.disabled = true; setPushStatus(root, 'Requesting browser permission…');
      try {
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') throw new Error('Permission was not granted.');
        const registration = await registerWorker();
        if (!registration) throw new Error('Service worker is unavailable.');
        let subscription = await registration.pushManager.getSubscription();
        if (!subscription) subscription = await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: decodeVapidKey(publicKey) });
        const json = subscription.toJSON();
        const response = await fetch(storeUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ endpoint: subscription.endpoint, p256dh: json.keys?.p256dh, auth: json.keys?.auth }) });
        if (!response.ok) throw new Error('Private Gather could not save this browser subscription.');
        setPushStatus(root, 'Push notifications are enabled on this browser.', 'good');
        if (disable) disable.hidden = false;
      } catch (error) { setPushStatus(root, error?.message || 'Push enrollment failed.', 'bad'); }
      finally { enable.disabled = false; }
    });

    disable?.addEventListener('click', async () => {
      disable.disabled = true; setPushStatus(root, 'Disabling push notifications…');
      try {
        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.getSubscription();
        if (subscription) {
          const endpoint = subscription.endpoint;
          await fetch(removeUrl, { method: 'DELETE', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ endpoint }) });
          await subscription.unsubscribe();
        }
        setPushStatus(root, 'Push notifications are disabled on this browser.', 'good');
        disable.hidden = true;
      } catch (error) { setPushStatus(root, error?.message || 'Push could not be disabled.', 'bad'); }
      finally { disable.disabled = false; }
    });
  });
})();
