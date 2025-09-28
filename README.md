# CQRS Library

A comprehensive Command Query Responsibility Segregation (CQRS) implementation for PHP using Symfony Messenger, featuring 
built-in metrics and monitoring capabilities.

## Standalone Usage Example

Here's how to set up and use the CQRS buses in a standalone PHP application:

```php
<?php

require 'vendor/autoload.php';

use CubicMushroom\Cqrs\Bus\SymfonyCommandBus;
use CubicMushroom\Cqrs\Bus\SymfonyQueryBus;
use CubicMushroom\Cqrs\Command\CommandInterface;
use CubicMushroom\Cqrs\Query\QueryInterface;
use CubicMushroom\Cqrs\Middleware\LoggingMiddleware;
use CubicMushroom\Cqrs\Middleware\MetricsMiddleware;
use CubicMushroom\Cqrs\Middleware\MessageIdStampMiddleware;
use CubicMushroom\Cqrs\Bus\IdProvider\UlidMessageIdFactory;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;

// 1. Create a PSR-3 logger
$logger = new NullLogger(); // Replace with your PSR-3 logger

// 2. Create Middleware dependencies
$messageIdFactory = new UlidMessageIdFactory();

// 3. Create middleware instances
$messageIdMiddleware = new MessageIdStampMiddleware($messageIdFactory);
$metricsMiddleware = new MetricsMiddleware(/* your metrics exporters */);
$metricsMiddleware->setLogger($logger);
$loggingMiddleware = new LoggingMiddleware($logger);

// 4. Define your commands and queries first
class CreateUserCommand implements CommandInterface 
{
    public function __construct(
        public readonly string $username,
        public readonly string $email
    ) {}
}

class GetUserQuery implements QueryInterface
{
    public function __construct(
        public readonly string $userId
    ) {}
}

// 5. Create and register command/query handlers
$commandHandlers = [
    CreateUserCommand::class => [
        function (CreateUserCommand $command) {
            // Handle user creation
            echo "Creating user: {$command->username} ({$command->email})\n";
            return $command->username; // Return any result
        }
    ]
];

$queryHandlers = [
    GetUserQuery::class => [
        function (GetUserQuery $query) {
            // Fetch user data
            return [
                'id' => $query->userId,
                'username' => 'example_user',
                'email' => 'user@example.com'
            ];
        }
    ]
];

// 6. Create the message buses with middleware and handlers
$commandBus = new MessageBus([
    $messageIdMiddleware,
    $loggingMiddleware,
    $metricsMiddleware,
    new HandleMessageMiddleware(new HandlersLocator($commandHandlers))
]);

$queryBus = new MessageBus([
    $messageIdMiddleware,
    $loggingMiddleware,
    $metricsMiddleware,
    new HandleMessageMiddleware(new HandlersLocator($queryHandlers))
]);

// 7. Create the CQRS buses
$cqrsCommandBus = new SymfonyCommandBus($commandBus);
$cqrsQueryBus = new SymfonyQueryBus($queryBus);

// 8. Dispatch commands and queries
$userId = $cqrsCommandBus->dispatch(new CreateUserCommand('johndoe', 'john@example.com'));
$user = $cqrsQueryBus->dispatch(new GetUserQuery($userId));

// 9. Use the results
echo "Created and retrieved user: " . $user['username'] . "\n";
```

For a complete example with dependency injection and middleware configuration, see the [examples](examples/) directory.

## Features

- **Complete CQRS Implementation**: Commands, Queries, and Domain Events with dedicated buses
- **Built-in Metrics**: Automatic collection of performance metrics with support for multiple exporters (Prometheus, StatsD)
- **Symfony Messenger Integration**: Built on top of Symfony's robust message bus system
- **Asynchronous Processing**: Commands and events are processed asynchronously by default
- **Comprehensive Monitoring**: Track message processing times, memory usage, and error rates
- **Time-Ordered IDs**: Uses ULIDs for non-sequential, time-based identification
- **Extensible Middleware**: Customizable middleware system for logging, metrics, and custom behavior
- **Type Safety**: Full PHP 8.4+ type hints and strict typing
- **PSR-12 & PSR-3 Compliant**: Follows PHP coding standards and logging interfaces
- **Comprehensive Testing**: High test coverage with unit and integration tests

## Installation

```bash
composer require cubicmushroom/cqrs
```

## Quick Start

### 1. Installation

```bash
composer require cubicmushroom/cqrs
```

### 2. Configure Metrics (Optional)

The library includes built-in support for metrics collection. To enable it, configure your preferred metrics exporter:

