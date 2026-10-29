/**
 * Comportamento compartilhado do shell.
 *
 * Tudo aqui é progressivo: se este arquivo não carregar, a navegação, os
 * formulários e o conteúdo continuam funcionando -- some só o polimento
 * (tema manual, sombra da topbar, botão de copiar, confirmação bonita).
 */
(function () {
    'use strict';

    /* ---------- Tema claro/escuro -------------------------------------------
       Três estados: 'light', 'dark' e "sem preferência salva" (= segue o
       sistema). O primeiro toque a partir de "sem preferência" escolhe o
       OPOSTO do que a pessoa está vendo, que é o que ela espera de um toggle. */

    var STORAGE_KEY = 'lectta-theme';
    var root = document.documentElement;

    var ICON_MOON = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg>';
    var ICON_SUN = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.9 4.9 1.4 1.4"/><path d="m17.7 17.7 1.4 1.4"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m4.9 19.1 1.4-1.4"/><path d="m17.7 6.3 1.4-1.4"/></svg>';

    function currentlyDark() {
        var explicit = root.getAttribute('data-theme');
        if (explicit === 'dark') return true;
        if (explicit === 'light') return false;
        return window.matchMedia('(prefers-color-scheme: dark)').matches;
    }

    function paintThemeToggles() {
        var dark = currentlyDark();
        // No escuro o botão oferece o sol (ir pro claro), e vice-versa.
        document.querySelectorAll('[data-theme-icon]').forEach(function (slot) {
            slot.innerHTML = dark ? ICON_SUN : ICON_MOON;
        });
        document.querySelectorAll('[data-theme-label]').forEach(function (slot) {
            slot.textContent = dark ? 'Tema claro' : 'Tema escuro';
        });
        document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
            btn.setAttribute('title', dark ? 'Mudar para o tema claro' : 'Mudar para o tema escuro');
        });
    }

    function applyTheme(theme) {
        root.setAttribute('data-theme', theme);
        try { localStorage.setItem(STORAGE_KEY, theme); } catch (e) {}
        // A barra de status do sistema segue as <meta name="theme-color">, que
        // são por media query e não enxergam o data-theme -- então a escolha
        // manual precisa reescrevê-las na mão.
        var color = theme === 'dark' ? '#131210' : '#fbf8f2';
        document.querySelectorAll('meta[name="theme-color"]').forEach(function (meta) {
            meta.setAttribute('content', color);
        });
        paintThemeToggles();
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-theme-toggle]');
        if (!btn) return;
        applyTheme(currentlyDark() ? 'light' : 'dark');
    });

    // Se a pessoa nunca escolheu manualmente, acompanhar o sistema ao vivo.
    try {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
            if (!root.getAttribute('data-theme')) paintThemeToggles();
        });
    } catch (e) {}

    paintThemeToggles();

    /* ---------- Sombra da topbar ao rolar -----------------------------------
       A borda só aparece quando há conteúdo passando por baixo do blur.
       requestAnimationFrame + passive pra não travar a rolagem no celular. */

    var topbar = document.querySelector('[data-topbar]');
    if (topbar) {
        var ticking = false;
        var sync = function () {
            topbar.classList.toggle('is-scrolled', window.scrollY > 4);
            ticking = false;
        };
        window.addEventListener('scroll', function () {
            if (!ticking) {
                ticking = true;
                window.requestAnimationFrame(sync);
            }
        }, { passive: true });
        sync();
    }

    /* ---------- Botão de copiar --------------------------------------------- */

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.copy-btn');
        if (!btn) return;

        var text = btn.getAttribute('data-copy-text') || '';
        var label = btn.querySelector('span');
        var original = label ? label.textContent : '';

        var done = function () {
            btn.classList.add('is-copied');
            if (label) label.textContent = 'Copiado!';
            setTimeout(function () {
                btn.classList.remove('is-copied');
                if (label) label.textContent = original;
            }, 1800);
        };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done, fallback);
        } else {
            fallback();
        }

        function fallback() {
            // navigator.clipboard não existe fora de HTTPS (e o iOS antigo
            // recusa fora de um gesto), então o textarea escondido continua
            // sendo o caminho de reserva.
            var helper = document.createElement('textarea');
            helper.value = text;
            helper.setAttribute('readonly', '');
            helper.style.position = 'fixed';
            helper.style.opacity = '0';
            document.body.appendChild(helper);
            helper.select();
            try { document.execCommand('copy'); } catch (err) {}
            document.body.removeChild(helper);
            done();
        }
    });

    /* ---------- Confirmação de ações destrutivas ---------------------------
       Um data-confirm no <form> em vez de onsubmit="return confirm(...)"
       espalhado no HTML. */

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement)) return;

        var message = form.getAttribute('data-confirm');
        if (message && !window.confirm(message)) {
            e.preventDefault();
            return;
        }

        // Trava o botão do envio pra não criar aula duplicada com dois toques.
        var submitter = e.submitter || form.querySelector('[type="submit"]');
        if (submitter && form.hasAttribute('data-busy-label')) {
            var busy = form.getAttribute('data-busy-label');
            setTimeout(function () {
                submitter.disabled = true;
                var span = submitter.querySelector('span');
                if (span) { span.textContent = busy; } else { submitter.textContent = busy; }
            }, 0);
        }
    });

    /* ---------- Lembrar grupos abertos na lista de aulas --------------------
       Sem isto, cada volta pra home reabre tudo e a pessoa perde o lugar. */

    document.querySelectorAll('details.list-group[data-group-key]').forEach(function (group) {
        var key = 'lectta-group:' + group.getAttribute('data-group-key');
        try {
            var saved = localStorage.getItem(key);
            if (saved === 'closed') group.open = false;
            if (saved === 'open') group.open = true;
        } catch (e) {}
        group.addEventListener('toggle', function () {
            try { localStorage.setItem(key, group.open ? 'open' : 'closed'); } catch (e) {}
        });
    });
})();
