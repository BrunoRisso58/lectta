@extends('layouts.app', ['title' => 'Enviar aula', 'nav' => ''])

@section('content')
<x-topbar variant="back" title="Enviar aula" :back="route('home')" narrow />

<div class="page is-narrow">
    <div class="page-head">
        <h1>Nova aula</h1>
        <p class="sub">Áudio, link do YouTube ou transcrição pronta — o resumo e os flashcards saem sozinhos.</p>
    </div>

    <x-flash />

    @if (session('success'))
        <div class="actions">
            <a class="button secondary" href="{{ route('home') }}">{!! icon('home', 18) !!} Ver minhas aulas</a>
            <a class="button ghost" href="{{ route('lessons.create') }}">Enviar outra</a>
        </div>
    @endif

    @if ($subjects->isEmpty())
        <div class="error-box">
            {!! icon('alert-circle', 20) !!}
            <span>Nenhuma matéria cadastrada ainda. <a href="{{ route('subjects.index') }}">Cadastre uma matéria</a> antes de enviar uma aula.</span>
        </div>
    @endif

    <div class="segmented" id="mode-tabs" role="tablist" style="margin-bottom:var(--sp-5);">
        <button type="button" class="segment is-active" id="tab-file" role="tab" aria-selected="true">
            {!! icon('music', 17) !!}
            <span class="segment-label">Áudio</span>
        </button>
        <button type="button" class="segment" id="tab-youtube" role="tab" aria-selected="false">
            {!! icon('youtube', 17) !!}
            <span class="segment-label">YouTube</span>
        </button>
        <button type="button" class="segment" id="tab-text" role="tab" aria-selected="false">
            {!! icon('clipboard', 17) !!}
            <span class="segment-label">Texto</span>
        </button>
    </div>

    <form method="post" action="{{ route('lessons.store') }}" enctype="multipart/form-data" class="stack" data-busy-label="Enviando…">
        @csrf
        <input type="hidden" name="mode" id="mode-input" value="{{ $mode }}">

        <label for="subject_id">Matéria
            <select id="subject_id" name="subject_id" required @disabled($subjects->isEmpty())>
                @foreach ($subjects as $s)
                    <option value="{{ $s->id }}" @selected($selectedSubjectId === (int) $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
            <span class="hint"><a href="{{ route('subjects.index') }}">gerenciar matérias e assuntos</a></span>
        </label>

        <label for="topic_id">Assunto <span class="hint">(opcional)</span>
            <select id="topic_id" name="topic_id">
                <option value="">Sem assunto específico</option>
            </select>
        </label>

        <label for="title" id="title-label">Título da aula
            <input type="text" id="title" name="title" placeholder="Ex: Escatologia — aula 4" value="{{ old('title') }}">
        </label>

        <div id="panel-file">
            <label for="audio">Arquivo MP3
                <span class="file-drop" id="file-drop">
                    {!! icon('music', 38, 'file-icon', 1.5) !!}
                    <span class="file-primary" id="file-primary">Toque para escolher o áudio</span>
                    <span class="file-secondary" id="file-secondary">.mp3 — o arquivo fica salvo com segurança</span>
                    <input type="file" id="audio" name="audio" accept=".mp3,audio/mpeg">
                </span>
            </label>
        </div>

        <div id="panel-youtube" hidden>
            <label for="youtube_urls">Link(s) do YouTube
                <textarea id="youtube_urls" name="youtube_urls" rows="5" placeholder="https://www.youtube.com/watch?v=...&#10;https://youtu.be/...&#10;(um link por linha — cada um vira uma aula)">{{ old('youtube_urls') }}</textarea>
                <span class="hint">O título de cada aula é pego automaticamente do vídeo. Cole vários links, um por linha, pra criar várias aulas de uma vez.</span>
            </label>
        </div>

        <div id="panel-text" hidden>
            <label for="transcript_text">Transcrição
                <textarea id="transcript_text" name="transcript_text" rows="10" placeholder="Cole aqui o texto já transcrito da aula...">{{ old('transcript_text') }}</textarea>
                <span class="hint">Sem áudio: o texto colado vai direto pra etapa de resumo e geração de flashcards.</span>
            </label>
        </div>

        <button type="submit" class="button block" id="submit-btn">
            {!! icon('arrow-up', 19, '', 2.2) !!}
            <span id="submit-label">Enviar</span>
        </button>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    var topicsBySubject = @json($topicsBySubject);
    var selectedTopicId = {{ (int) $selectedTopicId }};
    var subjectSelect = document.getElementById('subject_id');
    var topicSelect = document.getElementById('topic_id');

    function renderTopicOptions() {
        var topics = topicsBySubject[parseInt(subjectSelect.value, 10)] || [];
        topicSelect.innerHTML = '<option value="">Sem assunto específico</option>';
        topics.forEach(function (t) {
            var opt = document.createElement('option');
            opt.value = t.id;
            opt.textContent = t.name;
            if (t.id === selectedTopicId) opt.selected = true;
            topicSelect.appendChild(opt);
        });
    }
    if (subjectSelect) {
        subjectSelect.addEventListener('change', renderTopicOptions);
        renderTopicOptions();
    }

    var fileInput = document.getElementById('audio');
    var filePrimary = document.getElementById('file-primary');
    var fileSecondary = document.getElementById('file-secondary');
    var dropZone = document.getElementById('file-drop');

    fileInput.addEventListener('change', function () {
        if (!fileInput.files.length) return;
        filePrimary.textContent = fileInput.files[0].name;
        filePrimary.classList.add('file-name');
        fileSecondary.textContent = (fileInput.files[0].size / (1024 * 1024)).toFixed(1) + ' MB';
    });
    ['dragenter', 'dragover'].forEach(function (evt) {
        dropZone.addEventListener(evt, function (e) { e.preventDefault(); dropZone.classList.add('is-dragover'); });
    });
    ['dragleave', 'drop'].forEach(function (evt) {
        dropZone.addEventListener(evt, function (e) { e.preventDefault(); dropZone.classList.remove('is-dragover'); });
    });

    // Alterna entre "Áudio", "YouTube" e "Texto" -- os três painéis
    // compartilham matéria/assunto, só o campo de conteúdo muda. required é
    // setado via JS porque só um dos painéis conta por vez.
    var panels = {
        file: document.getElementById('panel-file'),
        youtube: document.getElementById('panel-youtube'),
        text: document.getElementById('panel-text')
    };
    var tabs = {
        file: document.getElementById('tab-file'),
        youtube: document.getElementById('tab-youtube'),
        text: document.getElementById('tab-text')
    };
    var titleLabel = document.getElementById('title-label');
    var modeInput = document.getElementById('mode-input');
    var titleInput = document.getElementById('title');
    var youtubeUrlsInput = document.getElementById('youtube_urls');
    var transcriptTextInput = document.getElementById('transcript_text');
    var submitLabel = document.getElementById('submit-label');

    function setMode(mode) {
        if (['file', 'youtube', 'text'].indexOf(mode) === -1) mode = 'file';
        modeInput.value = mode;

        Object.keys(panels).forEach(function (key) {
            panels[key].hidden = key !== mode;
            tabs[key].classList.toggle('is-active', key === mode);
            tabs[key].setAttribute('aria-selected', String(key === mode));
        });

        titleLabel.hidden = mode === 'youtube';
        fileInput.required = mode === 'file';
        titleInput.required = mode !== 'youtube';
        youtubeUrlsInput.required = mode === 'youtube';
        transcriptTextInput.required = mode === 'text';
        submitLabel.textContent = mode === 'youtube' ? 'Enviar links' : (mode === 'text' ? 'Enviar transcrição' : 'Enviar áudio');
    }
    Object.keys(tabs).forEach(function (key) {
        tabs[key].addEventListener('click', function () { setMode(key); });
    });
    setMode(modeInput.value);
})();
</script>
@endpush
