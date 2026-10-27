import {
  act,
  fireEvent,
  render,
  screen,
  waitFor,
} from "@testing-library/react";
import { createInstance } from "i18next";
import { I18nextProvider } from "react-i18next";
import { SWRConfig } from "swr";
import { beforeEach, describe, expect, it, vi } from "vitest";
import BlogProductsSection from "@/components/Blog/BlogProductsSection";
import { getBlogPosts } from "@/config/blog";
import { adaptHomeLayoutProduct } from "@/helpers/homeLayout";
import { LOCATION_CHANGED_EVENT } from "@/helpers/events";
import type { Product } from "@/types/ApiResponse";
import type { BlogLinkedProduct, BlogPost } from "@/types/blog";
import en from "../../public/locales/en.json";
import hi from "../../public/locales/hi.json";
import ar from "../../public/locales/ar.json";
import homeLayoutResponse from "../fixtures/homeLayoutResponse.json";

const mocks = vi.hoisted(() => ({
  productCard: vi.fn(),
  getProduct: vi.fn(),
  defaultLocation: { lat: 20, lng: 70 } as { lat: number; lng: number } | null,
  cookieLocation: null as { lat: number | string; lng: number | string } | null,
  token: "",
  authListeners: new Set<() => void>(),
}));
vi.mock("@/components/Cards/ProductCard", () => ({
  default: mocks.productCard,
}));
vi.mock("@/components/Skeletons/ProductCardSkeleton", () => ({
  default: () => <div data-testid="catalog-skeleton" />,
}));
vi.mock("@/routes/api", () => ({ getProductBySlug: mocks.getProduct }));
vi.mock("@/contexts/SettingsContext", () => ({
  useSettings: () => ({ defaultLocation: mocks.defaultLocation }),
}));
vi.mock("react-redux", () => ({
  useSelector: (
    selector: (state: { auth: { access_token: string } }) => unknown,
  ) => selector({ auth: { access_token: mocks.token } }),
}));
vi.mock("@/lib/cookies", () => ({
  getCookie: (name: string) =>
    name === "userLocation" ? mocks.cookieLocation : null,
  subscribeToAuthSessionChanges: (listener: () => void) => {
    mocks.authListeners.add(listener);
    return () => mocks.authListeners.delete(listener);
  },
}));

const resources = {
  en: { translation: en },
  hi: { translation: hi },
  ar: { translation: ar },
};
const product = adaptHomeLayoutProduct(
  homeLayoutResponse.data.sections[0].content.products[0],
)!;
const linked = (slug = product.slug): BlogLinkedProduct => ({
  id: product.id,
  title: "Summary title",
  slug,
  short_description: "",
  main_image: "/summary.jpg",
  category_name: "",
  brand_name: "",
  price: 999,
  special_price: 888,
  is_available: true,
  is_deliverable: null,
});

async function renderProducts({
  language = "en",
  products,
  linkedProducts,
}: {
  language?: "en" | "hi" | "ar";
  products?: Product[];
  linkedProducts?: BlogLinkedProduct[];
} = {}) {
  const i18n = createInstance();
  await i18n.init({
    lng: language,
    fallbackLng: false,
    resources,
    interpolation: { escapeValue: false },
  });
  const story = getBlogPosts(i18n.t)[0];
  const post: BlogPost = { ...story, products, linkedProducts };
  return {
    ...render(
      <SWRConfig value={{ provider: () => new Map(), dedupingInterval: 0 }}>
        <I18nextProvider i18n={i18n}>
          <BlogProductsSection post={post} />
        </I18nextProvider>
      </SWRConfig>,
    ),
    post,
    i18n,
  };
}

