<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q'));
        $customers = Customer::query()->with('user:id,email_verified_at,last_login_at')
            ->when($term !== '', function ($q) use ($term) {
                $digits = preg_replace('/\D/', '', $term);
                $q->where(function ($w) use ($term, $digits) {
                    $w->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%");
                    if ($digits !== '') {
                        $w->orWhere('phone', 'like', "%{$digits}%");
                    }
                });
            })
            ->latest()->paginate(30)->withQueryString();

        return view('admin.customers.index', ['customers' => $customers]);
    }

    public function show(Customer $customer): View
    {
        return view('admin.customers.show', ['customer' => $customer->load('user')]);
    }
}
