<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->stripWorkshopSlugOnCustomDomains();
    }

    /**
     * On a workshop's own domain the workshop is the whole site, so links to it
     * leave out the slug: https://workshop.example/services rather than /demo/services.
     */
    private function stripWorkshopSlugOnCustomDomains(): void
    {
        URL::formatPathUsing(function (string $path): string {
            $slug = request()->attributes->get('custom_domain_slug');

            if ($slug && ($path === "/{$slug}" || str_starts_with($path, "/{$slug}/"))) {
                return substr($path, strlen($slug) + 1) ?: '/';
            }

            return $path;
        });
    }

    /**
     * Every customer message costs an LLM round trip, so it is limited per
     * conversation and, as a backstop against rotating tokens, per address.
     * The address limits are generous because every customer on a workshop's Wi-Fi
     * shares one address.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('advisor-start', fn (Request $request) => Limit::perMinute(30)->by('start:'.$request->ip()));

        RateLimiter::for('advisor-message', fn (Request $request) => [
            Limit::perMinute(12)->by('conversation:'.($request->input('customer_token') ?: $request->ip())),
            Limit::perMinute(120)->by('ip:'.$request->ip()),
        ]);

        RateLimiter::for('booking-manage', fn (Request $request) => [
            Limit::perMinute(10)->by('booking-ref:'.strtoupper((string) $request->input('reference')).'|'.$request->ip()),
            Limit::perMinute(60)->by('booking-ip:'.$request->ip()),
        ]);

        RateLimiter::for('member-code', fn (Request $request) => [
            Limit::perMinute(3)->by('member-code:'.strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(30)->by('member-code-ip:'.$request->ip()),
        ]);

        RateLimiter::for('member-verify', fn (Request $request) => [
            Limit::perMinute(10)->by('member-verify:'.strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(60)->by('member-verify-ip:'.$request->ip()),
        ]);

        RateLimiter::for('narration-speech', fn (Request $request) => Limit::perMinute(20)->by('speech:'.$request->ip()));

        RateLimiter::for('advisor-feedback', fn (Request $request) => Limit::perMinute(30)->by('feedback:'.($request->input('customer_token') ?: $request->ip())));

        RateLimiter::for('advisor-history', fn (Request $request) => [
            Limit::perMinute(20)->by('history-conversation:'.($request->input('customer_token') ?: $request->ip())),
            Limit::perMinute(300)->by('history:'.$request->ip()),
        ]);
    }
}
