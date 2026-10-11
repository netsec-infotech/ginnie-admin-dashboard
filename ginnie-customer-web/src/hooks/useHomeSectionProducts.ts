import {
  adaptHomeLayoutProducts,
  getSectionProducts,
} from "@/helpers/homeLayout";
import { useHomeSectionItems } from "@/hooks/useHomeSectionItems";
import { HomeLayoutSection, Product } from "@/types/ApiResponse";

/**
 * Infinite scroll for the products inside ONE /home-layout section rail.
 *
 * A thin wrapper over `useHomeSectionItems` (which does the same job for every
 * other section kind) — it exists to keep `products` as the returned field name
 * for the product rail. See that hook for the paging contract.
 */
export const useHomeSectionProducts = (section: HomeLayoutSection) => {
  const { items, hasMore, isLoadingMore, loadMore } =
    useHomeSectionItems<Product>(section, {
      getInline: getSectionProducts,
      adaptPage: adaptHomeLayoutProducts,
    });

  return { products: items, hasMore, isLoadingMore, loadMore };
};

export default useHomeSectionProducts;
