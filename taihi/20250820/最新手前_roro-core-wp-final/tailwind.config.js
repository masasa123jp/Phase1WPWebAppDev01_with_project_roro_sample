/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './src/**/*.{ts,tsx}',
    './wp-content/**/*.php' // ブロックエディタ + テーマテンプレート
  ],
  theme: {
    extend: {
      colors: {
        brand: { DEFAULT: '#1C7A6F', light: '#53B6A8', dark: '#0E5349' }
      }
    }
  },
  plugins: [require('daisyui')]
};
