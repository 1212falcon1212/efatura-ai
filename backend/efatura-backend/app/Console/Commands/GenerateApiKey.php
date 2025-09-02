<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\{Organization, ApiKey};
use Illuminate\Support\Str;

class GenerateApiKey extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-api-key {organization_id} {--name=Default}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate an API key for an organization';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $orgId = (int) $this->argument('organization_id');
        $name = (string) $this->option('name');

        $org = Organization::find($orgId);
        if (!$org) {
            $this->error('Organization not found: '.$orgId);
            return self::FAILURE;
        }

        $prefix = app()->isProduction() ? 'live_' : 'test_';
        $plain = $prefix.Str::random(40);

        $apiKey = ApiKey::create([
            'organization_id' => $org->id,
            'name' => $name,
            'key' => $plain,
        ]);

        $this->info('API key created');
        $this->line('Organization: '.$org->id.' ('.$org->name.')');
        $this->line('Name: '.$apiKey->name);
        $this->line('Key: '.$plain);

        return self::SUCCESS;
    }
}
