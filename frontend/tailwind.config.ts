import type { Config } from 'tailwindcss';

export default <Partial<Config>>{
  content: ['./index.html', './src/**/*.{ts,tsx}'],
  theme: {
    extend: {
      colors: {
        brand: { DEFAULT: '#1C7A6F', light: '#4EB7A8', dark: '#0E5349' }
      }
    }
  },
  plugins: []
};
