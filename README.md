# Support Ticket Management API

REST API for a simple support ticket system, built with **Laravel 10**, **Laravel Sanctum** and **MySQL**.

Users raise support tickets; tickets are auto-assigned to the least loaded agent, and agents move
them through a fixed status workflow.

---

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configure the database and queue driver in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ticket_management
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database
```

Create the database, then run migrations and seeders:

```bash
php artisan migrate --seed
php artisan serve
```

Run the queue worker in a second terminal (required for the assignment notification job):

```bash
php artisan queue:work
```

---

## Sample accounts

Seeded by `AgentSeeder` / `DatabaseSeeder`. Password for all: `password`

| Role  | Name        | Email               |
|-------|-------------|---------------------|
| user  | Demo User   | user@support.test   |
| agent | John Deo    | john@support.test   |
| agent | Priya Nair  | priya@support.test  |
| agent | Arun Kumar  | arun@support.test   |

---

## API Endpoints

Base URL: `http://127.0.0.1:8000/api/v1`

Send `Accept: application/json` on every request, and `Authorization: Bearer <token>` on
protected routes.

| Method | Endpoint                 | Auth | Description                                   |
|--------|--------------------------|------|-----------------------------------------------|
| POST   | `/register`              | No   | Register (`role` optional: `user` \| `agent`)  |
| POST   | `/login`                 | No   | Login, returns a Sanctum token                 |
| POST   | `/logout`                | Yes  | Revoke the current token                       |
| GET    | `/me`                    | Yes  | Current authenticated user                     |
| GET    | `/tickets`               | Yes  | List tickets (scoped by role)                  |
| POST   | `/tickets`               | Yes  | Create a ticket (users only)                   |
| GET    | `/tickets/{id}`          | Yes  | Show a single ticket                           |
| PATCH  | `/tickets/{id}/status`   | Yes  | Update status (assigned agent only)            |

### Listing, filtering, sorting, pagination

```
GET /api/v1/tickets?status=open&priority=high&sort=newest&per_page=10
```

| Param      | Values                              | Default  |
|------------|-------------------------------------|----------|
| `status`   | `open`, `in_progress`, `resolved`   | –        |
| `priority` | `low`, `medium`, `high`             | –        |
| `sort`     | `newest`, `oldest`                  | `newest` |
| `per_page` | 1–100                               | `10`     |
| `page`     | integer                             | `1`      |

**Visibility:** users see only tickets they created; agents see only tickets assigned to them.
Invalid filter values are rejected with a `422`.

### Example — create a ticket

```bash
curl -X POST http://127.0.0.1:8000/api/v1/tickets \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <token>" \
  -d '{"title":"Cannot login","description":"I get a 500 error.","priority":"high"}'
```

```json
{
  "success": true,
  "message": "Ticket created successfully.",
  "data": {
    "id": 1,
    "ticket_number": "TKT-10001",
    "title": "Cannot login",
    "description": "I get a 500 error.",
    "priority": "high",
    "status": "open",
    "resolved_at": null,
    "created_at": "2026-07-27 13:03:17",
    "created_by": { "id": 4, "name": "Demo User", "email": "user@support.test" },
    "assigned_agent": { "id": 1, "name": "John Deo", "email": "john@support.test" }
  }
}
```

---

## Business rules

### Ticket number

Sequential and unique, generated inside a database transaction with a row lock:
`TKT-10001`, `TKT-10002`, …

### Automatic agent assignment

`app/Services/AgentAssignmentService.php`

On creation the ticket goes to the agent with the fewest **active** tickets
(`open` + `in_progress`). Ties are broken in favour of the earliest created agent (lowest `id`).
The logic lives in the service class, not the controller.

### Status workflow

`app/Services/TicketService.php`

Only `open → in_progress → resolved` is permitted. Anything else
(`open → resolved`, `resolved → in_progress`, …) returns `422` with the current status and the
allowed transitions:

```json
{
  "success": false,
  "message": "Invalid status transition from 'open' to 'resolved'.",
  "current_status": "open",
  "allowed_transitions": ["in_progress"]
}
```

`resolved_at` is stamped when a ticket becomes `resolved`.
An agent may only update tickets assigned to them — otherwise `403`
(enforced in `UpdateTicketStatusRequest::authorize()`).

### Queue job

`App\Jobs\TicketAssignedNotification` is dispatched on the **database** queue when a ticket is
created and assigned. It logs:

```
Ticket TKT-10001 has been assigned to Agent John Deo.
```

Check `storage/logs/laravel.log` while `php artisan queue:work` is running.

---

## Error responses

| Status | When                                                     |
|--------|----------------------------------------------------------|
| `401`  | Missing / invalid token, or bad login credentials         |
| `403`  | Agent creating a ticket, or acting on another's ticket    |
| `404`  | Ticket not found                                          |
| `422`  | Validation failure or invalid status transition           |

API errors are returned as JSON via `app/Exceptions/Handler.php`.

---

## Project structure

```
app/
├── Exceptions/
│   ├── Handler.php                         # JSON error responses for api/*
│   └── InvalidStatusTransitionException.php
├── Http/
│   ├── Controllers/Api/V1/
│   │   ├── AuthController.php
│   │   └── TicketController.php
│   ├── Requests/
│   │   ├── Auth/{RegisterRequest, LoginRequest}.php
│   │   └── Ticket/{StoreTicketRequest, IndexTicketRequest, UpdateTicketStatusRequest}.php
│   └── Resources/TicketResource.php
├── Jobs/TicketAssignedNotification.php
├── Models/{User, Ticket}.php
└── Services/
    ├── AgentAssignmentService.php          # least-loaded agent selection
    └── TicketService.php                   # creation + status workflow

database/
├── migrations/                             # users (with role), tickets, jobs
└── seeders/{AgentSeeder, DatabaseSeeder}.php

routes/api.php
postman/SupportTicketAPI.postman_collection.json
```

---

## Postman

Import `postman/SupportTicketAPI.postman_collection.json`.

The register and login requests store the returned token in the `token` collection variable
automatically, and **Create Ticket** stores the new id in `ticket_id` — so the remaining requests
work without copying values by hand.

**Suggested run order:** Login (User) → Create Ticket → List Tickets →
Login (Agent John) → Update Status `in_progress` → Update Status `resolved`.
Then try *Invalid Transition* to see the `422`.
# ticket-management
