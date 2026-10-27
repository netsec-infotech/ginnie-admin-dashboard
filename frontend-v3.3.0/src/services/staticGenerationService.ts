import {
  getBrands,
  getCategories,
  getSubCategories,
  getDeliveryZones,
  getHomeLayout,
  getProductBySlug,
  getProducts,
  getSections,
  getSettings,
  getStores,
} from "@/routes/api";
import {
  getConfiguredDefaultLocation,
  getWebSettings,
} from "@/helpers/getters";
import { Brand, Category, Product, Settings, Store, type PaginatedResponse } from "@/types/ApiResponse";
import { resolve } from "node:path";

type PaginatedEnvelope<T> = {
  success: boolean;
  message?: string;
  data?: {
    current_page?: number;
    data?: T[];
    per_page?: number;
    total?: number;
  };
};

export type StaticGenerationContext = {
  settings: Settings;
  latitude: string;
  longitude: string;
};

const PAGE_SIZE = 100;
const MAX_PAGES = 500;
const MAX_ATTEMPTS = 2;
const DEFAULT_TIMEOUT_MS = 10000;

const getProductSnapshotPath = () =>
  process.env.HYPERLOCAL_STATIC_PRODUCT_SNAPSHOT
    ? resolve(process.env.HYPERLOCAL_STATIC_PRODUCT_SNAPSHOT)
    : resolve(
        process.cwd(),
        "node_modules",
        ".cache",
        "hyperlocal-static-products.json",
      );

export const readStaticProductSnapshot = async (
  filename = getProductSnapshotPath(),
): Promise<Product[] | null> => {
  const { readFile } = await import("node:fs/promises");

  try {
    const snapshot = JSON.parse(await readFile(filename, "utf8"));
    if (!Array.isArray(snapshot?.products)) {
      throw new Error("snapshot does not contain a products array");
    }
    return snapshot.products as Product[];
  } catch (error) {
    if (
      error &&
      typeof error === "object" &&
      "code" in error &&
      error.code === "ENOENT"
    ) {
      return null;
    }
    throw new Error(
      `Static product snapshot is invalid: ${
        error instanceof Error ? error.message : String(error)
      }`,
    );
  }
};

let contextPromise: Promise<StaticGenerationContext> | undefined;
let productsPromise: Promise<Product[]> | undefined;
let brandsPromise: Promise<Brand[]> | undefined;
let categoriesPromise: Promise<Category[]> | undefined;
let storesPromise: Promise<Store[]> | undefined;
let featureSectionsPromise:
  | Promise<Array<{ slug: string; title: string }>>
  | undefined;
let homeLayoutSectionsPromise:
  | Promise<Array<{ id: string; title: string }>>
  | undefined;

const wait = (milliseconds: number) =>
  new Promise((resolve) => setTimeout(resolve, milliseconds));

export const getStaticApiResponse = async <
  T extends { success: boolean; message?: string },
>(
  label: string,
  request: () => Promise<T>,
  options: { maxAttempts?: number; timeoutMs?: number } = {},
): Promise<T> => {
  const maxAttempts = options.maxAttempts ?? MAX_ATTEMPTS;
  const timeoutMs = options.timeoutMs ?? DEFAULT_TIMEOUT_MS;
  let lastError = "unknown API error";

  for (let attempt = 1; attempt <= maxAttempts; attempt += 1) {
    let timer: NodeJS.Timeout | undefined;
    try {
      const timeoutPromise = new Promise<never>((_, reject) => {
        timer = setTimeout(
          () => reject(new Error(`Timeout after ${timeoutMs}ms for ${label}`)),
          timeoutMs,
        );
      });
      const response = await Promise.race([request(), timeoutPromise]);
      if (timer) clearTimeout(timer);
      if (response.success) return response;
      lastError = response.message || lastError;
    } catch (error) {
      if (timer) clearTimeout(timer);
      lastError = error instanceof Error ? error.message : String(error);
    }

    if (attempt < maxAttempts) await wait(attempt * 1000);
  }

  throw new Error(
    `Static generation could not load ${label} after ${maxAttempts} attempts: ${lastError}`,
  );
};

/**
 * Catalog APIs are location-scoped. Static pages and the sitemap must use the
 * same configured default location or a deploy can quietly publish an empty
 * catalog. Fail the build when that contract is unavailable.
 */
export const getStaticGenerationContext = async (): Promise<StaticGenerationContext> => {
  if (!contextPromise) {
    contextPromise = (async () => {
      const response = await getStaticApiResponse("settings", () =>
        getSettings(),
      );
      if (!response.data) {
        throw new Error(
          `Static generation could not load settings: ${response.message || "unknown API error"}`,
        );
      }

      const location = getConfiguredDefaultLocation(
        getWebSettings(response.data),
      );
      if (!location) {
        throw new Error(
          "Static generation requires valid defaultLatitude/defaultLongitude web settings.",
        );
      }

      return {
        settings: response.data,
        latitude: String(location.lat),
        longitude: String(location.lng),
      };
    })();
  }

  return contextPromise;
};

