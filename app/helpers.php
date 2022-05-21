<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

if (!function_exists('vite_assets')) {
    /**
     * @return HtmlString
     */
    function vite_assets(): HtmlString
    {
        $devServerIsRunning = false;

        $viteLocal = config('content.vite_local');
        if (app()->environment('local')) {
            try {
                Http::get($viteLocal);
                $devServerIsRunning = true;
            } catch (\Exception $ex) {
                Log::error($ex->getMessage());
            }
        }

        if ($devServerIsRunning) {
            return new HtmlString(<<<HTML
            <script type="module" src="{$viteLocal}/@vite/client"></script>
            <script type="module" src="{$viteLocal}/resources/js/app.js"></script>
        HTML
            );
        }

        $manifest = json_decode(file_get_contents(
            public_path('build/manifest.json')
        ), true);

        return new HtmlString(<<<HTML
        <script type="module" src="/build/{$manifest['resources/js/app.js']['file']}"></script>
        <link rel="stylesheet" href="/build/{$manifest['resources/js/app.js']['css'][0]}">
    HTML
        );
    }
}