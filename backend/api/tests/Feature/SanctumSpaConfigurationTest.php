<?php

test('sanctum csrf cookie endpoint is available', function () {
    $this->get('/sanctum/csrf-cookie')
        ->assertNoContent();
});

test('sanctum stateful domains include dashboard and website editor local hosts', function () {
    $stateful = config('sanctum.stateful');

    expect($stateful)->toContain('localhost:3001')
        ->and($stateful)->toContain('localhost:3002');
});

test('cors allows credentialed requests from configured first-party origins', function () {
    expect(config('cors.supports_credentials'))->toBeTrue()
        ->and(config('cors.allowed_origins'))->toContain('http://localhost:3001')
        ->and(config('cors.allowed_origins'))->toContain('http://localhost:3002');
});
