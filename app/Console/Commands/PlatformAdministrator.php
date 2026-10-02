<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PlatformAdministrator extends Command
{
    protected $signature = 'ledger:admin {email} {--revoke}';

    protected $description = 'Grant or revoke platform administration for an existing login';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->firstOrFail();
        $user->forceFill(['is_platform_admin' => ! $this->option('revoke')])->save();
        $this->info('Platform administration updated.');

        return self::SUCCESS;
    }
}
