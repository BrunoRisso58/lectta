@extends('layouts.app', ['title' => 'Progresso', 'nav' => 'progress'])

@section('content')
<x-topbar variant="title" title="Progresso" />

<div class="page">
    <div class="page-head">
        <h1>Progresso</h1>
        <p class="sub">
            @if ($streak > 0)
                {{ $streak }} dia{{ $streak > 1 ? 's' : '' }} seguidos revisando. Não quebre a corrente.
            @elseif ($activeDays > 0)
                Você já revisou em {{ $activeDays }} dia{{ $activeDays > 1 ? 's' : '' }}. Bora recomeçar a sequência?
            @else
                Sua sequência começa na primeira revisão do dia.
            @endif
        </p>
    </div>

    <div class="stat-grid">
        <div class="stat-tile {{ $reviewedToday > 0 ? 'is-highlight' : '' }}">
            <strong>{{ $reviewedToday }}</strong>
            <span>cards hoje</span>
        </div>
        <div class="stat-tile {{ $streak > 0 ? 'is-highlight' : '' }}">
            <strong>{{ $streak }}</strong>
            <span>sequência atual</span>
        </div>
        <div class="stat-tile">
            <strong>{{ $longestStreak }}</strong>
            <span>melhor sequência</span>
        </div>
        <div class="stat-tile">
            <strong>{{ $activeDays }}</strong>
            <span>dias com revisão</span>
        </div>
    </div>

    <div class="section">
        <div class="section-title">
            <h2>Atividade</h2>
            <span class="count">últimas 18 semanas</span>
        </div>

        <div class="heatmap-card">
            <div class="heatmap-scroll">
                <div class="heatmap">
                    <div class="heatmap-months">
                        @foreach ($weeks as $i => $week)
                            <span class="heatmap-month-label">{{ $monthLabels[$i] ?? '' }}</span>
                        @endforeach
                    </div>
                    <div class="heatmap-grid">
                        @foreach ($weeks as $week)
                            <div class="heatmap-week">
                                @foreach ($week as $day)
                                    @if ($day['is_future'])
                                        <span class="heatmap-day is-empty"></span>
                                    @else
                                        <span
                                            class="heatmap-day level-{{ $day['level'] }} {{ $day['is_today'] ? 'is-today' : '' }}"
                                            title="{{ (new DateTime($day['date']))->format('d/m/Y') }} — {{ $day['count'] }} card{{ $day['count'] == 1 ? '' : 's' }}"
                                        ></span>
                                    @endif
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="heatmap-legend">
                <span>menos</span>
                <span class="heatmap-day level-0"></span>
                <span class="heatmap-day level-1"></span>
                <span class="heatmap-day level-2"></span>
                <span class="heatmap-day level-3"></span>
                <span class="heatmap-day level-4"></span>
                <span>mais</span>
            </div>
        </div>

        @if ($totalReviews > 0)
            <p class="faint" style="margin:var(--sp-3) 0 0; font-size:var(--t-sm);">
                {{ $totalReviews }} revis{{ $totalReviews == 1 ? 'ão' : 'ões' }} nesse período.
            </p>
        @endif
    </div>

    <div class="section">
        <a class="review-hero" href="{{ route('review') }}">
            <div class="review-hero-top">
                <span class="review-hero-icon">{!! icon('flame', 24, '', 1.8) !!}</span>
                <div>
                    <div class="review-hero-label">Manter a sequência</div>
                    <div class="review-hero-count" style="font-size:1.4rem;">Revisar agora</div>
                </div>
            </div>
            <div class="review-hero-cta">
                <span>Ir para a revisão</span>
                {!! icon('chevron-right', 17) !!}
            </div>
        </a>
    </div>
</div>
@endsection
