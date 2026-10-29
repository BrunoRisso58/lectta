<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $email = mb_strtolower(trim((string) $request->input('email')));
        $password = (string) $request->input('password');

        $user = User::where('email', $email)->first();

        // password_verify direto (e não Hash::check): aceita também hashes
        // bcrypt com prefixo $2b$ (gerados fora do PHP, ex. Python), que o
        // verificador estrito do Laravel recusa.
        if ($user && password_verify($password, $user->password_hash)) {
            Auth::login($user);
            $request->session()->regenerate();
            return redirect()->route('home');
        }

        return back()->withInput($request->only('email'))->with('error', 'E-mail ou senha incorretos.');
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
