# Local development

This repository contains both the WordPress theme and its front-end source code.

## Prerequisites

- Docker Desktop with the WSL 2 backend
- Node.js 24 LTS
- pnpm 11.19.0

## First-time setup

Install the root development tools and the front-end dependencies:

```powershell
pnpm install --frozen-lockfile
pnpm frontend:install
```

Start the WordPress environment:

```powershell
pnpm wp:start
```

The development site is available at <http://localhost:8088>. The default login is
`admin` / `password`.

Useful environment commands:

```powershell
pnpm wp:status
pnpm wp:logs
pnpm wp:stop
pnpm wp:reset
```

## Front-end workflow

Create a production build:

```powershell
pnpm frontend:build
```

Watch the front-end source and rebuild `scripts/dist/` during development:

```powershell
pnpm frontend:watch
```

The build directory is deliberately separate from the theme's committed `js/`
directory. Review the differences before replacing the assets used by WordPress:

```powershell
pnpm assets:check
pnpm assets:deploy
```

`assets:deploy` validates the expected entry points and atomically replaces `js/`.
Run a production build immediately before deployment; do not deploy a development
watch build for a release.

## Debugging

The local environment enables `WP_DEBUG`, `WP_DEBUG_LOG`, and `SCRIPT_DEBUG`, and
requests that PHP errors stay out of rendered pages. The theme's current
`php_notice_filter` handling can override the display setting, so check both the
page and the WordPress/PHP container output with `pnpm wp:logs`.

The local site uses the latest stable WordPress release and its default PHP version.
The theme itself currently declares WordPress 6.0+ and PHP 8.0+ support.
