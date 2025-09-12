<?php

use App\Models\User;

uses(Tests\TestCase::class);

test('user factory creates valid user', function () {
    $user = User::factory()->create();
    
    expect($user)->toBeInstanceOf(User::class);
    expect($user->name)->toBeString();
    expect($user->email)->toBeString();
    expect($user->password)->toBeString();
    expect($user->email_verified_at)->toBeNull();
});

test('user factory can create unverified user', function () {
    $user = User::factory()->unverified()->create();
    
    expect($user->email_verified_at)->toBeNull();
});

test('user factory can create verified user', function () {
    $user = User::factory()->create(); // Default state is verified
    
    expect($user->email_verified_at)->not->toBeNull();
});

test('user model has correct fillable attributes', function () {
    $user = new User();
    
    expect($user->getFillable())->toEqual([
        'name',
        'email',
        'password'
    ]);
});

test('user model has correct hidden attributes', function () {
    $user = new User();
    
    expect($user->getHidden())->toEqual([
        'password',
        'remember_token'
    ]);
});

test('user model casts password correctly', function () {
    $user = User::factory()->create(['password' => 'test-password']);
    
    expect($user->password)->not->toBe('test-password');
    expect($user->password)->toBeString();
});

test('user model casts email_verified_at correctly', function () {
    $user = User::factory()->create(); // Default state is verified
    
    expect($user->email_verified_at)->toBeInstanceOf(DateTime::class);
});

test('user can be serialized to array without hidden fields', function () {
    $user = User::factory()->create();
    $array = $user->toArray();
    
    expect($array)->not->toHaveKey('password');
    expect($array)->not->toHaveKey('remember_token');
    expect($array)->toHaveKey('name');
    expect($array)->toHaveKey('email');
});

test('user has two factor authentication traits', function () {
    $user = new User();
    
    expect(class_uses($user))->toContain(
        'Laravel\Fortify\TwoFactorAuthenticatable',
        'Illuminate\Database\Eloquent\Factories\HasFactory',
        'Illuminate\Notifications\Notifiable'
    );
});

test('user email is unique', function () {
    $email = fake()->unique()->email();
    
    User::factory()->create(['email' => $email]);
    
    expect(fn() => User::factory()->create(['email' => $email]))
        ->toThrow(Exception::class);
});