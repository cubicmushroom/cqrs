<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\Tests\Dummy\Command;

use CubicMushroom\Cqrs\Bus\DomainEventBusInterface;
use CubicMushroom\Cqrs\Tests\Dummy\Event\DummyDomainEvent;

final readonly class DummyEventDispatchingCommandHandler
{
    public function __construct(
        private DomainEventBusInterface $domainEventBus,
    ) {
    }


    public function __invoke(DummyEventDispatchingCommand $command)
    {
        $this->domainEventBus->dispatch(new DummyDomainEvent());
    }
}
