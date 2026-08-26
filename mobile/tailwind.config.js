/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["./src/**/*.{js,jsx,ts,tsx}"],
  presets: [require("nativewind/preset")],
  theme: {
    extend: {
      colors: {
        craie: "#FBF5EC",
        prune: "#201325",
        corail: "#FF4632",
        phase: {
          menstruelle: "#E41A63",
          folliculaire: "#FF5A2C",
          ovulation: "#FFA81C",
          luteale: "#8B3DE8",
        },
      },
    },
  },
  plugins: [],
};
