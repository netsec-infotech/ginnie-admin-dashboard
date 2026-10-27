import type { ReactNode } from "react";
import { act, renderHook, waitFor } from "@testing-library/react";
import { SWRConfig } from "swr";
import { beforeEach, describe, expect, it, vi } from "vitest";

import {
  InfiniteDataRequestError,
  useInfiniteData,
} from "@/hooks/useInfiniteData";

const { mockIsSSR } = vi.hoisted(() => ({
  mockIsSSR: vi.fn(() => false),
}));

vi.mock("@/contexts/SettingsContext", () => ({
  useSettings: () => ({
    defaultLocation: null,
    hasDefaultLocation: false,
  }),
}));

vi.mock("@/helpers/apiError", () => ({
  reportApiFailure: vi.fn(),
}));

vi.mock("@/helpers/getters", () => ({
  isSSR: mockIsSSR,
}));

vi.mock("@/lib/cookies", () => ({
  getCookie: () => undefined,
}));

interface Item {
  id: number;
}

interface FetchParams {
  page: number;
  per_page: number;
  [key: string]: unknown;
}

interface PageEnvelope {
  success: boolean;
  message: string;
  retryable?: boolean;
  data: {
    data: Item[];
    total: number;
  };
}

const successfulPage = (data: Item[], total: number): PageEnvelope => ({
  success: true,
  message: "Loaded",
  data: { data, total },
});

const failedPage: PageEnvelope = {
  success: false,
  message: "Catalog temporarily unavailable",
  data: { data: [], total: 0 },
};

const createDeferred = <T,>() => {
  let resolve!: (value: T | PromiseLike<T>) => void;
  let reject!: (reason?: unknown) => void;
  const promise = new Promise<T>((resolvePromise, rejectPromise) => {
    resolve = resolvePromise;
    reject = rejectPromise;
  });

  return { promise, reject, resolve };
};

const createWrapper = () => {
  const cache = new Map();

  return function HookTestWrapper({ children }: { children: ReactNode }) {
    return (
      <SWRConfig
        value={{
          dedupingInterval: 0,
          onErrorRetry: () => undefined,
          provider: () => cache,
        }}
      >
        {children}
      </SWRConfig>
    );
  };
};

