<?php

Artisan::command('subscriptions:renew', function () {
    $count = 0;
    foreach (Company::where('status', 'active')->cursor() as $company) {
        $count += app(CompanyContext::class)->run($company, fn () => app(SubscriptionService::class)->renewDue());
    }
    $this->info("Created {$count} renewal invoices.");
});
Schedule::command('subscriptions:renew')->daily()->withoutOverlapping();

use App\Models\Company;
use App\Services\Subscriptions\SubscriptionService;
use App\Services\Tenancy\CompanyContext;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
