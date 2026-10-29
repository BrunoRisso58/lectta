@extends('layouts.app', ['title' => 'Editar aula', 'nav' => ''])

@section('content')
<x-topbar variant="back" :title="$class->title" :back="route('home')" narrow>
    @if ($flashcards->isNotEmpty())
        <a class="icon-btn" href="{{ route('review', ['class_id' => $class->id, 'cram' => 1]) }}"
           title="Revisar esta aula" aria-label="Revisar esta aula">{!! icon('flame', 20) !!}</a>
    @endif
</x-topbar>

<div class="page is-narrow">
    <div class="page-head">
        <h1>{{ $class->title }}</h1>
        <p class="sub row">
            <span class="tag subject {{ subject_badge_class($class->subject) }}">
                <span class="dot"></span>{{ $class->subject }}
            </span>
            @if (!empty($class->topic))
                <span class="faint">{{ $class->topic }}</span>
            @endif
        </p>
    </div>

    <x-flash />

    <div class="card">
        <form method="post" action="{{ route('lessons.subject', $class->id) }}" class="stack">
            @csrf
            @method('PUT')
            <label for="subject_id">Matéria
                <select id="subject_id" name="subject_id" required @disabled($subjects->isEmpty())>
                    @foreach ($subjects as $s)
                        <option value="{{ $s->id }}" @selected((int) $class->subject_id === (int) $s->id)>
                            {{ $s->name }}
                        </option>
                    @endforeach
                </select>
                <span class="hint"><a href="{{ route('subjects.index') }}">gerenciar matérias e assuntos</a></span>
            </label>
            <label for="topic_id">Assunto <span class="hint">(opcional)</span>
                <select id="topic_id" name="topic_id">
                    <option value="">Sem assunto específico</option>
                </select>
            </label>
            <button type="submit" class="button secondary block" @disabled($subjects->isEmpty())>
                {!! icon('check', 18) !!}
                Salvar matéria e assunto
            </button>
        </form>
    </div>

    <div class="section">
        <div class="section-title">
            <h2>Conteúdo</h2>
        </div>
        <form method="post" action="{{ route('lessons.content', $class->id) }}" class="stack">
            @csrf
            @method('PUT')
            <label for="summary_markdown">Resumo <span class="hint">(markdown)</span>
                <textarea id="summary_markdown" name="summary_markdown" rows="14" required>{{ $class->summary_markdown }}</textarea>
            </label>
            <label for="transcript">Transcrição completa
                <textarea id="transcript" name="transcript" rows="18" required>{{ $class->transcript }}</textarea>
            </label>
            <button type="submit" class="button block">
                {!! icon('check', 19) !!}
                Salvar alterações
            </button>
        </form>
    </div>

    <div class="section">
        <div class="section-title">
            <h2>Flashcards</h2>
            <span class="count">{{ count($flashcards) }} no total</span>
        </div>

        <div class="row" style="gap:var(--sp-2); margin-bottom:var(--sp-4);">
            <button type="button" class="button secondary is-sm" data-panel-toggle="ai-flashcard-panel">
                {!! icon('sparkles', 16) !!}
                Gerar mais com IA
            </button>
            <button type="button" class="button secondary is-sm" data-panel-toggle="add-flashcard-panel">
                {!! icon('plus', 16) !!}
                Adicionar manualmente
            </button>
        </div>

        <div class="card" id="ai-flashcard-panel" hidden style="margin-bottom:var(--sp-4);">
            <form method="post" action="{{ route('flashcards.generate', $class->id) }}" class="stack" data-busy-label="Gerando com IA...">
                @csrf
                <label for="ai-count">Quantos cards gerar?
                    <span class="hint">(teto -- a IA gera menos se a aula não render mais conteúdo)</span>
                    <input type="number" id="ai-count" name="count" min="1" max="10" value="5" required>
                </label>
                <button type="submit" class="button block">
                    {!! icon('sparkles', 18) !!}
                    Gerar com IA
                </button>
            </form>
        </div>

        <div class="card" id="add-flashcard-panel" hidden style="margin-bottom:var(--sp-4);">
            <form method="post" action="{{ route('flashcards.store', $class->id) }}" class="stack">
                @csrf
                <label>Pergunta
                    <textarea name="question" rows="2" required></textarea>
                </label>
                <label>Resposta
                    <textarea name="answer" rows="3" required></textarea>
                </label>
                <button type="submit" class="button block">
                    {!! icon('check', 18) !!}
                    Adicionar flashcard
                </button>
            </form>
        </div>

        @if ($flashcards->isEmpty())
            <div class="empty-state">
                {!! icon('cards', 44, '', 1.5) !!}
                <p>
                    <strong>Nenhum flashcard</strong>
                    Esta aula não gerou cartões de revisão.
                </p>
            </div>
        @endif

        @foreach ($flashcards as $i => $fc)
            <div class="card" id="fc-{{ $fc->id }}">
                <div class="card-header">
                    <strong>Flashcard {{ $i + 1 }}</strong>
                    <button type="button" class="icon-btn is-sm" data-flashcard-toggle="{{ $fc->id }}" title="Editar flashcard" aria-label="Editar flashcard {{ $i + 1 }}">
                        {!! icon('pencil', 16) !!}
                    </button>
                    <form method="post" action="{{ route('flashcards.destroy', [$class->id, $fc->id]) }}" data-confirm="Excluir este flashcard? Essa ação não pode ser desfeita.">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="icon-btn is-sm" title="Excluir flashcard" aria-label="Excluir flashcard {{ $i + 1 }}">
                            {!! icon('trash', 16) !!}
                        </button>
                    </form>
                </div>

                <div id="flashcard-view-{{ $fc->id }}" style="margin-top:var(--sp-3);">
                    <div class="flash-question" style="font-size:var(--t-lg);">{{ $fc->question }}</div>
                    <div class="flash-answer" style="margin-top:var(--sp-3); animation:none;">{{ $fc->answer }}</div>
                </div>

                <form method="post" action="{{ route('flashcards.update', [$class->id, $fc->id]) }}" class="stack" id="flashcard-edit-{{ $fc->id }}" hidden style="margin-top:var(--sp-4);">
                    @csrf
                    @method('PUT')
                    <label>Pergunta
                        <textarea name="question" rows="2" required>{{ $fc->question }}</textarea>
                    </label>
                    <label>Resposta
                        <textarea name="answer" rows="3" required>{{ $fc->answer }}</textarea>
                    </label>
                    <div class="row" style="flex-wrap:nowrap;">
                        <button type="submit" class="button secondary is-sm" style="flex:1;">
                            {!! icon('check', 16) !!}
                            Salvar
                        </button>
                        <button type="button" class="button ghost is-sm" style="flex:1;" data-flashcard-toggle="{{ $fc->id }}">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        @endforeach
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    // Mesma cascata do envio: a lista de assuntos depende da matéria escolhida,
    // e trocar de matéria zera o assunto (o servidor recusa a combinação errada).
    var topicsBySubject = @json($topicsBySubject);
    var currentTopicId = {{ (int) ($class->topic_id ?? 0) }};
    var subjectSelect = document.getElementById('subject_id');
    var topicSelect = document.getElementById('topic_id');

    function renderTopicOptions() {
        var topics = topicsBySubject[parseInt(subjectSelect.value, 10)] || [];
        topicSelect.innerHTML = '<option value="">Sem assunto específico</option>';
        topics.forEach(function (t) {
            var opt = document.createElement('option');
            opt.value = t.id;
            opt.textContent = t.name;
            if (t.id === currentTopicId) opt.selected = true;
            topicSelect.appendChild(opt);
        });
    }
    if (subjectSelect) {
        subjectSelect.addEventListener('change', renderTopicOptions);
        renderTopicOptions();
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-flashcard-toggle]');
        if (!btn) return;
        var id = btn.getAttribute('data-flashcard-toggle');
        var view = document.getElementById('flashcard-view-' + id);
        var edit = document.getElementById('flashcard-edit-' + id);
        var editing = !edit.hidden;
        view.hidden = !editing;
        edit.hidden = editing;
        if (!editing) edit.querySelector('textarea').focus();
    });

    // Painéis "gerar com IA" / "adicionar manualmente" -- só um aberto por vez.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-panel-toggle]');
        if (!btn) return;
        var panel = document.getElementById(btn.getAttribute('data-panel-toggle'));
        if (!panel) return;
        var wasHidden = panel.hidden;
        ['ai-flashcard-panel', 'add-flashcard-panel'].forEach(function (otherId) {
            var other = document.getElementById(otherId);
            if (other) other.hidden = true;
        });
        panel.hidden = !wasHidden;
        if (!panel.hidden) {
            var field = panel.querySelector('textarea, input');
            if (field) field.focus();
        }
    });
})();
</script>
@endpush