describe("useInfiniteData", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockIsSSR.mockReturnValue(false);
  });

  it("revalidates a failed initial request in SSR mode and stays loading during the probe", async () => {
    mockIsSSR.mockReturnValue(true);
    const request = createDeferred<PageEnvelope>();
    const fetcher = vi.fn<(params: FetchParams) => Promise<PageEnvelope>>();
    fetcher.mockReturnValue(request.promise);

    const { result } = renderHook(
      () =>
        useInfiniteData<Item>({
          dataKey: "ssr-initial-failure",
          fetcher,
          initialRequestFailed: true,
        }),
      { wrapper: createWrapper() },
    );

    expect(result.current.isLoading).toBe(true);
    await waitFor(() => expect(fetcher).toHaveBeenCalledOnce());
    expect(result.current.isLoading).toBe(true);

    await act(async () => {
      request.resolve(successfulPage([], 0));
      await request.promise;
    });

    await waitFor(() => expect(result.current.isLoading).toBe(false));
    expect(result.current.error).toBeUndefined();
    expect(result.current.data).toEqual([]);
  });

  it("retains initial data, exposes a request error, and stops loading on success:false", async () => {
    const initialData = [{ id: 1 }];
    const fetcher = vi.fn<(params: FetchParams) => Promise<PageEnvelope>>();
    fetcher.mockResolvedValue(failedPage);

    const { result } = renderHook(
      () =>
        useInfiniteData<Item>({
          dataKey: "initial-failure",
          fetcher,
          forceFetchOnMount: true,
          initialData,
          initialTotal: 2,
        }),
      { wrapper: createWrapper() },
    );

    await waitFor(() =>
      expect(result.current.error).toBeInstanceOf(InfiniteDataRequestError),
    );
    await waitFor(() => expect(result.current.isLoading).toBe(false));

    expect(result.current.data).toEqual(initialData);
    expect(result.current.error).toMatchObject({
      message: "Catalog temporarily unavailable",
      name: "InfiniteDataRequestError",
      retryable: false,
    });
    expect(fetcher).toHaveBeenCalledOnce();

    await act(async () => {
      await expect(result.current.refetch()).resolves.toBeUndefined();
    });
    expect(fetcher).toHaveBeenCalledTimes(2);
  });

  it("clears the request error and replaces stale data after a successful refetch", async () => {
    const fetcher = vi.fn<(params: FetchParams) => Promise<PageEnvelope>>();
    fetcher
      .mockResolvedValueOnce(failedPage)
      .mockResolvedValueOnce(successfulPage([{ id: 2 }], 1));

    const { result } = renderHook(
      () =>
        useInfiniteData<Item>({
          dataKey: "eventual-recovery",
          fetcher,
          forceFetchOnMount: true,
          initialData: [{ id: 1 }],
          initialTotal: 1,
        }),
      { wrapper: createWrapper() },
    );

    await waitFor(() =>
      expect(result.current.error).toBeInstanceOf(InfiniteDataRequestError),
    );

    await act(async () => {
      await result.current.refetch();
    });

    await waitFor(() => expect(result.current.error).toBeUndefined());
    await waitFor(() => expect(result.current.data).toEqual([{ id: 2 }]));

    expect(result.current.hasLoadedSuccessfully).toBe(true);
    expect(result.current.isLoading).toBe(false);
    expect(fetcher.mock.calls.map(([params]) => params.page)).toEqual([1, 1]);
  });

  it("does not expose stale data after the request scope changes", async () => {
    const fetcher = vi.fn<(params: FetchParams) => Promise<PageEnvelope>>();
    fetcher.mockResolvedValue(failedPage);

    const { result, rerender } = renderHook(
      ({ scope }: { scope: string }) =>
        useInfiniteData<Item>({
          cacheKey: scope,
          dataKey: "scope-change",
          fetcher,
          forceFetchOnMount: true,
          initialData: [{ id: 1 }],
          initialTotal: 1,
        }),
      {
        initialProps: { scope: "old-category" },
        wrapper: createWrapper(),
      },
    );

    await waitFor(() =>
      expect(result.current.error).toBeInstanceOf(InfiniteDataRequestError),
    );
    expect(result.current.data).toEqual([{ id: 1 }]);

    rerender({ scope: "new-category" });

    expect(result.current.data).toEqual([]);
    expect(result.current.hasLoadedSuccessfully).toBe(false);
    await waitFor(() => expect(result.current.isLoading).toBe(false));
    expect(result.current.data).toEqual([]);
  });

  it("retains accumulated data and retries the same page after load-more fails", async () => {
    const fetcher = vi.fn<(params: FetchParams) => Promise<PageEnvelope>>();
    fetcher
      .mockResolvedValueOnce(successfulPage([{ id: 1 }], 2))
      .mockResolvedValueOnce(failedPage)
      .mockResolvedValueOnce(successfulPage([{ id: 2 }], 2));

    const { result } = renderHook(
      () =>
        useInfiniteData<Item>({
          dataKey: "load-more-retry",
          fetcher,
          forceFetchOnMount: true,
          initialData: [{ id: 1 }],
          initialTotal: 2,
          perPage: 1,
        }),
      { wrapper: createWrapper() },
    );

    await waitFor(() => expect(fetcher).toHaveBeenCalledOnce());
    await waitFor(() => expect(result.current.isLoading).toBe(false));

    await act(async () => {
      await result.current.loadMore();
    });

    await waitFor(() =>
      expect(result.current.loadMoreError).toBeInstanceOf(
        InfiniteDataRequestError,
      ),
    );
    expect(result.current.data).toEqual([{ id: 1 }]);
    expect(result.current.isLoadingMore).toBe(false);

    act(() => {
      result.current.retryLoadMore();
    });

    await waitFor(() =>
      expect(result.current.data).toEqual([{ id: 1 }, { id: 2 }]),
    );
    expect(result.current.loadMoreError).toBeNull();
    expect(fetcher.mock.calls.map(([params]) => params.page)).toEqual([
      1, 2, 2,
    ]);
  });

  it("ignores an old scope's load-more completion without unlocking the new request", async () => {
    const oldLoadMore = createDeferred<PageEnvelope>();
    const newLoadMore = createDeferred<PageEnvelope>();
    const fetcher = vi.fn<(params: FetchParams) => Promise<PageEnvelope>>(
      ({ page, category }) => {
        if (page === 1) {
          const firstId = category === "old" ? 1 : 10;
          return Promise.resolve(successfulPage([{ id: firstId }], 3));
        }

        return category === "old" ? oldLoadMore.promise : newLoadMore.promise;
      },
    );

    const { result, rerender } = renderHook(
      ({ category }: { category: string }) =>
        useInfiniteData<Item>({
          cacheKey: category,
          dataKey: "load-more-scope-race",
          extraParams: { category },
          fetcher,
          forceFetchOnMount: true,
          perPage: 1,
        }),
      {
        initialProps: { category: "old" },
        wrapper: createWrapper(),
      },
    );

    await waitFor(() => expect(result.current.data).toEqual([{ id: 1 }]));

    let oldRequest!: Promise<void>;
    act(() => {
      oldRequest = result.current.loadMore();
    });
    await waitFor(() => expect(result.current.isLoadingMore).toBe(true));

    rerender({ category: "new" });
    await waitFor(() => expect(result.current.data).toEqual([{ id: 10 }]));

    let newRequest!: Promise<void>;
    act(() => {
      newRequest = result.current.loadMore();
    });
    await waitFor(() => expect(result.current.isLoadingMore).toBe(true));

    await act(async () => {
      oldLoadMore.resolve(successfulPage([{ id: 2 }], 3));
      await oldRequest;
    });

    expect(result.current.data).toEqual([{ id: 10 }]);
    expect(result.current.isLoadingMore).toBe(true);
    expect(result.current.loadMoreError).toBeNull();

    await act(async () => {
      newLoadMore.resolve(successfulPage([{ id: 11 }], 3));
      await newRequest;
    });

    expect(result.current.data).toEqual([{ id: 10 }, { id: 11 }]);
    expect(result.current.isLoadingMore).toBe(false);
  });
});
