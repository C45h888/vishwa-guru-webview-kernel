# =====================================================================
# Temple Trust Management System — Runtime Image
# =====================================================================
#
# Base:    php:8.3-cli
# Purpose: Application runtime. Includes ext-redis for the Redis client,
#          ext-pdo_pgsql for Neon PostgreSQL, and the PHP extensions Laravel
#          + composer need to boot.
#
# This Dockerfile does NOT bake in application code — code is bind-mounted
# from the host for development, or copied in for production (override
# the CMD/ENTRYPOINT for your deployment target).
#
# Why a custom Dockerfile when php:8.3-cli ships with most of what we need:
#   - ext-redis is NOT in the standard php image — it must be built from
#     PECL via `docker-php-ext-install redis`. This is the only PHP extension
#     the runtime absolutely requires beyond defaults.
#   - ext-pdo_pgsql must be explicitly enabled for PostgreSQL/Neon access.
#   - ext-intl for locale-aware string handling (Indian donor addresses,
#     Bengali/Tamil names in future phases).
#
# Build:
#   docker build -t temple-trust/runtime:php8.3 .
#
# Run (development, with bind mount):
#   docker run --rm -it \
#     -v $(pwd):/app \
#     -w /app \
#     -p 8000:8000 \
#     temple-trust/runtime:php8.3 \
#     php artisan serve --host=0.0.0.0
#
# Doctrine note: every line in this file has a reason. Resist the urge to
# `apt-get install` ad-hoc packages — that turns the runtime into a snowflake.
# =====================================================================

FROM php:8.3-cli

# System dependencies for PHP extension builds
#   - libpq-dev: PostgreSQL client lib (for ext-pdo_pgsql)
#   - libicu-dev: ext-intl (locale-aware string handling)
#   - git: composer needs git for many packages
#   - unzip: composer needs unzip to extract dist packages
#   - libzip-dev: ext-zip
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
        libicu-dev \
        libzip-dev \
        git \
        unzip \
        ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions required by the kernel + Redis integration
# Order matters: dependencies first, then dependents.
#
# Bundled extensions (compiled in php:8.3-cli):
#   pdo_pgsql — PostgreSQL driver for Neon.
#   intl      — locale-aware string handling.
#   zip       — composer needs this for dist packages.
#   bcmath    — arbitrary precision math (Money VO arithmetic).
#
# PECL extensions (compiled from source at build time):
#   redis     — phpredis, our Redis client (doctrine: required by
#               composer.json as ext-redis:*).
#
# opcache is bundled but disabled by default — we enable it explicitly
# so production builds get the perf win.
RUN docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        intl \
        zip \
        bcmath \
    && pecl install redis \
    && docker-php-ext-enable redis opcache \
    && pecl clear-cache \
    && rm -rf /tmp/pear

# Install composer (image: composer:2 is the official image)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install Node.js (image: node:22-bookworm is the official image) so the
# frontend build runs inside this container and not on the host. The
# composer.json post-install-cmd invokes `npm ci && npm run build` when
# npm is on PATH; before this layer the build silently no-op'd and the
# app rendered against a stale (or missing) public/build/manifest.json.
#
# Doctrine: ONE runtime. The container owns the build so `docker compose
# up -d` is the canonical dev workflow — no host-side `npm run build`,
# no host-side `vite` dev server, no public/hot bridge file.
#
# Copy the full Node distribution, not just the binaries — npm/npx need
# their adjacent /usr/local/lib/node_modules/npm lib directory or they
# crash with `Cannot find module '../lib/cli.js'`.
COPY --from=node:22-bookworm /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm /usr/local/bin/npm /usr/local/bin/npm
COPY --from=node:22-bookworm /usr/local/bin/npx /usr/local/bin/npx
COPY --from=node:22-bookworm /usr/local/lib/node_modules /usr/local/lib/node_modules

# Restore the symlinks that the official node:22-bookworm image ships
# with. BuildKit's COPY resolved those symlinks and copied the script
# bodies instead of preserving the indirection, which makes npm's
# shebang (`require('../lib/cli.js')`) point at a non-existent file.
# Pointing the bin entries back at the lib's bin scripts restores the
# layout npm expects and matches what `node:22-bookworm` provides
# natively.
RUN ln -sf /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
 && ln -sf /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

# Sane PHP defaults for a Laravel application runtime
#   - memory_limit: 512M (queue workers need headroom for batch jobs)
#   - upload_max_filesize: 20M (receipt PDFs, gallery images)
#   - opcache.validate_timestamps: 0 in prod, 1 in dev (set per env)
#   - opcache.revalidate_freq: cheap recheck cadence
RUN { \
        echo 'memory_limit = 512M'; \
        echo 'upload_max_filesize = 20M'; \
        echo 'post_max_size = 24M'; \
        echo 'opcache.enable = 1'; \
        echo 'opcache.memory_consumption = 192'; \
        echo 'opcache.max_accelerated_files = 20000'; \
        echo 'date.timezone = Asia/Kolkata'; \
    } > /usr/local/etc/php/conf.d/99-temple-trust.ini

WORKDIR /app

# Production-ready CMD — for development, override with bind-mount + artisan serve
# CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
