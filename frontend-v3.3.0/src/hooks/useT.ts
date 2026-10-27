import { useCallback } from "react";
import { useSelector } from "react-redux";
import type { RootState } from "@/lib/redux/store";
import { translate } from "@/lib/localization/data";

export function useT() {
  const labels = useSelector((state: RootState) => state.localization.active.labels);
  return useCallback(
    (key: string, values?: Record<string, string | number>) =>
      translate(labels, key, values),
    [labels],
  );
}
