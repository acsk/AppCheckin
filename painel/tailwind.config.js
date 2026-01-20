/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './App.{js,jsx,ts,tsx}',
    './app/**/*.{js,jsx,ts,tsx}',
    './src/**/*.{js,jsx,ts,tsx}',
  ],
  presets: [require('nativewind/preset')],
  theme: {
    extend: {},
  },
  plugins: [],
  darkMode: 'class', // Desabilita dark mode automático baseado em media query
  corePlugins: {
    darkMode: false, // Desabilita completamente o dark mode se necessário
  },
};
