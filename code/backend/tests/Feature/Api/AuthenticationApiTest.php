<?php

use App\Models\User;

describe('Authentication API Endpoints', function () {
    
    describe('Home Page', function () {
        test('home page loads successfully', function () {
            $response = $this->get(route('home'));
            
            $response->assertStatus(200);
            $response->assertInertia(fn ($page) => $page->component('Welcome'));
        });
    });

    describe('Registration API', function () {
        test('registration page loads successfully', function () {
            $response = $this->get(route('register'));
            
            $response->assertStatus(200);
        });

        test('user can register with valid data', function () {
            $response = $this->post(route('register.store'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

            $response->assertRedirect(route('dashboard'));
            $this->assertAuthenticated();
            
            $user = User::where('email', 'test@example.com')->first();
            expect($user)->not->toBeNull();
            expect($user->name)->toBe('Test User');
        });

        test('registration fails with invalid email', function () {
            $response = $this->post(route('register.store'), [
                'name' => 'Test User',
                'email' => 'invalid-email',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

            $response->assertSessionHasErrors('email');
            $this->assertGuest();
        });

        test('registration fails with short password', function () {
            $response = $this->post(route('register.store'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => '123',
                'password_confirmation' => '123',
            ]);

            $response->assertSessionHasErrors('password');
            $this->assertGuest();
        });

        test('registration fails with mismatched password confirmation', function () {
            $response = $this->post(route('register.store'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password123',
                'password_confirmation' => 'different123',
            ]);

            $response->assertSessionHasErrors('password');
            $this->assertGuest();
        });

        test('registration fails with duplicate email', function () {
            User::factory()->create(['email' => 'test@example.com']);

            $response = $this->post(route('register.store'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

            $response->assertSessionHasErrors('email');
            $this->assertGuest();
        });
    });

    describe('Login API', function () {
        test('login page loads successfully', function () {
            $response = $this->get(route('login'));
            
            $response->assertStatus(200);
        });

        test('user can login with valid credentials', function () {
            $user = User::factory()->create([
                'email' => 'test@example.com',
                'password' => bcrypt('password123'),
            ]);

            $response = $this->post(route('login.store'), [
                'email' => 'test@example.com',
                'password' => 'password123',
            ]);

            $response->assertRedirect(route('dashboard'));
            $this->assertAuthenticated();
            $this->assertAuthenticatedAs($user);
        });

        test('login fails with invalid email', function () {
            $response = $this->post(route('login.store'), [
                'email' => 'nonexistent@example.com',
                'password' => 'password123',
            ]);

            $response->assertSessionHasErrors();
            $this->assertGuest();
        });

        test('login fails with invalid password', function () {
            User::factory()->create([
                'email' => 'test@example.com',
                'password' => bcrypt('correct-password'),
            ]);

            $response = $this->post(route('login.store'), [
                'email' => 'test@example.com',
                'password' => 'wrong-password',
            ]);

            $response->assertSessionHasErrors();
            $this->assertGuest();
        });

        test('login requires email field', function () {
            $response = $this->post(route('login.store'), [
                'password' => 'password123',
            ]);

            $response->assertSessionHasErrors('email');
            $this->assertGuest();
        });

        test('login requires password field', function () {
            $response = $this->post(route('login.store'), [
                'email' => 'test@example.com',
            ]);

            $response->assertSessionHasErrors('password');
            $this->assertGuest();
        });
    });

    describe('Logout API', function () {
        test('authenticated user can logout', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->post(route('logout'));

            $response->assertRedirect(route('home'));
            $this->assertGuest();
        });

        test('logout requires authentication', function () {
            $response = $this->post(route('logout'));

            $response->assertRedirect(route('login'));
        });
    });

    describe('Password Reset API', function () {
        test('forgot password page loads successfully', function () {
            $response = $this->get(route('password.request'));
            
            $response->assertStatus(200);
        });

        test('password reset link can be requested', function () {
            $user = User::factory()->create(['email' => 'test@example.com']);

            $response = $this->post(route('password.email'), [
                'email' => 'test@example.com',
            ]);

            $response->assertSessionHas('status');
        });

        test('password reset fails with invalid email', function () {
            $response = $this->post(route('password.email'), [
                'email' => 'invalid-email',
            ]);

            $response->assertSessionHasErrors('email');
        });

        test('password reset fails with nonexistent email', function () {
            $response = $this->post(route('password.email'), [
                'email' => 'nonexistent@example.com',
            ]);

            $response->assertSessionHasErrors('email');
        });
    });

    describe('Dashboard Access', function () {
        test('dashboard requires authentication', function () {
            $response = $this->get(route('dashboard'));

            $response->assertRedirect(route('login'));
        });

        test('authenticated user can access dashboard', function () {
            $user = User::factory()->verified()->create();

            $response = $this->actingAs($user)->get(route('dashboard'));

            $response->assertStatus(200);
            $response->assertInertia(fn ($page) => $page->component('Dashboard'));
        });
    });
});