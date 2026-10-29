@extends('layouts.app', ['title' => '', 'nav' => 'home', 'due' => $dueCount])

@section('content')
<x-topbar variant="brand" />

<div class="page">
    <div class="greeting">
        <h1>{{ $salutation }}, {{ auth()->user()->name }}</h1>
        <p>
            @if ($busyCount > 0)
                {{ $busyCount }} aula{{ $busyCount > 1 ? 's' : '' }} sendo processada{{ $busyCount > 1 ? 's' : '' }} agora.
            @else
                Suas aulas, resumidas e prontas pra revisar.
            @endif
        </p>
    </div>

    <a class="review-hero {{ $dueCount > 0 ? 'is-due' : '' }}" href="{{ route('review') }}">
        <div class="review-hero-top">
            <span class="review-hero-icon">{!! icon('cards', 24, '', 1.8) !!}</span>
            <div>
                <div class="review-hero-label">Revisão de hoje</div>
                @if ($dueCount > 0)
                    <div class="review-hero-count">
                        {{ $dueCount }}<span>flashcard{{ $dueCount > 1 ? 's' : '' }}</span>
                    </div>
                @else
                    <div class="review-hero-count" style="font-size:1.5rem;">Em dia</div>
                @endif
            </div>
        </div>
        <div class="review-hero-cta">
            <span>{{ $dueCount > 0 ? 'Começar a revisar' : 'Revisar mesmo assim' }}</span>
            {!! icon('chevron-right', 17) !!}
        </div>
    </a>

    <div class="mini-grid">
        <a class="mini-card {{ $streak > 0 ? 'is-active' : '' }}" href="{{ route('progress') }}">
            <span class="mini-card-icon">{!! icon('flame', 19, '', 1.9) !!}</span>
            <strong>{{ $streak > 0 ? $streak . ' dia' . ($streak > 1 ? 's' : '') : 'Sem streak' }}</strong>
            <span>
                @if ($reviewedToday > 0)
                    {{ $reviewedToday }} card{{ $reviewedToday > 1 ? 's' : '' }} hoje
                @elseif ($streak > 0)
                    Revise pra não perder
                @else
                    Revise hoje pra começar
                @endif
            </span>
        </a>

        <a class="mini-card" href="{{ route('subjects.index') }}">
            <span class="mini-card-icon">{!! icon('layers', 19, '', 1.9) !!}</span>
            <strong>{{ $doneCount }} aula{{ $doneCount == 1 ? '' : 's' }}</strong>
            <span>{{ count($grouped) }} matéria{{ count($grouped) == 1 ? '' : 's' }} · ver domínio</span>
        </a>
    </div>

    <div class="section">
        <div class="section-title">
            <h2>Suas aulas</h2>
            <span class="count">{{ count($classes) }} no total</span>
        </div>

        @if ($classes->isEmpty())
            <div class="empty-state">
                {!! icon('book-open', 44, '', 1.5) !!}
                <p>
                    <strong>Nenhuma aula ainda</strong>
                    Toque no <strong style="display:inline; color:var(--accent-ink);">+</strong> pra enviar um áudio,
                    um link do YouTube ou colar uma transcrição.
                </p>
                <a class="button" href="{{ route('lessons.create') }}" style="margin-top:var(--sp-2);">
                    {!! icon('plus', 19, '', 2.2) !!}
                    Enviar primeira aula
                </a>
            </div>
        @endif

        @foreach ($grouped as $subjectName => $data)
            @php
                $hasNamedTopics = count(array_filter(array_keys($data['topics']), fn($k) => $k !== '')) > 0;
                $subjectClass = subject_badge_class($subjectName);
            @endphp
            <details class="list-group" data-group-key="{{ md5($subjectName) }}" open>
                <summary class="list-group-header {{ $subjectClass }}">
                    <span class="dot"></span>
                    <h2>{{ $subjectName }}</h2>
                    <span class="count">{{ $data['count'] }}</span>
                    @if ($data['subject_id'] > 0)
                        <a class="icon-btn is-sm" href="{{ route('review', ['subject_id' => $data['subject_id']]) }}"
                           title="Revisar flashcards de &quot;{{ $subjectName }}&quot;"
                           aria-label="Revisar flashcards de {{ $subjectName }}"
                           onclick="event.stopPropagation()">
                            {!! icon('cards', 16) !!}
                        </a>
                        <a class="icon-btn is-sm" href="{{ route('review', ['subject_id' => $data['subject_id'], 'cram' => 1]) }}"
                           title="Modo prova: revisar todos os flashcards de &quot;{{ $subjectName }}&quot; agora"
                           aria-label="Modo prova em {{ $subjectName }}"
                           onclick="event.stopPropagation()">
                            {!! icon('flame', 16) !!}
                        </a>
                    @endif
                    <span class="chevron"></span>
                </summary>

                <div class="list-card {{ $subjectClass }}">
                    @foreach ($data['topics'] as $topicKey => $topicData)
                        @if ($topicData['name'] !== null)
                            <div class="topic-divider">
                                <span>{{ $topicData['name'] }}</span>
                                @if ($topicData['topic_id'] > 0)
                                    <a class="icon-btn is-sm" href="{{ route('review', ['subject_id' => $data['subject_id'], 'topic_id' => $topicData['topic_id']]) }}"
                                       title="Revisar flashcards de &quot;{{ $topicData['name'] }}&quot;"
                                       aria-label="Revisar flashcards de {{ $topicData['name'] }}">
                                        {!! icon('cards', 14) !!}
                                    </a>
                                @endif
                            </div>
                        @elseif ($hasNamedTopics)
                            <div class="topic-divider is-muted">Sem assunto</div>
                        @endif

                        @foreach ($topicData['classes'] as $c)
                            @php $isSpinning = in_array($c->status, ['processing', 'downloading'], true); @endphp
                            <details class="list-row">
                                <summary class="list-row-head">
                                    <span class="row-avatar">{{ subject_initials($subjectName) }}</span>
                                    <span class="row-main">
                                        <span class="row-title-line">
                                            <strong>{{ $c->title }}</strong>
                                            @if (($c->source_type ?? 'upload') === 'youtube' && $c->youtube_url)
                                                <a class="link-icon" href="{{ $c->youtube_url }}"
                                                   target="_blank" rel="noopener"
                                                   title="Ver no YouTube" aria-label="Abrir vídeo no YouTube"
                                                   onclick="event.stopPropagation()">
                                                    {!! icon('youtube', 16) !!}
                                                </a>
                                            @endif
                                        </span>
                                        <span class="row-meta">
                                            <span class="row-status-icon row-status-{{ $c->status }} {{ $isSpinning ? 'is-spinning' : '' }}">
                                                {!! icon(\App\Models\Lesson::STATUS_ICONS[$c->status] ?? 'clock', 14) !!}
                                            </span>
                                            <span class="row-meta-text">
                                                {{ \App\Models\Lesson::STATUS_LABELS[$c->status] ?? $c->status }} ·
                                                {{ (new DateTime($c->created_at))->format('d/m/Y') }}
                                            </span>
                                        </span>
                                    </span>
                                    <span class="row-accessory"></span>
                                </summary>

                                <div class="list-row-body">
                                    <div class="row-actions">
                                        @if ($c->status === 'done')
                                            <a class="button ghost is-sm" href="{{ route('lessons.edit', $c->id) }}">
                                                {!! icon('pencil', 16) !!}
                                                <span>Editar</span>
                                            </a>
                                        @endif
                                        <form method="post" action="{{ route('lessons.destroy', $c->id) }}" data-confirm="Excluir a aula &quot;{{ $c->title }}&quot;? Essa ação não pode ser desfeita.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="button ghost is-sm">
                                                {!! icon('trash', 16) !!}
                                                <span>Excluir</span>
                                            </button>
                                        </form>
                                    </div>

                                    @if ($c->status === 'error')
                                        <p class="error">
                                            {!! icon('alert-circle', 18) !!}
                                            <span>{{ $c->error_message }}</span>
                                        </p>
                                        <form method="post" action="{{ route('lessons.retry', $c->id) }}">
                                            @csrf
                                            <button type="submit" class="button ghost is-sm">
                                                {!! icon('refresh', 16) !!}
                                                <span>Tentar novamente</span>
                                            </button>
                                        </form>
                                    @elseif ($c->status === 'done')
                                        <details class="disclosure">
                                            <summary>Ver resumo</summary>
                                            <div class="content-actions">
                                                <button type="button" class="copy-btn" data-copy-text="{{ $c->summary_markdown }}">
                                                    {!! icon('copy', 16) !!}
                                                    <span>Copiar</span>
                                                </button>
                                            </div>
                                            <div class="summary">{!! markdown($c->summary_markdown) !!}</div>
                                        </details>
                                        <details class="disclosure">
                                            <summary>Ver transcrição completa</summary>
                                            <div class="content-actions">
                                                <button type="button" class="copy-btn" data-copy-text="{{ $c->transcript }}">
                                                    {!! icon('copy', 16) !!}
                                                    <span>Copiar</span>
                                                </button>
                                            </div>
                                            <div class="transcript">{!! nl2br(e($c->transcript)) !!}</div>
                                        </details>
                                    @endif
                                </div>
                            </details>
                        @endforeach
                    @endforeach
                </div>
            </details>
        @endforeach
    </div>
</div>
@endsection
