/** Runs exclusively in development, on a dedicated preview origin. */
export async function prepareDevPreview() {
  document.title = `[DEV] ${document.title}`;
  if ('serviceWorker' in navigator) {
    const registrations = await navigator.serviceWorker.getRegistrations();
    // The preview must use a separate origin from the published application.
    await Promise.all(registrations.map(registration => registration.unregister()));
    if (navigator.serviceWorker.controller) {
      const key = 'padaria-dev-sw-reload';
      if (!sessionStorage.getItem(key)) {
        sessionStorage.setItem(key, '1');
        location.reload();
        return;
      }
    } else {
      sessionStorage.removeItem('padaria-dev-sw-reload');
    }
  }
  if ('caches' in window) {
    const keys = await caches.keys();
    await Promise.all(keys.filter(key => key.startsWith('padaria-')).map(key => caches.delete(key)));
  }
}
