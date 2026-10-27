# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- No unreleased changes yet.

## [3.1.0] - 2026-09-09

### Added

- Added Chat Support (`/my-account/support`) with real-time Pusher and Laravel Reverb integration, live typing indicators, session management, file attachments, and a dedicated mobile full-screen view.
- Added OrderWise Delivery OTP display support (`order_wise` vs `item_wise` modes), presenting order-level delivery codes on order cards and order details.
- Added Mercado Pago payment gateway integration with hosted redirect flow and gateway selection logo.

### Changed

- Refined UI across payment selection modals, order items, and responsive navigation components.
- Improved build and static site generation resiliency with adaptive retry backoff on API requests.

## [3.0.0] - 2026-08-19

### Added

- Added a modular homepage section renderer with category cards, banners, image-background sections, and infinite-scroll support.
- Added responsive navigation with dynamic branding, cart management, animated header behavior, and scroll-driven pill tabs.
- Added search modal and Swiper-based navigation components.
- Added address management, cart and checkout API routing, scheduling, payment modals, and localized checkout support.
- Added product management UI, product image galleries with video, zoom, and interactive sliders.
- Added loyalty points, transaction history, redemption, and post-order rider tipping flows with multi-gateway payment support.
- Added Xendit payment support with customer details.
- Added localized authentication components and API response type definitions.
- Added sitemap generation and site-wide SEO/environment configuration support.

### Changed

- Improved homepage layout, loading behavior, spacing, and overall storefront UX.
- Improved location handling with default-location support and reverse geocoding.
- Improved build and deployment scripts, including static-site routing support.
- Updated `next-dev-inspector` to `0.4.0` before removing the in-repo inspector implementation.

### Fixed

- Fixed location handling and related storefront flows.
- Fixed banner peek-side rendering behavior.
- Restored and refined navbar behavior across responsive layouts.

### Security

- Added a pre-commit safeguard to prevent sensitive FTP credentials from being committed.

## [2.2.0]

- Previous release.

[Unreleased]: https://github.com/Infinitietechnologies/hyperlocal-web/compare/3.1.0...HEAD
[3.1.0]: https://github.com/Infinitietechnologies/hyperlocal-web/compare/3.0.0...3.1.0
[3.0.0]: https://github.com/Infinitietechnologies/hyperlocal-web/releases/tag/3.0.0
[2.2.0]: https://github.com/Infinitietechnologies/hyperlocal-web/releases/tag/2.2.0
