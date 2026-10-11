import {
  FC,
  ReactNode,
  createContext,
  useCallback,
  useContext,
  useMemo,
  useState,
} from "react";

/**
 * Tracks whether each rail on the legacy homepage has content.
 *
 * Every rail owns its own SWR call and hides itself when empty, so the page has
 * no way to tell "this category is empty" from "the rails are still loading".
 * Rails report up through this context; the page shows a single empty state only
 * once all of them have reported in as empty.
 */
interface HomeRailsContextValue {
  /** Called by each rail whenever its emptiness or loading state changes. */
  report: (rail: string, state: RailState) => void;
  /** True once every registered rail has settled with no content. */
  allEmpty: boolean;
}

export type RailState = "loading" | "empty" | "filled";

const HomeRailsContext = createContext<HomeRailsContextValue | null>(null);

export const HomeRailsProvider: FC<{ children: ReactNode }> = ({
  children,
}) => {
  const [rails, setRails] = useState<Record<string, RailState>>({});

  const report = useCallback((rail: string, state: RailState) => {
    setRails((prev) => (prev[rail] === state ? prev : { ...prev, [rail]: state }));
  }, []);

  const allEmpty = useMemo(() => {
    const states = Object.values(rails);
    // No rail has registered yet — can't conclude anything, and claiming "empty"
    // here would flash the empty state on every first paint.
    if (states.length === 0) return false;
    return states.every((s) => s === "empty");
  }, [rails]);

  const value = useMemo(() => ({ report, allEmpty }), [report, allEmpty]);

  return (
    <HomeRailsContext.Provider value={value}>
      {children}
    </HomeRailsContext.Provider>
  );
};

/**
 * Used by the page to decide whether to render the empty state. Returns false
 * outside a provider so the home-builder homepage is unaffected.
 */
export const useHomeRailsEmpty = () => {
  const ctx = useContext(HomeRailsContext);
  return ctx?.allEmpty ?? false;
};

/**
 * Called by a rail to report its own state. A no-op outside a provider, so rails
 * stay usable on pages that don't track this.
 */
export const useReportRail = () => {
  const ctx = useContext(HomeRailsContext);
  return ctx?.report;
};

export default HomeRailsContext;