const collectPaginated = async <T>(
  label: string,
  fetchPage: (page: number, perPage: number) => Promise<PaginatedEnvelope<T>>,
): Promise<T[]> => {
  const items: T[] = [];

  for (let page = 1; page <= MAX_PAGES; page += 1) {
    const response = await getStaticApiResponse(`${label} page ${page}`, () =>
      fetchPage(page, PAGE_SIZE),
    );
    if (!response.data) {
      throw new Error(
        `Static generation could not load ${label}: ${response.message || "unknown API error"}`,
      );
    }

    const batch = Array.isArray(response.data.data) ? response.data.data : [];
    items.push(...batch);

    const total = Number(response.data.total ?? items.length);
    const responsePageSize = Number(
      response.data.per_page ?? batch.length ?? PAGE_SIZE,
    );
    const lastPage = responsePageSize > 0
      ? Math.max(1, Math.ceil(total / responsePageSize))
      : page;

    if (batch.length === 0 || items.length >= total || page >= lastPage) {
      return items;
    }
  }

  throw new Error(
    `Static generation aborted ${label} pagination after ${MAX_PAGES} pages.`,
  );
};

const uniqueStrings = (values: Array<string | null | undefined>): string[] =>
  [...new Set(values.map((value) => value?.trim()).filter(Boolean) as string[])];

export const getStaticCategories = async (): Promise<Category[]> => {
  if (!categoriesPromise || process.env.NODE_ENV === "development") {
    categoriesPromise = (async () => {
      const { latitude, longitude } = await getStaticGenerationContext();
      const [mainCategories, subCategories] = await Promise.all([
        collectPaginated("categories", (page, per_page) =>
          getCategories({ page, per_page, latitude, longitude }),
        ),
        collectPaginated("subcategories", (page, per_page) =>
          getSubCategories({ page, per_page, latitude, longitude }),
        ).catch(() => [] as Category[]),
      ]);

      const map = new Map<string, Category>();
      for (const cat of [...mainCategories, ...subCategories]) {
        if (cat.slug && !map.has(cat.slug)) {
          map.set(cat.slug, cat);
        }
      }
      return Array.from(map.values());
    })();
  }
  return categoriesPromise;
};

export const getStaticCategory = async (
  slug: string,
): Promise<Category | null> => {
  const categories = await getStaticCategories();
  return categories.find((c) => c.slug === slug) ?? null;
};

export const getStaticCategorySlugs = async (): Promise<string[]> =>
  uniqueStrings((await getStaticCategories()).map((category) => category.slug));

/** Reuse the build's location-scoped catalog instead of querying products for every category page. */
export const getStaticCategoryProducts = async (
  slug: string,
  perPage: number,
): Promise<PaginatedResponse<Product[]>> => {
  const [categories, products] = await Promise.all([
    getStaticCategories(),
    getStaticProducts(),
  ]);
  const category = categories.find((item) => item.slug === slug);
  if (!category) throw new Error(`Static category ${slug} is missing from the catalog.`);

  const categoryIds = new Set([category.id]);
  // The API's include_child_categories=1 includes nested descendants too.
  // Iterate until no more descendants are found; the set also handles cycles.
  let previousSize = -1;
  while (categoryIds.size !== previousSize) {
    previousSize = categoryIds.size;
    for (const item of categories) {
      if (item.parent_id !== null && categoryIds.has(item.parent_id)) {
        categoryIds.add(item.id);
      }
    }
  }

  const matchingProducts = products.filter((product) =>
    categoryIds.has(product.category_id),
  );
  return {
    success: true,
    message: "",
    data: {
      current_page: 1,
      per_page: perPage,
      last_page: Math.max(1, Math.ceil(matchingProducts.length / perPage)),
      total: matchingProducts.length,
      data: matchingProducts.slice(0, perPage),
    },
  };
};

export const getStaticBrands = async (): Promise<Brand[]> => {
  if (!brandsPromise || process.env.NODE_ENV === "development") {
    brandsPromise = (async () => {
      const { latitude, longitude } = await getStaticGenerationContext();
      const [globalBrands, categoryBrands] = await Promise.all([
        collectPaginated("global brands", (page, per_page) =>
          getBrands({ page, per_page, latitude, longitude }),
        ),
        collectPaginated("category-scoped brands", (page, per_page) =>
          getBrands({
            page,
            per_page,
            latitude,
            longitude,
            scope_category_slug: "all",
          }),
        ),
      ]);
      const map = new Map<string, Brand>();
      for (const brand of [...globalBrands, ...categoryBrands]) {
        const slug = brand.slug?.trim();
        if (slug && !map.has(slug)) {
          map.set(slug, brand);
        }
      }
      return [...map.values()];
    })();
  }
  return brandsPromise;
};

export const getStaticBrand = async (slug: string): Promise<Brand | null> => {
  const brands = await getStaticBrands();
  return brands.find((brand) => brand.slug === slug) ?? null;
};

export const getStaticBrandSlugs = async (): Promise<string[]> =>
  uniqueStrings((await getStaticBrands()).map((brand) => brand.slug));

