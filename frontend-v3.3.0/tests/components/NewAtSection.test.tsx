import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";

import {
  NEW_AT_BACKGROUNDS,
  NewAtSection,
  type NewAtItem,
  type NewAtVariant,
  type NewAtVerticalVariant,
} from "@/components/NewAt";
import { SettingsProvider } from "@/contexts/SettingsContext";
import type { Settings, WebSettings } from "@/types/ApiResponse";
import HomeNewAt from "@/views/homePage/HomeNewAt";
import HomeNewAtShowcase from "@/views/homePage/HomeNewAtShowcase";
import HomeNewAtVerticalShowcase, {
  DEFAULT_HOME_NEW_AT_VERTICAL_SECTIONS,
  HOME_NEW_AT_VERTICAL_SECTIONS,
  type HomeNewAtVerticalSectionConfig,
} from "@/views/homePage/HomeNewAtVerticalShowcase";

const variants: readonly NewAtVariant[] = [
  "grid",
  "horizontal-scroll",
  "featured",
  "compact-grid",
  "mosaic",
  "spotlight",
  "stacked-deck",
  "staggered",
  "alternating",
  "gallery-strip",
  "vertical-grid",
  "vertical-compact",
  "vertical-featured",
  "vertical-mosaic",
];

const verticalVariants: readonly NewAtVerticalVariant[] = [
  "vertical-grid",
  "vertical-compact",
  "vertical-featured",
  "vertical-mosaic",
];

const items: readonly NewAtItem[] = [
  {
    id: "one",
    brand: "Local Brand",
    title: "First arrival",
    description: "The first description.",
    imageUrl: "/images/stories/seller-cafe.webp",
    deepLink: "/stores/",
  },
  {
    id: "two",
    brand: "Neighbourhood Brand",
    title: "Second arrival",
    description: "The second description.",
    imageUrl: "/images/stories/seller-bakery.webp",
    deepLink: "/categories/",
  },
];

