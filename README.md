# Earning Line — історія ручних коригувань

Proof of concept для payroll-платформи Alcor OS: **рядок нарахування** (earning line, наприклад базова
зарплата працівника) автоматично розраховується системою, може бути вручну скоригований payroll-спеціалістом
і завжди показує поточне значення разом із повним audit trail, який неможливо підробити.

Побудовано на **Laravel 13 / PHP 8.4** з доменною моделлю на основі **event sourcing** та **PostgreSQL**.

---

## Швидкий старт

Потрібно: Docker + `make`.

```bash
make install   # composer install + .env всередині контейнера
make up        # запуск PHP-застосунку (http://localhost:8000) і PostgreSQL
make migrate   # створення таблиць у dev-базі
make test      # запуск PHPUnit-тестів на PostgreSQL у Docker
make lint      # перевірка стилю коду (PHP-CS-Fixer, PER-CS)
make fix       # автоматичне виправлення стилю коду
```

Тести використовують окрему базу даних (`earning_lines_test`, її створює `docker/postgres/init.sql`).
Unit-тестам база взагалі не потрібна: `php artisan test --testsuite=Unit`.

---

## Як це працює

### Чому event sourcing

Бізнес-правила майже один в один лягають на журнал подій, у який можна лише дописувати (append-only):

| Бізнес-правило | Як модель його забезпечує |
|---|---|
| Корекції ніколи не можна змінити чи непомітно видалити | Кожна зміна — це незмінна подія, дописана в `earning_line_events`. В агрегаті немає методів редагування/видалення, немає PUT/PATCH/DELETE-ендпоінтів, а **тригер PostgreSQL відхиляє `UPDATE`, `DELETE` і `TRUNCATE`** у таблиці подій. |
| Помилки виправляються новою, компенсуючою корекцією | Єдиний спосіб змінити значення — `addManualAdjustment()`, який дописує ще одну подію. |
| Після першої корекції системний перерахунок не повинен впливати на рядок | `EarningLine::recalculate()` перевіряє, чи є хоч одна корекція; якщо так — записує `SystemRecalculationIgnored` замість зміни значення. |
| Поточне значення і повна історія доступні в будь-який момент | Поточне значення = заморожене системне значення + Σ корекцій. Історія доступна і як read model (`GET /api/earning-lines/{id}`), і як сирий потік подій (`GET /api/earning-lines/{id}/events`). |

### Доменні події

