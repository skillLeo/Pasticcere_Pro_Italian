<?php

namespace App\Providers;

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
     *
     * ╔══════════════════════════════════════════════════════════════════════╗
     * ║  ROOT CAUSE FIX — LC_NUMERIC LOCALE                                  ║
     * ║                                                                        ║
     * ║  On Italian / European Linux servers, PHP inherits the system locale  ║
     * ║  (e.g. it_IT.UTF-8) which sets the decimal separator to COMMA.        ║
     * ║                                                                        ║
     * ║  This corrupts every PHP function that formats numbers as strings:    ║
     * ║    number_format(25, 4)       →  "25,0000"  ← the reported bug        ║
     * ║    sprintf('%.4f', 25)        →  "25,0000"                            ║
     * ║    (string)(float) 25.5       →  "25,5"                              ║
     * ║    json_encode(['v' => 25.5]) →  '{"v":25,5}'  (INVALID JSON!)        ║
     * ║                                                                        ║
     * ║  Setting LC_NUMERIC to "C" (the POSIX/standard locale) forces ALL     ║
     * ║  numeric formatting to use DOT as decimal separator globally for      ║
     * ║  the lifetime of every PHP request. This is the correct industry fix. ║
     * ╚══════════════════════════════════════════════════════════════════════╝
     */
    public function boot(): void
    {
        // Force standard (C/POSIX) numeric locale for all number formatting.
        // This must run before ANY controller, view, model, or service executes.
        setlocale(LC_NUMERIC, 'C');

        // Verify it worked — if for any reason 'C' is unavailable, try alternatives.
        if (setlocale(LC_NUMERIC, '0') !== 'C') {
            setlocale(LC_NUMERIC, 'en_US.UTF-8');
            if (setlocale(LC_NUMERIC, '0') === false) {
                setlocale(LC_NUMERIC, 'en_US');
            }
        }
    }
}