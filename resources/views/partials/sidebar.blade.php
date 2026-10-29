<aside class="sidebar">
    <a class="sidebar-brand" href="{{ route('home') }}">
        <span class="brand-mark"><img src="{{ asset('img/logo-icon-1024.png') }}" alt=""></span>
        <span class="wordmark">Lectta</span>
    </a>
    @foreach (nav_items() as $item)
        @include('partials.nav-tab', ['item' => $item])
    @endforeach
    <a class="tab-fab" href="{{ route('lessons.create') }}">
        {!! icon('plus', 19, '', 2.4) !!}
        <span>Nova aula</span>
    </a>
    <div class="sidebar-footer">
        <button type="button" class="tab" data-theme-toggle aria-label="Alternar tema claro e escuro">
            <span data-theme-icon>{!! icon('moon', 20) !!}</span>
            <span data-theme-label>Tema</span>
        </button>
        <a class="tab" href="{{ route('logout') }}">
            {!! icon('logout', 20) !!}
            <span>Sair</span>
        </a>
    </div>
</aside>
