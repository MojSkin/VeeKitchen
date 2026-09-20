<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Route;

test('a signed-in admin lands on the admin dashboard after login', function () {
    $admin = User::factory()->admin()->create(['password' => 'password']);

    $response = $this->post(route('login'), [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('admin.dashboard'));
});

test('a signed-in cashier lands on the cashier panel after login', function () {
    $cashier = User::factory()->cashier()->create(['password' => 'password']);

    $response = $this->post(route('login'), [
        'email' => $cashier->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('cashier.index'));
});

test('every staff role home route actually exists', function () {
    // A stale route name here 500s the login redirect even though the
    // session is already established — the bug that hid this regression.
    foreach (UserRole::staff() as $role) {
        expect(Route::has($role->homeRoute()))->toBeTrue();
    }
});
