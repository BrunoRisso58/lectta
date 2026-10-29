{{-- Mensagens de erro/sucesso vindas do controller (session flash) ou passadas direto. --}}
@props(['error' => session('error'), 'success' => session('success')])
@if ($error)
    <div class="error-box">
        {!! icon('alert-circle', 20) !!}
        <span>{{ $error }}</span>
    </div>
@endif
@if ($success)
    <div class="success-box">
        {!! icon('check', 20) !!}
        <span>{{ $success }}</span>
    </div>
@endif
