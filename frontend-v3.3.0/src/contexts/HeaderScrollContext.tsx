import React, { createContext, useContext, useMemo, useState } from "react";

interface HeaderScrollContextValue {
  isMobileHeaderExpanded: boolean;
  isMobileViewport: boolean;
  setIsMobileHeaderExpanded: React.Dispatch<React.SetStateAction<boolean>>;
  setIsMobileViewport: React.Dispatch<React.SetStateAction<boolean>>;
}

const HeaderScrollContext = createContext<HeaderScrollContextValue | null>(
  null,
);

export const HeaderScrollProvider: React.FC<React.PropsWithChildren> = ({
  children,
}) => {
  const [isMobileHeaderExpanded, setIsMobileHeaderExpanded] = useState(true);
  const [isMobileViewport, setIsMobileViewport] = useState(false);
  const value = useMemo(
    () => ({
      isMobileHeaderExpanded,
      isMobileViewport,
      setIsMobileHeaderExpanded,
      setIsMobileViewport,
    }),
    [isMobileHeaderExpanded, isMobileViewport],
  );

  return (
    <HeaderScrollContext.Provider value={value}>
      {children}
    </HeaderScrollContext.Provider>
  );
};

export const useHeaderScroll = () => {
  const context = useContext(HeaderScrollContext);
  if (!context) {
    throw new Error("useHeaderScroll must be used inside HeaderScrollProvider");
  }
  return context;
};
