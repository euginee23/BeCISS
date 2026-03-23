# BeCISS — Barangay e-Community Information System and Services

A web-based management system for Philippine barangay (local government unit) administration. Built with Laravel 12, Livewire 4, and Flux UI.

## Features

- **Resident Management** — Register and manage barangay residents, household groupings, and profile information.
- **Certificate Requests** — Process barangay clearances, certificates of residency/indigency, business permits, cedulas, and more.
- **Appointment Scheduling** — Book and manage appointments for various barangay services.
- **Role-Based Access Control** — Three roles: `admin`, `staff`, and `resident`.
- **Two-Factor Authentication** — Powered by Laravel Fortify.

## Tech Stack

| Layer          | Technology                          |
|----------------|-------------------------------------|
| Language       | PHP 8.4                             |
| Framework      | Laravel 12                          |
| Frontend       | Livewire 4, Flux UI v2, Tailwind CSS v4 |
| Authentication | Laravel Fortify (with 2FA support)  |
| Build Tool     | Vite 7                              |
| Testing        | Pest 4 / PHPUnit 12                 |
| Dev Tools      | Laravel Sail, Pail, Pint            |

## Requirements

- PHP 8.4+
- Composer
- Node.js & npm
- A supported database (MySQL, SQLite, etc.)

## Getting Started

### One-command setup

```bash
composer setup
```

This installs PHP and JS dependencies, copies `.env`, generates the app key, runs migrations, and builds frontend assets.

### Manual setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install && npm run build
```

### Development server

```bash
composer dev
```

Starts the Laravel dev server, queue worker, Pail log viewer, and Vite dev server concurrently.

## Running Tests

```bash
composer test
```

Or to run tests directly:

```bash
php artisan test --compact
```

## Code Style

This project uses [Laravel Pint](https://laravel.com/docs/pint) for code formatting:

```bash
composer lint
```

## Database Overview

| Table          | Description                                      |
|----------------|--------------------------------------------------|
| `users`        | Authenticate `admin/staff/resident` roles         |
| `residents`    | Resident profiles with household relationships   |
| `certificates` | Certificate requests and their processing status |
| `appointments` | Service appointments and scheduling              |

## User Roles

| Role       | Access                                        |
|------------|-----------------------------------------------|
| `admin`    | Full system access                            |
| `staff`    | Manage residents, certificates, appointments  |
| `resident` | View own profile and service history          |

## License

Private — All rights reserved.
