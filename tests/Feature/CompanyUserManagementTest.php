<?php

use App\Models\CompanyInvitation;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\CreateCompany;
use App\Services\Tenancy\MembershipService;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->owner = User::factory()->create();
    $this->company = app(CreateCompany::class)->execute($this->owner, ['name' => 'User management', 'currency' => 'USD', 'timezone' => 'UTC', 'financial_year_start_date' => '2026-01-01']);
    $this->actingAs($this->owner)->withSession(['company_id' => $this->company->id]);
    $this->payload = ['name' => 'Team Member', 'email' => 'member@example.test', 'password' => 'StrongPassword123!', 'password_confirmation' => 'StrongPassword123!', 'role_id' => Role::where('name', 'Accountant')->firstOrFail()->id];
});

it('creates a hashed unverified account with only the selected company membership', function () {
    $this->post('/settings/users', $this->payload)->assertSessionHasNoErrors()->assertRedirect();
    $member = User::where('email', 'member@example.test')->firstOrFail();
    expect(Hash::check($this->payload['password'], $member->password))->toBeTrue()->and($member->email_verified_at)->toBeNull()->and($member->companies()->count())->toBe(1);
    $this->assertDatabaseHas('company_users', ['company_id' => $this->company->id, 'user_id' => $member->id, 'role_id' => $this->payload['role_id'], 'is_active' => true]);
    $this->assertDatabaseHas('audit_logs', ['event' => 'company.user_created', 'company_id' => $this->company->id]);
    $this->actingAs($member)->get('/dashboard')->assertRedirect('/verify-email');
});

it('does not replace an existing users password or add them without invitation acceptance', function () {
    $existing = User::factory()->create(['email' => $this->payload['email']]);
    $password = $existing->password;
    $this->post('/settings/users', $this->payload)->assertSessionHasErrors('email');
    expect($existing->fresh()->password)->toBe($password)->and($existing->companies()->count())->toBe(0);
});

it('rejects weak or unconfirmed user passwords', function () {
    $this->payload['password'] = 'short';
    $this->post('/settings/users', $this->payload)->assertSessionHasErrors('password');
    $this->assertDatabaseMissing('users', ['email' => $this->payload['email']]);
});
it('opens a permitted feature after an employee selects their company', function () {
    $employee = User::factory()->create();
    $this->company->users()->attach($employee->id, ['role_id' => Role::where('name', 'Employee')->firstOrFail()->id, 'is_active' => true]);
    $this->actingAs($employee)->post('/companies/'.$this->company->id.'/switch')->assertRedirect('/expenses');
    $this->get('/expenses')->assertOk();
});

it('rejects read only users from management pages and account creation', function () {
    $reader = User::factory()->create();
    $this->company->users()->attach($reader->id, ['role_id' => Role::where('name', 'Read-only User')->firstOrFail()->id, 'is_active' => true]);
    $this->actingAs($reader)->get('/settings/users')->assertForbidden();
    $this->post('/settings/users', $this->payload)->assertForbidden();
});

it('lists only users belonging to the active company', function () {
    $foreign = User::factory()->create(['name' => 'Foreign person']);
    app(CreateCompany::class)->execute($foreign, ['name' => 'Foreign', 'currency' => 'USD', 'timezone' => 'UTC', 'financial_year_start_date' => '2026-01-01']);
    $this->get('/settings/users')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Settings/Users')->has('members.data', 1)->where('members.data.0.id', $this->owner->id));
    $this->put('/settings/users/'.$foreign->id, ['role_id' => $this->payload['role_id'], 'is_active' => false])->assertNotFound();
});

it('blocks inactive membership access and protects the last owner', function () {
    $member = User::factory()->create();
    $this->company->users()->attach($member->id, ['role_id' => $this->payload['role_id'], 'is_active' => true]);
    $this->put('/settings/users/'.$member->id, ['role_id' => $this->payload['role_id'], 'is_active' => false])->assertSessionHasNoErrors();
    $this->put('/settings/users/'.$this->owner->id, ['role_id' => $this->payload['role_id'], 'is_active' => false])->assertSessionHasErrors('role_id');
    $this->actingAs($member)->get('/dashboard')->assertRedirect('/companies');
});

it('enforces the user plan limit when creating and reactivating accounts', function () {
    app(CompanyContext::class)->run($this->company, function () {
        Subscription::firstOrFail()->plan->update(['features' => ['users' => 1]]);
    });
    $this->post('/settings/users', $this->payload)->assertSessionHasErrors('subscription');
    $member = User::factory()->create();
    $this->company->users()->attach($member->id, ['role_id' => $this->payload['role_id'], 'is_active' => false]);
    $this->put('/settings/users/'.$member->id, ['role_id' => $this->payload['role_id'], 'is_active' => true])->assertSessionHasErrors('subscription');
});

it('revokes only current company invitations and rejects duplicate pending invitations', function () {
    app(CompanyContext::class)->run($this->company, fn () => app(MembershipService::class)->invite(['email' => 'invite@example.test', 'role_id' => $this->payload['role_id']]));
    $this->post('/settings/invitations', ['email' => 'invite@example.test', 'role_id' => $this->payload['role_id']])->assertSessionHasErrors('email');
    $invite = app(CompanyContext::class)->run($this->company, fn () => CompanyInvitation::firstOrFail());
    $this->delete('/settings/invitations/'.$invite->id)->assertSessionHasNoErrors()->assertRedirect();
    $this->assertDatabaseMissing('company_invitations', ['id' => $invite->id]);
    $this->assertDatabaseHas('audit_logs', ['event' => 'company.invitation_revoked']);
});
