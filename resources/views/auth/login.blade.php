@extends('layouts.app', ['title' => 'Entrar', 'auth' => true])

@section('content')
<div class="auth-shell">
    <div class="auth-card">
        <div class="auth-head">
            <span class="brand-mark is-lg"><img src="{{ asset('img/logo-icon-1024.png') }}" alt="Lectta"></span>
            <h1>Lectta</h1>
            <p>Entre com sua conta para continuar</p>
        </div>

        <x-flash />

        <form method="post" action="{{ route('login') }}" class="stack">
            @csrf
            <label for="email">E-mail
                <input type="email" id="email" name="email" placeholder="voce@exemplo.com"
                       autocomplete="email" inputmode="email" autofocus required
                       value="{{ old('email') }}">
            </label>
            <label for="password">Senha
                <input type="password" id="password" name="password" placeholder="••••••••"
                       autocomplete="current-password" required>
            </label>
            <button type="submit" class="button block">Entrar</button>
        </form>

        <p class="auth-switch">Ainda não tem conta? <a href="{{ route('register') }}">Criar conta</a></p>
    </div>
</div>
@endsection
