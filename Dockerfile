# https://frankenphp.dev/docs/docker/
#
# Variants for PHP 8.2, 8.3, 8.4 and 8.5 are provided.
#
# The tags follow this pattern: dunglas/frankenphp:<frankenphp-version>-php<php-version>-<os>
#
#    <frankenphp-version> and <php-version> are version numbers of FrankenPHP and PHP respectively, ranging from major (e.g. 1), minor (e.g. 1.2) to patch versions (e.g. 1.2.3).
#    <os> is either trixie (for Debian Trixie), bookworm (for Debian Bookworm), or alpine (for the latest stable version of Alpine).
#
FROM dunglas/frankenphp:1.11.3-php8.3

# Install PHP extensions
RUN install-php-extensions \
    curl \
    pdo_mysql \
    gd \
    intl \
    bcmath \
    calendar \
    zip \
    opcache

# Install Composer globally
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Verify installation
RUN composer --version

# Install bash, bash-completion, and enable history
RUN apt-get update \
 && apt-get install -y bash bash-completion \
 && rm -rf /var/lib/apt/lists/* \
 && echo "source /etc/bash_completion" >> /root/.bashrc \
 && echo 'export HISTFILE=/root/.bash_history' >> /root/.bashrc \
 && echo 'export HISTSIZE=1000' >> /root/.bashrc \
 && echo 'export HISTFILESIZE=2000' >> /root/.bashrc

# Set environment variables
ENV CADDY_GLOBAL_OPTIONS="debug"

# Set working directory
WORKDIR /app

# Copy composer files first to leverage Docker cache
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-interaction --optimize-autoloader --no-dev

# Copy the rest of the application
COPY . .

# Start FrankenPHP (Caddy)
CMD ["frankenphp", "run", "--config", "/app/Caddyfile"]
