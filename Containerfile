# Stage 1 - Builder
FROM        --platform=$TARGETOS/$TARGETARCH registry.access.redhat.com/ubi9/nodejs-18-minimal AS builder

USER        0
RUN         npm install -g pnpm

WORKDIR     /var/www/dashdactyl

COPY        --chown=1001:0 public ./public
COPY        --chown=1001:0 resources/scripts ./resources/scripts
COPY        --chown=1001:0 .eslintignore .eslintrc.js .npmrc .prettierrc.json package.json pnpm-lock.yaml tailwind.config.js tsconfig.json vite.config.ts .

RUN         /opt/app-root/src/.npm-global/bin/pnpm install \
                && /opt/app-root/src/.npm-global/bin/pnpm build \
                && rm -rf resources/scripts .eslintignore .eslintrc.yml .npmrc package.json pnpm-lock.yaml tailwind.config.js tsconfig.json vite.config.ts node_modules

USER        1001

COPY        --chown=1001:0 app ./app
COPY        --chown=1001:0 bootstrap ./bootstrap
COPY        --chown=1001:0 config ./config
COPY        --chown=1001:0 database ./database
COPY        --chown=1001:0 resources/lang ./resources/lang
COPY        --chown=1001:0 resources/views ./resources/views
COPY        --chown=1001:0 routes ./routes
COPY        --chown=1001:0 .env.example ./.env
COPY        --chown=1001:0 artisan CHANGELOG.md composer.json composer.lock LICENSE.md README.md SECURITY.md .

# Stage 2 - Final
FROM        --platform=$TARGETOS/$TARGETARCH registry.access.redhat.com/ubi9/ubi-minimal

RUN         microdnf update -y \
                && rpm --install https://dl.fedoraproject.org/pub/epel/epel-release-latest-9.noarch.rpm \
                && rpm --install https://rpms.remirepo.net/enterprise/remi-release-9.rpm \
                && microdnf update -y \
                && microdnf install -y ca-certificates shadow-utils tar tzdata unzip wget \
# ref; https://bugzilla.redhat.com/show_bug.cgi?id=1870814
                && microdnf reinstall -y tzdata \
                && microdnf module -y reset php \
                && microdnf module -y enable php:remi-8.2 \
                && microdnf install -y composer cronie php-{bcmath,cli,common,fpm,gd,gmp,intl,json,mbstring,mysqlnd,opcache,pdo,pecl-redis5,pecl-zip,phpiredis,pgsql,process,sodium,xml,zstd} supervisor \
                && rm /etc/php-fpm.d/www.conf \
                && useradd --home-dir /var/lib/caddy --create-home caddy \
                && mkdir /etc/caddy \
                && wget -O /usr/local/bin/yacron https://github.com/gjcarneiro/yacron/releases/download/0.17.0/yacron-0.17.0-x86_64-unknown-linux-gnu \
                && chmod 755 /usr/local/bin/yacron \
                && microdnf remove -y tar wget \
                && microdnf clean all

COPY        --chown=caddy:caddy --from=builder /var/www/dashdactyl /var/www/dashdactyl

WORKDIR     /var/www/dashdactyl

RUN         mkdir -p /tmp/dashdactyl/cache /tmp/dashdactyl/framework/{cache,sessions,views} storage/framework \
                && rm -rf bootstrap/cache storage/framework/sessions storage/framework/views storage/framework/cache \
                && ln -s /tmp/dashdactyl/cache /var/www/dashdactyl/bootstrap/cache \
                && ln -s /tmp/dashdactyl/framework/cache /var/www/dashdactyl/storage/framework/cache \
                && ln -s /tmp/dashdactyl/framework/sessions /var/www/dashdactyl/storage/framework/sessions \
                && ln -s /tmp/dashdactyl/framework/views /var/www/dashdactyl/storage/framework/views \
                && chmod -R 755 /var/www/dashdactyl/storage/* /tmp/dashdactyl/cache \
                && chown -R caddy:caddy /var/www/dashdactyl /tmp/dashdactyl/{cache,framework}

USER        caddy
ENV         USER=caddy

RUN         composer install --no-dev --optimize-autoloader \
                && rm -rf bootstrap/cache/*.php \
                && rm -rf .env storage/logs/*.log

COPY        --from=docker.io/library/caddy:latest /usr/bin/caddy /usr/local/bin/caddy
COPY        .github/docker/Caddyfile /etc/caddy/Caddyfile
COPY        .github/docker/php-fpm.conf /etc/php-fpm.conf
COPY        .github/docker/supervisord.conf /etc/supervisord.conf
COPY        .github/docker/yacron.yaml /etc/yacron.yaml

EXPOSE      8080
CMD         ["/usr/bin/supervisord", "--configuration=/etc/supervisord.conf"]
