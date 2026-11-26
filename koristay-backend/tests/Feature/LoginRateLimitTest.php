<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    $tenant = Tenant::create(['name' => 'Villa A', 'slug' => 'villa-a']);

    User::create([
        'name' => 'Owner',
        'email' => 'owner@example.com',
        'password' => 'password123',
        'tenant_id' => $tenant->id,
    ]);
});

function attemptLogin(string $email, string $password = 'wrong-password'): TestResponse
{
    return test()->postJson('/api/auth/login', ['email' => $email, 'password' => $password]);
}

it('logs in with the right password', function () {
    attemptLogin('owner@example.com', 'password123')
        ->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'email']]);
});

it('blocks the sixth login attempt for the same email within a minute', function () {
    foreach (range(1, 5) as $attempt) {
        attemptLogin('owner@example.com')->assertUnprocessable();
    }

    attemptLogin('owner@example.com', 'password123')
        ->assertTooManyRequests()
        ->assertHeader('Retry-After');
});

it('treats the email case-insensitively when counting attempts', function () {
    foreach (range(1, 5) as $attempt) {
        attemptLogin($attempt % 2 ? 'OWNER@example.com' : 'owner@example.com')->assertUnprocessable();
    }

    attemptLogin('Owner@Example.com')->assertTooManyRequests();
});

it('does not block other emails from the same address', function () {
    foreach (range(1, 5) as $attempt) {
        attemptLogin('owner@example.com');
    }

    attemptLogin('someone-else@example.com')->assertUnprocessable();
});

it('allows logging in again after a minute', function () {
    foreach (range(1, 5) as $attempt) {
        attemptLogin('owner@example.com');
    }

    $this->travel(61)->seconds();

    attemptLogin('owner@example.com', 'password123')->assertOk();
});
