interface PaginationRangeOptions {
  from?: number;
  to?: number;
  currentPage?: number;
  perPage?: number;
  itemCount: number;
  total: number;
}

export const getPaginationRange = ({
  from,
  to,
  currentPage = 1,
  perPage,
  itemCount,
  total,
}: PaginationRangeOptions): { from: number; to: number } => {
  if (itemCount <= 0 || total <= 0) return { from: 0, to: 0 };

  if (
    Number.isFinite(from) &&
    Number.isFinite(to) &&
    from! > 0 &&
    to! >= from! &&
    to! <= total
  ) {
    return { from: from!, to: to! };
  }

  const safePage = currentPage > 0 ? currentPage : 1;
  const safePerPage = perPage && perPage > 0 ? perPage : itemCount;
  const derivedFrom = (safePage - 1) * safePerPage + 1;

  return {
    from: derivedFrom,
    to: Math.min(derivedFrom + itemCount - 1, total),
  };
};
