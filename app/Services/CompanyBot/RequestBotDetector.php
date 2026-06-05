<?php

namespace App\Services\CompanyBot;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RequestBotDetector
{
    public function isSocialBot(Request $request): bool
    {
        $ua = $this->ua($request);

        foreach (config('companybot.social_bots', []) as $keyword) {
            if ($keyword && Str::contains($ua, strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }

    public function isSearchBot(Request $request): bool
    {
        $ua = $this->ua($request);

        foreach (config('companybot.search_bots', []) as $keyword) {
            if ($keyword && Str::contains($ua, strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }

    public function isHardBlocked(Request $request): bool
    {
        $ua = $this->ua($request);

        if ($ua === '') {
            return true;
        }

        if (strlen($ua) < 12) {
            return true;
        }

        foreach (config('companybot.hard_block_ua', []) as $keyword) {
            if ($keyword && Str::contains($ua, strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }

    public function hasBrowserLikePageHeaders(Request $request): bool
    {
        $ua = $this->ua($request);

        if ($ua === '') {
            return false;
        }

        $looksLikeBrowser = Str::contains($ua, [
            'mozilla',
            'chrome',
            'chromium',
            'safari',
            'firefox',
            'edg',
            'opr',
            'mobile',
            'iphone',
            'android',
        ]);

        if (!$looksLikeBrowser) {
            return false;
        }

        if (!$request->headers->has('accept')) {
            return false;
        }

        if (!$request->headers->has('accept-language')) {
            return false;
        }

        $accept = strtolower((string) $request->header('accept'));

        if (
            $accept && !Str::contains($accept, [
                'text/html',
                'application/xhtml+xml',
                '*/*',
            ])
        ) {
            return false;
        }

        return true;
    }

    public function hasBrowserLikeAjaxHeaders(Request $request): bool
    {
        $ua = $this->ua($request);

        if ($ua === '') {
            return false;
        }

        $looksLikeBrowser = Str::contains($ua, [
            'mozilla',
            'chrome',
            'chromium',
            'safari',
            'firefox',
            'edg',
            'opr',
            'mobile',
            'iphone',
            'android',
        ]);

        if (!$looksLikeBrowser) {
            return false;
        }

        if (!$request->headers->has('accept')) {
            return false;
        }

        if (!$request->headers->has('accept-language')) {
            return false;
        }

        $accept = strtolower((string) $request->header('accept'));

        if (
            $accept && !Str::contains($accept, [
                'application/json',
                '*/*',
            ])
        ) {
            return false;
        }

        if ($request->header('x-requested-with') !== 'XMLHttpRequest') {
            return false;
        }

        return true;
    }

    public function should404Immediately(Request $request): bool
    {
        if ($this->isSocialBot($request)) {
            return false;
        }

        if ($this->isSearchBot($request)) {
            return true;
        }

        if ($this->isHardBlocked($request)) {
            return true;
        }

        if (!$this->hasBrowserLikePageHeaders($request)) {
            return true;
        }

        return false;
    }

    public function should404Ajax(Request $request): bool
    {
        if ($this->isSocialBot($request)) {
            return true;
        }

        if ($this->isSearchBot($request)) {
            return true;
        }

        if ($this->isHardBlocked($request)) {
            return true;
        }

        if (!$this->hasBrowserLikeAjaxHeaders($request)) {
            return true;
        }

        return false;
    }

    protected function ua(Request $request): string
    {
        return strtolower((string) $request->userAgent());
    }
}