<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\EarningLine\AdjustmentComment;
use App\Domain\EarningLine\EarningLine;
use App\Domain\EarningLine\EarningLineId;
use App\Domain\EarningLine\Events\EarningLineCalculated;
use App\Domain\EarningLine\Events\EarningLineRecalculated;
use App\Domain\EarningLine\Events\ManualAdjustmentAdded;
use App\Domain\EarningLine\Events\SystemRecalculationIgnored;
use App\Domain\EarningLine\Exceptions\InvalidManualAdjustment;
use App\Domain\EarningLine\ManualAdjustment;
use App\Domain\EarningLine\Money;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class EarningLineTest extends TestCase
{
    #[Test]
    public function it_follows_the_reference_scenario_step_by_step(): void
    {
        $line = $this->newLine('1000.00');
        $this->assertCurrentValue('$1,000.00', $line);

        $line->recalculate(Money::fromString('1050.00'), $this->at());
        $this->assertCurrentValue('$1,050.00', $line);

        $this->adjust($line, '-45.55', 'Employee declined dental benefit; reversing deduction');
        $this->assertCurrentValue('$1,004.45', $line);

        $line->recalculate(Money::fromString('1200.00'), $this->at());
        $this->assertCurrentValue('$1,004.45', $line);

        $this->adjust($line, '+100.10', 'Late correction: missed approved overtime bonus');
        $this->assertCurrentValue('$1,104.55', $line);

        $this->adjust($line, '-0.10', 'Minor rounding adjustment');
        $this->assertCurrentValue('$1,104.45', $line);

        $this->adjust($line, '-0.20', 'Second minor rounding adjustment');
        $this->assertCurrentValue('$1,104.25', $line);

        $this->adjust($line, '+0.20', 'Correcting mistake in adjustment #4');
        $this->assertCurrentValue('$1,104.45', $line);

        $this->assertSame('$1,050.00', $line->systemValue()->format(), 'System value is frozen at step 3');
        $this->assertSame(
            [
                [1, "\u{2212}$45.55"],
                [2, '+$100.10'],
                [3, "\u{2212}$0.10"],
                [4, "\u{2212}$0.20"],
                [5, '+$0.20'],
            ],
            array_map(fn (ManualAdjustment $a) => [$a->number, $a->amount->formatSigned()], $line->adjustments()),
        );
        $this->assertSame('$1,104.45', $line->currentValue()->format());
    }

    #[Test]
    public function it_records_events_for_every_state_change(): void
    {
        $line = $this->newLine('1000.00');
        $line->recalculate(Money::fromString('1050.00'), $this->at());
        $this->adjust($line, '-45.55', 'Declined benefit');
        $line->recalculate(Money::fromString('1200.00'), $this->at());

        $this->assertSame(
            [EarningLineCalculated::class, EarningLineRecalculated::class, ManualAdjustmentAdded::class, SystemRecalculationIgnored::class],
            array_map(fn (object $e) => $e::class, $line->releaseEvents()),
        );
        $this->assertSame([], $line->releaseEvents(), 'Events are released only once');
    }

    #[Test]
    public function system_recalculation_is_allowed_while_there_are_no_manual_adjustments(): void
    {
        $line = $this->newLine('1000.00');

        $line->recalculate(Money::fromString('1050.00'), $this->at());
        $line->recalculate(Money::fromString('1075.00'), $this->at());

        $this->assertFalse($line->hasManualAdjustments());
        $this->assertSame('$1,075.00', $line->systemValue()->format());
        $this->assertSame('$1,075.00', $line->currentValue()->format());
    }

    #[Test]
    public function recalculating_to_the_same_amount_is_a_no_op(): void
    {
        $line = $this->newLine('1000.00');
        $line->releaseEvents();

        $line->recalculate(Money::fromString('1000.00'), $this->at());

        $this->assertSame([], $line->releaseEvents());
    }

    #[Test]
    public function the_first_manual_adjustment_freezes_the_system_value_forever(): void
    {
        $line = $this->newLine('1000.00');
        $this->adjust($line, '10.00', 'Bonus');

        foreach (['1.00', '5000.00', '1000.00'] as $sourceValue) {
            $line->recalculate(Money::fromString($sourceValue), $this->at());
        }

        $this->assertSame('$1,000.00', $line->systemValue()->format());
        $this->assertSame('$1,010.00', $line->currentValue()->format());
    }

    #[Test]
    public function an_ignored_recalculation_is_still_recorded_for_the_audit_trail(): void
    {
        $line = $this->newLine('1000.00');
        $this->adjust($line, '10.00', 'Bonus');
        $line->releaseEvents();

        $line->recalculate(Money::fromString('1200.00'), $this->at());

        [$event] = $line->releaseEvents();
        $this->assertInstanceOf(SystemRecalculationIgnored::class, $event);
        $this->assertSame(120000, $event->attemptedAmount->cents);
    }

    #[Test]
    public function adjustments_are_numbered_sequentially_and_keep_their_details(): void
    {
        $line = $this->newLine('1000.00');
        $at = new DateTimeImmutable('2026-10-01 10:00:00');

        $first = $line->addManualAdjustment(Money::fromString('-1.00'), AdjustmentComment::fromString('  First  '), 'specialist-1', $at);
        $second = $line->addManualAdjustment(Money::fromString('2.00'), AdjustmentComment::fromString('Second'), 'specialist-2', $at);

        $this->assertSame(1, $first->number);
        $this->assertSame(2, $second->number);
        $this->assertSame('First', $first->comment->value, 'Comment is trimmed');
        $this->assertSame('specialist-2', $second->authorId);
        $this->assertEquals($at, $second->addedAt);
    }

    #[Test]
    public function a_comment_is_mandatory(): void
    {
        $this->expectException(InvalidManualAdjustment::class);

        AdjustmentComment::fromString("   \n ");
    }

    #[Test]
    public function a_comment_cannot_be_unreasonably_long(): void
    {
        $this->expectException(InvalidManualAdjustment::class);

        AdjustmentComment::fromString(str_repeat('a', AdjustmentComment::MAX_LENGTH + 1));
    }

    #[Test]
    public function a_zero_adjustment_is_rejected(): void
    {
        $line = $this->newLine('1000.00');

        $this->expectException(InvalidManualAdjustment::class);

        $this->adjust($line, '0.00', 'Nothing');
    }

    #[Test]
    public function an_adjustment_requires_an_author(): void
    {
        $line = $this->newLine('1000.00');

        $this->expectException(InvalidManualAdjustment::class);

        $line->addManualAdjustment(Money::fromString('1.00'), AdjustmentComment::fromString('Why'), ' ', $this->at());
    }

    #[Test]
    public function a_rejected_adjustment_leaves_the_line_untouched(): void
    {
        $line = $this->newLine('1000.00');

        try {
            $this->adjust($line, '0', 'Nothing');
        } catch (InvalidManualAdjustment) {
        }

        $this->assertFalse($line->hasManualAdjustments());
        $line->recalculate(Money::fromString('1100.00'), $this->at());
        $this->assertSame('$1,100.00', $line->currentValue()->format());
    }

    #[Test]
    public function the_line_is_rebuilt_identically_from_its_event_history(): void
    {
        $line = $this->newLine('1000.00');
        $line->recalculate(Money::fromString('1050.00'), $this->at());
        $this->adjust($line, '-45.55', 'Declined benefit');
        $this->adjust($line, '+100.10', 'Overtime');
        $line->recalculate(Money::fromString('1300.00'), $this->at());

        $rebuilt = EarningLine::reconstitute($line->releaseEvents());

        $this->assertEquals($line->id(), $rebuilt->id());
        $this->assertSame('$1,050.00', $rebuilt->systemValue()->format());
        $this->assertSame('$1,104.55', $rebuilt->currentValue()->format());
        $this->assertCount(2, $rebuilt->adjustments());
        $this->assertSame(5, $rebuilt->version());
        $this->assertSame(5, $rebuilt->persistedVersion());
        $this->assertSame([], $rebuilt->releaseEvents());
    }

    #[Test]
    public function new_events_are_tracked_on_top_of_the_persisted_version(): void
    {
        $line = EarningLine::reconstitute($this->newLine('1000.00')->releaseEvents());

        $this->adjust($line, '1.00', 'Bonus');

        $this->assertSame(1, $line->persistedVersion());
        $this->assertSame(2, $line->version());
    }

    #[Test]
    public function adjustments_cannot_be_edited_or_removed_through_the_model(): void
    {
        $publicMethods = array_map(
            fn (ReflectionMethod $m) => $m->getName(),
            (new ReflectionClass(EarningLine::class))->getMethods(ReflectionMethod::IS_PUBLIC),
        );

        foreach ($publicMethods as $method) {
            $this->assertDoesNotMatchRegularExpression('/^(update|edit|remove|delete|change|replace)/i', $method);
        }
        $this->assertTrue((new ReflectionClass(ManualAdjustment::class))->isReadOnly());
    }

    private function newLine(string $amount): EarningLine
    {
        return EarningLine::calculate(EarningLineId::generate(), 'employee-42', Money::fromString($amount), $this->at());
    }

    private function adjust(EarningLine $line, string $amount, string $comment): void
    {
        $line->addManualAdjustment(Money::fromString($amount), AdjustmentComment::fromString($comment), 'specialist-1', $this->at());
    }

    private function at(): DateTimeImmutable
    {
        return new DateTimeImmutable;
    }

    private function assertCurrentValue(string $expected, EarningLine $line): void
    {
        $this->assertSame($expected, $line->currentValue()->format());
    }
}
