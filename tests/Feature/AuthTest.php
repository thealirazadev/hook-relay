<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('shows the login page to guests', function () {
    $this->get('/login')->assertOk()->assertSee('Log in');
});

it('logs in with valid credentials', function () {
    $user = User::factory()->create(['password' => Hash::make('secret123')]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'secret123',
    ])->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
});

it('rejects wrong password with a safe error and no session', function () {
    $user = User::factory()->create(['password' => Hash::make('secret123')]);

    $this->from('/login')->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertRedirect('/login')->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('throttles after too many failed attempts', function () {
    $user = User::factory()->create(['password' => Hash::make('secret123')]);

    foreach (range(1, 5) as $i) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
    }

    $response = $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
    $response->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toContain('Too many login attempts');
});

it('redirects guests away from the dashboard', function () {
    $this->get('/')->assertRedirect('/login');
});

it('logs out and ends the session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect('/login');

    $this->assertGuest();
});

it('has no registration route', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register')->assertNotFound();
});

it('creates an operator through the console command', function () {
    $this->artisan('app:create-user', ['email' => 'ada@example.com'])
        ->expectsQuestion('Password (min 8 characters)', 'secret123')
        ->expectsQuestion('Confirm password', 'secret123')
        ->assertSuccessful();

    expect(User::where('email', 'ada@example.com')->exists())->toBeTrue();
});

it('rejects a duplicate operator email in the console command', function () {
    User::factory()->create(['email' => 'ada@example.com']);

    $this->artisan('app:create-user', ['email' => 'ada@example.com'])
        ->expectsQuestion('Password (min 8 characters)', 'secret123')
        ->expectsQuestion('Confirm password', 'secret123')
        ->assertFailed();
});
