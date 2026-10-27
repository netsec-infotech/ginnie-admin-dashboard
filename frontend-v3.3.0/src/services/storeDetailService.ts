import { getSpecificStore, getStores } from "@/routes/api";
import type { Store } from "@/types/ApiResponse";

const PAGE_SIZE = 100;
const MAX_PAGES = 10;

type Location = { latitude?: string | number; longitude?: string | number };

/** The detail endpoint can fail for a store that is still present in the nearby list. */
export async function getStoreWithListingFallback(
  slug: string,
  location: Location = {},
): Promise<Store | null> {
  const detail = await getSpecificStore(slug);
  if (detail.success && detail.data && !Array.isArray(detail.data)) return detail.data;

  const latitude = Number(location.latitude);
  const longitude = Number(location.longitude);
  if (
    !Number.isFinite(latitude) || latitude < -90 || latitude > 90 ||
    !Number.isFinite(longitude) || longitude < -180 || longitude > 180
  ) return null;

  for (let page = 1; page <= MAX_PAGES; page += 1) {
    const response = await getStores({
      latitude,
      longitude,
      page,
      per_page: PAGE_SIZE,
    });
    if (!response.success || !Array.isArray(response.data?.data)) return null;
    const match = response.data.data.find((store) => store.slug === slug);
    if (match) return match;
    if (response.data.data.length < PAGE_SIZE || page * PAGE_SIZE >= response.data.total) break;
  }
  return null;
}
