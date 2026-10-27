import type { ReactNode } from "react";
import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";

import type { WishlistItem, WishTitle } from "@/types/ApiResponse";

vi.mock("react-i18next", () => ({
  useTranslation: () => ({
    t: (key: string) =>
      ({
        "wishlist.actions": "Wishlist actions",
        "wishlist.move_modal_title": "Move to another wishlist",
        remove_item: "Remove item",
        edit: "Edit",
        delete: "Delete",
      })[key] ?? key,
  }),
}));

vi.mock("@heroui/react", () => ({
  Card: ({ children }: { children: ReactNode }) => <div>{children}</div>,
  CardBody: ({ children }: { children: ReactNode }) => <div>{children}</div>,
  Button: ({
    children,
    onPress,
    title,
    "aria-label": ariaLabel,
  }: {
    children: ReactNode;
    onPress?: () => void;
    title?: string;
    "aria-label"?: string;
  }) => (
    <button aria-label={ariaLabel} title={title} onClick={onPress}>
      {children}
    </button>
  ),
  Image: ({ alt }: { alt: string }) => <span role="img" aria-label={alt} />,
  Tooltip: ({ children, content }: { children: ReactNode; content: ReactNode }) => (
    <div>
      <span role="tooltip">{content}</span>
      {children}
    </div>
  ),
  Dropdown: ({ children }: { children: ReactNode }) => <div>{children}</div>,
  DropdownTrigger: ({ children }: { children: ReactNode }) => <>{children}</>,
  DropdownMenu: ({ children }: { children: ReactNode }) => <div>{children}</div>,
  DropdownItem: ({ children }: { children: ReactNode }) => (
    <button>{children}</button>
  ),
  Spinner: () => <span>Loading</span>,
  useDisclosure: () => ({
    isOpen: false,
    onOpen: vi.fn(),
    onClose: vi.fn(),
  }),
}));

vi.mock("@/components/Modals/MoveWishlistModal", () => ({
  default: () => null,
}));

vi.mock("@/components/Modals/ConfirmationModal", () => ({
  default: () => null,
}));

import WishlistCard from "@/components/Cards/WishlistCard";
import WishlistItemCard from "@/components/Cards/WishlistItemCard";

const wishlist: WishTitle = {
  id: 1,
  title: "Clothes",
  slug: "clothes",
  items_count: 2,
  created_at: "2026-09-23",
};

const item: WishlistItem = {
  id: 10,
  wishlist_id: 1,
  product: {
    id: 20,
    title: "Dishwash liquid",
    slug: "dishwash-liquid",
    image: "/dishwash.png",
    short_description: "Fresh citrus scent",
  },
  variant: { id: 30, sku: null, image: "", price: null },
  store: { id: 40, name: "Store", slug: "store" },
  created_at: "2026-09-23",
  updated_at: "2026-09-23",
};

describe("wishlist action tooltips", () => {
  it("explains the wishlist overflow action", () => {
    render(
      <WishlistCard
        wishlist={wishlist}
        selectedWishlist={wishlist}
        loading={{ deleting: null }}
        fetchWishlistDetails={vi.fn()}
        setEditingWishlist={vi.fn()}
        onOpen={vi.fn()}
        confirmDelete={vi.fn()}
      />,
    );

    expect(screen.getByText("Wishlist actions")).toHaveAttribute(
      "role",
      "tooltip",
    );
    expect(
      screen.getByRole("button", { name: "Wishlist actions" }),
    ).toHaveAttribute("title", "Wishlist actions");
  });

  it("explains move and remove item actions", () => {
    render(
      <WishlistItemCard
        item={item}
        loading={{ removingItem: null }}
        handleRemoveItem={vi.fn()}
        wishlists={[wishlist]}
        fetchWishlistDetails={vi.fn()}
        fetchWishlists={vi.fn()}
      />,
    );

    expect(screen.getByText("Move to another wishlist")).toHaveAttribute(
      "role",
      "tooltip",
    );
    expect(screen.getByText("Remove item")).toHaveAttribute("role", "tooltip");
    expect(
      screen.getByRole("button", { name: "Move to another wishlist" }),
    ).toHaveAttribute("title", "Move to another wishlist");
    expect(screen.getByRole("button", { name: "Remove item" })).toHaveAttribute(
      "title",
      "Remove item",
    );
  });
});
