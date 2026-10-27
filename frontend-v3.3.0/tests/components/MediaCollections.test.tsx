import { fireEvent, render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";

import { formatCompactMetric } from "@/components/Media";
import { ReelCollection } from "@/components/Reels";
import { HOME_REELS } from "@/components/Reels/reelData";
import { StoryTray } from "@/components/Stories";
import { HOME_STORIES } from "@/components/Stories/storyData";

vi.mock("react-i18next", async (importOriginal) => {
  const actual = await importOriginal<typeof import("react-i18next")>();
  return {
    ...actual,
    useTranslation: () => ({
      t: (key: string, options?: Record<string, unknown>) =>
        String(options?.defaultValue ?? key)
          .replace("{{count}}", String(options?.count ?? ""))
          .replace("{{title}}", String(options?.title ?? ""))
          .replace("{{publisher}}", String(options?.publisher ?? "")),
    }),
  };
});

describe("standalone media collections", () => {
  it("renders and opens a Story tray without the section facade", () => {
    const onOpen = vi.fn();
    const group = HOME_STORIES[0];

    render(
      <StoryTray
        entries={[
          {
            group,
            progress: {
              total: group.items.length,
              seen: 0,
              unseen: group.items.length,
              state: "unseen",
            },
          },
        ]}
        label="Standalone stories"
        onOpen={onOpen}
      />,
    );

    expect(screen.getByRole("list", { name: "Standalone stories" })).toHaveClass(
      "touch-auto",
    );
    expect(screen.getByRole("list", { name: "Standalone stories" })).not.toHaveClass(
      "touch-pan-x",
    );
    fireEvent.click(screen.getByRole("button", { name: /Fresh Finds/i }));
    expect(onOpen).toHaveBeenCalledWith(group);
  });

  it("renders and opens a Reel collection without the section facade", () => {
    const onOpen = vi.fn();
    const reel = HOME_REELS[0];

    render(
      <ReelCollection
        label="Standalone reels"
        layout="grid"
        reels={[reel]}
        onOpen={onOpen}
      />,
    );

    fireEvent.click(
      screen.getByRole("button", {
        name: `Open ${reel.title} by ${reel.publisher}`,
      }),
    );
    expect(onOpen).toHaveBeenCalledWith(reel);
  });

  it("uses one compact metric formatter across both media domains", () => {
    expect(formatCompactMetric(999)).toBe("999");
    expect(formatCompactMetric(1200)).toBe("1.2K");
    expect(formatCompactMetric(12_400)).toBe("12K");
  });
});
