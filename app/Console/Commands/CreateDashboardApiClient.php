<?php

namespace App\Console\Commands;

use App\Models\DashboardApiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Command\Command as CommandAlias;

class CreateDashboardApiClient extends Command
{
    protected $signature = 'dashboard-api:create-client
                            {name : A descriptive name for the service client}';

    protected $description = 'Create a management dashboard API service client and one-time token';

    public function handle(): int
    {
        $name = trim((string) $this->argument('name'));

        if ($name === '') {
            $this->error('The service client name cannot be empty.');

            return CommandAlias::FAILURE;
        }

        [$client, $plainTextToken] = DB::transaction(function () use ($name): array {
            $client = DashboardApiClient::query()->create([
                'name' => $name,
                'is_active' => true,
            ]);
            $token = $client->createToken('management-dashboard', ['dashboard:read']);

            return [$client, $token->plainTextToken];
        });

        $this->info("Dashboard API client #{$client->getKey()} created.");
        $this->line($plainTextToken);
        $this->warn('Store this token securely now. It cannot be retrieved again.');

        return CommandAlias::SUCCESS;
    }
}
