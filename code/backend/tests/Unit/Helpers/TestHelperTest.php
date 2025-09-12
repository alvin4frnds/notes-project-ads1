<?php

uses(Tests\TestCase::class);

test('application environment is testing', function () {
    expect(app()->environment())->toBe('testing');
});

test('database connection is postgresql for testing', function () {
    expect(config('database.default'))->toBe('pgsql');
    expect(config('database.connections.pgsql.database'))->toBe('testing');
});

test('cache driver is array for testing', function () {
    expect(config('cache.default'))->toBe('array');
});

test('session driver is array for testing', function () {
    expect(config('session.driver'))->toBe('array');
});

test('queue connection is sync for testing', function () {
    expect(config('queue.default'))->toBe('sync');
});

test('mail driver is array for testing', function () {
    expect(config('mail.default'))->toBe('array');
});

test('bcrypt rounds are reduced for testing', function () {
    expect(config('hashing.bcrypt.rounds'))->toBe('4');
});