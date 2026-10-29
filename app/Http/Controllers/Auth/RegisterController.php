<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RegisterController extends Controller
{
    public function show()
    {
        return view('auth.register');
    }

    public function store(Request $request)
    {
        $name = trim((string) $request->input('name'));
        $email = mb_strtolower(trim((string) $request->input('email')));
        $password = (string) $request->input('password');

        $error = match (true) {
            $name === '' => 'Informe seu nome.',
            !filter_var($email, FILTER_VALIDATE_EMAIL) => 'Informe um e-mail válido.',
            mb_strlen($password) < 8 => 'A senha precisa ter pelo menos 8 caracteres.',
            $password !== (string) $request->input('password_confirm') => 'As senhas não coincidem.',
            User::where('email', $email)->exists() => 'Já existe uma conta com esse e-mail.',
            default => null,
        };

        if ($error !== null) {
            return back()->withInput($request->only('name', 'email'))->with('error', $error);
        }

        $user = DB::transaction(function () use ($name, $email, $password) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            // Toda conta nova começa com a matéria "Geral" -- garante que há
            // pelo menos uma opção pra enviar a primeira aula.
            Subject::create(['user_id' => $user->id, 'name' => 'Geral']);
            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        return redirect()->route('home');
    }
}
