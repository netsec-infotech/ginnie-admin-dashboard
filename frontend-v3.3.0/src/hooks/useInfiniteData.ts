import { UserLocation } from "@/components/Location/types/LocationAutoComplete.types";
import { useSettings } from "@/contexts/SettingsContext";
import { reportApiFailure } from "@/helpers/apiError";
import { isSSR } from "@/helpers/getters";
import { getCookie } from "@/lib/cookies";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import useSWR from "swr";

type InfiniteDataPage<T> = {
  data: T[];
  total: number;
};

export class InfiniteDataRequestError extends Error {
  readonly retryable: boolean;

  constructor(
    message = "We couldn't load this content. Please try again.",
    retryable = false,
  ) {
    super(message);
    this.name = "InfiniteDataRequestError";
    this.retryable = retryable;
  }
}

const parseInfiniteDataPage = <T>(response: unknown): InfiniteDataPage<T> => {
  if (!response || typeof response !== "object") {
    throw new TypeError("The API returned an invalid response envelope.");
  }

  const envelope = response as {
    success?: unknown;
    message?: unknown;
    retryable?: unknown;
    data?: unknown;
  };

  if (envelope.success === false) {
    const message =
      typeof envelope.message === "string" && envelope.message.trim()
        ? envelope.message
        : undefined;
    throw new InfiniteDataRequestError(message, envelope.retryable === true);
  }

  if (envelope.success !== true || !envelope.data) {
    throw new TypeError("The API returned an invalid success envelope.");
  }

  const page = envelope.data as { data?: unknown; total?: unknown };
  const total = Number(page.total);
  if (!Array.isArray(page.data) || !Number.isFinite(total) || total < 0) {
    throw new TypeError("The API returned invalid pagination data.");
  }

  return { data: page.data as T[], total };
};

interface UseInfiniteDataProps<T> {
  fetcher: (params: {
    page: number;
    per_page: number;
    [key: string]: any;
  }) => Promise<any>;

  perPage?: number;
  initialData?: T[];
  initialTotal?: number;
  extraParams?: {
    [key: string]: any;
  };
  passLocation?: boolean;
  forceFetchOnMount?: boolean;
  /** Revalidate an SSR fallback that did not come from a successful request. */
  initialRequestFailed?: boolean;
  dataKey?: string | null;
  /** Additional scope for the SWR cache (for example category + location). */
  cacheKey?: string | null;
  /** Keep the prior scope's layout only while the replacement is loading. */
  preserveDataOnScopeChange?: boolean;
}

