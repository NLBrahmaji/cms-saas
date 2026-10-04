<?php

test('versioned application routes are registered', function () {
    $uris = collect(app('router')->getRoutes()->getRoutes())
        ->map(fn ($route) => $route->uri())
        ->all();

    expect($uris)->toContain('v1/auth/login')
        ->and($uris)->toContain('v1/auth/register')
        ->and($uris)->toContain('v1/user')
        ->and($uris)->toContain('v1/accounts');
});

test('legacy unversioned auth routes are not available', function () {
    $this->postJson('/auth/login', [
        'email' => 'missing@example.com',
        'password' => 'wrong',
    ])->assertNotFound();
});

test('sanctum csrf route remains unversioned', function () {
    $this->get('/sanctum/csrf-cookie')->assertNoContent();

    $this->get('/v1/sanctum/csrf-cookie')->assertNotFound();
});

test('legacy unversioned application routes are not available', function () {
    $this->getJson('/user')->assertNotFound();
    $this->getJson('/accounts')->assertNotFound();
    $this->postJson('/auth/register', [])->assertNotFound();
});
