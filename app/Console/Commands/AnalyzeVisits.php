<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use App\Models\PageVisit;
use Carbon\Carbon;

class AnalyzeVisits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:analyze-visits {--days=7 : Number of days to analyze}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze PageVisits across all tenants to identify bots';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $dateLimit = Carbon::now()->subDays($days);
        
        $this->info("Analizando visitas de los ultimos $days dias...");

        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $this->warn("\n--- Tenant: {$tenant->id} ---");
            
            $tenant->run(function() use ($dateLimit) {
                $visits = PageVisit::where('created_at', '>=', $dateLimit)->get();
                
                $this->info("Total de visitas en este periodo: " . $visits->count());
                
                if ($visits->count() === 0) return;

                // Agrupar por User Agent
                $this->line("\nTop 10 User Agents:");
                $uas = $visits->groupBy('user_agent')->map->count()->sortDesc()->take(10);
                foreach($uas as $ua => $count) {
                    $this->line(sprintf(" [%3d] %s", $count, $ua));
                }

                // Agrupar por IP
                $this->line("\nTop 10 IPs:");
                $ips = $visits->groupBy('ip_address')->map->count()->sortDesc()->take(10);
                foreach($ips as $ip => $count) {
                    $this->line(sprintf(" [%3d] %s", $count, $ip));
                }
                
                // Agrupar por URL
                $this->line("\nTop 10 URLs visitadas:");
                $urls = $visits->groupBy('url')->map->count()->sortDesc()->take(10);
                foreach($urls as $url => $count) {
                    $this->line(sprintf(" [%3d] %s", $count, $url));
                }
            });
        }
    }
}
