<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $supportedLocales = array_keys(config('app.supported_locales', [
            'en' => 'English',
            'zh_CN' => '中文',
        ]));

        $data = $request->validate([
            'locale' => ['required', 'string', Rule::in($supportedLocales)],
        ]);

        Session::put('locale', $data['locale']);

        App::setLocale($data['locale']);

        return back();
    }
}