import '@fontsource-variable/plus-jakarta-sans';
import 'bootstrap-icons/font/bootstrap-icons.css';
import './styles/main.scss';

import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { App } from './app/App';

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <App />
  </StrictMode>,
);
