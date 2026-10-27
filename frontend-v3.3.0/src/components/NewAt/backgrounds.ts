import type { NewAtBackground } from "@/components/NewAt/types";

/** Ready-to-use backgrounds; layouts and backgrounds can be mixed freely. */
export const NEW_AT_BACKGROUNDS = {
  color: {
    type: "color",
    value:
      "linear-gradient(135deg, #fff0f6 0%, #f8efff 48%, #edf8ff 100%)",
  },
  image: {
    type: "image",
    imageUrl: "/images/new-at/new-at-background.webp",
    position: "center top",
  },
  marketPaper: {
    type: "image",
    imageUrl: "/images/new-at/backgrounds/market-paper.webp",
    position: "center",
  },
  homeBotanical: {
    type: "image",
    imageUrl: "/images/new-at/backgrounds/home-botanical.webp",
    position: "center",
  },
  plain: {
    type: "plain",
    value: "#ffffff",
  },
} as const satisfies Record<string, NewAtBackground>;
