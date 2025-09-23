# DevContainer Setup Guide

This project includes a complete DevContainer configuration for consistent development environments using JetBrains Gateway and Docker.

## Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop)
- [JetBrains Gateway](https://www.jetbrains.com/remote-development/gateway/) (free)

## Getting Started

1. **Clone the repository**:
   ```bash
   git clone <repository-url>
   cd cqrs
   ```

2. **Start DevContainer**:
   ```bash
   docker build -f .devcontainer/Dockerfile -t cqrs-dev .
   docker run -d --name cqrs-dev -v $(pwd):/workspace cqrs-dev
   ```

3. **Connect via JetBrains Gateway**:
   - Open JetBrains Gateway
   - Select "Docker" connection type
   - Choose your running container (`cqrs-dev`)
   - Gateway will install PHPStorm automatically

4. **Initial Setup**:
   The container will automatically run `composer install` during the post-create process.

## Development Environment Features

### PHP Environment
- **PHP 8.4** with CLI
- **Composer** for dependency management
- **Xdebug** configured for debugging and coverage
- **Task** runner for development workflows

### Code Quality Tools
- **PHPStan** (Level 9) - Static analysis
- **PHP CodeSniffer** (PSR-12) - Code style checking
- **PHP CS Fixer** - Automatic code formatting
- **PestPHP** - Testing framework

### JetBrains Gateway Integration
The DevContainer is optimized for JetBrains Gateway with:
- Pre-configured Xdebug for seamless debugging
- PHP interpreter auto-detection
- Code quality tools integration (PHPStan, PHPCS, PHP CS Fixer)
- Global tool installations (Pest, PHPStan, etc.)
- Task runner support through integrated terminal
- Git integration for version control
- Pre-configured PHPStorm settings via committed .idea directory

## Available Tasks

Use the Task runner to execute common development tasks:

### Testing
```bash
task test              # Run all tests
task test:unit         # Run unit tests only
task test:functional   # Run functional tests
task test:integration  # Run integration tests
task test:acceptance   # Run acceptance tests
task test:coverage     # Run tests with coverage report
task test:mutation     # Run mutation testing
```

### Code Quality
```bash
task dev:quality           # Run all quality checks
task dev:quality:phpstan   # Run PHPStan analysis
task dev:quality:phpcs     # Run PHP CodeSniffer
task dev:quality:security  # Run security audit
```

### Utilities
```bash
task dev:clean             # Clean temporary files
task build                 # Build for production
task ci:test               # Run CI tests
```

## Debugging

### Xdebug Configuration
Xdebug is pre-configured and ready to use:
- **Mode**: debug,coverage
- **Client Host**: localhost
- **Client Port**: 9003

### Setting up Debugging in PHPStorm
1. Configure the PHP interpreter to use the Docker container
2. Xdebug server is pre-configured (name: `cqrs-devcontainer`, port: `9003`)
3. Set breakpoints in your PHP code
4. Start listening for debug connections (**Run** → **Start Listening for PHP Debug Connections**)
5. The debugger will automatically connect when your code hits a breakpoint

For detailed PHPStorm setup instructions, see [PHPStorm Setup Guide](phpstorm-setup.md).

## Port Forwarding

The following ports are automatically forwarded:
- **8000**: Development server (if needed)
- **8080**: Alternative server port

## File Structure

```
.devcontainer/
├── devcontainer.json    # Main DevContainer configuration
├── Dockerfile          # Custom PHP development image
└── php.ini             # PHP configuration overrides
```

## Customization

### Adding PHP Extensions
Edit `.devcontainer/Dockerfile` and add your extensions:
```dockerfile
RUN docker-php-ext-install your_extension
```

### Adding DevContainer Features
Edit `.devcontainer/devcontainer.json` in the `features` section:
```json
"features": {
    "ghcr.io/devcontainers/features/your-feature:1": {}
}
```

### Modifying PHP Configuration
Edit `.devcontainer/php.ini` to add or modify PHP settings.

## Troubleshooting

### Container Won't Start
1. Ensure Docker Desktop is running
2. Check Docker has enough resources allocated
3. Try rebuilding the container: `docker build -f .devcontainer/Dockerfile -t cqrs-dev . --no-cache`

### Gateway Connection Issues
1. Ensure the container is running: `docker ps`
2. Restart JetBrains Gateway
3. Try reconnecting to the container

### Performance Issues
1. Increase Docker Desktop memory allocation
2. Consider using Docker volumes for better performance on macOS/Windows

## Security Considerations

- The container runs as a non-root user (`developer`)
- Roave Security Advisories package is included to check for known vulnerabilities
- Regular security audits can be run with `task dev:quality:security`

## Next Steps

After setting up the DevContainer:
1. Run `task test` to ensure everything is working
2. Start developing your CQRS implementation
3. Use `task dev:quality` regularly to maintain code quality
4. Refer to the main project documentation for architecture details
