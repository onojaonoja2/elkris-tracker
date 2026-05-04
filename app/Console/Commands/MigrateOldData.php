<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateOldData extends Command
{
    protected $signature = 'app:migrate-old-data
        {--force : Force the operation to run without confirmation}';

    protected $description = 'Migrate users and customers from the old database to the new database';

    public function handle(): int
    {
        if (! $this->confirmOldDatabase()) {
            return self::FAILURE;
        }

        if (! $this->option('force')) {
            if (! $this->confirm('This will migrate users and customers from the old database. Continue?', true)) {
                return self::FAILURE;
            }
        }

        $this->info('Starting data migration from old database...');
        $this->newLine();

        $userCount = $this->migrateUsers();
        $this->newLine();
        $customerCount = $this->migrateCustomers();
        $this->newLine();

        $this->info('Migration completed successfully!');
        $this->table(['Entity', 'Migrated'], [
            ['Users', $userCount],
            ['Customers', $customerCount],
        ]);

        return self::SUCCESS;
    }

    protected function confirmOldDatabase(): bool
    {
        try {
            $count = DB::connection('mysql-old')->table('users')->count();
            $this->info("Old database connected successfully. Found {$count} users.");

            return true;
        } catch (\Exception $e) {
            $this->error('Cannot connect to old database: '.$e->getMessage());

            return false;
        }
    }

    protected function migrateUsers(): int
    {
        $this->info('Migrating users...');

        $oldUsers = DB::connection('mysql-old')->table('users')->orderBy('id')->get();
        $migrated = 0;

        foreach ($oldUsers as $oldUser) {
            $existing = DB::table('users')->find($oldUser->id);

            if ($existing) {
                $this->warn("  Skipping user ID {$oldUser->id} ({$oldUser->email}) - already exists");

                continue;
            }

            DB::table('users')->insert([
                'id' => $oldUser->id,
                'name' => $oldUser->name,
                'email' => $oldUser->email,
                'email_verified_at' => $oldUser->email_verified_at,
                'password' => $oldUser->password,
                'role' => $oldUser->role,
                'my_id' => $oldUser->my_id,
                'lead_id' => $oldUser->lead_id,
                'stock_balance' => 0,
                'is_active' => true,
                'remember_token' => $oldUser->remember_token,
                'created_at' => $oldUser->created_at,
                'updated_at' => $oldUser->updated_at,
            ]);

            $migrated++;
            $this->line("  ✓ Migrated user: {$oldUser->name} ({$oldUser->email})");
        }

        DB::statement('ALTER TABLE users AUTO_INCREMENT = '.($oldUsers->max('id') + 1));

        $this->info("  Done: {$migrated} users migrated.");

        return $migrated;
    }

    protected function migrateCustomers(): int
    {
        $this->info('Migrating customers...');

        $oldCustomers = DB::connection('mysql-old')->table('customers')->orderBy('id')->get();
        $migrated = 0;
        $skipped = 0;

        foreach ($oldCustomers as $oldCustomer) {
            $existing = DB::table('customers')->find($oldCustomer->id);

            if ($existing) {
                $this->warn("  Skipping customer ID {$oldCustomer->id} ({$oldCustomer->customer_name}) - already exists");
                $skipped++;

                continue;
            }

            $repAcceptanceStatus = match ($oldCustomer->customer_status) {
                'customer' => 'accepted',
                'prospect' => 'pending',
                default => null,
            };

            $city = strtolower($oldCustomer->city ?? '');
            [$region, $state] = $this->deriveRegionState($city);

            DB::table('customers')->insert([
                'id' => $oldCustomer->id,
                'lead_id' => $oldCustomer->lead_id,
                'rep_id' => $oldCustomer->rep_id,
                'customer_name' => $oldCustomer->customer_name,
                'phone_number' => $oldCustomer->phone_number,
                'age' => $oldCustomer->age,
                'gender' => $oldCustomer->gender,
                'city' => $oldCustomer->city,
                'address' => $oldCustomer->address,
                'customer_status' => $oldCustomer->customer_status,
                'priority' => 'medium',
                'diabetic_awareness' => $oldCustomer->diabetic_awareness,
                'call_date' => $oldCustomer->call_date,
                'preffered_call_time' => $oldCustomer->preffered_call_time,
                'feedback' => $oldCustomer->feedback,
                'remarks' => $oldCustomer->remarks,
                'follow_up_date' => $oldCustomer->follow_up_date,
                'order_quantity' => $oldCustomer->order_quantity,
                'is_payment_verified' => false,
                'sort' => $oldCustomer->sort,
                'agent_id' => null,
                'rep_acceptance_status' => $repAcceptanceStatus,
                'rep_accepted_at' => null,
                'three_day_follow_up_done' => false,
                'seven_day_follow_up_done' => false,
                'trial_order_purchase' => null,
                'region' => $region,
                'state' => $state,
                'rejection_note' => null,
                'rejected_at' => null,
                'rejected_by' => null,
                'needs_replacement' => false,
                'replacement_requested_by' => null,
                'replacement_requested_at' => null,
                'lifetime_purchases' => null,
                'created_at' => $oldCustomer->created_at,
                'updated_at' => $oldCustomer->updated_at,
            ]);

            $migrated++;
            $this->line("  ✓ Migrated customer: {$oldCustomer->customer_name} (ID: {$oldCustomer->id})");
        }

        DB::statement('ALTER TABLE customers AUTO_INCREMENT = '.($oldCustomers->max('id') + 1));

        $this->info("  Done: {$migrated} customers migrated, {$skipped} skipped.");

        return $migrated;
    }

    protected function deriveRegionState(string $city): array
    {
        return match ($city) {
            'lagos' => ['South-West', 'Lagos'],
            'abuja' => ['North-Central', 'FCT'],
            default => [null, null],
        };
    }
}
