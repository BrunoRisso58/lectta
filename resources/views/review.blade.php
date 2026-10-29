@extends('layouts.app', ['title' => 'Revisão', 'nav' => 'review', 'due' => $totalDueAll, 'immersive' => $showPlayer])

@section('content')
@if ($showPlayer)
<div class="review-shell">
    <div class="review-topbar">
        <a class="icon-btn" href="{{ route('home') }}" aria-label="Sair da revisão">{!! icon('x', 21) !!}</a>
        <div class="review-progress-track">
            <div class="review-progress-fill" id="progress-fill"></div>
        </div>
        <div class="review-progress-count" id="progress-count"></div>
        <a class="icon-btn" href="{{ route('review', ['setup' => 1]) }}" aria-label="Escolher o que revisar">{!! icon('filter', 19) !!}</a>
    </div>

    <div class="review-body">
        <div class="review-body-inner" id="card-slot"></div>
    </div>

    <div class="review-footer" id="review-footer">
        <div class="review-footer-inner" id="footer-slot"></div>
    </div>
</div>

@else
<x-topbar variant="title" title="Revisão" narrow />

<div class="page is-narrow">
    <div class="page-head">
        <h1>O que vamos revisar?</h1>
        <p class="sub">
            @if ($totalCardsAll === 0)
                Você ainda não tem flashcards. Eles são gerados sozinhos quando uma aula termina de processar.
            @elseif ($totalDueAll > 0)
                {{ $totalDueAll }} flashcard{{ $totalDueAll > 1 ? 's' : '' }} vencido{{ $totalDueAll > 1 ? 's' : '' }} hoje, de {{ $totalCardsAll }} no total.
            @else
                Nada vencido hoje — mas dá pra adiantar qualquer matéria no modo prova.
            @endif
        </p>
    </div>

    @if ($totalCardsAll === 0)
        <div class="empty-state">
            {!! icon('cards', 44, '', 1.5) !!}
            <p>
                <strong>Nenhum flashcard ainda</strong>
                Envie uma aula e os flashcards aparecem aqui assim que o resumo ficar pronto.
            </p>
            <a class="button" href="{{ route('lessons.create') }}" style="margin-top:var(--sp-2);">
                {!! icon('plus', 19, '', 2.2) !!}
                Enviar uma aula
            </a>
        </div>
    @else

        @if (($subjectId || $classId || $cram) && !$setup)
            <div class="filter-status {{ $cram ? 'is-cram' : '' }}">
                {!! icon($cram ? 'flame' : 'filter', 16) !!}
                <span>
                    <strong>{{ $cram ? 'Modo prova' : 'Filtrando por' }}</strong>
                    @if ($selectedClassName !== null)
                        {{ $selectedClassName }}
                    @else
                        {{ $selectedSubjectName ?? '' }}{{ $selectedTopicName ? ' › ' . $selectedTopicName : '' }}
                    @endif
                    — nada encontrado.
                </span>
                <a href="{{ route('review', ['setup' => 1]) }}" class="filter-clear">Limpar</a>
            </div>
        @endif

        <form method="get" action="{{ route('review') }}" id="filter-form" class="card stack">
            <label for="f-subject">Matéria
                <select id="f-subject" name="subject_id">
                    <option value="0" @selected($subjectId === 0)>Todas as matérias</option>
                    @foreach ($subjects as $s)
                        <option value="{{ $s->id }}" @selected($subjectId === (int) $s->id)>
                            {{ $s->name }}{{ $s->due_count > 0 ? ' — ' . (int) $s->due_count . ' hoje' : '' }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label for="f-topic">Assunto <span class="hint">(opcional)</span>
                <select id="f-topic" name="topic_id">
                    <option value="0">Todos os assuntos</option>
                </select>
            </label>

            <div>
                <div class="segmented" id="review-mode-tabs" role="tablist" style="margin-bottom:var(--sp-3);">
                    <button type="button" class="segment {{ !$cram ? 'is-active' : '' }}" id="tab-today" role="tab" aria-selected="{{ !$cram ? 'true' : 'false' }}">
                        {!! icon('calendar', 17) !!}
                        <span class="segment-label">Revisão de hoje</span>
                    </button>
                    <button type="button" class="segment {{ $cram ? 'is-active' : '' }}" id="tab-cram" role="tab" aria-selected="{{ $cram ? 'true' : 'false' }}">
                        {!! icon('flame', 17) !!}
                        <span class="segment-label">Tenho prova</span>
                    </button>
                </div>
                <p class="filter-hint" id="cram-hint" style="{{ $cram ? '' : 'display:none;' }}">
                    O modo prova ignora a data de revisão e traz todos os cards da matéria (ou assunto) escolhida.
                </p>
            </div>

            <input type="hidden" name="cram" id="cram-input" value="{{ $cram ? '1' : '0' }}">
            <button type="submit" class="button block" id="apply-btn">
                <span id="apply-label">Revisar</span>
            </button>
        </form>
    @endif
</div>
@endif
@endsection

@push('scripts')
@php
    $setupUrl = route('review', ['setup' => 1]);
    $subjectsData = array_map(fn ($s) => ['id' => (int) $s->id, 'due' => (int) $s->due_count, 'total' => (int) $s->total_count], $subjects);
@endphp
<script>
(function () {
    'use strict';

    var cards = @json($cards);
    var csrfToken = @json(csrf_token());
    var cramMode = {{ $cram ? 'true' : 'false' }};
    var hasFilter = {{ ($subjectId || $classId) ? 'true' : 'false' }};
    var homeUrl = @json(route('home'));
    var setupUrl = @json($setupUrl);
    var gradeUrl = @json(route('review.grade'));
    var filterLabel = @json($selectedClassName ?: ($selectedTopicName ?: $selectedSubjectName));

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }

    /* =====================================================================
       Tela de escolher o que revisar
       ===================================================================== */

    var subjectSelect = document.getElementById('f-subject');
    if (subjectSelect) {
        var subjectsData = @json($subjectsData);
        var topicsBySubject = @json($topicsBySubject);
        var selectedTopicId = {{ (int) $topicId }};

        var topicSelect = document.getElementById('f-topic');
        var cramInput = document.getElementById('cram-input');
        var tabToday = document.getElementById('tab-today');
        var tabCram = document.getElementById('tab-cram');
        var cramHint = document.getElementById('cram-hint');
        var applyLabel = document.getElementById('apply-label');

        var renderTopicOptions = function () {
            var subjectId = parseInt(subjectSelect.value, 10);
            var topics = topicsBySubject[subjectId] || [];
            topicSelect.innerHTML = '<option value="0">Todos os assuntos</option>';
            topics.forEach(function (t) {
                var opt = document.createElement('option');
                opt.value = t.id;
                opt.textContent = t.name + (t.due > 0 ? ' — ' + t.due + ' hoje' : '');
                if (t.id === selectedTopicId) opt.selected = true;
                topicSelect.appendChild(opt);
            });
            topicSelect.disabled = subjectId === 0;
        };

        var setTab = function (isCram) {
            cramInput.value = isCram ? '1' : '0';
            tabToday.classList.toggle('is-active', !isCram);
            tabCram.classList.toggle('is-active', isCram);
            tabToday.setAttribute('aria-selected', String(!isCram));
            tabCram.setAttribute('aria-selected', String(isCram));
            cramHint.style.display = isCram ? 'block' : 'none';
        };

        // O botão diz quantos cards a escolha atual traz, antes de enviar --
        // "Revisar (nada pendente)" evita a viagem inútil até uma tela vazia.
        var updateApplyLabel = function () {
            var subjectId = parseInt(subjectSelect.value, 10);
            var topicId = parseInt(topicSelect.value, 10);
            var isCram = cramInput.value === '1';
            var due = 0, tot = 0;
            if (topicId) {
                var t = (topicsBySubject[subjectId] || []).find(function (x) { return x.id === topicId; });
                if (t) { due = t.due; tot = t.total; }
            } else if (subjectId) {
                var s = subjectsData.find(function (x) { return x.id === subjectId; });
                if (s) { due = s.due; tot = s.total; }
            } else {
                due = subjectsData.reduce(function (a, x) { return a + x.due; }, 0);
                tot = subjectsData.reduce(function (a, x) { return a + x.total; }, 0);
            }
            var n = isCram ? tot : due;
            if (isCram) {
                applyLabel.textContent = 'Iniciar modo prova · ' + n + ' card' + (n === 1 ? '' : 's');
            } else {
                applyLabel.textContent = n > 0 ? ('Revisar ' + n + ' card' + (n === 1 ? '' : 's')) : 'Revisar (nada pendente)';
            }
        };

        subjectSelect.addEventListener('change', function () {
            renderTopicOptions();
            if (parseInt(subjectSelect.value, 10) === 0 && cramInput.value === '1') setTab(false);
            updateApplyLabel();
        });
        topicSelect.addEventListener('change', updateApplyLabel);
        tabToday.addEventListener('click', function () { setTab(false); updateApplyLabel(); });
        tabCram.addEventListener('click', function () {
            if (parseInt(subjectSelect.value, 10) === 0) {
                cramHint.style.display = 'block';
                subjectSelect.focus();
                return;
            }
            setTab(true);
            updateApplyLabel();
        });

        renderTopicOptions();
        updateApplyLabel();
    }

    /* =====================================================================
       Player da revisão
       ===================================================================== */

    var cardSlot = document.getElementById('card-slot');
    if (!cardSlot) return;

    var footerSlot = document.getElementById('footer-slot');
    var footer = document.getElementById('review-footer');
    var progressFill = document.getElementById('progress-fill');
    var progressCount = document.getElementById('progress-count');
    var body = document.querySelector('.review-body');

    var index = 0;
    var originalTotal = cards.length;
    var revealed = false;

    var ICONS = {
        repeat: '<path d="m17 2 4 4-4 4"/><path d="M3 11v-1a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v1a4 4 0 0 1-4 4H3"/>',
        calendar: '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>',
        clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
        alert: '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        flame: '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>',
        again: '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>',
        hard: '<circle cx="12" cy="12" r="9"/><path d="M8 15s1.5-2 4-2 4 2 4 2"/><path d="M9 9h.01"/><path d="M15 9h.01"/>',
        good: '<circle cx="12" cy="12" r="9"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><path d="M9 9h.01"/><path d="M15 9h.01"/>',
        easy: '<circle cx="12" cy="12" r="9"/><path d="M8 13.5s1.5 3 4 3 4-3 4-3"/><path d="M8.5 8.5s1-1 2-1"/><path d="M15.5 8.5s-1-1-2-1"/>',
        check: '<path d="M20 6 9 17l-5-5"/>'
    };

    function svg(name, size, cls) {
        return '<svg ' + (cls ? 'class="' + cls + '" ' : '') + 'width="' + size + '" height="' + size +
            '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" ' +
            'stroke-linejoin="round" aria-hidden="true">' + ICONS[name] + '</svg>';
    }

    function renderProgress() {
        if (index >= cards.length) {
            progressFill.style.width = '100%';
            progressCount.textContent = '';
            return;
        }
        progressFill.style.width = (cards.length ? (index / cards.length) * 100 : 0) + '%';
        progressCount.textContent = (index + 1) + ' / ' + cards.length;
    }

    function renderDone() {
        footer.style.display = 'none';
        var title, text;
        if (originalTotal === 0) {
            title = hasFilter ? 'Nenhum flashcard aqui' : 'Tudo revisado';
            text = hasFilter
                ? 'Não encontrei flashcards' + (cramMode ? '' : ' vencidos') + ' em "' + filterLabel + '".'
                : 'Nenhum flashcard pendente por aqui. Bom trabalho.';
        } else if (cramMode) {
            title = 'Prova, pode vir!';
            text = 'Você repassou os ' + originalTotal + ' cards de "' + filterLabel + '". Boa sorte.';
        } else if (hasFilter) {
            title = 'Tudo revisado';
            text = 'Nenhum flashcard vencido em "' + filterLabel + '". Bom trabalho.';
        } else {
            title = 'Tudo revisado';
            text = 'Você fechou os ' + originalTotal + ' cards de hoje. Volte amanhã.';
        }

        cardSlot.innerHTML =
            '<div class="review-done">' +
                '<div class="review-done-mark">' + svg('check', 34) + '</div>' +
                '<h2>' + escapeHtml(title) + '</h2>' +
                '<p>' + escapeHtml(text) + '</p>' +
                '<div class="done-actions">' +
                    '<a class="button" href="' + homeUrl + '">Voltar para as aulas</a>' +
                    '<a class="button ghost" href="' + setupUrl + '">Revisar outra matéria</a>' +
                '</div>' +
            '</div>';
    }

    function renderCard() {
        var card = cards[index];
        var isNew = card.repetitions === 0;
        var intervalText = isNew ? 'Cartão novo' : 'Intervalo: ' + card.interval_days + ' dia' + (card.interval_days === 1 ? '' : 's');
        var lastReviewedText = card.last_reviewed_fmt ? 'Última vez: ' + card.last_reviewed_fmt : 'Ainda não revisado';

        cardSlot.innerHTML =
            '<div class="flashcard">' +
                '<div class="flashcard-meta">' +
                    '<span class="tag subject ' + escapeHtml(card.subject_class) + '"><span class="dot"></span>' + escapeHtml(card.class_subject) + '</span>' +
                    '<span class="class-title">' + escapeHtml(card.class_title) + '</span>' +
                    (card.class_topic ? '<span class="class-topic">· ' + escapeHtml(card.class_topic) + '</span>' : '') +
                '</div>' +
                '<div class="flash-question">' + escapeHtml(card.question) + '</div>' +
                '<div class="flash-answer" id="answer" hidden>' + escapeHtml(card.answer) + '</div>' +
                '<div class="flash-stats">' +
                    '<span class="stat">' + svg('repeat', 14) + 'Revisão nº ' + (card.repetitions + 1) + '</span>' +
                    '<span class="stat">' + svg('calendar', 14) + escapeHtml(intervalText) + '</span>' +
                    '<span class="stat">' + svg('clock', 14) + escapeHtml(lastReviewedText) + '</span>' +
                    (card.overdue_days > 0
                        ? '<span class="stat overdue">' + svg('alert', 14) + 'Atrasado ' + card.overdue_days + ' dia' + (card.overdue_days === 1 ? '' : 's') + '</span>'
                        : '') +
                    (card.not_due_yet
                        ? '<span class="stat cram">' + svg('flame', 14) + 'Revisão antecipada</span>'
                        : '') +
                '</div>' +
            '</div>';

        footer.style.display = '';
        footerSlot.innerHTML =
            '<button type="button" class="button reveal-btn" id="reveal-btn">Mostrar resposta</button>';
        document.getElementById('reveal-btn').addEventListener('click', reveal);
    }

    function reveal() {
        if (revealed) return;
        revealed = true;
        var answer = document.getElementById('answer');
        if (answer) answer.hidden = false;

        footerSlot.innerHTML =
            '<div class="grade-grid">' +
                '<button type="button" class="grade" data-q="0">' + svg('again', 21, 'grade-icon') + 'Novamente</button>' +
                '<button type="button" class="grade" data-q="3">' + svg('hard', 21, 'grade-icon') + 'Difícil</button>' +
                '<button type="button" class="grade" data-q="4">' + svg('good', 21, 'grade-icon') + 'Bom</button>' +
                '<button type="button" class="grade" data-q="5">' + svg('easy', 21, 'grade-icon') + 'Fácil</button>' +
            '</div>';

        footerSlot.querySelectorAll('.grade').forEach(function (btn) {
            btn.addEventListener('click', function () {
                grade(cards[index], parseInt(btn.dataset.q, 10));
            });
        });

        // A resposta entrou embaixo da pergunta: rola até ela em vez de
        // deixar a pessoa procurar (respostas longas nascem fora da tela).
        if (answer && body) {
            requestAnimationFrame(function () {
                answer.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            });
        }
    }

    function grade(card, quality) {
        // Dispara e segue: o SM-2 do servidor não muda nada nesta tela, e
        // esperar a resposta só faria a fila engasgar entre um card e outro.
        fetch(gradeUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ card_id: card.id, quality: quality })
        }).catch(function () {});

        if (quality < 3) {
            // "Novamente" volta pro fim da fila -- vence hoje de novo, então
            // reaparece ainda nesta sessão em vez de exigir recarregar a página.
            cards.push(Object.assign({}, card, {
                repetitions: 0,
                interval_days: 0,
                last_reviewed_fmt: new Date().toLocaleDateString('pt-BR'),
                overdue_days: 0,
                not_due_yet: false
            }));
        }
        index++;
        render();
    }

    function render() {
        revealed = false;
        renderProgress();
        if (body) body.scrollTop = 0;
        if (index >= cards.length) {
            renderDone();
        } else {
            renderCard();
        }
    }

    // Atalhos de teclado: espaço revela, 1-4 dá a nota. No celular não muda
    // nada; no computador é a diferença entre revisar 20 cards e desistir.
    document.addEventListener('keydown', function (e) {
        if (e.metaKey || e.ctrlKey || e.altKey) return;
        if (index >= cards.length) return;

        if (!revealed && (e.code === 'Space' || e.key === 'Enter')) {
            e.preventDefault();
            reveal();
            return;
        }
        if (revealed) {
            var map = { '1': 0, '2': 3, '3': 4, '4': 5 };
            if (Object.prototype.hasOwnProperty.call(map, e.key)) {
                e.preventDefault();
                grade(cards[index], map[e.key]);
            }
        }
    });

    render();
})();
</script>
@endpush