```php
// config/packages/cqrs.yaml
services:
    # For Prometheus
    CubicMushroom\Cqrs\Metrics\Exporter\PrometheusExporter:
        arguments:
            $registry: '@prometheus'  # Your Prometheus registry service

    # Or for StatsD
    CubicMushroom\Cqrs\Metrics\Exporter\StatsDExporter:
        arguments:
            $client: '@statsd_client'  # Your StatsD client service

    # Configure the metrics middleware
    CubicMushroom\Cqrs\Middleware\MetricsMiddleware:
        arguments: 
            - '@CubicMushroom\\Cqrs\\Metrics\\Exporter\\PrometheusExporter'
            # Add more exporters as needed
        tags: 
            - { name: messenger.middleware, priority: 100 }
```

### 3. Create a Command

```php
<?php

use CubicMushroom\Cqrs\Command\AbstractCommand;

final class CreateUserCommand extends AbstractCommand
{
    public function __construct(
        private readonly string $email,
        private readonly string $name,
    ) {
        parent::__construct();
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
```

### 2. Create a Command Handler

```php
<?php

use CubicMushroom\Cqrs\Command\CommandHandlerInterface;
use CubicMushroom\Cqrs\Command\CommandInterface;
use CubicMushroom\Cqrs\Bus\DomainEventBusInterface;

final class CreateUserCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly DomainEventBusInterface $eventBus,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function handle(CommandInterface $command): void
    {
        assert($command instanceof CreateUserCommand);

        // Validate business rules
        if ($this->userRepository->existsByEmail($command->getEmail())) {
            throw new \DomainException('User with this email already exists');
        }

        // Create the user
        $user = new User(
            id: Ulid::generate(),
            email: $command->getEmail(),
            name: $command->getName()
        );

        $this->userRepository->save($user);

        // Dispatch domain event
        $this->eventBus->dispatch(new UserCreatedEvent(
            userId: $user->getId(),
            email: $user->getEmail(),
            name: $user->getName()
        ));
    }
}
```

### 3. Create a Query

```php
<?php

use CubicMushroom\Cqrs\Query\AbstractQuery;

final class GetUserQuery extends AbstractQuery
{
    public function __construct(
        private readonly string $userId,
    ) {
        parent::__construct();
    }

    public function getUserId(): string
    {
        return $this->userId;
    }
}
```

### 4. Create a Query Handler

```php
<?php

use CubicMushroom\Cqrs\Query\QueryHandlerInterface;
use CubicMushroom\Cqrs\Query\QueryInterface;

final class GetUserQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly UserReadModel $userReadModel,
    ) {
    }

    public function handle(QueryInterface $query): array
    {
        assert($query instanceof GetUserQuery);

        $user = $this->userReadModel->findById($query->getUserId());

        if (!$user) {
            throw new \RuntimeException('User not found');
        }

        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'created_at' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
```

### 5. Create a Domain Event

```php
<?php

use CubicMushroom\Cqrs\DomainEvent\AbstractDomainEvent;

final class UserCreatedEvent extends AbstractDomainEvent
{
    public function __construct(
        private readonly string $userId,
        private readonly string $email,
        private readonly string $name,
    ) {
        parent::__construct();
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEventName(): string
    {
        return 'user.created';
    }
}
```

### 6. Use the Buses

```php
<?php

use CubicMushroom\Cqrs\Bus\CommandBusInterface;
use CubicMushroom\Cqrs\Bus\QueryBusInterface;

final class UserController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    public function createUser(Request $request): Response
    {
        $command = new CreateUserCommand(
            email: $request->get('email'),
            name: $request->get('name')
        );

        // Dispatch command asynchronously
        $requestId = $this->commandBus->dispatch($command);

        return new JsonResponse([
            'request_id' => $requestId,
            'message' => 'User creation initiated'
        ], 202);
    }

    public function getUser(string $userId): Response
    {
        $query = new GetUserQuery($userId);

        // Dispatch query synchronously
        $user = $this->queryBus->dispatch($query);

        return new JsonResponse($user);
    }
}
```

## Configuration

### Symfony Configuration

Configure the message buses in your Symfony application:

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        buses:
            command.bus:
                middleware:
                    - CubicMushroom\Cqrs\Middleware\LoggingMiddleware
                    - CubicMushroom\Cqrs\Middleware\MetricsMiddleware
            query.bus:
                middleware:
                    - CubicMushroom\Cqrs\Middleware\LoggingMiddleware
                    - CubicMushroom\Cqrs\Middleware\MetricsMiddleware
            event.bus:
                middleware:
                    - CubicMushroom\Cqrs\Middleware\LoggingMiddleware
                    - CubicMushroom\Cqrs\Middleware\MetricsMiddleware

        routing:
            # Message bus routing
            'CubicMushroom\Cqrs\Command\CommandInterface': command.bus
            'CubicMushroom\Cqrs\Query\QueryInterface': query.bus
            'CubicMushroom\Cqrs\Event\DomainEventInterface': event.bus

        transports:
            async: 
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                # Transport routing
                routing:
                    'CubicMushroom\Cqrs\Command\CommandInterface': async
                    'CubicMushroom\Cqrs\Event\DomainEventInterface': async
