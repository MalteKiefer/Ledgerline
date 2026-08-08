import 'vuetify/styles';
import { createVuetify } from 'vuetify';
import { md3 } from 'vuetify/blueprints';
import { aliases, mdi } from 'vuetify/iconsets/mdi-svg';

// Material Design 3, violet seed #6750a4 (matches the tokens in resources/css/app.css).
// SVG icon set (@mdi/js) — treeshaken, self-hosted, no webfont, no CDN.
const light = {
  dark: false,
  colors: {
    primary: '#6750a4',
    'on-primary': '#ffffff',
    secondary: '#625b71',
    surface: '#fdfcff',
    'surface-variant': '#e7e0ec',
    'on-surface': '#1f1d24',
    'on-surface-variant': '#5b5766',
    background: '#fdfcff',
    error: '#ba1a1a',
    success: '#3f6f43',
    warning: '#8a5a00',
    outline: '#79747e',
  },
};

const dark = {
  dark: true,
  colors: {
    primary: '#cfbcff',
    'on-primary': '#381e72',
    secondary: '#cbc2db',
    surface: '#1c1b1f',
    'surface-variant': '#49454f',
    'on-surface': '#e6e1e9',
    'on-surface-variant': '#cac4d0',
    background: '#141218',
    error: '#ffb4ab',
    success: '#a6d6a9',
    warning: '#ffb95c',
    outline: '#938f99',
  },
};

export const vuetify = createVuetify({
  blueprint: md3,
  icons: { defaultSet: 'mdi', aliases, sets: { mdi } },
  theme: { defaultTheme: 'light', themes: { light, dark } },
});