export const readStaticStoreSnapshot = async (
  filename = process.env.HYPERLOCAL_STATIC_STORE_SNAPSHOT || resolve(process.cwd(), "node_modules", ".cache", "hyperlocal-static-stores.json"),
): Promise<Store[] | null> => {
  const { readFile } = await import("node:fs/promises");
  try {
    const snapshot = JSON.parse(await readFile(filename, "utf8"));
    if (!Array.isArray(snapshot.stores)) throw new Error("Invalid store build snapshot");
    return snapshot.stores;
  } catch (error) {
    if (error && typeof error === "object" && "code" in error && error.code === "ENOENT") return null;
    throw error;
  }
};

export const getStaticStores = async (): Promise<Store[]> => {
  if (!storesPromise || process.env.NODE_ENV === "development") {
    storesPromise = (async () => {
      const snapshot = process.env.NODE_ENV === "production" && process.env.NEXT_PUBLIC_SSR !== "true" ? await readStaticStoreSnapshot() : null;
      if (snapshot) return snapshot;
      const { latitude, longitude } = await getStaticGenerationContext();
      return collectPaginated("stores", (page, per_page) => getStores({ page, per_page, latitude, longitude }));
    })();
  }
  return storesPromise;
};

export const getStaticStoreSlugs = async (): Promise<string[]> =>
  uniqueStrings((await getStaticStores()).map((store) => store.slug));

export const getStaticStore = async (slug: string): Promise<Store | null> =>
  (await getStaticStores()).find((store) => store.slug === slug) ?? null;

export const getStaticProducts = async (): Promise<Product[]> => {
  // next dev reruns getStaticPaths, but keeps this module alive. Its path list
  // must reflect admin changes instead of retaining the first catalog snapshot.
  if (!productsPromise || process.env.NODE_ENV === "development") {
    productsPromise = (async () => {
      const snapshot = process.env.NODE_ENV === "development"
        ? null
        : await readStaticProductSnapshot();
      if (snapshot) return snapshot;

      const { latitude, longitude } = await getStaticGenerationContext();
      return collectPaginated("products", (page, per_page) =>
        getProducts({
          page,
          per_page,
          latitude,
          longitude,
          include_child_categories: 0,
        }),
      );
    })();
  }
  return productsPromise;
};

export const getStaticProductSlugs = async (): Promise<string[]> =>
  uniqueStrings((await getStaticProducts()).map((product) => product.slug));

/** Build from the catalog snapshot; development and ISR check the live endpoint. */
export const getStaticProduct = async (
  slug: string,
  refresh = false,
): Promise<Product | null> => {
  if (!refresh && process.env.NODE_ENV !== "development") {
    return (await getStaticProducts()).find((product) => product.slug === slug) ?? null;
  }

  const { latitude, longitude } = await getStaticGenerationContext();
  const response = await getProductBySlug({ slug, latitude, longitude });
  // Throwing during ISR preserves the last successful page and allows a retry.
  // A temporary backend outage must not replace that page with a cached 404.
  if (!response.success && response.retryable) {
    throw new Error("Product data is temporarily unavailable");
  }
  return response.success && response.data && !Array.isArray(response.data)
    ? response.data
    : null;
};

export const getStaticDeliveryZoneIdentifiers = async (): Promise<string[]> => {
  const zones = await collectPaginated("delivery zones", (page, per_page) =>
    getDeliveryZones({ page, per_page }),
  );
  return uniqueStrings(zones.map((zone) => String(zone.id)));
};

export const getStaticFeatureSections = async (): Promise<
  Array<{ slug: string; title: string }>
> => {
  if (!featureSectionsPromise) {
    featureSectionsPromise = (async () => {
      const { latitude, longitude } = await getStaticGenerationContext();
      const sections = await collectPaginated(
        "feature sections",
        (page, per_page) =>
          getSections({ page, per_page, latitude, longitude }),
      );

      const bySlug = new Map<string, string>();
      for (const section of sections) {
        const slug = section.slug?.trim();
        if (slug) bySlug.set(slug, section.title || slug);
      }
      return [...bySlug].map(([slug, title]) => ({ slug, title }));
    })();
  }
  return featureSectionsPromise;
};

export const getStaticHomeLayoutSections = async (): Promise<
  Array<{ id: string; title: string }>
> => {
  if (!homeLayoutSectionsPromise) {
    homeLayoutSectionsPromise = (async () => {
      const { latitude, longitude } = await getStaticGenerationContext();
      const sections = new Map<string, string>();

      for (let page = 1; page <= MAX_PAGES; page += 1) {
        const response = await getStaticApiResponse(
          `home-layout sections page ${page}`,
          () =>
            getHomeLayout({
            page,
            per_page: PAGE_SIZE,
            latitude,
            longitude,
            }),
        );
        if (!response.data) {
          throw new Error(
            `Static generation could not load home-layout sections: ${response.message || "unknown API error"}`,
          );
        }

        for (const section of response.data.sections ?? []) {
          sections.set(String(section.id), section.title || "Products");
        }

        if (page >= (response.data.last_page || 1)) break;
      }

      return [...sections].map(([id, title]) => ({ id, title }));
    })();
  }
  return homeLayoutSectionsPromise;
};
