/**
 * Colour schemes for the terminal.
 *
 * A terminal is a place people stare at, and the right palette is a matter of
 * eyes and taste rather than of branding — so this offers the schemes people
 * already know by name instead of one house style. Each carries the sixteen
 * ANSI colours, because a shell that prints green for a passing test should
 * print the green its author meant.
 *
 * `dark` says which of the app's themes a scheme belongs with, so the default
 * can follow the interface rather than being chosen twice.
 */
export interface TerminalTheme {
  id: string;
  label: string;
  dark: boolean;
  colors: {
    background: string;
    foreground: string;
    cursor: string;
    selectionBackground: string;
    black: string; red: string; green: string; yellow: string;
    blue: string; magenta: string; cyan: string; white: string;
    brightBlack: string; brightRed: string; brightGreen: string; brightYellow: string;
    brightBlue: string; brightMagenta: string; brightCyan: string; brightWhite: string;
  };
}

export const TERMINAL_THEMES: TerminalTheme[] = [
  {
    id: 'ledgerline',
    label: 'Ledgerline',
    dark: true,
    colors: {
      background: '#16141f', foreground: '#e6e1f0', cursor: '#6d4aff', selectionBackground: '#6d4aff55',
      black: '#241f33', red: '#ff6b81', green: '#3fd28b', yellow: '#e0a11b', blue: '#6d4aff',
      magenta: '#c678dd', cyan: '#4bc9d6', white: '#d5d0e0',
      brightBlack: '#4a4360', brightRed: '#ff8fa1', brightGreen: '#6ee0ab', brightYellow: '#f2c14e',
      brightBlue: '#9b83ff', brightMagenta: '#dda0ea', brightCyan: '#7adde8', brightWhite: '#ffffff',
    },
  },
  {
    id: 'dracula',
    label: 'Dracula',
    dark: true,
    colors: {
      background: '#282a36', foreground: '#f8f8f2', cursor: '#f8f8f2', selectionBackground: '#44475a',
      black: '#21222c', red: '#ff5555', green: '#50fa7b', yellow: '#f1fa8c', blue: '#bd93f9',
      magenta: '#ff79c6', cyan: '#8be9fd', white: '#f8f8f2',
      brightBlack: '#6272a4', brightRed: '#ff6e6e', brightGreen: '#69ff94', brightYellow: '#ffffa5',
      brightBlue: '#d6acff', brightMagenta: '#ff92df', brightCyan: '#a4ffff', brightWhite: '#ffffff',
    },
  },
  {
    id: 'nord',
    label: 'Nord',
    dark: true,
    colors: {
      background: '#2e3440', foreground: '#d8dee9', cursor: '#d8dee9', selectionBackground: '#434c5e',
      black: '#3b4252', red: '#bf616a', green: '#a3be8c', yellow: '#ebcb8b', blue: '#81a1c1',
      magenta: '#b48ead', cyan: '#88c0d0', white: '#e5e9f0',
      brightBlack: '#4c566a', brightRed: '#bf616a', brightGreen: '#a3be8c', brightYellow: '#ebcb8b',
      brightBlue: '#81a1c1', brightMagenta: '#b48ead', brightCyan: '#8fbcbb', brightWhite: '#eceff4',
    },
  },
  {
    id: 'gruvbox-dark',
    label: 'Gruvbox Dark',
    dark: true,
    colors: {
      background: '#282828', foreground: '#ebdbb2', cursor: '#ebdbb2', selectionBackground: '#504945',
      black: '#282828', red: '#cc241d', green: '#98971a', yellow: '#d79921', blue: '#458588',
      magenta: '#b16286', cyan: '#689d6a', white: '#a89984',
      brightBlack: '#928374', brightRed: '#fb4934', brightGreen: '#b8bb26', brightYellow: '#fabd2f',
      brightBlue: '#83a598', brightMagenta: '#d3869b', brightCyan: '#8ec07c', brightWhite: '#ebdbb2',
    },
  },
  {
    id: 'solarized-dark',
    label: 'Solarized Dark',
    dark: true,
    colors: {
      background: '#002b36', foreground: '#839496', cursor: '#93a1a1', selectionBackground: '#073642',
      black: '#073642', red: '#dc322f', green: '#859900', yellow: '#b58900', blue: '#268bd2',
      magenta: '#d33682', cyan: '#2aa198', white: '#eee8d5',
      brightBlack: '#586e75', brightRed: '#cb4b16', brightGreen: '#586e75', brightYellow: '#657b83',
      brightBlue: '#839496', brightMagenta: '#6c71c4', brightCyan: '#93a1a1', brightWhite: '#fdf6e3',
    },
  },
  {
    id: 'tokyo-night',
    label: 'Tokyo Night',
    dark: true,
    colors: {
      background: '#1a1b26', foreground: '#c0caf5', cursor: '#c0caf5', selectionBackground: '#33467c',
      black: '#15161e', red: '#f7768e', green: '#9ece6a', yellow: '#e0af68', blue: '#7aa2f7',
      magenta: '#bb9af7', cyan: '#7dcfff', white: '#a9b1d6',
      brightBlack: '#414868', brightRed: '#f7768e', brightGreen: '#9ece6a', brightYellow: '#e0af68',
      brightBlue: '#7aa2f7', brightMagenta: '#bb9af7', brightCyan: '#7dcfff', brightWhite: '#c0caf5',
    },
  },
  {
    id: 'solarized-light',
    label: 'Solarized Light',
    dark: false,
    colors: {
      background: '#fdf6e3', foreground: '#657b83', cursor: '#586e75', selectionBackground: '#eee8d5',
      black: '#073642', red: '#dc322f', green: '#859900', yellow: '#b58900', blue: '#268bd2',
      magenta: '#d33682', cyan: '#2aa198', white: '#eee8d5',
      brightBlack: '#002b36', brightRed: '#cb4b16', brightGreen: '#586e75', brightYellow: '#657b83',
      brightBlue: '#839496', brightMagenta: '#6c71c4', brightCyan: '#93a1a1', brightWhite: '#fdf6e3',
    },
  },
  {
    id: 'github-light',
    label: 'GitHub Light',
    dark: false,
    colors: {
      background: '#ffffff', foreground: '#24292f', cursor: '#24292f', selectionBackground: '#0969da33',
      black: '#24292f', red: '#cf222e', green: '#116329', yellow: '#4d2d00', blue: '#0969da',
      magenta: '#8250df', cyan: '#1b7c83', white: '#6e7781',
      brightBlack: '#57606a', brightRed: '#a40e26', brightGreen: '#1a7f37', brightYellow: '#633c01',
      brightBlue: '#218bff', brightMagenta: '#a475f9', brightCyan: '#3192aa', brightWhite: '#8c959f',
    },
  },
];

const STORAGE_KEY = 'll_terminal_theme';

/** The remembered choice, or one matching the interface if there is none yet. */
export function preferredTheme(appIsDark: boolean): TerminalTheme {
  const saved = localStorage.getItem(STORAGE_KEY);
  const found = TERMINAL_THEMES.find((th) => th.id === saved);
  if (found) return found;

  return TERMINAL_THEMES.find((th) => th.dark === appIsDark) ?? TERMINAL_THEMES[0];
}

export function rememberTheme(id: string): void {
  localStorage.setItem(STORAGE_KEY, id);
}
