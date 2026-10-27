export const formatCompactMetric = (value: number) => {
  if (value < 1000) return String(value);

  const compact = value / 1000;
  return `${
    compact >= 10
      ? Math.round(compact)
      : compact.toFixed(1).replace(/\.0$/, "")
  }K`;
};
