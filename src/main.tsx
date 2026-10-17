import React, { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import Admin from './Admin';
import './admin.css';
function Root() {
  const [hash,setHash]=useState(location.hash);
  useEffect(()=>{const sync=()=>setHash(location.hash);window.addEventListener('hashchange',sync);return()=>window.removeEventListener('hashchange',sync);},[]);
  return hash.startsWith('#admin')?<Admin/>:<App/>;
}
import './style.css';
import './bakery-theme.css';
async function mount() {
  if (import.meta.env.DEV) {
    const { prepareDevPreview } = await import('./dev-preview');
    await prepareDevPreview();
  }
  createRoot(document.getElementById('root')!).render(<React.StrictMode><Root/></React.StrictMode>);
}
void mount();
if (import.meta.env.PROD) {
  void import('./pwa').then(({ startPwa }) => startPwa());
}