| Подія | Коли |
|---|---|
| `EarningLineCalculated` | Система вперше розраховує рядок |
| `EarningLineRecalculated` | Змінилися вихідні дані, а рядок ще не має ручних корекцій |
| `ManualAdjustmentAdded` | Спеціаліст додає корекцію (сума, обов'язковий коментар, автор, порядковий номер) |
| `SystemRecalculationIgnored` | Змінилися вихідні дані, але рядок уже заблокований ручною корекцією |

### Потік даних

```
         HR / система-джерело даних                    Payroll-спеціаліст
                     │                                         │
     EmployeeBaseSalaryChanged (інтеграційна подія)    HTTP POST /adjustments
                     │                                         │
       RecalculateEmployeeEarningLines (listener)       AddManualAdjustmentHandler
                     │                                         │
                     └──────────────► агрегат EarningLine ◄────┘
                                  (бізнес-правила, генерує доменні події)
                                              │
                                 EventSourcedEarningLineRepository
                         ┌────────────────────┴───────────────────┐  (одна транзакція БД)
                         ▼                                        ▼
            earning_line_events (append-only)          Laravel event dispatcher
            джерело правди, з версіями                            │
                                                       EarningLineProjector
                                                                  ▼
                                            earning_lines / earning_line_adjustments
                                                (read models, можна перебудувати)
```

* **Write side** — агрегат `EarningLine` (чистий PHP, без залежностей від фреймворку) відновлюється зі своїх
  подій, виконує команду і записує нові події. Репозиторій дописує їх в event store з **optimistic
  concurrency** (`UNIQUE(aggregate_id, version)`): якщо два спеціалісти одночасно змінюють один рядок, друге
  збереження завершиться `ConcurrencyException` (HTTP 409), а не непомітно перезапише перше.
* **Read side** — `EarningLineProjector` — це Laravel event subscriber, який синхронізує read models.
  Він працює синхронно в тій самій транзакції, тому read models завжди узгоджені з event store. Їх можна
  будь-коли видалити й перебудувати: `php artisan earning-lines:rebuild-projections`.
* **Зміни вихідних даних** надходять як інтеграційна подія `EmployeeBaseSalaryChanged`. Її listener просто
  просить кожен рядок цього працівника перерахуватися — чи буде перерахунок застосовано, чи проігноровано,
  вирішує агрегат, а не той, хто викликає.

### Структура проєкту

```
app/Domain/Shared/             AggregateRoot, DomainEvent
app/Domain/EarningLine/        агрегат EarningLine, Money, ManualAdjustment, події, винятки
app/Application/EarningLine/   команди, обробники команд, запит GetEarningLineHistory
app/Infrastructure/            PostgresEventStore, EventSerializer, репозиторій, проєктор
app/Events, app/Listeners      інтеграційна подія EmployeeBaseSalaryChanged + listener
app/Http/                      EarningLineController, form requests, EarningLineResource
app/Console/Commands/          earning-lines:rebuild-projections
database/migrations/           event store (з тригером незмінності) і read models
tests/Unit/                    тести домену та серіалізатора (без БД)
tests/Feature/                 end-to-end тести на PostgreSQL
```

---

## API

| Метод | Шлях | Тіло запиту | Опис |
|---|---|---|---|
| `POST` | `/api/earning-lines` | `employee_id`, `amount` | Система розраховує новий рядок |
| `POST` | `/api/earning-lines/{id}/recalculations` | `amount` | Системний перерахунок (ігнорується, щойно рядок має корекції) |
| `POST` | `/api/earning-lines/{id}/adjustments` | `amount`, `comment`, `author_id` | Спеціаліст додає ручну корекцію |
| `GET` | `/api/earning-lines/{id}` | — | Поточне значення + audit-історія |
| `GET` | `/api/earning-lines/{id}/events` | — | Сирий незмінний потік подій |

Суми передаються як десяткові рядки з не більш ніж двома знаками після крапки та необов'язковим знаком:
`"1050.00"`, `"-45.55"`, `"+0.20"`.

Приклад відповіді `GET /api/earning-lines/{id}` після еталонного сценарію:

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

## Тести

Таблиці даних із завдання перенесені дослівно в
`tests/Feature/EarningLine/BusinessCaseIntegrationTest.php` (кроки 1–8 та очікувана фінальна audit-історія)
і керують інтеграційними тестами всього стеку: HTTP API → агрегат → event store у PostgreSQL → проєкції,
а зміни вихідних даних надходять як подія `EmployeeBaseSalaryChanged`. Тест перевіряє поточне значення після
**кожного** кроку (окремий кейс data provider на кожен крок), фінальну audit-історію рядок за рядком,
коментарі та авторів кожної корекції, проігнорований перерахунок на кроці 4, компенсуючу корекцію,
незмінність і перебудову історії з event store.

Сценарій також перевіряється без бази даних у `tests/Unit/Domain/EarningLineTest.php` (чистий домен).
Кожен інший тест покриває лише одну тему: незмінність подій на рівні БД і виявлення одночасних змін
(`EventStoreTest`), поширення змін вихідних даних на незаблоковані рядки (`SourceDataChangeTest`),
HTTP-валідацію та відповіді з помилками (`EarningLineApiTest`), серіалізацію подій туди й назад та арифметику
й форматування `Money` (unit-тести).

---

## Припущення

* **Гроші** зберігаються як цілі центи (жодних float) в одній валюті (USD).
* **Коментар** обов'язковий і не може бути порожнім (пробіли на краях обрізаються); максимум 1000 символів.
* **Корекції з нульовою сумою** відхиляються — вони лише додавали б шум, нічого не змінюючи.
* **Блокування рядка** — перша ручна корекція блокує рядок, навіть якщо пізніша компенсуюча корекція повертає
  сумарну корекцію до нуля. Правило в завданні звучить як «хоча б одна ручна корекція», а не «ненульова
  сумарна корекція».
* **Проігноровані перерахунки** не відкидаються мовчки: вони записуються як події `SystemRecalculationIgnored`
  для аудиту (значення не змінюється). Перерахунок до того самого значення на незаблокованому рядку нічого
  не робить і нічого не записує.
* **Компенсуючі корекції** — це звичайні корекції; зв'язок із виправленою корекцією вказується в коментарі
  (як у завданні: "Correcting mistake in adjustment #4"). Природним наступним кроком було б формальне
  посилання `corrects_adjustment`.
* **Автентифікація / авторизація** поза межами завдання; `author_id` явно передається в запиті.
* **Що розраховує система** — у PoC системне значення рядка дорівнює значенню вихідних даних (базовій
  зарплаті). Справжня логіка розрахунку жила б у системі-джерелі / калькуляторі, який генерує
  `EmployeeBaseSalaryChanged`. Перерахунок отримують усі рядки працівника; у реальному payroll — лише рядки
  відкритих розрахункових періодів.
* **Проєкції синхронні** — заради простоти та строгої узгодженості. У продакшені їх можна перенести в
  listeners через черги (з `id` з event store як checkpoint), не чіпаючи домен.
* **Пакет для event sourcing** (наприклад, `spatie/laravel-event-sourcing`) свідомо не використовується:
  власний event store займає ~100 рядків, не має «магії» і робить модель простою для читання та рев'ю.

## Використання AI

Рішення розроблено за допомогою jev (суддя) + Claude Code (реалізація і тести), переглянуто й перевірено
запуском повного набору тестів на PostgreSQL.
