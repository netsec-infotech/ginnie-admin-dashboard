import { useCallback, useEffect, useRef, useState } from "react";
import { getOrders } from "@/routes/api";
import type { SupportOrder } from "@/types/support";

export function useSupportOrders() {
  const [orders, setOrders] = useState<SupportOrder[]>([]);
  const [loading, setLoading] = useState(false);
  const [hasMore, setHasMore] = useState(true);
  const [error, setError] = useState(false);
  const page = useRef(1);
  const pending = useRef(false);
  const mounted = useRef(false);
  useEffect(() => {
    mounted.current = true;
    return () => {
      mounted.current = false;
    };
  }, []);
  const load = useCallback(async () => {
    if (pending.current) return;
    pending.current = true;
    setLoading(true);
    setError(false);
    try {
      const response = await getOrders({ page: page.current, per_page: 10 });
      if (!mounted.current) return;
      if (!response.success || !response.data) {
        setError(true);
        return;
      }
      const next = response.data.data.map((order) => ({
        id: order.id,
        slug: order.slug,
        number: `#${order.id}`,
        status: order.status,
        payment_status: order.payment_status,
        payment_method: order.payment_method,
        store_names: [
          ...new Set(
            [
              order.store?.name,
              ...(order.items ?? []).map((item) => item.store?.name),
            ].filter((name): name is string => Boolean(name)),
          ),
        ],
        items: order.items?.map((item) => ({
          title: item.title,
          quantity: item.quantity,
          variant_title: item.variant_title,
        })),
        currency_code: order.currency_code,
        total: order.total_payable,
        created_at: order.created_at,
      }));
      setOrders((previous) => [
        ...new Map(
          [...previous, ...next].map((order) => [order.id, order]),
        ).values(),
      ]);
      setHasMore(page.current * 10 < response.data.total);
      page.current += 1;
    } catch {
      if (mounted.current) setError(true);
    } finally {
      pending.current = false;
      if (mounted.current) setLoading(false);
    }
  }, []);
  // The support endpoint returns compact orders. Enrich the first page in one
  // request, and continue from the next page when older orders are requested.
  useEffect(() => {
    void load();
  }, [load]);
  return { orders, loading, hasMore, error, load };
}
