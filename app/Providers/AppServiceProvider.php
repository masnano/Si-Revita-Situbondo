<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
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
        // Custom Blade Directives
        Blade::if('role', function ($roles) {
            if (!auth()->check()) {
                return false;
            }
            if (is_string($roles)) {
                $roles = explode(',', $roles);
            }
            return auth()->user()->hasRole($roles);
        });

        Blade::if('permission', function ($permission) {
            if (!auth()->check()) {
                return false;
            }
            return auth()->user()->hasPermission($permission);
        });

        // Rupiah currency formatter helper
        Blade::directive('rupiah', function ($expression) {
            return "<?php echo 'Rp ' . number_format($expression, 0, ',', '.'); ?>";
        });
    }
}

