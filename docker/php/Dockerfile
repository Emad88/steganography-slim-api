FROM php:8.3-fpm

# Install tools needed by Composer
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl

# Install system dependencies for GD
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libwebp-dev \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configure the GD extension (important for JPEG/WebP support)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp

# Install and enable the GD extension
RUN docker-php-ext-install -j$(nproc) gd

WORKDIR /app