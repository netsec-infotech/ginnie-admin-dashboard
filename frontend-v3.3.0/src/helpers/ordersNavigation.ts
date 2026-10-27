import type { NextRouter } from "next/router";

type OrdersRouter = Pick<NextRouter, "push">;
type OrdersQuery = NextRouter["query"];

/** Update order filters or pagination without re-running page data loading. */
export const updateOrdersRoute = (
  router: OrdersRouter,
  query: OrdersQuery,
) =>
  router.push(
    {
      pathname: "/my-account/orders",
      query,
    },
    undefined,
    { shallow: true },
  );
