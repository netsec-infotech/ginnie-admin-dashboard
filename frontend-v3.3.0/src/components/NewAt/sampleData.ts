import type { NewAtItem } from "@/components/NewAt/types";

/** Static showcase data. Replace with CMS items later without changing markup. */
export const SAMPLE_NEW_AT_ITEMS: readonly NewAtItem[] = [
  {
    id: "rose-reed-velvet-lip",
    brand: "Rose & Reed",
    title: "Velvet Rose Lip Duo",
    description: "A softly luminous lip pairing with comfortable all-day colour.",
    imageUrl: "/images/new-at/rose-lipsticks.webp",
    deepLink: "/categories/",
  },
  {
    id: "aurelia-lab-glow-serum",
    brand: "Aurelia Lab",
    title: "Golden Hour Serum",
    description: "A lightweight daily concentrate for soft, hydrated radiance.",
    imageUrl: "/images/new-at/serum-duo.webp",
    deepLink: "/stores/",
  },
  {
    id: "lilac-theory-cloud-cream",
    brand: "Lilac Theory",
    title: "Cloud Veil Cream",
    description: "A cushiony moisture cream that leaves skin calm and supple.",
    imageUrl: "/images/new-at/face-cream.webp",
    deepLink: "/stores/",
  },
  {
    id: "maison-sol-petal-eau",
    brand: "Maison Sol",
    title: "Petal Eau de Parfum",
    description: "Airy florals warmed with amber for an effortless signature.",
    imageUrl: "/images/new-at/blush-perfume.webp",
    deepLink: "/categories/",
  },
  {
    id: "verdant-silk-hair-duo",
    brand: "Verdant",
    title: "Soft Silk Hair Duo",
    description: "A nourishing cleanse-and-condition set for weightless shine.",
    imageUrl: "/images/new-at/hair-care-duo.webp",
    deepLink: "/categories/",
  },
];

/** Static mixed-category catalogue used by the multi-row vertical mode. */
export const SAMPLE_NEW_AT_VERTICAL_ITEMS: readonly NewAtItem[] = [
  {
    id: "paw-whisker-pet-wellness",
    brand: "Paw & Whisker",
    title: "Treat & Play Starter Kit",
    description: "Natural dog treats with everyday feeding and play essentials.",
    imageUrl: "/images/new-at/vertical/pet-wellness-kit.webp",
    deepLink: "/categories/",
  },
  {
    id: "tavola-artisan-pasta",
    brand: "Tavola Kitchen",
    title: "Pantry Pasta Pairing",
    description: "Artisan pasta, golden olive oil and herbs for an easy dinner.",
    imageUrl: "/images/new-at/vertical/artisan-pasta-meal.webp",
    deepLink: "/categories/",
  },
  {
    id: "green-basket-groceries",
    brand: "Green Basket",
    title: "Market Day Grocery Box",
    description: "Colourful produce and everyday pantry staples in one box.",
    imageUrl: "/images/new-at/vertical/farm-grocery-box.webp",
    deepLink: "/stores/",
  },
  {
    id: "morning-crumb-breakfast",
    brand: "Morning Crumb",
    title: "Fresh Bread & Croissant Set",
    description: "A bakery selection of crusty sourdough and flaky favourites.",
    imageUrl: "/images/new-at/vertical/bakery-breakfast-box.webp",
    deepLink: "/stores/",
  },
  {
    id: "neat-nest-home-care",
    brand: "Neat Nest",
    title: "Refillable Home Care Set",
    description: "Reusable cleaning basics for an easy everyday refresh.",
    imageUrl: "/images/new-at/vertical/home-cleaning-set.webp",
    deepLink: "/categories/",
  },
  {
    id: "everwell-daily-balance",
    brand: "Everwell",
    title: "Daily Balance Essentials",
    description: "Simple wellness staples made for a calm everyday routine.",
    imageUrl: "/images/new-at/vertical/daily-wellness-set.webp",
    deepLink: "/categories/",
  },
  {
    id: "common-ground-coffee-snacks",
    brand: "Common Ground",
    title: "Small-Batch Coffee Trio",
    description: "Whole beans, cold brew and a warm cup for any coffee moment.",
    imageUrl: "/images/new-at/vertical/coffee-snack-pairing.webp",
    deepLink: "/stores/",
  },
  {
    id: "petal-post-table-bouquet",
    brand: "Petal Post",
    title: "Seasonal Table Bouquet",
    description: "Fresh stems arranged to bring an easy lift to the room.",
    imageUrl: "/images/new-at/vertical/fresh-flower-bouquet.webp",
    deepLink: "/stores/",
  },
];
