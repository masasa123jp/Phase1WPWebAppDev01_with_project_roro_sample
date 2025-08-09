import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import './styles/global.css';

const container = document.getElementById('root') as HTMLElement;
const root = createRoot(container);
root.render(<App />);

/**
 * Vite injects env variables at build-time. We expose them globally for debugging.
 * Avoid leaking secrets—only public keys should be placed here.
 */
if (import.meta.env.DEV) {
  console.info('Env →', import.meta.env);
}
