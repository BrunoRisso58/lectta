{{-- A ação primária ("nova aula") é um botão elevado no meio, com dois itens de cada lado. --}}
<nav class="tabbar" aria-label="Navegação principal">
    @foreach (array_slice(nav_items(), 0, 2) as $item)
        @include('partials.nav-tab', ['item' => $item])
    @endforeach
    <a class="tab-fab" href="{{ route('lessons.create') }}" aria-label="Enviar nova aula">
        {!! icon('plus', 26, '', 2.4) !!}
    </a>
    @foreach (array_slice(nav_items(), 2) as $item)
        @include('partials.nav-tab', ['item' => $item])
    @endforeach
</nav>
