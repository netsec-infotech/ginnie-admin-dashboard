import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";

import { HomeLayoutSection } from "@/types/ApiResponse";
import HomeHeroSection from "@/views/homePage/HomeHeroSection";

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
  it("uses a full-width responsive banner size by default", () => {
    render(<HomeHeroSection section={makeHeroSection()} />);

    const frame = screen.getByTestId("home-hero-frame");
    const image = screen.getByRole("img", { name: "Fresh deals" });

    expect(frame.className).toContain("w-full");
    expect(frame.className).toContain("max-w-none");
    expect(frame.className).toContain(
      "lg:h-[var(--home-hero-height-desktop)]",
    );
    expect(frame.style.getPropertyValue("--home-hero-height-mobile")).toBe(
      "220px",
    );
    expect(frame.style.getPropertyValue("--home-hero-height-tablet")).toBe(
      "300px",
    );
    expect(frame.style.getPropertyValue("--home-hero-height-desktop")).toBe(
      "500px",
    );
    expect(screen.queryByTestId("home-hero-backdrop")).not.toBeInTheDocument();
    expect(image).toHaveStyle({
      objectFit: "cover",
      objectPosition: "center center",
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
    expect(frame.style.getPropertyValue("--home-hero-height-mobile")).toBe(
      "180px",
    );
    expect(frame.style.getPropertyValue("--home-hero-height-tablet")).toBe(
      "260px",
    );
    expect(frame.style.getPropertyValue("--home-hero-height-desktop")).toBe(
      "420px",
    );
    expect(frame).toHaveStyle({ borderRadius: "8px" });
    expect(image).toHaveStyle({
      objectFit: "contain",
      objectPosition: "left top",
    });
  });

  it("applies nested section spacing and per-corner radius", () => {
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
      marginTop: "12px",
      marginBottom: "16px",
      marginLeft: "0px",
      marginRight: "0px",
    });
    expect(frame).toHaveStyle({
      paddingTop: "4px",
      paddingBottom: "6px",
      paddingLeft: "8px",
      paddingRight: "8px",
      borderTopLeftRadius: "12px",
      borderTopRightRadius: "10px",
      borderBottomLeftRadius: "8px",
      borderBottomRightRadius: "6px",
    });
  });

  it("applies meaningful outer spacing when the radius remains zero", () => {
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
      marginTop: "40px",
      marginBottom: "6px",
      marginLeft: "0px",
      marginRight: "0px",
    });
    expect(frame.style.paddingTop).toBe("");
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
