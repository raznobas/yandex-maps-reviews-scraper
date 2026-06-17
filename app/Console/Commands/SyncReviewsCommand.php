<?php

namespace App\Console\Commands;

use App\Jobs\SyncOrganizationReviews;
use App\Models\Organization;
use Illuminate\Console\Command;

class SyncReviewsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-reviews {organization_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Yandex Maps reviews for all or a specific organization';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $orgId = $this->argument('organization_id');

        $query = Organization::query();
        if ($orgId) {
            $query->where('id', $orgId);
        }

        $organizations = $query->get();

        if ($organizations->isEmpty()) {
            $this->info('No organizations found to sync.');

            return;
        }

        foreach ($organizations as $org) {
            $this->info("Dispatching sync for Organization ID {$org->id} (Yandex ID: {$org->yandex_organization_id})");
            SyncOrganizationReviews::dispatchSync($org); // Run synchronously for the console command
            $this->info("Successfully synced Organization ID {$org->id}");
        }
    }
}
