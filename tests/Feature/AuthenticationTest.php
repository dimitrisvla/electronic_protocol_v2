<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Conditional first-Administrator registration
|--------------------------------------------------------------------------
|
| Public registration is available only while the application has no
| Administrator. The user registered in that state becomes an Administrator.
|
*/

test('registration page is available when no administrator exists', function () {
    $response = $this->get(route('register'));

    $response
        ->assertOk()
        ->assertSee(__('auth.register.heading'))
        ->assertSee(__('auth.register.submit'));

    $this->assertGuest();
});

test('first public registration creates and authenticates an administrator', function () {
    $response = $this->post(route('register'), [
        'name' => 'Dimitris',
        'email' => 'dimitris@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',

        // A visitor must not be able to choose a different public role.
        'role' => UserRole::Viewer->value,
    ]);

    $administrator = User::where('email', 'dimitris@example.com')->firstOrFail();

    $response
        ->assertRedirect(route('protocols.index'))
        ->assertSessionHas('success', __('flash.auth.registered'));

    $this->assertSame(UserRole::Administrator, $administrator->role);
    $this->assertAuthenticatedAs($administrator);

    $this->assertDatabaseHas('users', [
        'id' => $administrator->id,
        'role' => UserRole::Administrator->value,
    ]);
});

test('registration remains available when users exist but no administrator exists', function () {
    User::factory()->create([
        'role' => UserRole::ProtocolOfficer,
    ]);

    User::factory()->create([
        'role' => UserRole::Viewer,
    ]);

    $this->get(route('register'))->assertOk();

    $response = $this->post(route('register'), [
        'name' => 'Replacement Administrator',
        'email' => 'replacement-administrator@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertRedirect(route('protocols.index'));

    $this->assertDatabaseHas('users', [
        'email' => 'replacement-administrator@example.com',
        'role' => UserRole::Administrator->value,
    ]);
});

test('registration page is disabled while an administrator exists', function () {
    User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    $response = $this->get(route('register'));

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});

test('direct registration submission is disabled while an administrator exists', function () {
    User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    $response = $this->post(route('register'), [
        'name' => 'Forbidden Registration',
        'email' => 'forbidden@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertRedirect(route('login'));

    $this->assertDatabaseMissing('users', [
        'email' => 'forbidden@example.com',
    ]);

    $this->assertGuest();
});

test('removing one of multiple administrators does not reopen registration', function () {
    $firstAdministrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    $firstAdministrator->delete();

    $this->get(route('register'))
        ->assertRedirect(route('login'));
});

test('deleting the last administrator reopens registration', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    $administrator->delete();

    $this->get(route('register'))->assertOk();
});

test('demoting the last administrator reopens registration', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    $administrator->role = UserRole::Viewer;
    $administrator->save();

    $this->get(route('register'))->assertOk();
});

test('guest interface shows register only when no administrator exists', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee(__('common.navigation.register'))
        ->assertSee(route('register'), false);

    User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    $this->get(route('login'))
        ->assertOk()
        ->assertDontSee(__('common.navigation.register'))
        ->assertDontSee(route('register'), false);
});

test('authentication pages are displayed in Greek', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('<html lang="el">', false)
        ->assertSee(__('auth.login.heading'))
        ->assertSee(__('auth.login.email'))
        ->assertSee(__('auth.login.password'))
        ->assertSee(__('auth.login.remember'))
        ->assertSee(__('auth.login.submit'));

    $this->get(route('register'))
        ->assertOk()
        ->assertSee(__('auth.register.heading'))
        ->assertSee(__('auth.register.administrator_notice'))
        ->assertSee(__('auth.register.name'))
        ->assertSee(__('auth.register.email'))
        ->assertSee(__('auth.register.password'))
        ->assertSee(__('auth.register.password_confirmation'))
        ->assertSee(__('auth.register.submit'))
        ->assertSee(__('auth.register.login_link'));
});

/*
|--------------------------------------------------------------------------
| Login and logout
|--------------------------------------------------------------------------
*/

test('a registered user can log in with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'dimitris@example.com',
    ]);

    $response = $this->post('/login', [
        'email' => 'dimitris@example.com',
        'password' => 'password',
    ]);

    $response->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

test('a non-administrator can log in when no administrator exists', function () {
    $viewer = User::factory()->create([
        'email' => 'viewer@example.com',
        'role' => UserRole::Viewer,
    ]);

    $response = $this->post('/login', [
        'email' => 'viewer@example.com',
        'password' => 'password',
    ]);

    $response->assertRedirect();
    $this->assertAuthenticatedAs($viewer);
});

test('invalid login credentials are rejected', function () {
    User::factory()->create([
        'email' => 'dimitris@example.com',
    ]);

    $response = $this->post('/login', [
        'email' => 'dimitris@example.com',
        'password' => 'incorrect-password',
    ]);

    $response
        ->assertRedirect()
        ->assertSessionHasErrors([
            'email' => __('auth.failed'),
        ]);

    $this->assertGuest();
});

test('an authenticated user can log out', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('logout'));

    $response
        ->assertRedirect(route('login'))
        ->assertSessionHas('success', __('flash.auth.logged_out'));
    $this->assertGuest();
});

test('logout cannot be performed with a get request', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/logout');

    $response->assertStatus(405);
    $this->assertAuthenticatedAs($user);
});

test('a guest is redirected from protected protocol routes', function () {
    $response = $this->get(route('protocols.index'));

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});
