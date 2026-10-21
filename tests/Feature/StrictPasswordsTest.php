<?php

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

afterEach(function () {
    DB::prohibitDestructiveCommands(false);
});

function passwordPasses(string $password): bool
{
    return Validator::make(['password' => $password], ['password' => [Password::default()]])->passes();
}

test('the production environment alone does not force strict passwords', function () {
    $this->app['env'] = 'production';
    config(['auth.strict_passwords' => false]);
    (new AppServiceProvider($this->app))->boot();

    expect(passwordPasses('password'))->toBeTrue();
});

test('strict passwords apply when switched on', function () {
    config(['auth.strict_passwords' => true]);
    (new AppServiceProvider($this->app))->boot();

    expect(passwordPasses('password'))->toBeFalse();
});
