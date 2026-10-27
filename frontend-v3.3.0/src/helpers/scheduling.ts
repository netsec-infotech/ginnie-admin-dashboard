/**
 * Which scheduled-delivery contract the deployed backend speaks.
 *
 * Two exist and they identify a slot differently:
 *
 *   "store" (default, live today)
 *     GET /scheduled-delivery/stores/{id}/dates   -> booking RULES
 *     GET /scheduled-delivery/stores/{id}/slots   -> slots WITH numeric `id`
 *     order books it with `delivery_time_slot_id`
 *
 *   "cart"
 *     GET /cart/dates                             -> explicit `dates[]`
 *     GET /cart/slots?date=                       -> slots with NO id
 *     order books it with `scheduled_slot_start_time` / `_end_time`
 *
 * The two cannot be merged: without an id there is nothing to put in
 * `delivery_time_slot_id`, and a slot identified by time can't be recovered
 * from one. So the storefront picks a lane and the picker/checkout follow it.
 *
 * Default is "store" because that is what the backend actually serves today —
 * probing staging, the cart routes 404 while the store routes answer 422/401.
 * Flip this once /cart/dates is deployed.
 */
export type SchedulingContract = "store" | "cart";

/**
 * Reads NEXT_PUBLIC_SCHEDULING_CONTRACT. Anything other than "cart" — unset,
 * empty, a typo — resolves to "store", so a misconfigured env can only ever
 * fall back to the contract known to work rather than breaking checkout.
 */
export const getSchedulingContract = (): SchedulingContract =>
  process.env.NEXT_PUBLIC_SCHEDULING_CONTRACT?.trim().toLowerCase() === "cart"
    ? "cart"
    : "store";

export const isCartScheduling = (): boolean =>
  getSchedulingContract() === "cart";
