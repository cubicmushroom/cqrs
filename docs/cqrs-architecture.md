# CQRS Architecture Documentation

## Overview

This library provides a complete Command Query Responsibility Segregation (CQRS) implementation using Symfony Messenger
as the underlying message bus. The architecture follows the Hexagonal pattern to keep business logic separate from
implementation details.

The library uses a **stamp-based message identification system** that keeps infrastructure concerns (like message IDs)
separate from business logic, following clean architecture principles.

## Core Concepts

### Commands

Commands represent the intention to change state in the system. They are immutable objects that contain only business
data.

- **CommandInterface**: Base interface for all commands (contains only business data)
- **CommandBusInterface**: Interface for dispatching commands
- **SymfonyCommandBus**: Symfony Messenger implementation of the command bus

### Queries

Queries represent the intention to retrieve data from the system. They are immutable objects that contain only query
parameters.

- **QueryInterface**: Base interface for all queries (contains only business data)
- **QueryBusInterface**: Interface for dispatching queries
- **SymfonyQueryBus**: Symfony Messenger implementation of the query bus

### Domain Events

Domain events represent something that has happened in the domain. They are used to communicate changes between bounded
contexts.

- **DomainEventInterface**: Base interface for all domain events (uses PHP 8.4 property hooks)
- **AbstractDomainEvent**: Base implementation with timestamp tracking
- **DomainEventBusInterface**: Interface for dispatching domain events
- **SymfonyDomainEventBus**: Symfony Messenger implementation of the domain event bus

## Architecture Components

### Message Identification System

The library uses a **stamp-based message identification system** that separates infrastructure concerns from business
logic:

- **MessageIdStamp**: Carries unique message identifiers on the Symfony Messenger envelope
- **MessageIdInterface**: Base interface for all message ID types
- **CommandId**, **QueryId**, **DomainEventId**: Specific ID types for different message types
- **UlidMessageIdProvider**: Generates time-ordered ULIDs for message identification
- **CausedByMessageStamp**: Tracks which previously processed messages triggered the current dispatch

This approach ensures that:

- Commands, queries, and events contain only business data
- Message tracking is handled at the infrastructure level
- IDs are automatically generated and attached during dispatch
- Full audit trails are maintained without polluting business objects

### Message Buses

#### Command Bus

- Dispatches commands asynchronously by default
- Returns a `CommandId` for tracking, prepared from the `MessageIdStamp::$messageId`, so relied on the presence of the
  `MessageIdStampMiddleware`.
- Uses `DispatchAfterCurrentBusStamp` for proper async handling

#### Query Bus

- Dispatches queries synchronously (queries need to return data)
- Returns the query result directly
- Uses Symfony Messenger's synchronous handling

#### Domain Event Bus

- Dispatches domain events synchronously
- Returns a `DomainEventId` for tracking
- Automatically attaches `MessageIdStamp` with generated `DomainEventId`
- Allows multiple handlers for the same event
- Uses `DispatchAfterCurrentBusStamp` for deferred dispatch
- Supports event-driven architecture patterns

### Middleware

#### LoggingMiddleware

- Logs all commands, queries, and events as they pass through the bus
- Records processing times and memory usage
- Provides detailed error logging with stack traces
- Supports audit trails for compliance requirements

#### MetricsMiddleware

- Collects performance metrics for all CQRS operations
- Tracks success/failure rates, processing times, and memory usage
- Provides per-class and aggregate statistics
- Supports monitoring and alerting systems

#### BlameMiddleware

- Propagates `CausedByMessageStamp` instances to downstream messages to build a causal chain
- Ensures only previously processed messages are marked as causes (no self-causation)
- Enables auditors and observability tools to trace cross-bus dispatch flows (command → event → query)
- Designed to sit in tandem with `MessageIdStampMiddleware` so all messages have identifiers before causation tracking

## Design Principles

### Separation of Concerns

- Commands handle state changes
- Queries handle data retrieval
- Events handle cross-cutting concerns
- Each has dedicated buses and handlers

### Immutability

- All commands, queries, and events are immutable
- State changes are communicated through events
- Handlers are stateless and side-effect free

### Asynchronous Processing

- Commands are processed asynchronously by default
- Events are always processed asynchronously
- Queries are processed synchronously (they need to return data)

### Comprehensive Logging

- All operations are logged for audit purposes
- Processing times and memory usage are tracked
- Errors include full context and stack traces

### Time-Ordered Identification

- Uses ULIDs (Universally Unique Lexicographically Sortable Identifiers)
- Provides non-sequential, time-based identification
- Supports distributed systems and high-throughput scenarios
- Message IDs are automatically generated and attached via stamps
- Different ID types for different message types (CommandId, QueryId, DomainEventId)
- IDs are kept separate from business logic through the stamp system

## Security Considerations

### Input Validation

- All commands and queries should validate their input
- Use type hints and readonly properties where possible
- Validate business rules in handlers

### Audit Logging

- All operations are logged with full context
- Request IDs allow correlation across system boundaries
- Timestamps use immutable DateTimeImmutable objects

