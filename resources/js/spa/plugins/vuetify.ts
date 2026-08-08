import 'vuetify/styles';
import { createVuetify } from 'vuetify';
import { md3 } from 'vuetify/blueprints';
import { aliases, mdi } from 'vuetify/iconsets/mdi-svg';

// Hacker/nerdy dark aesthetic (Proton / Tutanota vibe): deep near-black canvas,
// low-chroma dark surfaces, a vivid violet primary + a terminal-green accent.
// SVG icon set (@mdi/js) — treeshaken, self-hosted, no webfont, no CDN.
const dark = {
  dark: true,
  colors: {
    background: '#0b0e14',
    surface: '#11151d',
    'surface-bright': '#1c2230',
    'surface-light': '#161b25',
    'surface-variant': '#161b25',
    'on-surface-variant': '#9aa4b2',
    primary: '#a78bfa',            // violet (Proton)
    'on-primary': '#1a1030',
    secondary: '#3fb950',          // terminal green (hacker accent)
    'on-secondary': '#06210c',
    accent: '#3fb950',
    error: '#ff6b6b',
    info: '#58a6ff',
    success: '#3fb950',
    warning: '#e3b341',
    'on-background': '#e6edf3',
    'on-surface': '#e6edf3',
    outline: '#30363d',
    'outline-variant': '#222831',
  },
  variables: {
    'border-color': '#30363d',
    'border-opacity': 1,
    'high-emphasis-opacity': 0.95,
    'medium-emphasis-opacity': 0.68,
    'theme-on-surface': '#e6edf3',
  },
};

const light = {
  dark: false,
  colors: {
    background: '#fdfcff',
    surface: '#ffffff',
    'surface-variant': '#f2eef8',
    'on-surface-variant': '#5b5766',
    primary: '#6750a4',
    'on-primary': '#ffffff',
    secondary: '#2f855a',
    accent: '#2f855a',
    error: '#ba1a1a',
    info: '#1d4ed8',
    success: '#2f855a',
    warning: '#8a5a00',
    'on-surface': '#1f1d24',
    outline: '#79747e',
    'outline-variant': '#e7e0ec',
  },
};

export const vuetify = createVuetify({
  blueprint: md3,
  icons: { defaultSet: 'mdi', aliases, sets: { mdi } },
  theme: {
    // Dark by default (the requested nerdy look); users can still flip to light.
    defaultTheme: 'dark',
    themes: { dark, light },
  },
  defaults: {
    VCard: { rounded: 'lg' },
    VBtn: { rounded: 'lg' },
    VTextField: { variant: 'outlined', density: 'comfortable' },
    VSelect: { variant: 'outlined', density: 'comfortable' },
    VTextarea: { variant: 'outlined', density: 'comfortable' },
  },
});
