# JetBrains Gateway Setup Guide

This project is configured for secure, containerized development using JetBrains Gateway. This approach provides better security isolation by running PHPStorm inside the container, protecting your host machine from potentially malicious plugins.

## Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop)
- [JetBrains Gateway](https://www.jetbrains.com/remote-development/gateway/) (free)

## Getting Started

### 1. Clone and Start the DevContainer

```bash
git clone <repository-url>
cd cqrs
```

Build and start the container using your preferred method:

**Option A: Using DevContainer CLI**
```bash
devcontainer up --workspace-folder .
```

**Option B: Using Docker directly**
```bash
docker build -f .devcontainer/Dockerfile -t cqrs-dev .
docker run -d --name cqrs-dev -v $(pwd):/workspace cqrs-dev
```

### 2. Connect via JetBrains Gateway

1. Open **JetBrains Gateway**
2. Click **"Docker"** connection type
3. Select your running container (`cqrs-dev`)
4. Gateway will automatically handle the connection and authentication
5. Select **"PHPStorm"** as the IDE
6. Gateway will automatically download and install PHPStorm in the container
7. Select your project directory (`/workspace`) and click **"Start IDE and Project"**

### 3. Configure PHP Interpreter

Once the container is running:

1. Go to **File** → **Settings** (or **PHPStorm** → **Preferences** on macOS)
2. Navigate to **PHP** → **Interpreters**
3. The container PHP interpreter should be automatically detected
4. If not, click **+** → **From Docker, Vagrant, VM, WSL, Remote...**
5. Select **Docker Compose** and configure:
   - **Server**: Docker (should be auto-detected)
   - **Configuration files**: `.devcontainer/docker-compose.yml` (if using compose)
   - **Service**: `app` or the service name
   - **PHP executable**: `/usr/local/bin/php`

### 4. Configure Xdebug

The DevContainer is pre-configured with Xdebug. To set up debugging:

1. Go to **Settings** → **PHP** → **Debug**
2. Ensure **Xdebug** is selected as the debug engine
3. Set **Debug port** to `9003`
4. Under **Settings** → **PHP** → **Servers**:
   - Click **+** to add a new server
   - **Name**: `cqrs-devcontainer` (matches `PHP_IDE_CONFIG`)
   - **Host**: `localhost`
   - **Port**: `8000` (or your web server port)
   - **Debugger**: `Xdebug`
   - Check **Use path mappings**
   - Map your project root to `/workspace`

### 5. Configure Code Quality Tools

The container includes all necessary code quality tools. Configure them in PHPStorm:

#### PHPStan
1. Go to **Settings** → **PHP** → **Quality Tools** → **PHPStan**
2. Click **...** next to **Configuration**
3. **PHPStan path**: `/home/developer/.composer/vendor/bin/phpstan`
4. **Configuration file**: `phpstan.neon`

#### PHP CS Fixer
1. Go to **Settings** → **PHP** → **Quality Tools** → **PHP CS Fixer**
2. **PHP CS Fixer path**: `/home/developer/.composer/vendor/bin/php-cs-fixer`
3. **Configuration**: `.php-cs-fixer.php`

#### PHP CodeSniffer
1. Go to **Settings** → **PHP** → **Quality Tools** → **PHP_CodeSniffer**
2. **PHP_CodeSniffer path**: `/home/developer/.composer/vendor/bin/phpcs`
3. **PHP Code Beautifier and Fixer path**: `/home/developer/.composer/vendor/bin/phpcbf`
4. **Coding standard**: Custom → `phpcs.xml`

### 6. Configure Testing Framework

Set up PestPHP for testing:

1. Go to **Settings** → **PHP** → **Test Frameworks**
2. Click **+** → **PHPUnit by Remote Interpreter**
3. Select your Docker interpreter
4. **PHPUnit library**: Use Composer autoloader
5. **Path to script**: `/workspace/vendor/autoload.php`
6. **Test Runner**: Default configuration file → `pest.xml`

## Development Workflow

### Running Tasks

Use the integrated terminal in PHPStorm to run Task commands:

```bash
# Setup development environment
task dev:setup

# Run tests
task dev:test

# Run code quality checks
task dev:quality

# Fix code style issues
task dev:fix
```

### Debugging

1. Set breakpoints in your PHP code
2. Start listening for debug connections: **Run** → **Start Listening for PHP Debug Connections**
3. Trigger your code (web request, CLI command, test)
4. PHPStorm will stop at breakpoints

### Running Tests

- **Right-click** on test files/directories → **Run Tests**
- Use **Run** → **Run...** → Select test configuration
- View results in the integrated test runner

## Container Management

### Rebuilding the Container

If you need to rebuild the container (e.g., after Dockerfile changes):

1. **Tools** → **DevContainers** → **Rebuild Container**
2. Or use Docker commands in terminal:
   ```bash
   docker-compose -f .devcontainer/docker-compose.yml down
   docker-compose -f .devcontainer/docker-compose.yml build --no-cache
   ```

### Container Logs

View container logs:
- **Tools** → **DevContainers** → **Show Container Logs**
- Or use: **View** → **Tool Windows** → **Services** → **Docker**

## File Synchronization

PHPStorm automatically syncs files between your local machine and the container. Changes are reflected immediately in both environments.

## Performance Optimization

### For macOS/Windows Users

To improve file system performance:

1. Consider using Docker volumes for vendor directories
2. Exclude `vendor/` and `node_modules/` from file watchers
3. **Settings** → **Directories** → Mark `vendor` as **Excluded**

### Memory Settings

If you experience performance issues:

1. **Help** → **Change Memory Settings**
2. Increase heap size to 2048MB or higher
3. Restart PHPStorm

## Troubleshooting

### Container Won't Start

1. Ensure Docker Desktop is running
2. Check Docker has sufficient resources allocated
3. Try rebuilding the container
4. Check Docker logs for errors

### Xdebug Not Working

1. Verify Xdebug is enabled: `php -m | grep xdebug`
2. Check Xdebug configuration: `php -i | grep xdebug`
3. Ensure port 9003 is not blocked by firewall
4. Verify path mappings in server configuration

### Code Quality Tools Not Found

1. Ensure the container is built and running
2. Check tool paths in PHPStorm settings
3. Verify tools are installed: `which phpstan`, `which php-cs-fixer`

### Performance Issues

1. Increase Docker Desktop memory allocation
2. Use Docker volumes for better I/O performance
3. Exclude large directories from indexing

## Advanced Configuration

### Custom PHP Configuration

Edit `.devcontainer/php.ini` to customize PHP settings. Rebuild the container after changes.

### Additional Tools

To add more development tools:

1. Edit `.devcontainer/Dockerfile`
2. Add your tools installation commands
3. Rebuild the container

### Environment Variables

Add environment variables in `.devcontainer/devcontainer.json` under `remoteEnv`.

## Integration with Other Tools

### Database Tools

If your project uses databases, configure them in PHPStorm:
- **View** → **Tool Windows** → **Database**
- Add data sources for your containerized databases

### Version Control

Git integration works seamlessly:
- Use **VCS** menu for Git operations
- Configure Git settings in **Settings** → **Version Control** → **Git**

## Next Steps

After setup:

1. Run `task dev:test` to ensure everything works
2. Start developing your CQRS implementation
3. Use the example implementations as templates
4. Refer to the main project documentation for architecture details

## Support

For PHPStorm-specific issues:
- [PHPStorm Documentation](https://www.jetbrains.com/help/phpstorm/)
- [DevContainer Support](https://www.jetbrains.com/help/phpstorm/connect-to-devcontainer.html)

For project-specific issues:
- Check the main [README.md](../README.md)
- Review [CQRS Architecture Documentation](cqrs-architecture.md)
