# Use the official PHP 8.4 image with Apache
FROM php:8.4-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    wget \
    gnupg \
    lsb-release \
    && rm -rf /var/lib/apt/lists/*


# Install PHP extensions
RUN docker-php-ext-install mbstring pcntl bcmath

# Install Xdebug for debugging
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Task (task runner)
RUN sh -c "$(curl --location https://taskfile.dev/install.sh)" -- -d -b /usr/local/bin

# Create a non-root user
ARG USERNAME=developer
ARG USER_UID=1000
ARG USER_GID=$USER_UID

RUN groupadd --gid $USER_GID $USERNAME \
    && useradd --uid $USER_UID --gid $USER_GID -m $USERNAME \
    && apt-get update \
    && apt-get install -y sudo \
    && echo $USERNAME ALL=\(root\) NOPASSWD:ALL > /etc/sudoers.d/$USERNAME \
    && chmod 0440 /etc/sudoers.d/$USERNAME

# Install global PHP tools for code quality and testing
RUN composer global require \
    phpstan/phpstan \
    squizlabs/php_codesniffer \
    friendsofphp/php-cs-fixer \
    roave/security-advisories:dev-latest \
    pestphp/pest

# Install the Github ssh key fingerprint
RUN mkdir -p /home/$USERNAME/.ssh \
    && ssh-keyscan github.com >> /home/$USERNAME/.ssh/known_hosts

# Add Composer global bin to PATH
ENV PATH="/home/$USERNAME/.composer/vendor/bin:$PATH"

# Set working directory
WORKDIR /workspace

# Switch to non-root user
USER $USERNAME
