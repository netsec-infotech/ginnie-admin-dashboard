import { describe, expect, it } from "vitest";

import {
  enrichStoreReelItems,
  getSeenStoryIds,
  toReel,
  toStoreReels,
  toStoreStoryGroup,
  toStoryGroup,
  toStoryTrayGroup,
} from "@/components/WatchAndBuy/adapters";
import { getStoryGroupProgress } from "@/components/Stories/storyProgress";
import type {
  WatchAndBuyProfile,
  WatchAndBuyProfileStoriesData,
} from "@/types/WatchAndBuy";

const profile: WatchAndBuyProfile = {
  id: 1,
  type: "platform",
  username: "hyperlocal",
  bio: "Official updates",
  photo_url: "https://cdn.example.com/profile.webp",
  status: "active",
  has_active_status: true,
  has_unseen_status: true,
};

describe("Watch & Buy adapters", () => {
  it("creates an unhydrated Story tray group from profile metadata", () => {
    const group = toStoryTrayGroup({ profile, status_count: 7 });

    expect(group).toMatchObject({
      id: "hyperlocal",
      publisherType: "admin",
      coverSrc: "https://cdn.example.com/profile.webp",
      itemCount: 7,
      unseenItemCount: 7,
      items: [],
    });
    expect(getStoryGroupProgress(group, {})).toEqual({
      total: 7,
      seen: 0,
      unseen: 7,
      state: "unseen",
    });
  });

  it("maps profile Stories and uses UUIDs as public IDs", () => {
    const response: WatchAndBuyProfileStoriesData = {
      profile,
      items: [
        {
          id: 7,
          uuid: "story-public-uuid",
          source: "admin_direct",
          content_type: "image",
          text: "A nearby update",
          media_url: "https://cdn.example.com/story.webp",
          duration_ms: null,
          seen_by_me: true,
          products: [],
          created_at: "2026-08-29T08:00:00Z",
          expires_at: "2026-08-30T08:00:00Z",
        },
      ],
      pagination: {
        has_more_pages: false,
        next_cursor: null,
        previous_cursor: null,
        per_page: 15,
        total: 1,
      },
    };

    const group = toStoryGroup(response);
    expect(group.items[0]).toMatchObject({
      id: "story-public-uuid",
      apiId: 7,
      seen: true,
      mediaType: "image",
      durationMs: 5_000,
      imageSrc: "https://cdn.example.com/story.webp",
      title: "A nearby update",
    });
    expect(group.items[0].likeCount).toBeUndefined();
    expect(group.items[0].views24h).toBeUndefined();
    expect(getSeenStoryIds(response)).toEqual(["story-public-uuid"]);
  });

  it("links seller Story profiles to their storefront", () => {
    const sellerProfile = {
      ...profile,
      type: "seller" as const,
      username: "daily bake",
      photo_url: "https://cdn.example.com/seller-profile.webp",
      store: {
        id: 9,
        name: "Daily Bake Store",
        slug: "daily-bake-store",
        image: "https://cdn.example.com/daily-bake-store.webp",
      },
    };

    expect(
      toStoryTrayGroup({ profile: sellerProfile, status_count: 1 }),
    ).toMatchObject({
      id: "daily bake",
      title: "Daily Bake Store",
      publisherHref: "/stores/daily-bake-store/",
      coverSrc: "https://cdn.example.com/seller-profile.webp",
      visual: { headline: "Daily Bake Store" },
    });

    expect(
      toStoryTrayGroup({
        profile: { ...sellerProfile, photo_url: null },
        status_count: 1,
      }).coverSrc,
    ).toBe("https://cdn.example.com/daily-bake-store.webp");
  });

  it("maps rich Reel products and preserves backend like state", () => {
    const reel = toReel(
      {
        id: 42,
        uuid: "reel-public-uuid",
        title: "The first batch is out",
        caption: "Warm pastries nearby.",
        profile: { ...profile, type: "seller", username: "daily-bake" },
        poster_url: "https://cdn.example.com/reel.webp",
        video_url: "https://cdn.example.com/reel.mp4",
        duration_seconds: 24,
        distance_label: "850 m",
        delivery_estimate: "15–20 min",
        views_count: 1_270,
        likes_count: 198,
        is_liked: true,
        products: [
          {
            id: 11,
            name: "Butter croissant",
            price: 75,
            compare_at_price: 90,
            rating: "4.7",
            rating_count: 48,
            image_url: "https://cdn.example.com/croissant.webp",
            slug: "butter-croissant",
          },
        ],
      },
      "₹",
    );

    expect(reel).toMatchObject({
      id: "reel-public-uuid",
      apiId: 42,
      durationLabel: "0:24",
      publisherType: "seller",
      likes: 197,
      isLiked: true,
    });
    expect(reel?.products?.[0]).toMatchObject({
      id: "11",
      name: "Butter croissant",
      price: "₹75",
      compareAtPrice: "₹90",
      rating: 4.7,
      ratingCount: 48,
      href: "/products/butter-croissant/",
    });
  });

  it("maps the live nested Reel product contract", () => {
    const reel = toReel(
      {
        id: 1,
        uuid: "98893583-0d0c-431c-8e97-dae1d95356f3",
        slug: "7onaa9yzwwv9",
        caption: "This is Caption",
        source: "admin_direct",
        video_url: "https://cdn.example.com/reel.mp4",
        cover_url: null,
        video_width: 608,
        video_height: 1080,
        duration_ms: 19_467,
        like_count: 1,
        liked_by_me: false,
        effective_primary_variant_id: 2,
        profile,
        products: [
          {
            id: 1,
            sort_order: 0,
            is_primary: true,
            is_effective_primary: true,
            product: {
              id: 5,
              slug: "organic-carrots",
              title: "Organic Carrots",
              image_url: "https://cdn.example.com/carrots.jpg",
            },
            variant: {
              id: 2,
              title: "Organic Carrots",
              sku: "50",
              price: 120,
              special_price: 80,
              stock: 43,
              availability: true,
            },
            store: {
              id: 1,
              slug: "city-square-mart-1",
              name: "City Square Mart",
              distance_km: 4.61,
              distance_label: "4.6 km",
            },
          },
        ],
        published_at: "2026-08-29T11:24:59.000000Z",
      },
      "$",
    );

    expect(reel).toMatchObject({
      id: "98893583-0d0c-431c-8e97-dae1d95356f3",
      durationLabel: "0:19",
      likes: 1,
      isLiked: false,
    });
    expect(reel?.products).toEqual([
      {
        id: "5",
        name: "Organic Carrots",
        price: "$80",
        compareAtPrice: "$120",
        rating: undefined,
        ratingCount: undefined,
        badge: "Featured",
        actionLabel: undefined,
        imageSrc: "https://cdn.example.com/carrots.jpg",
        imagePosition: undefined,
        href: "/products/organic-carrots/",
        store: {
          id: "1",
          slug: "city-square-mart-1",
          name: "City Square Mart",
          distanceKm: 4.61,
          distanceLabel: "4.6 km",
          href: "/stores/city-square-mart-1/",
        },
      },
    ]);
  });

  it("formats a numeric product-store distance when no label is provided", () => {
    const reel = toReel({
      id: 2,
      video_url: "https://cdn.example.com/reel.mp4",
      cover_url: "https://cdn.example.com/reel.webp",
      products: [
        {
          id: 9,
          name: "Nearby item",
          price: 20,
          image_url: "https://cdn.example.com/item.webp",
          store: {
            id: 4,
            name: "Nearby Store",
            distance_km: 2,
          },
        },
      ],
    });

    expect(reel?.products?.[0].store).toMatchObject({
      id: "4",
      name: "Nearby Store",
      distanceKm: 2,
      distanceLabel: "2 km",
    });
  });

  it("rejects Reel records that cannot render playable media", () => {
    expect(toReel({ id: 1, title: "Missing media" })).toBeNull();
  });

  it("maps the compact store-detail Reel contract", () => {
    const reels = toStoreReels(
      {
        id: 11,
        name: "Amit's Beauty Essentials",
        slug: "amits-beauty-essentials",
        logo: "https://cdn.example.com/store-logo.webp",
        reels: [
          {
            id: 15,
            uuid: "store-reel-uuid",
            caption: "A beauty update",
            video_url: "https://cdn.example.com/store-reel.mp4",
            cover_url: "https://cdn.example.com/store-reel.webp",
            duration_ms: 10_006,
            like_count: 1,
            profile: {
              id: 8,
              type: "seller",
              username: "amit-seller",
              status: "active",
            },
            products: [
              {
                product_id: 152,
                product_variant_id: 209,
                store_id: 11,
                name: "Repair shampoo",
                image_url: "https://cdn.example.com/shampoo.webp",
                is_primary: true,
              },
            ],
          },
        ],
      },
      "₹",
    );

    expect(reels[0]).toMatchObject({
      id: "store-reel-uuid",
      apiId: 15,
      publisher: "Amit's Beauty Essentials",
      durationLabel: "0:10",
      likes: 1,
    });
    expect(reels[0]?.products?.[0]).toMatchObject({
      id: "152",
      name: "Repair shampoo",
      imageSrc: "https://cdn.example.com/shampoo.webp",
      price: undefined,
      store: {
        id: "11",
        slug: "amits-beauty-essentials",
        name: "Amit's Beauty Essentials",
        href: "/stores/amits-beauty-essentials/",
      },
    });
  });

  it("keeps the store Reel list while enriching matching product data", () => {
    const compact = {
      id: 15,
      uuid: "store-reel-uuid",
      video_url: "https://cdn.example.com/store-reel.mp4",
      cover_url: "https://cdn.example.com/store-reel.webp",
      products: [{ product_id: 152, name: "Repair shampoo" }],
    };
    const unrelated = {
      id: 99,
      uuid: "another-store-reel",
      video_url: "https://cdn.example.com/unrelated.mp4",
      cover_url: "https://cdn.example.com/unrelated.webp",
    };
    const rich = {
      ...compact,
      liked_by_me: true,
      products: [
        {
          id: 49,
          product: {
            id: 152,
            slug: "repair-shampoo",
            title: "Repair shampoo",
            image_url: "https://cdn.example.com/shampoo.webp",
          },
          variant: {
            id: 209,
            title: "250 ml",
            price: 200,
            special_price: 170,
          },
        },
      ],
    };

    expect(enrichStoreReelItems([compact], [unrelated, rich])).toEqual([rich]);
  });

  it("uses only real store-detail Stories", () => {
    const store = {
      id: 11,
      name: "Amit's Beauty Essentials",
      slug: "amits-beauty-essentials",
      logo: "https://cdn.example.com/store-logo.webp",
      stories: [
        {
          id: 19,
          uuid: "store-story-uuid",
          source: "seller",
          content_type: "image" as const,
          text: "New arrival",
          media_url: "https://cdn.example.com/store-story.webp",
          duration_ms: 6_000,
          seen_by_me: false,
          created_at: "2026-09-20T00:00:00Z",
          expires_at: "2026-09-21T00:00:00Z",
          profile: {
            id: 8,
            type: "seller" as const,
            username: "amit-seller",
            status: "active",
          },
        },
      ],
    };

    expect(toStoreStoryGroup({ ...store, stories: [] })).toBeNull();
    expect(toStoreStoryGroup(store)).toMatchObject({
      id: "amit-seller",
      title: "Amit's Beauty Essentials",
      publisherHref: "/stores/amits-beauty-essentials/",
      coverSrc: "https://cdn.example.com/store-logo.webp",
      itemCount: 1,
      unseenItemCount: 1,
      items: [
        {
          id: "store-story-uuid",
          apiId: 19,
          imageSrc: "https://cdn.example.com/store-story.webp",
          durationMs: 6_000,
        },
      ],
    });
  });
});
