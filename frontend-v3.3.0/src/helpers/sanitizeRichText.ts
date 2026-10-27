import sanitizeHtml from "sanitize-html";

// Use the same parser and policy during SSR and browser rendering. Descriptions
// support rich text, but must never define scripts, forms, or embedded documents.
const options: sanitizeHtml.IOptions = {
  allowedTags: [...sanitizeHtml.defaults.allowedTags, "img"],
  allowedAttributes: {
    ...sanitizeHtml.defaults.allowedAttributes,
    "*": ["title", "dir", "lang", "style"],
    td: ["colspan", "rowspan"],
    th: ["colspan", "rowspan", "scope"],
    ol: ["start", "reversed", "type"],
    li: ["value"],
  },
  allowedSchemes: ["http", "https", "mailto", "tel"],
  allowedSchemesByTag: { img: ["http", "https"] },
  // Permit common editor formatting without URL-bearing or positioning styles.
  allowedStyles: {
    "*": {
      "text-align": [/^(left|right|center|justify|start|end)$/],
      "font-weight": [/^(normal|bold|bolder|lighter|[1-9]00)$/],
      "font-style": [/^(normal|italic|oblique)$/],
      "text-decoration": [/^(none|underline|line-through|overline)$/],
      "font-size": [/^\d+(\.\d+)?(px|pt|em|rem|%)$/],
      color: [/^#[0-9a-fA-F]{3,8}$/, /^[a-zA-Z]+$/, /^rgba?\([\d.,%\s]+\)$/],
      "background-color": [
        /^#[0-9a-fA-F]{3,8}$/,
        /^[a-zA-Z]+$/,
        /^rgba?\([\d.,%\s]+\)$/,
      ],
    },
  },
};

export const sanitizeRichText = (
  html: string,
  { allowRasterDataImages = false }: { allowRasterDataImages?: boolean } = {},
): string => {
  if (!allowRasterDataImages) return sanitizeHtml(html, options);

  return sanitizeHtml(html, {
    ...options,
    allowedSchemesByTag: { img: ["http", "https", "data"] },
    transformTags: {
      img: (tagName, attributes) => {
        const safeAttributes = { ...attributes };
        const source = (safeAttributes.src || "").trim();
        // Editor images may be embedded PNG/JPEG/GIF/WebP files. Keep vector
        // documents and executable data URLs outside the article policy.
        if (/^data:/i.test(source.replace(/[\u0000-\u0020]/g, ""))) {
          if (
            /^data:image\/(?:png|jpeg|gif|webp);base64,[a-z\d+/]+={0,2}$/i.test(
              source,
            )
          ) {
            safeAttributes.src = source;
          } else {
            delete safeAttributes.src;
          }
        }
        // A srcset must not bypass the validated single-image source above.
        delete safeAttributes.srcset;
        return { tagName, attribs: safeAttributes };
      },
    },
  });
};
