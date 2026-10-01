<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use DateTimeImmutable;

interface DomainEvent
{
    public const string DATE_FORMAT = 'Y-m-d\TH:i:s.uP';

    public static function eventType(): string;

    public function aggregateId(): string;

    public function occurredAt(): DateTimeImmutable;

    public function toPayload(): array;

    public static function fromPayload(array $payload): static;
}
