# ElkrisFoods Diabetic Tracker

A comprehensive management system for ElkrisFoods to streamline diabetic food customer tracking, order management, stockist operations, and sales team performance monitoring.

## Tech Stack
- **Backend**: Laravel 13, PHP 8.3+
- **Admin Panel**: Filament v5 (Livewire v4, Tailwind CSS v4)
- **Testing**: PHPUnit 12
- **AI Integration**: Laravel AI, Filament Copilot
- **Tooling**: Laravel Boost, Pint, Pail

## Key Features
- **Role-Based Dashboards**: Dedicated interfaces for Field Agents, Leads, Sales Reps, Managers, and Supervisors with tailored widgets
- **Customer Management**: Track diabetic food customers, trial orders, call logs, and follow-up schedules
- **Order & Inventory**: Manage orders, products, stockist transactions, and real-time stock balances
- **Sales Analytics**: Visualize order trends, rep performance, conversion rates, and city-wise order distribution
- **Export Tools**: Export trial orders and stockist data for reporting
- **AI-Powered Tools**: Copilot integrations for quick customer lookups and list management

## Installation

1. Clone the repository:
   ```bash
   git clone <repository-url>
   cd elkrisfoods-diabetic-tracker
   ```

2. Install dependencies and set up:
   ```bash
   composer run setup
   ```

   This runs: `composer install`, `.env` setup, key generation, migrations, `npm install`, and `npm run build`.

3. (Optional) Configure your database in `.env` if not using the default SQLite.

## Running the Application

For development with hot reload, queue workers, and log tailing:
```bash
composer run dev
```

For production:
```bash
php artisan serve
```

## Testing

Run the full test suite:
```bash
composer test
```

Run specific tests:
```bash
php artisan test --filter=TestName
```

## Role Access
- **Field Agent**: Daily submissions, customer replacements
- **Lead**: Portfolio management, agent submissions, order tracking
- **Sales Rep**: Personal stats, pending assignments, follow-ups
- **Manager**: Team analytics, conversion tracking, agent portfolios
- **Supervisor**: Stock management, trial order metrics
- **Admin**: Full system access via User resource

## License

MIT License
