import { render, screen } from "@testing-library/react";
import { Bookmark } from "lucide-react";
import { describe, expect, it } from "vitest";

import EmptyWishListState from "@/views/WishListPageView/EmptyWishListState";

describe("EmptyWishListState", () => {
  it("presents the empty-state explanation and next action", () => {
    render(
      <EmptyWishListState
        icon={Bookmark}
        title="Your Wishlist is Empty"
        description="Save products here for later."
        action={<button>Browse products</button>}
      />,
    );

    expect(
      screen.getByRole("heading", { name: "Your Wishlist is Empty" }),
    ).toBeInTheDocument();
    expect(screen.getByText("Save products here for later.")).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "Browse products" }),
    ).toBeInTheDocument();
  });
});
