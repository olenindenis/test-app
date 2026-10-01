# Earning Line — History of Manual Adjustments

Proof of concept for the Alcor OS payroll platform: an **earning line** (e.g. an employee's base salary)
is calculated automatically by the system, can be manually corrected by a payroll specialist, and
always exposes its current value together with a complete, tamper-proof audit trail.

Built with **Laravel 13 / PHP 8.4**, an **event-sourced** domain model and **PostgreSQL**.

---

## Quick start

Requirements: Docker + `make`.

```bash
make install   # composer install + .env inside the container
make up        # start PHP app (http://localhost:8000) and PostgreSQL
make migrate   # create tables in the dev database
make test      # run the PHPUnit suite against PostgreSQL in Docker
```

The test suite uses a dedicated database (`earning_lines_test`, created by `docker/postgres/init.sql`).
Unit tests don't need a database at all: `php artisan test --testsuite=Unit`.

---

## How it works

### Why event sourcing

The business rules map almost one-to-one onto an append-only log of events:

| Business rule | How the model enforces it |
|---|---|
| Corrections can never be edited or silently deleted | Every change is an immutable event appended to `earning_line_events`. The aggregate has no edit/delete methods, there are no PUT/PATCH/DELETE endpoints, and a **PostgreSQL trigger rejects `UPDATE`, `DELETE` and `TRUNCATE`** on the event table. |
| Mistakes are fixed by a new, compensating correction | The only way to change the value is `addManualAdjustment()`, which appends another event. |
| After the first correction, system recalculation must not affect the line | `EarningLine::recalculate()` checks whether any adjustment exists; if so it records `SystemRecalculationIgnored` instead of changing the value. |
| Current value and full history are available at any time | Current value = frozen system value + Σ adjustments. The history is available both as a read model (`GET /api/earning-lines/{id}`) and as the raw event stream (`GET /api/earning-lines/{id}/events`). |

### Domain events

| Event | When |
|---|---|
| `EarningLineCalculated` | The system calculates the line for the first time |
| `EarningLineRecalculated` | Source data changed and the line has no manual adjustments yet |
| `ManualAdjustmentAdded` | A specialist adds a correction (amount, mandatory comment, author, sequence number) |
| `SystemRecalculationIgnored` | Source data changed, but the line is already locked by a manual adjustment |

### Flow

```
             HR / source system                        Payroll specialist
                     │                                         │
     EmployeeBaseSalaryChanged (integration event)     HTTP POST /adjustments
                     │                                         │
       RecalculateEmployeeEarningLines (listener)       AddManualAdjustmentHandler
                     │                                         │
                     └──────────────► EarningLine aggregate ◄──┘
                                      (business rules, emits domain events)
                                              │
                                 EventSourcedEarningLineRepository
                         ┌────────────────────┴───────────────────┐  (one DB transaction)
                         ▼                                        ▼
            earning_line_events (append-only)          Laravel event dispatcher
             source of truth, versioned                           │
                                                       EarningLineProjector
                                                                  ▼
                                            earning_lines / earning_line_adjustments
                                                    (read models, rebuildable)
```

* **Write side** — the `EarningLine` aggregate (plain PHP, no framework dependencies) is rebuilt from its
  events, executes a command, and records new events. The repository appends them to the event store
  with **optimistic concurrency** (`UNIQUE(aggregate_id, version)`): if two specialists edit the same line
  simultaneously, the second save fails with `ConcurrencyException` (HTTP 409) instead of silently
  overwriting.
* **Read side** — `EarningLineProjector` is a Laravel event subscriber that keeps the read models in sync.
  It runs synchronously inside the same transaction, so the read models are always consistent with the
  event store. They can be thrown away and rebuilt at any time:
  `php artisan earning-lines:rebuild-projections`.
* **Source data changes** arrive as the `EmployeeBaseSalaryChanged` integration event. Its listener simply
  asks every line of that employee to recalculate — whether that is applied or ignored is decided by the
  aggregate, not by the caller.

### Project structure

```
app/Domain/Shared/             AggregateRoot, DomainEvent
app/Domain/EarningLine/        EarningLine aggregate, Money, ManualAdjustment, events, exceptions
app/Application/EarningLine/   Commands, command handlers, GetEarningLineHistory query + DTO
app/Infrastructure/            PostgresEventStore, EventSerializer, repository, projector
app/Events, app/Listeners      EmployeeBaseSalaryChanged integration event + listener
app/Http/                      EarningLineController, form requests
app/Console/Commands/          earning-lines:rebuild-projections
database/migrations/           event store (with immutability trigger) and read models
tests/Unit/                    domain + serializer tests (no DB)
tests/Feature/                 end-to-end tests against PostgreSQL
```

---

## API

| Method | Path | Body | Description |
|---|---|---|---|
| `POST` | `/api/earning-lines` | `employee_id`, `amount` | System calculates a new line |
| `POST` | `/api/earning-lines/{id}/recalculations` | `amount` | System recalculation (ignored once the line has adjustments) |
| `POST` | `/api/earning-lines/{id}/adjustments` | `amount`, `comment`, `author_id` | Specialist adds a manual correction |
| `GET` | `/api/earning-lines/{id}` | — | Current value + audit history |
| `GET` | `/api/earning-lines/{id}/events` | — | Raw immutable event stream |

Amounts are decimal strings with at most two decimals and an optional sign: `"1050.00"`, `"-45.55"`, `"+0.20"`.

Example response of `GET /api/earning-lines/{id}` after the reference scenario:

```json
{
  "data": {
    "id": "01a0f77f-4f0c-70f4-87d7-be8b9bc6e247",
    "employee_id": "employee-42",
    "is_locked": true,
    "locked_at": "2026-10-01T12:45:10.000+00:00",
    "system_value": { "amount": "1050.00", "formatted": "$1,050.00" },
    "adjustments": [
      { "number": 1, "label": "Adjustment 1", "amount": "-45.55", "formatted": "−$45.55",
        "comment": "Employee declined dental benefit; reversing deduction", "author_id": "specialist-1", "added_at": "…" },
      { "number": 2, "label": "Adjustment 2", "amount": "100.10", "formatted": "+$100.10", "comment": "Late correction: missed approved overtime bonus", "…": "…" },
      { "number": 3, "label": "Adjustment 3", "amount": "-0.10",  "formatted": "−$0.10",  "comment": "Minor rounding adjustment", "…": "…" },
      { "number": 4, "label": "Adjustment 4", "amount": "-0.20",  "formatted": "−$0.20",  "comment": "Second minor rounding adjustment", "…": "…" },
      { "number": 5, "label": "Adjustment 5", "amount": "0.20",   "formatted": "+$0.20",  "comment": "Correcting mistake in adjustment #4", "…": "…" }
    ],
    "current_value": { "amount": "1104.45", "formatted": "$1,104.45" },
    "ignored_recalculations": 1
  }
}
```

---

## Tests

The reference scenario from the brief is verified at three levels:

* `tests/Unit/Domain/EarningLineTest.php` — pure domain, value checked after **every** step of the scenario.
* `tests/Feature/EarningLine/ReferenceScenarioTest.php` — through command handlers, the event store and
  projections in PostgreSQL; source data changes are delivered as the `EmployeeBaseSalaryChanged` event.
* `tests/Feature/EarningLine/EarningLineApiTest.php` — over HTTP.

Other notable tests: DB-level immutability of events (`UPDATE` / `DELETE` / `TRUNCATE` fail), concurrent
modification detection, rebuilding projections from the event store, validation (mandatory comment,
zero / malformed amounts), serialization round-trips, and `Money` arithmetic/formatting.

---

## Assumptions

* **Money** is stored as integer cents (no floats anywhere) in a single currency (USD).
* **Comment** is mandatory and must not be blank (it is trimmed); max 1000 characters.
* **Zero-amount adjustments** are rejected — they would add noise without changing anything.
* **Lock trigger** — the first manual adjustment locks the line, even if a later compensating adjustment
  brings the net adjustment total back to zero. The rule in the brief is "at least one manual correction",
  not "non-zero net correction".
* **Ignored recalculations** are not silently dropped: they are recorded as `SystemRecalculationIgnored`
  events for auditability (the value does not change). A recalculation to the same value on an unlocked
  line is a no-op and records nothing.
* **Compensating corrections** are regular adjustments; the link to the corrected adjustment is expressed
  in the comment (as in the brief: "Correcting mistake in adjustment #4"). A formal `corrects_adjustment`
  reference would be a natural next step.
* **Authentication / authorization** is out of scope; `author_id` is passed explicitly in the request.
* **What the system calculates** — the PoC treats the line's system value as being equal to the source data
  value (the base salary). The real calculation logic would live in the source system / calculator that
  emits `EmployeeBaseSalaryChanged`. All lines of the employee receive the recalculation; in a real payroll
  only lines of open pay periods would.
* **Projections are synchronous** for simplicity and strong consistency. In production they could be moved
  to queued listeners (with the event store `id` as a checkpoint) without touching the domain.
* **No event-sourcing package** (e.g. `spatie/laravel-event-sourcing`) is used on purpose: the hand-rolled
  event store is ~100 lines, has no "magic", and keeps the model easy to read and review.

## Use of AI

This solution was developed with the help of Claude Code (planning, implementation and tests),
reviewed and verified by running the full test suite against PostgreSQL.