describe("catalog products associated with a blog story", () => {
  beforeEach(() => {
    mocks.productCard
      .mockReset()
      .mockImplementation(({ product: value }: { product: Product }) => (
        <div data-testid="catalog-product">{value.title}</div>
      ));
    mocks.getProduct
      .mockReset()
      .mockResolvedValue({ success: true, data: product });
    mocks.defaultLocation = { lat: 20, lng: 70 };
    mocks.cookieLocation = null;
    mocks.token = "";
  });

  it("passes supplied full products unchanged to the shared catalog card without fetching summaries", async () => {
    await renderProducts({ products: [product], linkedProducts: [linked()] });
    expect(screen.getByTestId("catalog-product")).toHaveTextContent(
      product.title,
    );
    expect(mocks.productCard.mock.calls[0][0].product).toBe(product);
    expect(mocks.getProduct).not.toHaveBeenCalled();
    expect(screen.queryByText(en.blog.previewLabel)).toBeNull();
  });

  it.each([{ products: [] }, { linkedProducts: [] }, {}])(
    "does not substitute sample product cards for an empty association: %j",
    async (association) => {
      const { container } = await renderProducts(association);
      expect(container).toBeEmptyDOMElement();
      expect(mocks.productCard).not.toHaveBeenCalled();
      expect(mocks.getProduct).not.toHaveBeenCalled();
    },
  );

  it("resolves linked summaries to real catalog records for the current delivery location", async () => {
    mocks.cookieLocation = { lat: "21", lng: "71" };
    await renderProducts({ linkedProducts: [linked(), linked()] });
    expect(await screen.findByTestId("catalog-product")).toHaveTextContent(
      product.title,
    );
    expect(mocks.getProduct).toHaveBeenCalledTimes(1);
    expect(mocks.getProduct).toHaveBeenCalledWith({
      slug: encodeURIComponent(product.slug),
      latitude: 21,
      longitude: 71,
    });
    expect(mocks.productCard.mock.calls[0][0].product).toBe(product);
    expect(screen.queryByText("Summary title")).toBeNull();
    expect(screen.queryByText(en.blog.viewProduct)).toBeNull();
  });

  it("uses the standard skeleton until full product variants arrive", async () => {
    let resolve!: (value: { success: boolean; data: Product }) => void;
    mocks.getProduct.mockImplementation(
      () =>
        new Promise((done) => {
          resolve = done;
        }),
    );
    await renderProducts({ linkedProducts: [linked()] });
    expect(
      screen.getByRole("status", { name: en.blog.productsLoading }),
    ).toBeInTheDocument();
    expect(screen.getByTestId("catalog-skeleton")).toBeInTheDocument();
    expect(mocks.productCard).not.toHaveBeenCalled();
    await act(async () => {
      resolve({ success: true, data: product });
    });
    expect(await screen.findByTestId("catalog-product")).toBeInTheDocument();
  });

  it("opens the shared location selector when no delivery location is available", async () => {
    mocks.defaultLocation = null;
    const selector = document.createElement("button");
    selector.id = "location-modal-btn";
    const open = vi.fn();
    selector.addEventListener("click", open);
    document.body.append(selector);
    try {
      await renderProducts({ linkedProducts: [linked()] });
      fireEvent.click(
        screen.getByRole("button", { name: en.blog.productsChooseLocation }),
      );
      expect(open).toHaveBeenCalledTimes(1);
      expect(mocks.getProduct).not.toHaveBeenCalled();
    } finally {
      selector.remove();
    }
  });

  it("recovers failed product requests without constructing fake variants or replacing other products", async () => {
    mocks.getProduct.mockImplementation(({ slug }: { slug: string }) =>
      Promise.resolve(
        slug === "other-product"
          ? { success: false }
          : { success: true, data: product },
      ),
    );
    await renderProducts({
      linkedProducts: [linked(), linked("other-product")],
    });
    expect(await screen.findByTestId("catalog-product")).toBeInTheDocument();
    expect(await screen.findByRole("alert")).toHaveTextContent(
      en.blog.productsLoadError,
    );
    mocks.getProduct.mockResolvedValue({
      success: true,
      data: {
        ...product,
        id: 1234,
        slug: "other-product",
        title: "Recovered product",
      },
    });
    fireEvent.click(screen.getByRole("button", { name: en.blog.retry }));
    expect(await screen.findByText("Recovered product")).toBeInTheDocument();
    expect(screen.getAllByTestId("catalog-product")).toHaveLength(2);
  });

  it("rejects a success response without purchasable catalog variants", async () => {
    mocks.getProduct.mockResolvedValue({
      success: true,
      data: { ...product, variants: [] },
    });
    await renderProducts({ linkedProducts: [linked()] });
    expect(await screen.findByRole("alert")).toBeInTheDocument();
    expect(mocks.productCard).not.toHaveBeenCalled();
  });

  it("keeps a late response for the old location from replacing the current catalog card", async () => {
    let resolveEarlier!: (value: { success: boolean; data: Product }) => void;
    mocks.getProduct
      .mockImplementationOnce(
        () =>
          new Promise((resolve) => {
            resolveEarlier = resolve;
          }),
      )
      .mockResolvedValue({
        success: true,
        data: { ...product, title: "Current location product" },
      });
    await renderProducts({ linkedProducts: [linked()] });
    await waitFor(() => expect(mocks.getProduct).toHaveBeenCalledTimes(1));
    mocks.cookieLocation = { lat: 22, lng: 72 };
    act(() => {
      window.dispatchEvent(new Event(LOCATION_CHANGED_EVENT));
    });
    expect(
      await screen.findByText("Current location product"),
    ).toBeInTheDocument();
    await act(async () => {
      resolveEarlier({ success: true, data: product });
    });
    expect(screen.queryByText(product.title)).toBeNull();
    expect(mocks.getProduct).toHaveBeenLastCalledWith({
      slug: encodeURIComponent(product.slug),
      latitude: 22,
      longitude: 72,
    });
  });

  it("refreshes catalog records for a language or customer session change", async () => {
    const { i18n } = await renderProducts({ linkedProducts: [linked()] });
    await screen.findByTestId("catalog-product");
    mocks.getProduct.mockResolvedValue({
      success: true,
      data: { ...product, title: "Translated product" },
    });
    await act(async () => {
      await i18n.changeLanguage("hi");
    });
    expect(await screen.findByText("Translated product")).toBeInTheDocument();
    mocks.token = "next-session";
    mocks.getProduct.mockResolvedValue({
      success: true,
      data: { ...product, title: "Current session product" },
    });
    act(() => {
      mocks.authListeners.forEach((listener) => listener());
    });
    expect(
      await screen.findByText("Current session product"),
    ).toBeInTheDocument();
  });

  it.each(["en", "hi", "ar"] as const)(
    "uses complete %s product loading and error labels",
    async (language) => {
      mocks.getProduct.mockResolvedValue({ success: false });
      const { container } = await renderProducts({
        language,
        linkedProducts: [linked()],
      });
      const copy = resources[language].translation.blog;
      expect(await screen.findByRole("alert")).toHaveTextContent(
        copy.productsLoadError,
      );
      expect(
        screen.getByRole("button", { name: copy.retry }),
      ).toBeInTheDocument();
      expect(container.textContent).not.toContain("blog.");
      for (const value of [
        copy.productsLoading,
        copy.productsLoadError,
        copy.productsLocationPrompt,
        copy.productsChooseLocation,
      ]) {
        expect(value).not.toMatch(/\?{2,}|\uFFFD/);
        if (language !== "en")
          expect(value).toMatch(
            language === "hi" ? /[\u0900-\u097f]/ : /[\u0600-\u06ff]/,
          );
      }
    },
  );
});
