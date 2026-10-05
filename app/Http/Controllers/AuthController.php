<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $loginField = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $attempt = Auth::attempt([
            $loginField => $credentials['login'],
            'password' => $credentials['password'],
            'is_active' => true,
        ], $request->boolean('remember'));

        if ($attempt) {
            $request->session()->regenerate();
            return redirect()->intended(route('dashboard'))
                ->with('success', 'Selamat datang kembali, ' . Auth::user()->name . '!');
        }

        return back()->withErrors([
            'login' => 'Kombinasi Username/Email dan Password tidak cocok atau akun dinonaktifkan.',
        ])->onlyInput('login');
    }

    public function quickLogin(string $role)
    {
        $user = User::whereHas('role', function ($q) use ($role) {
            $q->where('name', $role);
        })->first();

        if ($user) {
            Auth::login($user);
            request()->session()->regenerate();
            return redirect()->route('dashboard')
                ->with('success', "Berhasil masuk sebagai [{$user->role->display_name}] ({$user->name})");
        }

        return redirect()->route('login')->with('error', "User dengan role {$role} tidak ditemukan.");
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Anda telah berhasil keluar dari sistem.');
    }
}
