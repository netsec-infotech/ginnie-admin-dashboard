import { Lexend_Deca } from "next/font/google";

export const fontSans = Lexend_Deca({
  subsets: ["latin"],
  weight: "variable",
  variable: "--font-sans",
  display: "swap",
  preload: true,
  fallback: ["system-ui", "arial"],
  adjustFontFallback: true,
});

export const fontMono = Lexend_Deca({
  subsets: ["latin"],
  weight: "variable",
  variable: "--font-mono",
  display: "swap",
  preload: true,
  fallback: ["Courier New", "monospace"],
  adjustFontFallback: true,
});