```

### Service Registration

```yaml
# config/services.yaml
services:
    # Bus implementations
    CubicMushroom\Cqrs\Bus\CommandBusInterface:
        class: CubicMushroom\Cqrs\Bus\SymfonyCommandBus
        arguments:
            $messageBus: '@command.bus'
            $logger: '@logger'

    CubicMushroom\Cqrs\Bus\QueryBusInterface:
        class: CubicMushroom\Cqrs\Bus\SymfonyQueryBus
        arguments:
            $messageBus: '@query.bus'
            $logger: '@logger'

    CubicMushroom\Cqrs\Bus\EventBusInterface:
        class: CubicMushroom\Cqrs\Bus\SymfonyEventBus
        arguments:
            $messageBus: '@event.bus'
            $logger: '@logger'

    # Middleware
    CubicMushroom\Cqrs\Middleware\LoggingMiddleware:
        arguments:
            $logger: '@logger'

    CubicMushroom\Cqrs\Middleware\MetricsMiddleware: ~

    # Auto-register handlers
    App\Application\Handler\:
        resource: '../src/Application/Handler/'
        tags: ['messenger.message_handler']
```

## Development

### Requirements

- PHP 8.4+
- Symfony Messenger ^7.3
- Symfony UID ^7.3
- PSR Log ^3.0

### Development Setup

#### Using DevContainer (Recommended)

1. Clone the repository
2. Open in PHPStorm
3. Click "Open in Container" when prompted
4. PHPStorm will build the container and set up the development environment

#### Manual Setup

1. Clone the repository
2. Install dependencies: `composer install`
3. Run tests: `task dev:test`
4. Check code quality: `task dev:quality`

For detailed PHPStorm setup instructions, see [PHPStorm Setup Guide](docs/phpstorm-setup.md).

### Available Tasks

```bash
# Development environment
task dev:setup          # Initial setup
task dev:test           # Run all tests
task dev:quality        # Run quality checks
task dev:fix            # Fix code style issues

# Testing
task dev:test:unit      # Run unit tests
task dev:test:coverage  # Run tests with coverage
task dev:test:mutation  # Run mutation tests

# Code Quality
task dev:quality:phpstan     # Static analysis
task dev:quality:phpcs       # Code style check
task dev:quality:security    # Security audit
```

## Architecture

This library follows the CQRS pattern with clear separation between:

- **Commands**: Represent intentions to change state
- **Queries**: Represent intentions to retrieve data  
- **Events**: Represent things that have happened

### Key Design Principles

1. **Immutability**: All messages are immutable
2. **Asynchronous Processing**: Commands and events are async by default
3. **Comprehensive Logging**: Full audit trail for all operations
4. **Type Safety**: Strict typing throughout
5. **Testability**: Designed for easy testing and mocking

### Middleware System

The library includes powerful middleware for:

- **Logging**: Comprehensive audit logging
- **Metrics**: Performance and usage metrics
- **Custom Behavior**: Easy to extend with custom middleware

## Testing

The library includes comprehensive tests:

- **Unit Tests**: Test individual components in isolation
- **Integration Tests**: Test complete workflows
- **Example Implementations**: Working examples for common patterns

Run tests with:

```bash
composer test
# or
task dev:test
```

## Documentation

- [Architecture Documentation](docs/cqrs-architecture.md)
- [DevContainer Setup](docs/devcontainer-setup.md)

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Ensure all tests pass
6. Submit a pull request

## License

This project is licensed under the MIT License.

## Security

If you discover any security vulnerabilities, please email security@cubicmushroom.co.uk instead of using the issue tracker.

## Support

- Documentation: [docs/](docs/)
- Issues: [GitHub Issues](https://github.com/cubicmushroom/cqrs/issues)
- Email: toby@cubicmushroom.co.uk


## Roadmap

- Update MessageStatusRecorderMiddleware to store the ID responses from CreateCommentInterface implementing commands;
- Provide Queries to query whether a command has been processed successfully, as well as it's child commands and events;
- Rename $baseTags to $tags in \CubicMushroom\Cqrs\Middleware\MetricsMiddleware::recordMetrics()
- Add error/timeout handling to the MessageStatusRecorderMiddleware
- Enforce the DomainEvents are handled synchronously (but can dispatch async commands)
- Add someway of exporting the metrics to an ingestor, such as StatsD or Prometheus/Grafana;
- Move GenericInterfaceTemplateRule into the PHPStan rules repo;
