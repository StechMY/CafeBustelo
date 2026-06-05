<?php

namespace App\Services\CompanyBot;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class HumanRiskScorer
{
    public function score(Request $request, array $signals): array
    {
        $score = 0;
        $reasons = [];

        if (empty($signals)) {
            return $this->result(100, ['missing_js_payload']);
        }

        /*
        |--------------------------------------------------------------------------
        | Automation traces
        |--------------------------------------------------------------------------
        */

        if (Arr::get($signals, 'automation.webdriver') === true) {
            $score += 70;
            $reasons[] = 'navigator_webdriver_true';
        }

        if (Arr::get($signals, 'automation.playwright') === true) {
            $score += 60;
            $reasons[] = 'playwright_trace';
        }

        if (Arr::get($signals, 'automation.selenium') === true) {
            $score += 60;
            $reasons[] = 'selenium_trace';
        }

        if (Arr::get($signals, 'automation.phantom') === true) {
            $score += 60;
            $reasons[] = 'phantom_trace';
        }

        if (Arr::get($signals, 'automation.chromeRuntimeMissingOnChrome') === true) {
            $score += 12;
            $reasons[] = 'chrome_runtime_missing';
        }

        $suspiciousGlobals = Arr::get($signals, 'automation.suspiciousGlobals', []);

        if (is_array($suspiciousGlobals) && count($suspiciousGlobals) > 0) {
            $score += 30;
            $reasons[] = 'suspicious_automation_globals';
        }

        /*
        |--------------------------------------------------------------------------
        | Browser capabilities
        |--------------------------------------------------------------------------
        */

        if (!Arr::get($signals, 'canvas.hash')) {
            $score += 18;
            $reasons[] = 'missing_canvas_hash';
        }

        if (!Arr::get($signals, 'webgl.supported')) {
            $score += 15;
            $reasons[] = 'missing_webgl';
        }

        $renderer = strtolower((string) Arr::get($signals, 'webgl.renderer'));

        if (Str::contains($renderer, ['swiftshader', 'llvmpipe', 'mesa offscreen'])) {
            $score += 25;
            $reasons[] = 'software_webgl_renderer';
        }

        if (!Arr::get($signals, 'audio.hash')) {
            $score += 8;
            $reasons[] = 'missing_audio_hash';
        }

        /*
        |--------------------------------------------------------------------------
        | Screen / navigator sanity
        |--------------------------------------------------------------------------
        */

        $width = (int) Arr::get($signals, 'screen.width', 0);
        $height = (int) Arr::get($signals, 'screen.height', 0);

        if ($width <= 0 || $height <= 0) {
            $score += 30;
            $reasons[] = 'invalid_screen';
        }

        if (($width === 800 && $height === 600) || ($width === 1024 && $height === 768)) {
            $score += 10;
            $reasons[] = 'common_automation_screen';
        }

        $hardwareConcurrency = (int) Arr::get($signals, 'navigator.hardwareConcurrency', 0);

        if ($hardwareConcurrency <= 0) {
            $score += 12;
            $reasons[] = 'missing_hardware_concurrency';
        }

        if (!Arr::get($signals, 'navigator.language')) {
            $score += 12;
            $reasons[] = 'missing_navigator_language';
        }

        if (!Arr::get($signals, 'timezone.name')) {
            $score += 12;
            $reasons[] = 'missing_timezone';
        }

        /*
        |--------------------------------------------------------------------------
        | Storage / cookies
        |--------------------------------------------------------------------------
        */

        if (Arr::get($signals, 'storage.cookieEnabled') === false) {
            $score += 40;
            $reasons[] = 'cookies_disabled';
        }

        if (Arr::get($signals, 'storage.localStorage') === false) {
            $score += 25;
            $reasons[] = 'local_storage_disabled';
        }

        if (Arr::get($signals, 'storage.sessionStorage') === false) {
            $score += 15;
            $reasons[] = 'session_storage_disabled';
        }

        /*
        |--------------------------------------------------------------------------
        | Behavior
        |--------------------------------------------------------------------------
        */

        $dwellMs = (int) Arr::get($signals, 'behavior.dwellMs', 0);
        $mouseMoves = (int) Arr::get($signals, 'behavior.mouseMoves', 0);
        $touchEvents = (int) Arr::get($signals, 'behavior.touchEvents', 0);
        $scrollEvents = (int) Arr::get($signals, 'behavior.scrollEvents', 0);
        $clicks = (int) Arr::get($signals, 'behavior.clicks', 0);
        $keyEvents = (int) Arr::get($signals, 'behavior.keyEvents', 0);
        $firstInteractionMs = Arr::get($signals, 'behavior.firstInteractionMs');

        $interactionCount = $mouseMoves + $touchEvents + $scrollEvents + $clicks + $keyEvents;

        if ($dwellMs < config('companybot.min_dwell_ms')) {
            $score += 35;
            $reasons[] = 'too_fast';
        }

        if ($interactionCount < config('companybot.min_interactions')) {
            $score += 35;
            $reasons[] = 'no_human_interaction';
        }

        if ($firstInteractionMs !== null && (int) $firstInteractionMs < 20) {
            $score += 15;
            $reasons[] = 'interaction_too_instant';
        }

        if ($mouseMoves > 3000) {
            $score += 15;
            $reasons[] = 'excessive_mouse_events';
        }

        /*
        |--------------------------------------------------------------------------
        | Visibility
        |--------------------------------------------------------------------------
        */

        if (Arr::get($signals, 'visibility.wasHidden') === true && $dwellMs < 3000) {
            $score += 12;
            $reasons[] = 'hidden_tab_fast_verify';
        }

        /*
        |--------------------------------------------------------------------------
        | HTTP / JS consistency
        |--------------------------------------------------------------------------
        */

        if (!$request->headers->has('accept-language')) {
            $score += 12;
            $reasons[] = 'missing_accept_language';
        }

        if (!$request->headers->has('accept')) {
            $score += 12;
            $reasons[] = 'missing_accept';
        }

        $httpLang = strtolower((string) $request->header('accept-language'));
        $jsLang = strtolower((string) Arr::get($signals, 'navigator.language'));

        if ($httpLang && $jsLang && !Str::startsWith($httpLang, substr($jsLang, 0, 2))) {
            $score += 8;
            $reasons[] = 'language_mismatch';
        }

        return $this->result($score, $reasons);
    }

    protected function result(int $score, array $reasons): array
    {
        $score = min(100, max(0, $score));

        return [
            'score' => $score,
            'decision' => $score <= config('companybot.allow_score') ? 'allow' : 'block',
            'reasons' => array_values(array_unique($reasons)),
        ];
    }
}