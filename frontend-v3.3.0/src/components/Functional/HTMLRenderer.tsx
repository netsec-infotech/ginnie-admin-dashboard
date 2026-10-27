// components/HTMLRenderer.tsx

import { FC, useMemo } from "react";
import { sanitizeRichText } from "@/helpers/sanitizeRichText";

interface HTMLRendererProps {
  html: string;
  className?: string;
}

const HTMLRenderer: FC<HTMLRendererProps> = ({ html, className }) => {
  const sanitizedHtml = useMemo(() => sanitizeRichText(html), [html]);
  return (
    <div
      className={`html-content ${className ?? ""}`}
      dangerouslySetInnerHTML={{ __html: sanitizedHtml }}
    />
  );
};

export default HTMLRenderer;
