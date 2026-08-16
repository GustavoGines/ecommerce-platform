<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PurgeAbandonedCarts extends Command
{
    protected $signature = 'carts:purge';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge abandoned carts from guests older than 7 days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        \App\Models\Tenant::all()->runForEach(function ($tenant) {
            $deleted = \App\Models\Cart::whereNull('user_id')
                ->where('updated_at', '<', now()->subDays(7))
                ->delete();

            $this->info("Purged {$deleted} abandoned carts for tenant {$tenant->id}.");
        });
    }
}
