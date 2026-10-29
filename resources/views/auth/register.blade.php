@extends('layouts.app', ['title' => 'Criar conta', 'auth' => true])

@section('content')
<div class="auth-shell">
    <div class="auth-card">
        <div class="auth-head">
            <span class="brand-mark is-lg"><img src="{{ asset('img/logo-icon-1024.png') }}" alt="Lectta"></span>
            <h1>Criar conta</h1>
            <p>Suas aulas, resumidas e prontas pra revisar</p>
        </div>

        <x-flash />

        <form method="post" action="{{ route('register') }}" class="stack">
            @csrf
            <label for="name">Nome
                <input type="text" id="name" name="name" placeholder="Seu nome"
                       autocomplete="name" autofocus required value="{{ old('name') }}">
            </label>
            <label for="email">E-mail
                <input type="email" id="email" name="email" placeholder="voce@exemplo.com"
                       autocomplete="email" inputmode="email" required value="{{ old('email') }}">
            </label>
            <label for="password">Senha
                <input type="password" id="password" name="password" placeholder="Pelo menos 8 caracteres"
                       autocomplete="new-password" minlength="8" required>
            </label>
            <label for="password_confirm">Confirmar senha
                <input type="password" id="password_confirm" name="password_confirm" placeholder="Repita a senha"
                       autocomplete="new-password" minlength="8" required>
            </label>
            <button type="submit" class="button block">Criar conta</button>
        </form>

        <p class="auth-switch">Já tem conta? <a href="{{ route('login') }}">Entrar</a></p>
    </div>
</div>
@endsection