describe("NewAtSection", () => {
  it("places the color treatment immediately after the image treatment", () => {
    const { container } = render(<HomeNewAtShowcase siteName="Hyper Local" />);
    const sections = container.querySelectorAll("section");

    expect(sections).toHaveLength(3);
    expect(sections[0]).toHaveAttribute("data-background", "image");
    expect(sections[0]).toHaveAttribute("data-variant", "grid");
    expect(sections[1]).toHaveAttribute("data-background", "color");
    expect(sections[1]).toHaveAttribute("data-variant", "staggered");
    expect(sections[2]).toHaveAttribute("data-background", "image");
    expect(sections[2]).toHaveAttribute("data-variant", "vertical-grid");
    expect(DEFAULT_HOME_NEW_AT_VERTICAL_SECTIONS).toHaveLength(1);
  });

  it("uses the live site name when no explicit title or site name is passed", () => {
    const settings = [
      {
        variable: "web",
        value: {
          siteName: "Live Local",
        } as WebSettings,
      },
    ] as unknown as Settings;

    render(
      <SettingsProvider settings={settings}>
        <HomeNewAt items={items} />
      </SettingsProvider>,
    );

    expect(
      screen.getByRole("heading", { name: "New At Live Local" }),
    ).toBeInTheDocument();
  });

  it("keeps the default vertical backgrounds ordered and config-driven", () => {
    expect(HOME_NEW_AT_VERTICAL_SECTIONS.map(({ background }) => background.type))
      .toEqual(["image", "color", "image", "color"]);
    expect(HOME_NEW_AT_VERTICAL_SECTIONS[0].background).toEqual(
      NEW_AT_BACKGROUNDS.marketPaper,
    );
    expect(HOME_NEW_AT_VERTICAL_SECTIONS[2].background).toEqual(
      NEW_AT_BACKGROUNDS.homeBotanical,
    );

    const { container } = render(
      <HomeNewAtVerticalShowcase sections={HOME_NEW_AT_VERTICAL_SECTIONS} />,
    );
    const backgrounds = container.querySelectorAll(
      'section[data-variant^="vertical-"] img[alt=""]',
    );

    expect(backgrounds).toHaveLength(2);
    expect(backgrounds[0].getAttribute("src")).toContain(
      encodeURIComponent("/images/new-at/backgrounds/market-paper.webp"),
    );
    expect(backgrounds[1].getAttribute("src")).toContain(
      encodeURIComponent("/images/new-at/backgrounds/home-botanical.webp"),
    );
  });

  it("can override each background distribution independently", () => {
    const { container } = render(
      <HomeNewAtShowcase
        colorVariant="gallery-strip"
        imageVariant="stacked-deck"
        verticalVariant="vertical-grid"
      />,
    );
    const sections = container.querySelectorAll("section");

    expect(sections[0]).toHaveAttribute("data-variant", "stacked-deck");
    expect(sections[1]).toHaveAttribute("data-variant", "gallery-strip");
    expect(sections[2]).toHaveAttribute("data-variant", "vertical-grid");
  });

  it("renders mixed-category vertical items through reusable section config", () => {
    const { container } = render(<HomeNewAtShowcase siteName="Hyper Local" />);
    const verticalSections = container.querySelectorAll(
      'section[data-variant^="vertical-"]',
    );

    expect(verticalSections).toHaveLength(1);
    verticalSections.forEach((section) => {
      expect(section.querySelectorAll("a")).toHaveLength(8);
    });
    expect(
      screen.getAllByRole("link", {
        name: "Explore Treat & Play Starter Kit by Paw & Whisker",
      }),
    ).toHaveLength(1);
    expect(
      screen.getAllByRole("link", {
        name: "Explore Pantry Pasta Pairing by Tavola Kitchen",
      }),
    ).toHaveLength(1);
  });

  it("accepts replacement items and configuration for the vertical mode", () => {
    const sections: readonly HomeNewAtVerticalSectionConfig[] = [
      {
        id: "custom-mosaic",
        variant: "vertical-mosaic",
        background: NEW_AT_BACKGROUNDS.color,
        title: "Custom new arrivals",
        subtitle: "A replaceable catalogue.",
        items: [items[0]],
      },
    ];
    const { container } = render(
      <HomeNewAtVerticalShowcase items={items} sections={sections} />,
    );

    expect(container.querySelectorAll("section")).toHaveLength(1);
    expect(container.querySelector("section")).toHaveAttribute(
      "data-variant",
      "vertical-mosaic",
    );
    expect(screen.getByRole("heading", { name: "Custom new arrivals" }))
      .toBeInTheDocument();
    expect(screen.getAllByRole("link")).toHaveLength(1);
  });

  it("applies a vertical variant override without discarding custom sections", () => {
    const sections: readonly HomeNewAtVerticalSectionConfig[] = [
      {
        id: "custom-one",
        variant: "vertical-compact",
        background: NEW_AT_BACKGROUNDS.color,
        title: "Custom one",
      },
      {
        id: "custom-two",
        variant: "vertical-featured",
        background: NEW_AT_BACKGROUNDS.color,
        title: "Custom two",
      },
    ];
    const { container } = render(
      <HomeNewAtShowcase
        verticalItems={items}
        verticalSections={sections}
        verticalVariant="vertical-grid"
      />,
    );
    const verticalSections = container.querySelectorAll(
      'section[data-variant^="vertical-"]',
    );

    expect(verticalSections).toHaveLength(sections.length);
    verticalSections.forEach((section) => {
      expect(section).toHaveAttribute("data-variant", "vertical-grid");
    });
  });

  it.each(
    verticalVariants.flatMap((variant) =>
      [1, 5, 17].map((count) => ({ variant, count })),
    ),
  )("keeps all $count items in the $variant layout", ({ variant, count }) => {
    const catalogue = Array.from({ length: count }, (_, index) => ({
      ...items[index % items.length],
      id: `item-${index}`,
      title: `Arrival ${index}`,
    }));

    const { container } = render(
      <NewAtSection items={catalogue} title="Vertical arrivals" variant={variant} />,
    );

    expect(container.querySelectorAll("a")).toHaveLength(count);
  });

  it.each(variants)(
    "renders every item with the %s layout",
    (variant) => {
      const { container } = render(
        <NewAtSection
          items={items}
          subtitle="Recently added"
          title="New At Hyper Local"
          variant={variant}
        />,
      );

      expect(container.querySelector("section")).toHaveAttribute(
        "data-variant",
        variant,
      );
      expect(screen.getAllByRole("link")).toHaveLength(items.length);
    },
  );

  it.each(variants)(
    "supports the image background with the %s layout",
    (variant) => {
      const { container } = render(
        <NewAtSection
          background={NEW_AT_BACKGROUNDS.image}
          items={items}
          title="New At Hyper Local"
          variant={variant}
        />,
      );

      expect(container.querySelector("section")).toHaveAttribute(
        "data-background",
        "image",
      );
      expect(container.querySelector('img[alt=""]')).toBeInTheDocument();
    },
  );

  it.each([NEW_AT_BACKGROUNDS.color, NEW_AT_BACKGROUNDS.plain])(
    "supports the $type background preset",
    (background) => {
      const { container } = render(
        <NewAtSection
          background={background}
          items={items}
          title="New At Hyper Local"
        />,
      );

      expect(container.querySelector("section")).toHaveAttribute(
        "data-background",
        background.type,
      );
      expect(container.querySelector('img[alt=""]')).not.toBeInTheDocument();
    },
  );

  it("gives each full-card link and image an accessible name", () => {
    render(<NewAtSection items={items} title="New At Hyper Local" />);

    expect(
      screen.getByRole("link", {
        name: "Explore First arrival by Local Brand",
      }),
    ).toHaveAttribute("href", "/stores");
    expect(
      screen.getByRole("img", { name: "First arrival by Local Brand" }),
    ).toHaveAttribute("loading", "lazy");
  });

  it("does not leave an empty landmark when there are no items", () => {
    const { container } = render(
      <NewAtSection items={[]} title="New At Hyper Local" />,
    );

    expect(container).toBeEmptyDOMElement();
  });
});
