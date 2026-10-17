import { registerSW } from 'virtual:pwa-register';

// Updates are offered explicitly so an active order isn't interrupted.
export function startPwa() {
  let banner: HTMLDivElement | undefined;
  const updateSW = registerSW({
    immediate: true,
    onNeedRefresh() {
      if (banner) return;
      banner = document.createElement('div');
      banner.className = 'pwa-update';
      banner.setAttribute('role', 'status');
      const message = document.createElement('span');
      message.textContent = 'Nova versão disponível. Os pedidos salvos serão mantidos.';
      const update = document.createElement('button');
      update.textContent = 'Atualizar agora';
      update.onclick = () => { void updateSW(true); };
      const later = document.createElement('button');
      later.textContent = 'Depois';
      later.onclick = () => { banner?.remove(); banner = undefined; };
      banner.append(message, update, later);
      document.body.append(banner);
    },
    onRegisteredSW(_url, registration) {
      if (!registration) return;
      const check = () => {
        if (navigator.onLine) void registration.update().catch(() => {});
      };
      window.setInterval(check, 60_000);
      window.addEventListener('online', check);
      document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') check();
      });
    },
    onRegisterError(error) { console.warn('PWA indisponível; o aplicativo continua no navegador.', error); },
  });
}
