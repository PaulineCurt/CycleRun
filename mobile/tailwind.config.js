/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["./src/**/*.{js,jsx,ts,tsx}"],
  presets: [require("nativewind/preset")],
  theme: {
    extend: {
      colors: {
        bg: "#F5FBFA",
        surface: "#FFFFFF",
        ink: "#12302B",
        accent: "#FF6355",
        danger: "#C4292E",
        phase: {
          menstrual: "#FF5D8F",
          follicular: "#FF9F1C",
          ovulation: "#2EC4B6",
          luteal: "#6A4C93",
        },
      },
    },
  },
  plugins: [],
};
