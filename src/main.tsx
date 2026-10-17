import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import './style.css';
import './bakery-theme.css';
async function mount() {
  if (import.meta.env.DEV) {
    const { prepareDevPreview } = await import('./dev-preview');
    await prepareDevPreview();
  }
  createRoot(document.getElementById('root')!).render(<React.StrictMode><App/></React.StrictMode>);
}
void mount();
if (import.meta.env.PROD) {
  void import('./pwa').then(({ startPwa }) => startPwa());
}