### Error Handling

- Exceptions are properly logged and re-thrown
- No sensitive information is exposed in error messages
- Stack traces are logged for debugging but not exposed to users

## Performance Considerations

### Asynchronous Processing

- Commands are processed asynchronously to improve response times
- Events are processed asynchronously to support scalability
- Message queues can be scaled independently

### Memory Management

- Middleware tracks memory usage for monitoring
- Objects are designed to be lightweight and immutable
- Garbage collection is optimized through proper object lifecycle

### Monitoring

- Comprehensive metrics collection for performance analysis
- Processing time tracking for identifying bottlenecks
- Success/failure rate monitoring for reliability

## Integration with Symfony

### Message Bus Configuration

The library uses Symfony Messenger for message routing and handling:

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
      domain_event.bus:
        middleware:
          - CubicMushroom\Cqrs\Middleware\LoggingMiddleware
          - CubicMushroom\Cqrs\Middleware\MetricsMiddleware
```

### Service Configuration

Register the buses, ID providers, and stamp factories in your service container:

```yaml
# config/services.yaml
services:
  # Message ID Provider
  CubicMushroom\Cqrs\Bus\IdProvider\MessageIdFactoryInterface:
    class: CubicMushroom\Cqrs\Bus\IdProvider\UlidMessageIdProvider

  # Command Bus
  CubicMushroom\Cqrs\Bus\CommandBusInterface:
    class: CubicMushroom\Cqrs\Bus\SymfonyCommandBus
    arguments:
      $messageBus: '@command.bus'

  # Query Bus
  CubicMushroom\Cqrs\Bus\QueryBusInterface:
    class: CubicMushroom\Cqrs\Bus\SymfonyQueryBus
    arguments:
      $messageBus: '@query.bus'

  # Domain Event Bus
  CubicMushroom\Cqrs\Bus\DomainEventBusInterface:
    class: CubicMushroom\Cqrs\Bus\SymfonyDomainEventBus
    arguments:
      $messageBus: '@domain_event.bus'
```

## Testing Strategy

### Unit Tests

- Test all interfaces and stamp factories
- Mock dependencies for isolated testing
- Verify logging and metrics collection
- Test error handling and edge cases
- Test message ID stamp attachment and generation
- Verify proper separation of business logic from infrastructure

### Integration Tests

- Test complete command/query/event flows
- Verify middleware integration
- Test with real Symfony Messenger configuration
- Validate async processing behavior
- Test stamp-based ID system integration
- Verify proper ID generation and tracking

### Example Implementations

- Provide working examples using the new stamp-based system
- Demonstrate proper handler implementation with `__invoke()`
- Show event-driven architecture patterns
- Include validation and error handling examples
- Demonstrate clean separation of business logic from infrastructure

## Extension Points

### Custom Middleware

Implement `MiddlewareInterface` to add custom behavior:

- Authentication and authorization
- Rate limiting
- Caching
- Custom metrics collection

### Custom Bus Implementations

Implement the bus interfaces for different message brokers:

- RabbitMQ
- Apache Kafka
- Amazon SQS
- Redis

### Custom Identification

Customize the ID generation system:

- Implement `MessageIdFactoryInterface` for custom ID generation
- Create custom ID types by implementing `MessageIdInterface`
- Use different UUID versions or custom ID schemes
- Add additional metadata to IDs
- Replace `UlidMessageIdProvider` with your own implementation

## Best Practices

### Command Design

- Keep commands focused on a single action
- Include all necessary business data in the command
- **Do not include infrastructure concerns like IDs**
- Validate input in the command constructor
- Commands should implement only `CommandInterface` and be readonly.

### Query Design

- Design queries for specific use cases
- Include pagination parameters where appropriate
- **Do not include infrastructure concerns like IDs**
- Consider caching strategies for expensive queries
- Return DTOs rather than domain objects
- Queries should implement only `QueryInterface` and be readonly.

### Domain Event Design

- Events should represent past tense actions (e.g., `UserCreated`, `OrderProcessed`)
- Include all relevant business context in the event
- **Do not include infrastructure concerns like IDs**
- Keep events focused and cohesive
- Use `AbstractDomainEvent` for timestamp tracking
- Consider event versioning for evolution
- Events should extend `AbstractDomainEvent` or implement `DomainEventInterface` and be readonly.

### Handler Implementation

- Keep handlers focused on a single responsibility
- Validate input and business rules
- Dispatch events for side effects using the `DomainEventBusInterface`
- Handle errors gracefully with proper logging
- Handlers receive only business data (no infrastructure concerns like IDs)

## Practical Examples

### Command Example

```php
<?php

use CubicMushroom\Cqrs\Command\CommandInterface;

// Command contains only business data
final readonly class CreateUserCommand implements CommandInterface
{
    public function __construct(
        public string $email,
        public string $name,
        public string $password,
    ) {
    }
}
```

### Command Handler Example

```php
<?php

