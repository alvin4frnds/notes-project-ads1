<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

describe('Settings API Endpoints', function () {
    
    describe('Profile Settings', function () {
        test('settings redirects to profile page', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->get('/settings');

            $response->assertRedirect('/settings/profile');
        });

        test('profile edit page loads successfully', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->get(route('profile.edit'));

            $response->assertStatus(200);
        });

        test('profile edit requires authentication', function () {
            $response = $this->get(route('profile.edit'));

            $response->assertRedirect(route('login'));
        });

        test('user can update profile information', function () {
            $user = User::factory()->create([
                'name' => 'Old Name',
                'email' => 'old@example.com',
            ]);

            $response = $this->actingAs($user)->patch(route('profile.update'), [
                'name' => 'New Name',
                'email' => 'new@example.com',
            ]);

            $response->assertSessionHas('status', 'profile-updated');

            $user->refresh();
            expect($user->name)->toBe('New Name');
            expect($user->email)->toBe('new@example.com');
        });

        test('profile update validates email uniqueness', function () {
            $existingUser = User::factory()->create(['email' => 'existing@example.com']);
            $user = User::factory()->create(['email' => 'user@example.com']);

            $response = $this->actingAs($user)->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'existing@example.com',
            ]);

            $response->assertSessionHasErrors('email');
        });

        test('profile update validates required fields', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->patch(route('profile.update'), [
                'name' => '',
                'email' => '',
            ]);

            $response->assertSessionHasErrors(['name', 'email']);
        });

        test('user can delete their account', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

            $response->assertRedirect(route('home'));
            $this->assertGuest();
            expect(User::find($user->id))->toBeNull();
        });

        test('account deletion requires correct password', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

            $response->assertSessionHasErrors('password');
            expect(User::find($user->id))->not->toBeNull();
        });
    });

    describe('Password Settings', function () {
        test('password edit page loads successfully', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->get(route('password.edit'));

            $response->assertStatus(200);
        });

        test('password edit requires authentication', function () {
            $response = $this->get(route('password.edit'));

            $response->assertRedirect(route('login'));
        });

        test('user can update password', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ]);

            $response->assertSessionHas('status', 'password-updated');
        });

        test('password update requires current password', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->put(route('password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ]);

            $response->assertSessionHasErrors('current_password');
        });

        test('password update validates confirmation', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'newpassword123',
                'password_confirmation' => 'different123',
            ]);

            $response->assertSessionHasErrors('password');
        });

        test('password update validates minimum length', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->put(route('password.update'), [
                'current_password' => 'password',
                'password' => '123',
                'password_confirmation' => '123',
            ]);

            $response->assertSessionHasErrors('password');
        });
    });

    describe('Appearance Settings', function () {
        test('appearance page loads successfully', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->get(route('appearance'));

            $response->assertStatus(200);
        });

        test('appearance page requires authentication', function () {
            $response = $this->get(route('appearance'));

            $response->assertRedirect(route('login'));
        });
    });

    describe('Two Factor Authentication Settings', function () {
        test('two factor page loads successfully', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->get(route('two-factor.show'));

            $response->assertStatus(200);
        });

        test('two factor page requires authentication', function () {
            $response = $this->get(route('two-factor.show'));

            $response->assertRedirect(route('login'));
        });
    });
});