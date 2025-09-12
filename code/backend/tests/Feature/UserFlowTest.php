<?php

use App\Models\User;

describe('Complete User Flow Tests', function () {
    
    test('complete user registration and login flow', function () {
        // Step 1: Visit home page
        $response = $this->get(route('home'));
        $response->assertStatus(200);

        // Step 2: Visit registration page
        $response = $this->get(route('register'));
        $response->assertStatus(200);

        // Step 3: Register new user
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->post(route('register.store'), $userData);
        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        // Step 4: Verify user was created
        $user = User::where('email', 'john@example.com')->first();
        expect($user)->not->toBeNull();
        expect($user->name)->toBe('John Doe');

        // Step 5: Logout
        $response = $this->post(route('logout'));
        $response->assertRedirect(route('home'));
        $this->assertGuest();

        // Step 6: Login with same credentials
        $response = $this->post(route('login.store'), [
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertAuthenticatedAs($user);
    });

    test('user profile management flow', function () {
        // Step 1: Create and authenticate user
        $user = User::factory()->verified()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);

        $this->actingAs($user);

        // Step 2: Access dashboard
        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);

        // Step 3: Navigate to profile settings
        $response = $this->get(route('profile.edit'));
        $response->assertStatus(200);

        // Step 4: Update profile information
        $response = $this->patch(route('profile.update'), [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);

        $response->assertSessionHas('status', 'profile-updated');

        // Step 5: Verify changes
        $user->refresh();
        expect($user->name)->toBe('Updated Name');
        expect($user->email)->toBe('updated@example.com');

        // Step 6: Navigate to password settings
        $response = $this->get(route('password.edit'));
        $response->assertStatus(200);

        // Step 7: Update password
        $response = $this->put(route('password.update'), [
            'current_password' => 'password',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertSessionHas('status', 'password-updated');

        // Step 8: Logout and login with new password
        $this->post(route('logout'));
        $this->assertGuest();

        $response = $this->post(route('login.store'), [
            'email' => 'updated@example.com',
            'password' => 'newpassword123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    });

    test('password reset flow', function () {
        // Step 1: Create user
        $user = User::factory()->create(['email' => 'test@example.com']);

        // Step 2: Visit forgot password page
        $response = $this->get(route('password.request'));
        $response->assertStatus(200);

        // Step 3: Request password reset
        $response = $this->post(route('password.email'), [
            'email' => 'test@example.com',
        ]);

        $response->assertSessionHas('status');
        // Note: In a real application, you'd test the actual email sending and token validation
    });

    test('guest user redirect flow', function () {
        // Step 1: Try to access protected routes as guest
        $protectedRoutes = [
            'dashboard',
            'profile.edit',
            'password.edit',
            'appearance',
            'two-factor.show',
        ];

        foreach ($protectedRoutes as $routeName) {
            $response = $this->get(route($routeName));
            $response->assertRedirect(route('login'));
        }

        // Step 2: Try to access profile actions as guest
        $protectedActions = [
            ['method' => 'patch', 'route' => 'profile.update', 'data' => ['name' => 'Test', 'email' => 'test@example.com']],
            ['method' => 'delete', 'route' => 'profile.destroy', 'data' => ['password' => 'password']],
            ['method' => 'put', 'route' => 'password.update', 'data' => ['current_password' => 'password', 'password' => 'new', 'password_confirmation' => 'new']],
            ['method' => 'post', 'route' => 'logout', 'data' => []],
        ];

        foreach ($protectedActions as $action) {
            $response = $this->{$action['method']}(route($action['route']), $action['data']);
            $response->assertRedirect(route('login'));
        }
    });

    test('authenticated user access flow', function () {
        // Step 1: Create and authenticate user
        $user = User::factory()->verified()->create();
        $this->actingAs($user);

        // Step 2: Access all protected routes
        $protectedRoutes = [
            'dashboard' => 200,
            'profile.edit' => 200,
            'password.edit' => 200,
            'appearance' => 200,
            'two-factor.show' => 200,
        ];

        foreach ($protectedRoutes as $routeName => $expectedStatus) {
            $response = $this->get(route($routeName));
            $response->assertStatus($expectedStatus);
        }

        // Step 3: Try to access guest-only routes
        $guestOnlyRoutes = [
            'register',
            'login',
            'password.request',
        ];

        foreach ($guestOnlyRoutes as $routeName) {
            $response = $this->get(route($routeName));
            $response->assertRedirect(route('dashboard'));
        }
    });

    test('settings navigation flow', function () {
        // Step 1: Create and authenticate user
        $user = User::factory()->verified()->create();
        $this->actingAs($user);

        // Step 2: Access settings root (should redirect to profile)
        $response = $this->get('/settings');
        $response->assertRedirect('/settings/profile');

        // Step 3: Navigate through all settings pages
        $settingsPages = [
            'profile.edit',
            'password.edit',
            'appearance',
            'two-factor.show',
        ];

        foreach ($settingsPages as $pageName) {
            $response = $this->get(route($pageName));
            $response->assertStatus(200);
        }
    });
});