use CubicMushroom\Cqrs\Command\CommandHandlerInterface;
use CubicMushroom\Cqrs\Bus\DomainEventBusInterface;
use CubicMushroom\Cqrs\Bus\QueryBusInterface;

final readonly class CreateUserCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private DomainEventBusInterface $eventBus,
        private UserRepository $userRepository,
    ) {
    }

    public function __invoke(CreateUserCommand $command): void
    {
        // Validate business rules using Query Bus
        $existingUser = $this->queryBus->dispatch(
            new FindUserByEmailQuery($command->email)
        );
        
        if ($existingUser !== null) {
            throw new UserAlreadyExistsException($command->email);
        }

        // Create user
        $user = new User(
            $command->email,
            $command->name,
            $command->password
        );
        
        $this->userRepository->save($user);

        // Dispatch domain event (ID will be automatically generated)
        $this->eventBus->dispatch(
            new UserCreatedEvent(
                new DateTimeImmutable(),
                $user->getId(),
                $command->email,
                $command->name
            )
        );
    }
}
```

### Domain Event Example

```php
<?php

use CubicMushroom\Cqrs\DomainEvent\AbstractDomainEvent;

// Event contains only business data and extends AbstractDomainEvent for timestamp
final readonly class UserCreatedEvent extends AbstractDomainEvent
{
    public function __construct(
        DateTimeImmutable $occurredAt,
        public string $userId,
        public string $email,
        public string $name,
    ) {
        parent::__construct($occurredAt);
    }
}
```

### Query Example

```php
<?php

use CubicMushroom\Cqrs\Query\QueryInterface;

// Query contains only business parameters and specifies return type via generics
/**
 * @implements QueryInterface<UserDto|null>
 */
final readonly class FindUserByEmailQuery implements QueryInterface
{
    public function __construct(
        public string $email,
    ) {
    }
}
```

### Query Handler Example

```php
<?php

use CubicMushroom\Cqrs\Query\QueryHandlerInterface;

final readonly class FindUserByEmailQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    // Use __invoke() instead of handle()
    public function __invoke(FindUserByEmailQuery $query): ?UserDto
    {
        $user = $this->userRepository->findByEmail($query->email);
        
        return $user ? UserDto::fromUser($user) : null;
    }
}
```

### Bus Usage Example

```php
<?php

use CubicMushroom\Cqrs\Bus\CommandBusInterface;
use CubicMushroom\Cqrs\Bus\QueryBusInterface;
use CubicMushroom\Cqrs\Bus\DomainEventBusInterface;

final readonly class UserService
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
        private DomainEventBusInterface $eventBus,
    ) {
    }

    public function createUser(string $email, string $name, string $password): string
    {
        // Dispatch command - returns CommandId for tracking
        $commandId = $this->commandBus->dispatch(
            new CreateUserCommand($email, $name, $password)
        );
        
        // The CommandId can be used for tracking, logging, etc.
        return $commandId->id;
    }

    public function findUserByEmail(string $email): ?UserDto
    {
        // Dispatch query - returns the actual result
        // IDE knows this returns UserDto|null thanks to generics
        return $this->queryBus->dispatch(
            new FindUserByEmailQuery($email)
        );
    }
}
```

### Type-Safe Query Bus with Generics

The QueryBus supports PHP generics through PHPDoc annotations, providing full IDE type inference:

```php
<?php

use CubicMushroom\Cqrs\Query\QueryInterface;

// Define your DTO
final readonly class UserDto
{
    public function __construct(
        public string $id,
        public string $email,
        public string $name,
    ) {
    }
}

// Query with generic type annotation
/**
 * @implements QueryInterface<UserDto|null>
 */
final readonly class FindUserByEmailQuery implements QueryInterface
{
    public function __construct(
        public string $email,
    ) {
    }
}

final readonly class UserService
{
    public function __construct(
        private QueryBusInterface $queryBus,
    ) {
    }

    public function findUser(string $email): ?UserDto
    {
        // IDE knows this returns UserDto|null
        $user = $this->queryBus->dispatch(
            new FindUserByEmailQuery($email)
        );
        
        // IDE provides full autocomplete and type checking
        if ($user !== null) {
            echo $user->name; // IDE knows $user is UserDto
            echo $user->email; // Full property autocomplete
        }
        
        return $user;
    }
}
```

### Benefits of Generic Type Support

1. **Full IDE Type Inference**: Your IDE knows exactly what type `dispatch()` will return
2. **Compile-time Type Safety**: Static analysis tools can catch type errors
3. **Better Autocomplete**: IDE provides accurate property and method suggestions
4. **Refactoring Safety**: IDE can safely rename properties and methods
5. **Documentation**: Types serve as living documentation of what queries return

### Key Benefits of the Stamp-Based System

1. **Clean Separation**: Business objects contain only business data
2. **Automatic ID Generation**: IDs are generated and attached automatically
3. **Full Audit Trail**: Every message is tracked with unique IDs
4. **Type Safety**: Different ID types for different message types
5. **Infrastructure Independence**: Business logic is not coupled to ID generation
