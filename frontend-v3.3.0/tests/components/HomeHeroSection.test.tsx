import { render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

const language = vi.hoisted(() => ({ value: "en" }));
vi.mock("react-i18next", () => ({
  useTranslation: () => ({
    i18n: { language: language.value, dir: () => language.value === "ar" ? "rtl" : "ltr" },
  }),
}));
beforeEach(() => { language.value = "en"; });

import { HomeLayoutSection } from "@/types/ApiResponse";
import HomeHeroSection, {
  HomeHeroSkeleton,
} from "@/views/homePage/HomeHeroSection";

vi.mock("next/link", () => ({
  default: ({ href, children, ...rest }: any) => (
    <a href={typeof href === "string" ? href : "#"} {...rest}>
      {children}
    </a>
  ),
}));

vi.mock("swiper/react", () => ({
  Swiper: ({ children, className, style, autoplay, pagination, ...rest }: any) => (
    <div
      className={className}
      style={style}
      data-autoplay-delay={autoplay ? String(autoplay.delay) : "off"}
      data-pagination={String(Boolean(pagination))}
      {...rest}
    >
      {children}
    </div>
  ),
  SwiperSlide: ({ children, className }: any) => (
    <div className={className}>{children}</div>
  ),
}));

vi.mock("swiper/modules", () => ({ Autoplay: {}, Pagination: {} }));

const makeHeroSection = (
  overrides: Partial<HomeLayoutSection> = {},
): HomeLayoutSection =>
  ({
    id: 99,
    type: "hero",
    title: "Fresh deals",
    style: "full",
    config: {},
    background_image: null,
    hero_media: null,
    card_background_image: null,
    content: {
      items: [
        {
          id: 1,
          image: "https://cdn.test/hero.png",
          media_type: "image",
          video_url: null,
          link_type: "none",
          link_url: null,
          target_id: null,
          slug: null,
        },
      ],
    },
    ...overrides,
  }) as HomeLayoutSection;

describe("HomeHeroSection", () => {
  it("updates carousel direction when switching Arabic and English", () => {
    const section = makeHeroSection();
    const first = section.content.items![0] as Record<string, unknown>;
    section.content.items = [first, { ...first, id: 2 }];
    const { rerender } = render(<HomeHeroSection section={section} />);
    expect(screen.getByTestId("home-hero-frame")).toHaveAttribute("dir", "ltr");
    language.value = "ar";
    rerender(<HomeHeroSection section={section} />);
    expect(screen.getByTestId("home-hero-frame")).toHaveAttribute("dir", "rtl");
    expect(screen.getByTestId("home-hero-99").style.marginLeft).toBe("calc(50% - 50cqw)");
    expect(screen.getByTestId("home-hero-99").style.marginRight).toBe("calc(50% - 50cqw)");
    language.value = "en";
    rerender(<HomeHeroSection section={section} />);
    expect(screen.getByTestId("home-hero-frame")).toHaveAttribute("dir", "ltr");
  });
  it("uses a full-width responsive banner size by default", () => {
    render(<HomeHeroSection section={makeHeroSection()} />);

    const section = screen.getByTestId("home-hero-99");
    const frame = screen.getByTestId("home-hero-frame");
    const image = screen.getByRole("img", { name: "Fresh deals" });

    expect(section.className).toContain("w-page");
    expect(section.className).not.toContain("w-screen");
    expect(section.className).not.toContain("-mt-4");
    expect(frame.className).toContain("w-full");
    expect(frame.className).toContain("max-w-none");
    expect(frame.className).toContain(
      "aspect-[var(--home-hero-aspect-ratio-mobile)]",
    );
    expect(frame.className).toContain(
      "md:aspect-[var(--home-hero-aspect-ratio-desktop)]",
    );
    expect(frame.className).not.toContain(
      "lg:h-[var(--home-hero-height-desktop)]",
    );
    expect(
      frame.style.getPropertyValue("--home-hero-aspect-ratio-mobile"),
    ).toBe("4 / 3");
    expect(
      frame.style.getPropertyValue("--home-hero-aspect-ratio-desktop"),
    ).toBe("1600 / 630");
    expect(screen.queryByTestId("home-hero-backdrop")).not.toBeInTheDocument();
    expect(image).toHaveStyle({
      objectFit: "cover",
      objectPosition: "center center",
    });
  });

  it("uses the same responsive height for the loading skeleton", () => {
    render(<HomeHeroSkeleton />);

    const section = screen.getByTestId("home-hero-skeleton");
    const skeleton = section.firstElementChild;

    expect(section).toHaveClass("w-page");
    expect(section).not.toHaveClass("-mt-4");
    expect(skeleton).toHaveClass(
      "aspect-[var(--home-hero-aspect-ratio-mobile)]",
      "md:aspect-[var(--home-hero-aspect-ratio-desktop)]",
    );
    expect(skeleton).toHaveStyle({
      "--home-hero-aspect-ratio-mobile": "4 / 3",
      "--home-hero-aspect-ratio-desktop": "1600 / 630",
    });
  });

  it("applies hero config sizing, media fit, focal point, and colour", () => {
    render(
      <HomeHeroSection
        section={makeHeroSection({
          config: {
            background_type: "color",
            background_color: "#102030",
            mobile_height: 180,
            tablet_height: "260px",
            desktop_height: "420px",
            border_radius: 8,
            image_fit: "contain",
            object_position: "left top",
          },
        })}
      />,
    );

    const section = screen.getByTestId("home-hero-99");
    const frame = screen.getByTestId("home-hero-frame");
    const image = screen.getByRole("img", { name: "Fresh deals" });

    expect(section).toHaveStyle("background-color: #102030");
    expect(frame.className).toContain("w-full");
    expect(frame.className).toContain("max-w-none");
    expect(frame.className).toContain(
      "lg:h-[var(--home-hero-height-desktop)]",
    );
    expect(frame.style.getPropertyValue("--home-hero-height-mobile")).toBe(
      "180px",
    );
    expect(frame.style.getPropertyValue("--home-hero-height-tablet")).toBe(
      "260px",
    );
    expect(frame.style.getPropertyValue("--home-hero-height-desktop")).toBe(
      "420px",
    );
    expect(frame.style.borderRadius).toBe("8px");
    expect(frame.style.borderTopLeftRadius).toBe("0px");
    expect(frame.style.borderTopRightRadius).toBe("0px");
    expect(image).toHaveStyle({
      objectFit: "contain",
      objectPosition: "left top",
    });
  });

  it("keeps the header and page edges flush while preserving bottom spacing and radius", () => {
    render(
      <HomeHeroSection
        section={makeHeroSection({
          config: {
            spacing: {
              outer: { top: 12, bottom: "16", left: 0, right: 0 },
              inner: { top: 4, bottom: 6, sides: 8 },
              item_gap: 0,
              border_radius: {
                top_left: 12,
                top_right: 10,
                bottom_left: 8,
                bottom_right: 6,
              },
            },
          },
        })}
      />,
    );

    const section = screen.getByTestId("home-hero-99");
    const frame = screen.getByTestId("home-hero-frame");
    expect(section).toHaveStyle({
      marginTop: "0px",
      marginBottom: "16px",
      marginLeft: "calc(50% - 50cqw)",
      marginRight: "calc(50% - 50cqw)",
    });
    expect(frame).toHaveStyle({
      paddingTop: "0px",
      paddingBottom: "6px",
      paddingLeft: "0px",
      paddingRight: "0px",
      borderTopLeftRadius: "0px",
      borderTopRightRadius: "0px",
      borderBottomLeftRadius: "8px",
      borderBottomRightRadius: "6px",
    });
  });

  it("preserves bottom spacing without opening a gap below the header", () => {
    render(
      <HomeHeroSection
        section={makeHeroSection({
          config: {
            spacing: {
              outer: { top: 40, bottom: 6, left: 0, right: 0 },
              inner: { top: 0, bottom: 0, sides: 0 },
              border_radius: 0,
            },
          },
        })}
      />,
    );

    const section = screen.getByTestId("home-hero-99");
    const frame = screen.getByTestId("home-hero-frame");

    expect(section).toHaveStyle({
      marginTop: "0px",
      marginBottom: "6px",
      marginLeft: "calc(50% - 50cqw)",
      marginRight: "calc(50% - 50cqw)",
    });
    expect(frame.style.paddingTop).toBe("0px");
    expect(frame.style.borderRadius).toBe("");
  });

  it("renders hero_media as the main image and layout items as linked cards", () => {
    render(
      <HomeHeroSection
        section={makeHeroSection({
          style: "image_title",
          config: {
            media_type: "image",
            layout: "items",
            item_source: "categories",
            card_bg_type: "color",
            card_bg_color: "#F1EFE8",
            card_title_color: "#222222",
            spacing: {
              outer: { top: 0, bottom: 12, left: 0, right: 0 },
              inner: { top: 0, bottom: 0, sides: 0 },
              item_gap: 0,
              border_radius: {
                top_left: 12,
                top_right: 12,
                bottom_left: 12,
                bottom_right: 12,
              },
            },
          },
          hero_media: "https://cdn.test/furniture-main.png",
          content: {
            items: [
              {
                id: 283,
                title: "this title",
                image: "https://cdn.test/category-card.jpg",
                default_image: "https://cdn.test/category-fallback.png",
                type: "category",
                target_id: 119,
                slug: "atta-rice",
              },
            ],
          },
        })}
      />,
    );

    expect(screen.getByRole("img", { name: "Fresh deals" })).toHaveAttribute(
      "src",
      "https://cdn.test/furniture-main.png",
    );
    expect(screen.getByRole("img", { name: "this title" })).toHaveAttribute(
      "src",
      "https://cdn.test/category-card.jpg",
    );

    const card = screen.getByTestId("home-hero-item-283");
    expect(card).toHaveAttribute("href", "/categories/atta-rice");
    expect(card).toHaveClass("flex-row", "w-44", "sm:w-52", "lg:w-60");
    expect(card).toHaveStyle({
      backgroundColor: "#F1EFE8",
      color: "#222222",
    });
    expect(screen.getByText("this title")).toHaveStyle({ color: "#222222" });
    expect(screen.getByText("this title").parentElement).toHaveClass(
      "rounded-full",
      "bg-white/90",
    );
    const frame = screen.getByTestId("home-hero-frame");
    const items = screen.getByTestId("home-hero-items");
    expect(items).toHaveStyle({ gap: "0px" });
    expect(items.parentElement).toHaveClass("relative");
    expect(items.parentElement).not.toHaveClass("absolute");
    expect(frame.nextElementSibling).toBe(items.parentElement);
  });

  it("uses hero_media_web_mobile below the mobile breakpoint", () => {
    const { container } = render(
      <HomeHeroSection
        section={makeHeroSection({
          hero_media: "https://cdn.test/hero-desktop.png",
          hero_media_web_mobile: "https://cdn.test/hero-mobile.png",
          content: { items: [] },
        })}
      />,
    );

    const source = container.querySelector(
      "source[data-home-hero-mobile-source]",
    );
    const image = screen.getByRole("img", { name: "Fresh deals" });

    expect(source).toHaveAttribute("media", "(max-width: 767px)");
    expect(source).toHaveAttribute(
      "srcset",
      "https://cdn.test/hero-mobile.png",
    );
    expect(image).toHaveAttribute("src", "https://cdn.test/hero-desktop.png");
    expect(image).toHaveAttribute("data-home-hero-desktop-image");
    expect(image).toHaveClass(
      "object-fill",
      "md:[object-fit:var(--home-hero-desktop-media-fit)]",
    );
    expect(image.style.getPropertyValue("--home-hero-desktop-media-fit")).toBe(
      "cover",
    );
  });

  it("uses image_web_mobile for banner items promoted into the hero", () => {
    const { container } = render(
      <HomeHeroSection
        section={makeHeroSection({
          type: "banners",
          content: {
            items: [
              {
                id: 1,
                image: "https://cdn.test/banner-desktop.png",
                image_web_mobile: "https://cdn.test/banner-mobile.png",
              },
            ],
          },
        })}
      />,
    );

    expect(
      container.querySelector("source[data-home-hero-mobile-source]"),
    ).toHaveAttribute("srcset", "https://cdn.test/banner-mobile.png");
    expect(screen.getByRole("img", { name: "Fresh deals" })).toHaveAttribute(
      "src",
      "https://cdn.test/banner-desktop.png",
    );
    expect(screen.getByRole("img", { name: "Fresh deals" })).toHaveClass(
      "object-fill",
      "md:[object-fit:var(--home-hero-desktop-media-fit)]",
    );
  });

  it("applies carousel timing and pagination config", () => {
    render(
      <HomeHeroSection
        section={makeHeroSection({
          config: {
            autoplay_delay: 3000,
            pagination: false,
          },
          content: {
            items: [
              {
                id: 1,
                image: "https://cdn.test/hero-a.png",
                media_type: "image",
                video_url: null,
                link_type: "none",
                link_url: null,
                target_id: null,
                slug: null,
              },
              {
                id: 2,
                image: "https://cdn.test/hero-b.png",
                media_type: "image",
                video_url: null,
                link_type: "none",
                link_url: null,
                target_id: null,
                slug: null,
              },
            ],
          },
        })}
      />,
    );

    const frame = screen.getByTestId("home-hero-frame");
    expect(frame).toHaveAttribute("data-autoplay-delay", "3000");
    expect(frame).toHaveAttribute("data-pagination", "false");
  });
});
