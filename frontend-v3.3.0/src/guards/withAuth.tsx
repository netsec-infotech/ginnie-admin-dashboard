// src/guards/withAuth.tsx
import { useRouter } from "next/router";
import { useEffect, useSyncExternalStore } from "react";
import { useSelector } from "react-redux";
import { RootState } from "@/lib/redux/store";
import { addToast } from "@heroui/react";
import { isProtectedRoute } from "./authGuard";

const subscribeToHydration = () => () => {};
const getClientSnapshot = () => true;
const getServerSnapshot = () => false;

export const withAuth = <P extends object>(
  WrappedComponent: React.ComponentType<P>
) => {
  return function WithAuthComponent(props: P) {
    const router = useRouter();
    const isLoggedIn = useSelector((state: RootState) => state.auth.isLoggedIn);
    const hydrated = useSelector((state: RootState) => state._persist?.rehydrated);
    const clientReady = useSyncExternalStore(
      subscribeToHydration,
      getClientSnapshot,
      getServerSnapshot,
    );
    const protectedRoute = isProtectedRoute(router.pathname);

    useEffect(() => {
      if (clientReady && hydrated && router.isReady && !isLoggedIn && protectedRoute) {
        addToast({
          title: "Authentication Required",
          description: "Please login to access this page",
          color: "warning",
        });
        router.replace("/");
      }
    }, [clientReady, hydrated, isLoggedIn, protectedRoute, router, router.isReady]);

    // Persistence can still be restoring a signed-in customer on a cold load.
    // Keep the first browser render consistent with the server placeholder.
    if (protectedRoute && (!clientReady || !hydrated || !isLoggedIn)) return null;

    return <WrappedComponent {...props} />;
  };
};
