<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function __invoke(Request $request): View
    {
        $contacts = Contact::query()
            ->where('status', Contact::STATUS_ACTIVE)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('contact.index', [
            'contacts' => $contacts,
        ]);
    }
}