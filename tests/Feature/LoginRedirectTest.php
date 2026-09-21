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

test('a signed-in kitchen user lands on the kitchen display after login', function () {
    $kitchen = User::factory()->kitchen()->create(['password' => 'password']);

    $response = $this->post(route('login'), [
        'email' => $kitchen->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('kitchen.index'));
});

test('a signed-in customer lands on the public home after login', function () {
    $customer = User::factory()->customer()->create(['password' => 'password']);

    $response = $this->post(route('login'), [
        'email' => $customer->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('home'));
});

test('every staff role home route actually exists', function () {
    // A stale route name here 500s the login redirect even though the
    // session is already established — the bug that hid this regression.
    foreach (UserRole::staff() as $role) {
        expect(Route::has($role->homeRoute()))->toBeTrue();
    }
});

test('a remembered session re-enters through the role home route', function () {
    // Session recovery rides the same named-route redirect as a fresh
    // login: a staff member opening a staff area must land on their own
    // home, never on someone else's panel or a 500.
    foreach (UserRole::staff() as $role) {
        $user = User::factory()->{$role->value}()->create();

        $response = $this->actingAs($user)->get(route($role->homeRoute()));

        $response->assertOk();
    }
});

test('guests are kept out of every staff area', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    $this->get(route('cashier.index'))->assertRedirect(route('login'));
    $this->get(route('kitchen.index'))->assertRedirect(route('login'));
});

test('a wrong password never redirects to any panel', function () {
    $admin = User::factory()->admin()->create(['password' => 'password']);

    $response = $this->from(route('login'))->post(route('login'), [
        'email' => $admin->email,
        'password' => 'not-the-password',
    ]);

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});
