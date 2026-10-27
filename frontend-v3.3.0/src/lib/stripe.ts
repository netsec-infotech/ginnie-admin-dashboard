import { loadStripe } from "@stripe/stripe-js";

const clients = new Map<string, ReturnType<typeof loadStripe>>();

/** Reuse the SDK instance across checkout, wallet, and tip dialogs. */
export const getStripeClient = (key: string) => {
  const existing = clients.get(key);
  if (existing) return existing;
  const client = loadStripe(key, {
    developerTools: { assistant: { enabled: true } },
  }).then((stripe) => {
    if (!stripe) clients.delete(key);
    return stripe;
  }).catch((error: unknown) => {
    clients.delete(key);
    throw error;
  });
  clients.set(key, client);
  return client;
};
