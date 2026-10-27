import { act, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, expect, it, vi } from "vitest";
import { SWRConfig } from "swr";
import type { Product } from "@/types/ApiResponse";
import SoldBySection from "@/components/Products/ProductDetailPage/SoldBySection";

const { getStore } = vi.hoisted(() => ({ getStore: vi.fn() }));
vi.mock("@/routes/api", () => ({ getSpecificStore: getStore }));
vi.mock("react-i18next", () => ({ useTranslation: () => ({ t: (key: string) => key }) }));
vi.mock("@heroui/react", () => ({
  Card: ({ children }: React.PropsWithChildren) => <div>{children}</div>,
  CardBody: ({ children }: React.PropsWithChildren) => <div>{children}</div>,
  CardHeader: ({ children }: React.PropsWithChildren) => <div>{children}</div>,
  Chip: ({ children }: React.PropsWithChildren) => <span>{children}</span>,
  Avatar: ({ src, name, alt }: { src?: string; name: string; alt: string }) => (
    <span role="img" aria-label={alt} data-src={src}>{name}</span>
  ),
}));
const product = {
  seller: "N/A", seller_ratings: { average_rating: "5.0000" },
  store_status: { is_open: true },
  variants: [{ store_slug: "beauty", store_name: "Beauty Essentials" }],
} as unknown as Product;
const wrapper = ({ children }: React.PropsWithChildren) => (
  <SWRConfig value={{ provider: () => new Map(), dedupingInterval: 0 }}>{children}</SWRConfig>
);
beforeEach(() => getStore.mockReset());

it("loads the API logo while preserving API seller, rating and status", async () => {
  getStore.mockResolvedValue({ success: true, data: { logo: "https://example.com/logo.png" } });
  render(<SoldBySection product={product} />, { wrapper });
  await waitFor(() => expect(screen.getByRole("img")).toHaveAttribute("data-src", "https://example.com/logo.png"));
  expect(getStore).toHaveBeenCalledWith("beauty");
  expect(screen.getByRole("heading")).toHaveTextContent("N/A");
  expect(screen.getByText("5.0")).toBeInTheDocument();
  expect(screen.getByText("soldBySection.open")).toBeInTheDocument();
  for (const link of screen.getAllByRole("link")) {
    expect(link).toHaveAttribute("href", "/stores/beauty");
  }
});

it("uses the store name fallback during loading and API failure", async () => {
  let resolve!: (value: unknown) => void;
  getStore.mockReturnValue(new Promise(r => { resolve = r; }));
  render(<SoldBySection product={product} />, { wrapper });
  expect(screen.getByRole("img")).not.toHaveAttribute("data-src");
  expect(screen.getByRole("img")).toHaveTextContent("Beauty Essentials");
  await act(async () => resolve({ success: false }));
  expect(getStore).toHaveBeenCalledOnce();
  expect(screen.getByRole("img")).not.toHaveAttribute("data-src");
});

it("does not carry the previous store logo into another product", async () => {
  getStore.mockResolvedValueOnce({ success: true, data: { logo: "/first.png" } });
  const { rerender } = render(<SoldBySection product={product} />, { wrapper });
  await waitFor(() => expect(screen.getByRole("img")).toHaveAttribute("data-src", "/first.png"));
  getStore.mockResolvedValueOnce({ success: true, data: { logo: "/second.png" } });
  rerender(<SoldBySection product={{ ...product, variants: [{ ...product.variants[0], store_slug: "second", store_name: "Second Store" }] }} />);
  await waitFor(() => expect(screen.getByRole("img")).toHaveAttribute("data-src", "/second.png"));
  expect(getStore).toHaveBeenLastCalledWith("second");
});
