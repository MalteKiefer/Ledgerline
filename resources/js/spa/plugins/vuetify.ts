import 'vuetify/styles';
import { createVuetify } from 'vuetify';
import { md3 } from 'vuetify/blueprints';
import { aliases, mdi } from 'vuetify/iconsets/mdi-svg';

// Proton-fresh aesthetic: light, airy, generous whitespace, soft shadows,
// rounded surfaces, a vibrant violet accent (#6d4aff) with a mint-green
// secondary. Dark stays available as a toggle. SVG icon set (@mdi/js).
const light = {
  dark: false,
  colors: {
    background: '#f4f4fb',
    surface: '#ffffff',
    'surface-bright': '#ffffff',
    'surface-light': '#fafaff',
    'surface-variant': '#eeeef7',
    'on-surface-variant': '#6b6880',
    primary: '#6d4aff',
    'on-primary': '#ffffff',
    secondary: '#1ea885',
    'on-secondary': '#ffffff',
    accent: '#6d4aff',
    error: '#e5484d',
    info: '#3b82f6',
    success: '#1ea885',
    warning: '#e5a000',
    'on-background': '#1a1826',
    'on-surface': '#1a1826',
    outline: '#e3e2ee',
    'outline-variant': '#ededf5',
  },
  variables: {
    'border-color': '#e3e2ee',
    'border-opacity': 1,
    'high-emphasis-opacity': 0.92,
    'medium-emphasis-opacity': 0.60,
    'theme-surface': '#ffffff',
  },
};

const dark = {
  dark: true,
  colors: {
    background: '#16151f',
    surface: '#1d1c29',
    'surface-bright': '#26243a',
    'surface-light': '#232235',
    'surface-variant': '#26243a',
    'on-surface-variant': '#a5a1bd',
    primary: '#8a6eff',
    'on-primary': '#ffffff',
    secondary: '#3fd0a8',
    'on-secondary': '#04231a',
    accent: '#8a6eff',
    error: '#ff6b6b',
    info: '#60a5fa',
    success: '#3fd0a8',
    warning: '#f0b849',
    'on-background': '#eceaf6',
    'on-surface': '#eceaf6',
    outline: '#35334a',
    'outline-variant': '#2a2940',
  },
  variables: {
    'border-color': '#35334a',
    'border-opacity': 1,
  },
};

export const vuetify = createVuetify({
  blueprint: md3,
  icons: { defaultSet: 'mdi', aliases, sets: { mdi } },
  theme: {
    // Fresh light look by default (Proton-style); dark stays a toggle.
    defaultTheme: 'light',
    themes: { light, dark },
  },
  defaults: {
    global: { rounded: 'lg' },
    VCard: { rounded: 'xl', elevation: 0 },
    VBtn: { rounded: 'lg' },
    VTextField: { variant: 'outlined', density: 'comfortable' },
    VSelect: { variant: 'outlined', density: 'comfortable' },
    VTextarea: { variant: 'outlined', density: 'comfortable' },
    VChip: { rounded: 'md' },
  },
});
