import { useEffect } from "react";
import { RailState, useReportRail } from "@/contexts/HomeRailsContext";

/**
 * Reports a homepage rail's emptiness up to HomeRailsProvider so the page can
 * show one empty state instead of rendering nothing at all.
 *
 * @param rail   Stable identifier for the rail.
 * @param isEmpty  Whether the rail has settled with no content.
 * @param isLoading  Whether the rail is still fetching.
 */
export const useRailReport = (
  rail: string,
  isEmpty: boolean,
  isLoading: boolean
) => {
  const report = useReportRail();

  useEffect(() => {
    if (!report) return;

    const state: RailState = isLoading
      ? "loading"
      : isEmpty
        ? "empty"
        : "filled";

    report(rail, state);
  }, [report, rail, isEmpty, isLoading]);
};

export default useRailReport;
