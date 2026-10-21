<?php

namespace App\Providers;

use App\Services\Sms\SmsManager;
use App\Services\Sms\SmsSender;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsManager::class);

        $this->app->bind(SmsSender::class, fn ($app): SmsSender => $app->make(SmsManager::class)->driver());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        /**
         * Strict passwords are opted into separately from APP_ENV, so the
         * live site can run in production without forcing residents into
         * 12-character passwords with symbols.
         */
        Password::defaults(fn (): ?Password => config('auth.strict_passwords')
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
