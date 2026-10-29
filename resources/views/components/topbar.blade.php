{{--
    Barra superior contextual:
      brand -- logo + nome (só a home)
      back  -- seta de voltar + título (telas de segundo nível)
      title -- só o título (telas da tab bar)
    O título repete grande no corpo de propósito (padrão "large title" do iOS).
--}}
@props(['variant' => 'title', 'title' => '', 'back' => null, 'narrow' => false])
<header class="topbar" data-topbar>
    <div class="topbar-inner{{ $narrow ? ' is-narrow' : '' }}">
        @if ($variant === 'brand')
            <div class="topbar-brand">
                <span class="brand-mark"><img src="{{ asset('img/logo-icon-1024.png') }}" alt=""></span>
                <span class="wordmark">Lectta</span>
            </div>
        @else
            @if ($variant === 'back')
                <a class="icon-btn" href="{{ $back ?? route('home') }}" aria-label="Voltar">
                    {!! icon('chevron-left', 22) !!}
                </a>
            @endif
            <h1 class="topbar-title">{{ $title }}</h1>
        @endif

        <div class="topbar-actions">
            {{ $slot }}
            <button type="button" class="icon-btn" data-theme-toggle aria-label="Alternar tema claro e escuro">
                <span data-theme-icon>{!! icon('moon', 20) !!}</span>
            </button>
            <a class="icon-btn" href="{{ route('logout') }}" aria-label="Sair da conta">
                {!! icon('logout', 20) !!}
            </a>
        </div>
    </div>
</header>