export const useInfiniteData = <T>({
  fetcher,
  perPage = 24,
  initialData = [],
  initialTotal = 0,
  extraParams = {},
  passLocation = false,
  forceFetchOnMount = false,
  initialRequestFailed = false,
  dataKey = null,
  cacheKey = null,
  preserveDataOnScopeChange = false,
}: UseInfiniteDataProps<T>) => {
  const { defaultLocation, hasDefaultLocation } = useSettings();
  const [data, setData] = useState<T[]>(initialData);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(initialTotal);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [hasMore, setHasMore] = useState(initialData.length < initialTotal);
  const [hasLoadedSuccessfully, setHasLoadedSuccessfully] = useState(
    initialData.length > 0 || initialTotal > 0,
  );
  const [loadMoreError, setLoadMoreError] = useState<Error | null>(null);

  // Add this state to track if we're in the initial sync phase
  const [isSyncing, setIsSyncing] = useState(!isSSR() || initialRequestFailed);

  const isLoadingRef = useRef(false);
  const currentPageRef = useRef(1);
  const loadMoreGenerationRef = useRef(0);
  const extraParamsRef = useRef(extraParams);
  const passLocationRef = useRef(passLocation);
  const hasPreservedScopeDataRef = useRef(false);

  useEffect(() => {
    extraParamsRef.current = extraParams;
    passLocationRef.current = passLocation;
  }, [extraParams, passLocation]);

  const serializedParams = useMemo(
    () => JSON.stringify(extraParams),
    [extraParams],
  );
  const defaultLatitude = hasDefaultLocation ? defaultLocation?.lat : undefined;
  const defaultLongitude = hasDefaultLocation
    ? defaultLocation?.lng
    : undefined;
  const resolveRequestLocation = useCallback(() => {
    const cookieLocation = getCookie<UserLocation>("userLocation");

    if (
      Number.isFinite(cookieLocation?.lat) &&
      Number.isFinite(cookieLocation?.lng)
    ) {
      return { lat: cookieLocation!.lat, lng: cookieLocation!.lng };
    }

    if (defaultLatitude !== undefined && defaultLongitude !== undefined) {
      return { lat: defaultLatitude, lng: defaultLongitude };
    }

    return { lat: "", lng: "" };
  }, [defaultLatitude, defaultLongitude]);
  const locationScope = passLocation
    ? `${defaultLatitude ?? ""}:${defaultLongitude ?? ""}`
    : "";

  const swrKey = dataKey
    ? [
        `/infinite-data-${dataKey}`,
        serializedParams,
        locationScope,
        cacheKey ?? "",
      ]
    : ["/infinite-data", serializedParams, locationScope, cacheKey ?? ""];
  const requestScope = JSON.stringify(swrKey);
  const dataScopeRef = useRef(requestScope);
  const latestRequestScopeRef = useRef(requestScope);
  latestRequestScopeRef.current = requestScope;
  const isCurrentDataScope = dataScopeRef.current === requestScope;
  const canShowCurrentData = isCurrentDataScope || preserveDataOnScopeChange;

  const {
    data: swrResponse,
    isLoading: isInitialLoading,
    isValidating,
    error,
    mutate,
  } = useSWR<InfiniteDataPage<T>, Error>(
    swrKey,
    async ([, params]: string[]) => {
      const currentParams = JSON.parse(params);
      const { lat, lng } = resolveRequestLocation();

      if (passLocationRef.current && (lat === "" || lng === "")) {
        return { data: [], total: 0 };
      }

      const location = passLocationRef.current
        ? { latitude: lat, longitude: lng }
        : {};
      const res = await fetcher({
        page: 1,
        per_page: perPage,
        ...currentParams,
        ...location,
      });
      return parseInfiniteDataPage<T>(res);
    },
    {
      errorRetryCount: 2,
      errorRetryInterval: 1500,
      revalidateOnFocus: false,
      revalidateOnMount: forceFetchOnMount || initialRequestFailed || !isSSR(),
      revalidateOnReconnect: true,
      shouldRetryOnError: (requestError) =>
        requestError instanceof InfiniteDataRequestError &&
        requestError.retryable,
      onError: (requestError) => {
        if (!(requestError instanceof InfiniteDataRequestError)) {
          reportApiFailure(requestError, "paginated data parsing");
        }
      },
    },
  );

  useEffect(() => {
    if (dataScopeRef.current === requestScope) return;

    // Results from a previous category/search/location must never remain
    // clickable while a different request scope is loading or has failed.
    loadMoreGenerationRef.current += 1;
    dataScopeRef.current = requestScope;
    hasPreservedScopeDataRef.current =
      preserveDataOnScopeChange && data.length > 0;
    if (!hasPreservedScopeDataRef.current) {
      setData([]);
      setTotal(0);
    }
    setHasMore(false);
    setPage(1);
    currentPageRef.current = 1;
    isLoadingRef.current = false;
    setHasLoadedSuccessfully(false);
    setLoadMoreError(null);
    setIsLoadingMore(false);
    setIsSyncing(true);
  }, [data.length, preserveDataOnScopeChange, requestScope]);

  useEffect(() => {
    if (swrResponse) {
      // Replacing page one (including an explicit refetch) invalidates any
      // pagination request that started from the previous page-one snapshot.
      loadMoreGenerationRef.current += 1;
      const items = swrResponse?.data || [];
      const totalItems = swrResponse?.total || 0;
      setData(items);
      setTotal(totalItems);
      setHasMore(items.length < totalItems);
      setPage(1);
      currentPageRef.current = 1;
      dataScopeRef.current = requestScope;
      hasPreservedScopeDataRef.current = false;
      isLoadingRef.current = false;
      setHasLoadedSuccessfully(true);
      setLoadMoreError(null);
      setIsSyncing(false);
    }
  }, [requestScope, swrResponse]);

  useEffect(() => {
    if (!error) return;

    if (hasPreservedScopeDataRef.current) {
      // Preservation is a loading affordance, not permission to display the
      // wrong category/search after the replacement request has failed.
      hasPreservedScopeDataRef.current = false;
      setData([]);
      setTotal(0);
      setHasMore(false);
    }
    setIsSyncing(false);
  }, [error]);

  const loadMorePage = useCallback(
    async (retry: boolean) => {
      if (
        dataScopeRef.current !== requestScope ||
        isLoadingRef.current ||
        !hasMore ||
        (loadMoreError && !retry)
      ) {
        return;
      }

      const nextPage = page + 1;

      if (currentPageRef.current >= nextPage) {
        return;
      }

      setIsLoadingMore(true);
      setLoadMoreError(null);
      isLoadingRef.current = true;
      currentPageRef.current = nextPage;
      loadMoreGenerationRef.current += 1;
      const requestGeneration = loadMoreGenerationRef.current;
      const requestScopeAtStart = requestScope;
      const isCurrentRequest = () =>
        loadMoreGenerationRef.current === requestGeneration &&
        dataScopeRef.current === requestScopeAtStart &&
        latestRequestScopeRef.current === requestScopeAtStart;

      const { lat, lng } = resolveRequestLocation();
      const location = passLocationRef.current
        ? { latitude: lat, longitude: lng }
        : {};

      try {
        const res = await fetcher({
          page: nextPage,
          per_page: perPage,
          ...extraParamsRef.current,
          ...location,
        });

        const nextPageData = parseInfiniteDataPage<T>(res);
        if (!isCurrentRequest()) return;

        const newItems = nextPageData.data;
        const newTotal = nextPageData.total;

        setData((prev) => [...prev, ...newItems]);
        setPage(nextPage);
        setTotal(newTotal);
        setHasMore(data.length + newItems.length < newTotal);
      } catch (requestError) {
        if (!isCurrentRequest()) return;

        const normalizedError =
          requestError instanceof Error
            ? requestError
            : new Error("An unknown pagination error occurred.");

        setLoadMoreError(normalizedError);
        currentPageRef.current = page;

        if (!(normalizedError instanceof InfiniteDataRequestError)) {
          reportApiFailure(requestError, "loading more paginated data");
        }
      } finally {
        if (isCurrentRequest()) {
          setIsLoadingMore(false);
          isLoadingRef.current = false;
        }
      }
    },
    [
      page,
      perPage,
      hasMore,
      loadMoreError,
      fetcher,
      data.length,
      resolveRequestLocation,
      requestScope,
    ],
  );

  const loadMore = useCallback(() => loadMorePage(false), [loadMorePage]);

  const retryLoadMore = useCallback(() => {
    void loadMorePage(true);
  }, [loadMorePage]);

  const refetch = useCallback(async () => {
    loadMoreGenerationRef.current += 1;
    setPage(1);
    currentPageRef.current = 1;
    isLoadingRef.current = false;
    setIsLoadingMore(false);
    setLoadMoreError(null);
    // Keep successfully loaded/SSR content visible during a refresh. The
    // syncing skeleton is only needed when there is nothing usable yet.
    setIsSyncing(!hasLoadedSuccessfully);

    try {
      await mutate();
    } catch {
      // SWR exposes this through `error`; retry controls intentionally do not
      // turn an already-managed request failure into an unhandled rejection.
    } finally {
      // Always clear the syncing flag. Relying solely on the [swrResponse]
      // effect hangs the loading state whenever SWR returns deep-equal data
      // (it keeps the previous reference, so the effect never re-runs) or the
      // request fails — both left the UI stuck on skeletons forever.
      setIsSyncing(false);
    }
  }, [hasLoadedSuccessfully, mutate]);

  return {
    data: canShowCurrentData ? data : [],
    isLoading: isInitialLoading || isSyncing || !isCurrentDataScope,
    isLoadingMore,
    hasMore: isCurrentDataScope ? hasMore : false,
    total: canShowCurrentData ? total : 0,
    loadMore,
    error,
    hasLoadedSuccessfully: isCurrentDataScope && hasLoadedSuccessfully,
    loadMoreError,
    refetch,
    retryLoadMore,
    isValidating,
  };
};
