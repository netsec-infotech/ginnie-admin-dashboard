# Shared carts

Creating a share requires sign-in. The creator enters a cart title, then gets
a link. The Copy button copies only the URL. Native sharing passes the title
and URL to the system share sheet.

Recipients see the public cart title and items before choosing **Merge carts**
or **Replace my cart**. Merge is the default. Replace warns that existing
items will be removed. Signed-in recipients send the selected action to
`POST /api/user/cart/shared/{code}/import`. Guests validate the snapshot and
live products before changing the persisted offline cart. Unavailable items
are skipped; network failures leave the cart unchanged.

## Static and server deployments

Shared cart codes are created after a static build. On Apache static hosting,
`out/.htaccess` rewrites `/cart/shared/<code>/` to the exported shared-cart
shell, and the page fetches the public cart snapshot from
`GET /api/user/cart/shared/{code}`. Upload the complete `out/` directory,
including the hidden `.htaccess` file.

With `NEXT_PUBLIC_SSR=true`, the Next route loads the public snapshot for
new codes on demand. Neither deployment needs a PHP handler, PM2 process, or
special crawler route for sharing and importing carts.

Shared-cart pages use the normal cart page title and remain `noindex`.
They do not generate cart-specific search or social-preview metadata. The
rest of the storefront's SEO is unchanged.

Older FTP uploads may leave `cart-shared.php` on the host because FTP does
not delete remote files. The generated `.htaccess` blocks direct requests to
that old handler; remove the file from the host when possible.
