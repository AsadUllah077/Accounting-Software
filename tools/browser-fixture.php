<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!app()->environment('local')) throw new RuntimeException('Browser fixture is local-only.');
$password=\Illuminate\Support\Str::password(24);
$user=\App\Models\User::firstOrCreate(['email'=>'browser@ledger.test'],['name'=>'Browser Test','password'=>$password]);
$user->forceFill(['password'=>\Illuminate\Support\Facades\Hash::make($password),'email_verified_at'=>now()])->save();
$company=$user->companies()->first()??app(\App\Services\Tenancy\CreateCompany::class)->execute($user,['name'=>'Browser Studio','currency'=>'USD','timezone'=>'Asia/Karachi','financial_year_start_date'=>'2026-01-01']);
file_put_contents(__DIR__.'/../.browser-session.json',json_encode(['email'=>$user->email,'password'=>$password,'company_id'=>$company->id]));
echo "Local browser fixture initialized.\n";
