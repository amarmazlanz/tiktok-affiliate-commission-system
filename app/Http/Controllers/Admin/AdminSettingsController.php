<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('admin.settings', [
            'admins' => User::query()
                ->where('role', 'admin')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'created_at']),
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Current password is incorrect.',
            ]);
        }

        $request->user()->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();

        $request->session()->regenerate();

        return redirect()
            ->route('admin.settings.edit')
            ->with('success', 'Password changed successfully.');
    }

    public function storeAdmin(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'admin',
        ]);

        return redirect()
            ->route('admin.settings.edit')
            ->with('success', 'Admin account created successfully.');
    }
}
