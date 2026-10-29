{{--
    <head> + shell compartilhado por todas as telas.

    Configurado pelo @extends de cada página:
      @extends('layouts.app', [
          'title'     => 'Progresso',  // vira "Progresso — Lectta"; vazio = só "Lectta"
          'nav'       => 'progress',   // aba ativa: home|review|progress|subjects
          'auth'      => true,         // tela de login/cadastro: sem navegação
          'immersive' => true,         // tela cheia (revisão): sem topbar nem tabbar
          'due'       => 3,            // badge da aba Revisar (opcional)
      ])
--}}
@php
    $title = $title ?? '';
    $nav = $nav ?? '';
    $isAuth = !empty($auth);
    $isImmersive = !empty($immersive);
    $hasShell = !$isAuth && !$isImmersive;
    $due = $due ?? null;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    {{-- viewport-fit=cover libera env(safe-area-inset-*) no iOS (tab bar longe do risquinho do gesto). --}}
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title !== '' ? $title . ' — Lectta' : 'Lectta' }}</title>

    <link rel="icon" type="image/png" href="{{ asset('img/favicon-32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">

    {{-- Uma cor por esquema: com um valor só, a barra de status ficava errada no tema escuro. --}}
    <meta name="theme-color" content="#fbf8f2" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#131210" media="(prefers-color-scheme: dark)">
    <meta name="color-scheme" content="light dark">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Lectta">
    <meta name="description" content="Suas aulas, resumidas e prontas pra revisar.">

    {{-- As fontes são o caminho crítico da primeira pintura. --}}
    <link rel="preload" href="{{ asset('fonts/atkinson-400-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/crimson-400-700-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset_v('style.css') }}">

    {{-- Inline e síncrono de propósito: aplica o tema salvo ANTES da primeira pintura. --}}
    <script>
    (function () {
        try {
            var t = localStorage.getItem('lectta-theme');
            if (t === 'dark' || t === 'light') {
                document.documentElement.setAttribute('data-theme', t);
            }
        } catch (e) {}
    })();
    </script>

    <script src="{{ asset_v('app.js') }}" defer></script>
    <script src="{{ asset_v('pwa.js') }}" defer></script>
</head>
<body @if($isImmersive) class="is-immersive" @endif>
@if ($hasShell)
<div class="app">
    @include('partials.sidebar', ['active' => $nav, 'due' => $due ?? nav_due_count()])
    <main class="app-main">
@endif

@yield('content')

@if ($hasShell)
    </main>
    @include('partials.tabbar', ['active' => $nav, 'due' => $due ?? nav_due_count()])
</div>
@endif
@stack('scripts')
</body>
</html>
