@php
    $isActive = $item['key'] === $active;
    $showBadge = !empty($item['badge']) && $due > 0;
@endphp
<a class="tab{{ $isActive ? ' is-active' : '' }}"
   href="{{ $item['href'] }}"
   @if($isActive) aria-current="page" @endif>
    {!! icon($item['icon'], 23, '', $isActive ? 2.3 : 1.9) !!}
    <span>{{ $item['label'] }}</span>
    @if ($showBadge)
        <span class="tab-badge" aria-label="{{ $due }} flashcards para revisar">{{ $due > 99 ? '99+' : $due }}</span>
    @endif
</a>
