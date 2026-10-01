<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use DateTimeImmutable;

/**
 * A fact that happened in the domain. Events are immutable and are the
 * single source of truth: aggregate state is always derived from them.
 */
interface DomainEvent
{
    /** Stable name used to persist the event (must never change once events are stored). */
    public static function eventType(): string;

    public function aggregateId(): string;

    public function occurredAt(): DateTimeImmutable;

    /** @return array<string, mixed> */
    public function toPayload(): array;

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): static;
}
