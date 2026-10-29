@extends('layouts.app', ['title' => 'Matérias', 'nav' => 'subjects'])

@section('content')
<x-topbar variant="title" title="Matérias" />

<div class="page">
    <div class="page-head">
        <h1>Matérias</h1>
        <p class="sub">Organize as aulas por matéria e, dentro delas, por assunto. O domínio de cada uma vem dos seus flashcards.</p>
    </div>

    <x-flash />

    @if ($totalCardsAll > 0)
        <div class="section">
            <div class="section-title">
                <h2>Radar de domínio</h2>
                <span class="count">{{ $totalCardsAll }} flashcard{{ $totalCardsAll == 1 ? '' : 's' }}</span>
            </div>
            <div class="stat-grid">
                <div class="stat-tile {{ $overallMasteredPct >= 70 ? 'is-highlight' : '' }}">
                    <strong>{{ $overallMasteredPct }}%</strong>
                    <span>domínio geral</span>
                </div>
                <div class="stat-tile">
                    <strong>{{ $totalMasteredAll }}</strong>
                    <span>cards dominados</span>
                </div>
                <div class="stat-tile {{ $totalHardAll > 0 ? 'is-danger' : '' }}">
                    <strong>{{ $totalHardAll }}</strong>
                    <span>cards difíceis</span>
                </div>
                <div class="stat-tile">
                    <strong>{{ count($subjects) }}</strong>
                    <span>matérias</span>
                </div>
            </div>
            @if ($weakestSubjectName !== null && $weakestSubjectPct !== null && $weakestSubjectPct < 60)
                <div class="filter-status is-focus" style="margin-top:var(--sp-3); margin-bottom:0;">
                    {!! icon('target', 18) !!}
                    <span>Foco recomendado: <strong>{{ $weakestSubjectName }}</strong> está com apenas <strong>{{ $weakestSubjectPct }}%</strong> de domínio.</span>
                </div>
            @endif
        </div>
    @endif

    <div class="section">
        <div class="section-title">
            <h2>Nova matéria</h2>
        </div>
        <form method="post" action="{{ route('subjects.store') }}" class="inline-form">
            @csrf
            <input type="text" name="name" placeholder="Ex: Seminário Teológico" aria-label="Nome da nova matéria" required>
            <button type="submit" class="button">
                {!! icon('plus', 19, '', 2.2) !!}
                <span class="button-label">Adicionar</span>
            </button>
        </form>
    </div>

    @if ($hardCards)
        <div class="section">
            <div class="section-title">
                <h2>Cartões mais difíceis</h2>
                <span class="count">{{ count($hardCards) }}</span>
            </div>
            <div class="card">
                @foreach ($hardCards as $hc)
                    <div class="hard-card-row">
                        <span class="tag subject {{ subject_badge_class($hc->subject_name ?? '') }}">
                            <span class="dot"></span>{{ $hc->subject_name ?? 'Sem matéria' }}
                        </span>
                        <span class="hard-card-question">{{ $hc->question }}</span>
                        <span class="mastery-pill is-weak" title="{{ (int) $hc->repetitions }} repetições concluídas">fator {{ number_format((float) $hc->ease_factor, 2) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="section">
        <div class="section-title">
            <h2>Cadastradas</h2>
            <span class="count">{{ count($subjects) }} no total</span>
        </div>

        @if (!$subjects)
            <div class="empty-state">
                {!! icon('book', 44, '', 1.5) !!}
                <p>
                    <strong>Nenhuma matéria ainda</strong>
                    Crie a primeira acima — toda aula precisa estar em uma matéria.
                </p>
            </div>
        @endif

        @foreach ($subjects as $s)
            @php
                $topics = $topicsBySubject[(int) $s->id] ?? [];
                $total = (int) $s->card_total;
                $mastered = (int) $s->card_mastered;
                $hard = (int) $s->card_hard;
                $inProgress = max(0, $total - $mastered - $hard);
                $masteredPct = $total ? round($mastered / $total * 100) : 0;
                $hardPct = $total ? round($hard / $total * 100) : 0;
                $progressPct = $total ? max(0, 100 - $masteredPct - $hardPct) : 0;
                if ($total > 0 && $hardPct >= 25) {
                    [$mLevel, $mLabel] = ['weak', 'Precisa de atenção'];
                } elseif ($total > 0 && $masteredPct >= 70) {
                    [$mLevel, $mLabel] = ['good', 'Dominando'];
                } else {
                    [$mLevel, $mLabel] = ['mid', 'Em progresso'];
                }
            @endphp
            <div class="card">
                <div class="card-header">
                    <span class="tag subject tag-lg {{ subject_badge_class($s->name) }}">
                        <span class="dot"></span>{{ $s->name }}
                    </span>
                    <span class="card-meta">{{ (int) $s->class_count }} aula{{ $s->class_count == 1 ? '' : 's' }}</span>
                    @if ((int) $s->class_count === 0)
                        <form method="post" action="{{ route('subjects.destroy', $s->id) }}" style="margin-left:auto;" data-confirm="Remover a matéria &quot;{{ $s->name }}&quot;?">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="icon-btn is-sm" title="Remover matéria" aria-label="Remover matéria {{ $s->name }}">
                                {!! icon('trash', 16) !!}
                            </button>
                        </form>
                    @elseif ($total > 0)
                        <a class="icon-btn is-sm" style="margin-left:auto;" href="{{ route('review', ['subject_id' => $s->id, 'cram' => 1]) }}"
                           title="Modo prova nesta matéria" aria-label="Modo prova em {{ $s->name }}">
                            {!! icon('flame', 17) !!}
                        </a>
                    @endif
                </div>

                @if ($total > 0)
                    <div class="mastery-bar">
                        <div class="mastery-bar-head">
                            <span class="mastery-pct">{{ $masteredPct }}% dominado</span>
                            <span class="mastery-pill is-{{ $mLevel }}">{{ $mLabel }}</span>
                        </div>
                        <div class="track" title="{{ $mastered }} dominados · {{ $inProgress }} em progresso · {{ $hard }} difíceis">
                            <div class="seg seg-mastered" style="width:{{ $masteredPct }}%"></div>
                            <div class="seg seg-progress" style="width:{{ $progressPct }}%"></div>
                            <div class="seg seg-hard" style="width:{{ $hardPct }}%"></div>
                        </div>
                        <div class="mastery-legend">
                            <span class="seg-mastered">{{ $mastered }} dominados</span>
                            <span class="seg-progress">{{ $inProgress }} em progresso</span>
                            <span class="seg-hard">{{ $hard }} difíceis</span>
                        </div>
                    </div>
                @else
                    <p class="faint" style="font-size:var(--t-sm); margin:var(--sp-3) 0 0;">Nenhum flashcard ainda nesta matéria.</p>
                @endif

                <div class="topic-chips">
                    @if (!$topics)
                        <span class="faint" style="font-size:var(--t-sm);">Nenhum assunto cadastrado ainda.</span>
                    @endif
                    @foreach ($topics as $t)
                        <span class="topic-chip">
                            {{ $t->name }}
                            @if ((int) $t->class_count === 0)
                                <form method="post" action="{{ route('topics.destroy', $t->id) }}" data-confirm="Remover o assunto &quot;{{ $t->name }}&quot;?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="chip-remove" title="Remover assunto" aria-label="Remover assunto {{ $t->name }}">
                                        {!! icon('x', 13, '', 2.5) !!}
                                    </button>
                                </form>
                            @else
                                <span class="chip-count">{{ (int) $t->class_count }}</span>
                            @endif
                        </span>
                    @endforeach
                </div>

                <form method="post" action="{{ route('topics.store', $s->id) }}" class="inline-form" style="margin-top:var(--sp-4);">
                    @csrf
                    <input type="text" name="name" placeholder="Novo assunto nesta matéria" aria-label="Novo assunto em {{ $s->name }}" required>
                    <button type="submit" class="button secondary">
                        {!! icon('plus', 18, '', 2.2) !!}
                        <span class="button-label">Adicionar</span>
                    </button>
                </form>
            </div>
        @endforeach
    </div>
</div>
@endsection
