# Lungenfachärzte in der Bertoldstrasse — CMS

[![Build and deploy CMS to IONOS](https://github.com/ugrupp/lungenfachaerzte-cms/actions/workflows/build-deploy-production.yml/badge.svg)](https://github.com/ugrupp/lungenfachaerzte-cms/actions/workflows/build-deploy-production.yml)

[cms.lungenfachaerzte.de](https://cms.lungenfachaerzte.de/)

- [**Frontend**](https://github.com/ugrupp/lungenfachaerzte-webapp) — TanStack Start (React 19, SSR) deployed to Netlify
- ➡️ **CMS** — Craft CMS 5 in headless mode, GraphQL API

Craft CMS 5 running in headless mode. No front-end templates — all content is served via GraphQL to the TanStack Start frontend.

## Stack

| Layer | Technology |
|---|---|
| CMS | Craft CMS 5 |
| Rich text | CraftCMS CKEditor plugin |
| SEO | ether/seo plugin |
| GraphQL | Built-in Craft GraphQL API at `/api` |

## Prerequisites

- PHP ≥ 8.2
- MySQL 8+ or PostgreSQL 13+
- Composer
- A local dev environment (DDEV recommended)

## Setup

```bash
composer install
cp .env.example.dev .env
# Fill in DB credentials and other values in .env
php craft setup
```

Key `.env` values:

| Variable | Description |
|---|---|
| `DB_*` | Database connection |
| `PRIMARY_SITE_URL` | Front-end origin — used for CORS (e.g. `http://localhost:3000` locally, Netlify URL in production) |
| `SECURITY_KEY` | Craft security key — generate with `php craft setup/security-key` |

## Development

```bash
php craft serve          # optional built-in PHP server
php craft migrate/all    # run pending migrations
php craft project-config/apply  # apply project config changes from git
```

The Craft control panel is at `/admin`.

## GraphQL

The public GraphQL endpoint is at `/api` (configured in `config/routes.php`).

- Test queries in the GraphiQL IDE: `http://<host>/api/graphiql`
- Schema and token are managed in the CP → GraphQL → Schemas
- The bearer token must be set as `GRAPHQL_TOKEN` in the webapp's `.env`

CORS is configured in `config/app.web.php` to allow requests from `PRIMARY_SITE_URL`.

## Live preview

The `modules/livepreview/` module injects JavaScript into the Craft CP that converts the standard iframe-refresh live preview into `postMessage`-based hot reload. The front-end (`CraftPreviewListener.tsx`) listens for the `entry:live-preview:updated` message and calls `router.invalidate()` instead of doing a full reload.

Preview target URL template (set per section in the CP):

```
{siteUrl}/{uri}?token={previewToken}&x-craft-live-preview=1
```

Disable "Auto-refresh" on the preview target to use postMessage instead of iframe reload.

## Project config

Schema changes (sections, fields, entry types, etc.) are tracked in `config/project/` and committed to git. After pulling changes, run:

```bash
php craft project-config/apply
```

## Sitemap

The ether/seo plugin generates sitemaps. The webapp's `netlify.toml` proxies `/sitemap*` requests to `$CRAFT_URL/sitemap*` so the sitemap is served from the production domain.
