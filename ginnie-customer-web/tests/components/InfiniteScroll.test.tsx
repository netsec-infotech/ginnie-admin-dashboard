import { describe, it, expect, vi } from "vitest";
import { render, waitFor } from "@testing-library/react";

import InfiniteScroll from "@/components/Functional/InfiniteScroll";

/**
 * jsdom reports every scroll metric as 0, so the viewport always counts as
 * "near the bottom" here — which is exactly the condition these tests are
 * about: a feed that cannot be scrolled any further must still be able to
 * advance, or paging stalls with no gesture left to restart it.
 */
describe("InfiniteScroll", () => {
  it("asks for the next page once a load settles", async () => {
    const onLoadMore = vi.fn();
    const { rerender } = render(
      <InfiniteScroll hasMore isLoading onLoadMore={onLoadMore}>
        <div />
      </InfiniteScroll>,
    );

    expect(onLoadMore).not.toHaveBeenCalled();

    // The page arrived but rendered nothing taller — /home-layout returns
    // sections with zero products, which render null. No scroll event will
    // ever fire again, so the settle check is the only way forward.
    rerender(
      <InfiniteScroll hasMore isLoading={false} onLoadMore={onLoadMore}>
        <div />
      </InfiniteScroll>,
    );

    await waitFor(() => expect(onLoadMore).toHaveBeenCalledTimes(1));
  });

  it("stays put once the feed is exhausted", async () => {
    const onLoadMore = vi.fn();
    const { rerender } = render(
      <InfiniteScroll hasMore isLoading onLoadMore={onLoadMore}>
        <div />
      </InfiniteScroll>,
    );

    rerender(
      <InfiniteScroll hasMore={false} isLoading={false} onLoadMore={onLoadMore}>
        <div />
      </InfiniteScroll>,
    );

    await new Promise((resolve) => setTimeout(resolve, 120));
    expect(onLoadMore).not.toHaveBeenCalled();
  });

  it("calls the latest onLoadMore, not the one bound at mount", async () => {
    const stale = vi.fn();
    const fresh = vi.fn();

    const { rerender } = render(
      <InfiniteScroll hasMore isLoading onLoadMore={stale}>
        <div />
      </InfiniteScroll>,
    );

    // useInfiniteData rebuilds loadMore on every render, so the component must
    // read it through a ref rather than capture it.
    rerender(
      <InfiniteScroll hasMore isLoading={false} onLoadMore={fresh}>
        <div />
      </InfiniteScroll>,
    );

    await waitFor(() => expect(fresh).toHaveBeenCalledTimes(1));
    expect(stale).not.toHaveBeenCalled();
  });
});
