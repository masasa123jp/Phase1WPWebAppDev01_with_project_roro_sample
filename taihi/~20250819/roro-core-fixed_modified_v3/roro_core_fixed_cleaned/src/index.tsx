/**
 * Admin / Block Editor bundle entry for RoRo Core.
 * Loaded via wp_enqueue_script() in PHP.
 */

import React from 'react';
import { createRoot } from 'react-dom/client';
import Dashboard from './components/dashboard/KpiOverview';
import './styles/global.css';

// WP 6.5 admin enqueue: <div id="roro-admin-root"></div>
const container = document.getElementById('roro-admin-root');

if (container) {
  const root = createRoot(container);
  root.render(<Dashboard />);
} else {
  // Gutenberg block context – registerBlockType is available
  import('./blocks').then(({ registerBlocks }) => registerBlocks());
}
