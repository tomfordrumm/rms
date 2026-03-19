# RMS

Restaurant Management System for small and medium restaurants that need two things in one product:

- a public digital menu available by QR code
- online table reservations without guest registration

The project gives restaurant owners a private back office to manage restaurant data, dishes, categories, tables, reservations, and QR links. Guests get a simple public experience: open the menu, browse dishes, check booking availability, reserve a table, and manage or cancel a reservation through a secure tokenized link.

## Who This Project Is For

- Restaurant owners and managers who want to publish a digital menu and accept reservations online
- Restaurant staff who need a simple admin panel for tables and bookings
- Guests who want to view the menu and reserve a table without creating an account

## What The Project Does

### Public side

- Public restaurant menu by slug: `/r/{slug}/menu`
- Public booking flow by slug: `/r/{slug}/booking`
- Availability lookup before reservation confirmation
- Reservation success page and self-service management page
- Reservation update and cancellation by tokenized link
- Optional AI-powered "Magic Order" dish recommendations based on guest preferences

### Admin side

- User authentication with registration, login, email verification, password management, and two-factor authentication
- Restaurant onboarding after signup
- Restaurant profile management:
  name, description, slug, contacts, working hours, closed dates, logo, and cover image
- CRUD for menu categories
- CRUD for dishes
- CRUD for restaurant tables
- Reservation list, filtering, editing, and cancellation
- QR code generation for the public menu URL and booking URL

### Operational behavior

- Email notifications for reservation confirmation and reservation updates
- Public and admin validation around reservation availability and table capacity
- Uploaded media served from Laravel public storage

## Domain Overview

Core entities in the system:

- `User`
- `Restaurant`
- `RestaurantTable`
- `Reservation`
- `Category`
- `Dish`
- `restaurant_user`
- `category_dish`

At a high level:

- one user can be attached to one or more restaurants
- one restaurant has many tables, reservations, categories, and dishes
- dishes and categories are connected through a pivot table
- reservations belong to a restaurant and a table

## Tech Stack

### Backend

- PHP 8.2+
- Laravel 12
- Laravel Fortify for authentication and account security
- Inertia.js Laravel adapter
- Laravel Wayfinder

### Frontend

- Vue 3
- TypeScript
- Inertia.js for SPA-like server-driven pages
- Vite 7
- Tailwind CSS 4

### UI and frontend utilities

- `reka-ui`
- `lucide-vue-next`
- `@vueuse/core`
- `class-variance-authority`
- `clsx`
- `tailwind-merge`

### Data and infrastructure defaults

- SQLite by default for local development
- Database-backed queue
- Database-backed cache
- Database-backed sessions
- Local and public filesystem storage
- Log mailer by default in local environment

### Tooling and quality

- PHPUnit 11
- Laravel Pint
- ESLint
- Prettier
- `vue-tsc`

## Main User Flows

### Restaurant owner

1. Register or log in
2. Complete restaurant onboarding
3. Add categories and dishes
4. Add tables and capacities
5. Share menu QR and booking QR with guests
6. Receive and manage reservations in the admin panel

### Guest

1. Open menu by QR or direct URL
2. Browse dishes and categories
3. Open booking page
4. Select date, time, party size, and available table
5. Confirm reservation
6. Manage or cancel reservation from the tokenized link

## Requirements

To run the project locally you need:

- PHP 8.2 or newer
- Composer
- Node.js and npm
- SQLite or another supported Laravel database

## Installation

### Quick setup

The repository already defines a convenience setup command:

```bash
composer setup
```

This command:

- installs PHP dependencies
- creates `.env` from `.env.example` if missing
- generates the app key
- runs migrations
- installs frontend dependencies
- builds frontend assets

### Manual setup

If you prefer to run everything step by step:

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm install
php artisan storage:link
```

Then update `.env` if needed.

Default local database settings in `.env.example` use SQLite:

```env
DB_CONNECTION=sqlite
```

## Environment Configuration

Important environment variables:

- `APP_NAME`
- `APP_URL`
- `DB_CONNECTION`
- `MAIL_MAILER`
- `QUEUE_CONNECTION`
- `OPENAI_API_KEY`
- `OPENAI_MODEL`

Notes:

- `OPENAI_API_KEY` is only required if you want to use the Magic Order recommendation feature
- mail defaults to `log`, which is enough for local development
- queue defaults to `database`, so make sure migrations are applied

## Running The Project

### Full local development mode

```bash
composer dev
```

This starts:

- Laravel local server
- queue listener
- Laravel log tailing
- Vite dev server

### Frontend only

```bash
npm run dev
```

### Production asset build

```bash
npm run build
```

## Useful Commands

### Run tests

```bash
composer test
```

### Run formatting and static checks

```bash
npm run lint:check
npm run format:check
npm run types:check
composer lint:check
```

### Generate demo content

Generate a sample menu for a restaurant:

```bash
php artisan dishes:generate {restaurant_id_or_slug}
```

Generate fake reservations:

```bash
php artisan reservations:fake {count} --restaurant={restaurant_id_or_slug}
```

## Project Structure

```text
app/                 Laravel application code
app/Http/Controllers Public and admin HTTP controllers
app/Models           Domain models
database/migrations  Database schema
database/factories   Test and seed factories
resources/js         Vue + TypeScript frontend
resources/views      Blade templates and email views
routes/              Web and settings routes
tests/               Feature and unit tests
```

## Current Feature Notes

- The public menu shows only active dishes
- The public booking flow supports availability lookup and guest self-service management
- The admin panel includes reservation editing and cancellation
- QR codes are generated for both menu and booking pages
- The dashboard page exists, but most business functionality currently lives in the dedicated admin sections

## Local Development Tips

- If uploaded images are not visible, make sure `php artisan storage:link` has been executed
- If reservation-related background behavior is not being processed, make sure the queue listener is running
- If Magic Order returns unavailable, verify `OPENAI_API_KEY` and outbound network access

## License

This repository does not currently define a dedicated project license in the README. Check repository policy before distributing or reusing the code outside the intended environment.
