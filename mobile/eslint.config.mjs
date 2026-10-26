// @ts-nocheck — eslint-config-expo ships no type declarations.
// https://docs.expo.dev/guides/using-eslint/
import { defineConfig } from "eslint/config";
import expoConfig from "eslint-config-expo/flat.js";

export default defineConfig([
  expoConfig,
  {
    ignores: ["dist/*"],
  },
  {
    // HTML-entity rule: meaningless in React Native <Text>, and French copy is
    // full of apostrophes.
    rules: { "react/no-unescaped-entities": "off" },
  },
]);
