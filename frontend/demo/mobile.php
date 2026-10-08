<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

mhl_demo_no_cache_headers();

$sessionId = isset($_GET['s']) && is_string($_GET['s'])
    ? trim($_GET['s'])
    : '';

try {
    mhl_demo_read_session($sessionId);
} catch (Throwable $e) {
    // Neplatná, smazaná nebo expirovaná demo relace není pro návštěvníka
    // chybová obrazovka. Vrátíme jej na nový úvod dema, kde může ihned
    // spustit další relaci.
    header('Location: /demo/?ended=1', true, 302);
    exit;
}
?>
<!doctype html>
<html lang="<?php echo mhl_ui_language(); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <meta name="theme-color" content="#0f62d8">
    <title><?php echo mhl_ui_html('Demo hlasování – Hlasuj! by MiloslavHub'); ?></title>
    <link rel="stylesheet" href="/demo/assets/demo.css?v=0.8.9">
    <?php mhl_ui_bootstrap(); ?>
</head>
<body class="demo-mobile">
<main class="mobile-shell">
    <header class="mobile-brand">
        <img class="mobile-brand-logo" src="/demo/assets/hlasuj-logo.png" alt="Hlasuj! by MiloslavHub">
        <span><?php echo mhl_ui_html('Interaktivní demo'); ?></span>
    </header>

    <section id="mobile-screen" class="mobile-screen">
        <?php if (isset($error)): ?>
            <div class="mobile-card">
                <h1><?php echo mhl_ui_html('Demo není dostupné'); ?></h1>
                <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                <a class="mobile-primary anchor-button" href="/"><?php echo mhl_ui_html('Zpět'); ?></a>
            </div>
        <?php else: ?>
            <div class="mobile-card">
                <div class="mobile-loading"><?php echo mhl_ui_html('Načítám demo…'); ?></div>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php if (!isset($error)): ?>
<script>{
const tr=globalThis.MHLUI?.text||(v=>v),ui=globalThis.MHLUI?.html||((p,...v)=>p.reduce((o,s,i)=>o+s+(i<v.length?v[i]:''),''));
const num=globalThis.MHLUI?.number||((v,d=0)=>Number(v).toLocaleString(document.documentElement.lang==='en'?'en-US':'cs-CZ',{minimumFractionDigits:d,maximumFractionDigits:d}));

(() => {
    'use strict';

    const session = <?php echo json_encode($sessionId, JSON_UNESCAPED_SLASHES); ?>;
    const screen = document.getElementById('mobile-screen');

    const storageKey = ui`mhlDemoParticipant:${session}`;
    let participant = localStorage.getItem(storageKey);

    if (!participant || !/^[a-f0-9]{32}$/.test(participant)) {
        const bytes = new Uint8Array(16);
        crypto.getRandomValues(bytes);
        participant = Array.from(bytes).map(b => b.toString(16).padStart(2, '0')).join('');
        localStorage.setItem(storageKey, participant);
    }

    let joined = localStorage.getItem(ui`${storageKey}:joined`) === '1';
    let state = null;
    let sending = false;
    let pollingStopped = false;
    let pollTimer = null;

    const esc = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    function renderJoin() {
        screen.innerHTML = ui`
            <div class="mobile-card join-mobile-card">
                <div class="mobile-step"><b>1</b><span>Připojení k ukázce</span></div>

                <h1>Zadejte přezdívku</h1>
                <p class="join-lead">
                    Pod ní budete odpovídat v této ukázce.
                </p>

                <form id="join-form" novalidate>
                    <label for="nickname">Vaše přezdívka</label>

                    <input
                        id="nickname"
                        name="nickname"
                        type="text"
                        maxlength="40"
                        autocomplete="nickname"
                        inputmode="text"
                        autocapitalize="words"
                        spellcheck="false"
                        enterkeyhint="go"
                        placeholder="Např. Milouš"
                        aria-describedby="nickname-help form-error"
                    >

                    <button id="join-button" class="mobile-primary join-button" type="submit">
                        <span>Připojit se</span>
                        <b aria-hidden="true">→</b>
                    </button>

                    <small id="nickname-help" class="field-help">
                        1–40 znaků. Nemusí to být skutečné jméno.
                    </small>

                    <div id="form-error" class="form-error" role="alert" aria-live="polite"></div>
                </form>

                <small class="privacy-note compact-privacy">
                    Bez registrace. Demo po 15 minutách vyprší; data se odstraní při následném úklidu.
                </small>
            </div>
        `;

        const form = document.getElementById('join-form');
        const input = document.getElementById('nickname');

        form.addEventListener('submit', join);
        input.addEventListener('input', () => {
            document.getElementById('form-error').textContent = '';
        });

        // Klávesnici neotevíráme automaticky; uživatel klepne do pole sám.
    }

    function renderWaiting(message = tr('Jste připojeni')) {
        screen.innerHTML = ui`
            <div class="mobile-card waiting-card">
                <div class="connected-dot"><i></i></div>
                <span class="mobile-kicker">PŘIPOJENO</span>
                <h1>${esc(message)}</h1>
                <p>Načítám první otázku…</p>
                <div class="waiting-animation"><i></i><i></i><i></i></div>
            </div>
        `;
    }

    function renderQuestion() {
        const q = state.question;
        const answered = state.my_answer;
        const nowSeconds = Date.now() / 1000;
        const startsAt = Number(state.question_starts_at || 0);

        if (startsAt > nowSeconds) {
            screen.innerHTML = ui`
                <div class="mobile-card prestart-card">
                    <span class="mobile-kicker">PŘIPRAVTE SE</span>
                    <h1>Otázka začne za</h1>
                    <div class="prestart-number" data-prestart-countdown>2</div>
                    <p>Čas odpovědi se bude počítat všem stejně od okamžiku <strong>START</strong>.</p>
                </div>
            `;
            tickTimers();
            return;
        }

        if (answered) {
            const answerText = q.options?.[answered] || answered;
            screen.innerHTML = ui`
                <div class="mobile-card answer-saved-card">
                    <div class="mobile-result-topline">
                        <span class="mobile-kicker">ODPOVĚĎ ULOŽENA</span>
                        <div class="mobile-timer waiting-timer">
                            <span>Zbývá</span>
                            <strong data-question-countdown>—</strong>
                        </div>
                    </div>
                    <div class="answer-saved-check">✓</div>
                    <h1>Odpověď je uložena.</h1>
                    <p>Zvolili jste <strong>${esc(answered)} – ${esc(answerText)}</strong>.</p>
                    <p class="answer-wait-note">Výsledky se zobrazí až po uzavření otázky, stejně jako v ostrém hlasování.</p>
                </div>`;
            tickTimers();
            return;
        }

        const options = Object.entries(q.options).map(([key, value]) => ui`
            <button class="answer-button" data-answer="${esc(key)}" type="button">
                <span>${esc(key)}</span><strong>${esc(value)}</strong>
            </button>`).join('');

        screen.innerHTML = ui`
            <div class="mobile-card question-card-polished">
                <div class="mobile-result-topline">
                    <span class="mobile-kicker">${q.kind === 'poll' ? tr('ANONYMNÍ ANKETA') : tr('KVÍZ')}</span>
                    <div class="mobile-timer"><span>Zbývá</span><strong data-question-countdown>—</strong></div>
                </div>
                <h1>${esc(q.title)}</h1>
                <p>${esc(q.subtitle)}</p>
                <div class="question-time-track" aria-hidden="true"><i data-question-progress></i></div>
                <div class="mobile-options">${options}</div>
            </div>`;

        document.querySelectorAll('.answer-button').forEach(button => {
            button.addEventListener('click', () => answer(button.dataset.answer));
        });
        tickTimers();
    }

    function rankingPreview() {
        if (!Array.isArray(state.leaderboard) || !state.leaderboard.length) {
            return '';
        }

        const me = state.leaderboard.find(row => row.is_me);
        const top = state.leaderboard.slice(0, 3);

        let rows = top.map(row => ui`
            <div class="mini-rank-row ${row.is_me ? 'me' : ''}">
                <b>${Number(row.rank)}.</b>
                <span>${esc(row.nickname)}</span>
                <strong>${Number(row.score)} b.</strong>
                <em>${Number(row.response_time_ms || 0) > 0 ? ui`${num(Number(row.response_time_ms) / 1000,1)} s` : '—'}</em>
                ${row.synthetic ? '<small>demo</small>' : ui`<small>vy</small>`}
            </div>
        `).join('');

        if (me && Number(me.rank) > 3) {
            rows += ui`
                <div class="mini-rank-separator">…</div>
                <div class="mini-rank-row me">
                    <b>${Number(me.rank)}.</b>
                    <span>${esc(me.nickname)}</span>
                    <strong>${Number(me.score)} b.</strong>
                    <small>vy</small>
                </div>
            `;
        }

        return ui`
            <div class="mini-ranking">
                <div class="mini-ranking-head">
                    <strong>Průběžné pořadí</strong>
                    <span>body · čas od STARTU</span>
                </div>
                ${rows}
            </div>
        `;
    }

    function renderResults() {
        const q = state.question;
        const results = state.results || {};
        const my = state.my_answer;

        const rows = Object.entries(q.options).map(([key, value]) => {
            const result = results[key] || {count: 0, percent: 0};
            const correct = state.correct && key === state.correct;
            const mine = my === key;

            const percent = Number(result.percent || 0);
            const count = Number(result.count || 0);

            return ui`
                <div class="mobile-result vote-chart-row ${correct ? 'correct' : ''} ${mine ? 'mine' : ''}">
                    <div class="vote-chart-head mobile-vote-chart-head">
                        <span class="option-key">${esc(key)}</span>
                        <strong>${esc(value)}</strong>
                        <span class="vote-chart-stats">
                            <b>${count}</b> ${count === 1 ? tr('hlas') : (count >= 2 && count <= 4 ? tr('hlasy') : tr('hlasů'))}
                            <em>${num(percent, Number.isInteger(percent) ? 0 : 1)} %</em>
                        </span>
                    </div>
                    <div class="vote-chart-track" role="img" aria-label="${esc(value)}: ${num(percent, Number.isInteger(percent) ? 0 : 1)} procent">
                        <i style="width:${Math.max(0, Math.min(100, percent))}%"></i>
                    </div>
                </div>
            `;
        }).join('');

        const verdict = q.kind === 'quiz'
            ? (my === null ? tr('Čas vypršel.') : (my === state.correct ? tr('Správně.') : tr('Tentokrát ne.')))
            : tr('Výsledky ankety');

        screen.innerHTML = ui`
            <div class="mobile-card results-card-polished">
                <div class="mobile-result-topline">
                    <span class="mobile-kicker">VÝSLEDKY</span>
                    <div class="mobile-timer result-timer"><span>${Number(state.stage) === 6 ? tr('Žebříček za') : tr('Další za')}</span><strong data-result-countdown>—</strong></div>
                </div>
                <h1>${esc(verdict)}</h1>
                <p>${esc(q.title)}</p>
                <div class="mobile-results">${rows}</div>
                ${state.explanation ? ui`<p class="mobile-explanation">${esc(state.explanation)}</p>` : ''}

                ${q.kind === 'quiz' ? ui`
                    <div class="score-panel">
                        <div>
                            <span>Za tuto otázku</span>
                            <strong>${Number(state.my_question_points || 0)} b.</strong>
                        </div>
                        <div>
                            <span>Celkem</span>
                            <strong>${Number(state.my_total_points || 0)} b.</strong>
                        </div>
                        <div>
                            <span>Čas hlasování</span>
                            <strong>${my === null ? '—' : ui`${num(Number(state.my_answer_time || 0), 1)} s`}</strong>
                        </div>
                        <div class="rank-emphasis">
                            <span>Průběžné pořadí</span>
                            <strong>${Number(state.my_rank || 0)}. / ${Number(state.rank_total || 18)}</strong>
                        </div>
                        <small>${my === null ? tr('Tentokrát jste neodpověděl(a) v časovém limitu.') : ui`Čas ukazuje, za kolik sekund od spuštění otázky byl hlas odeslán.`}</small>
                    </div>

                    ${rankingPreview()}
                ` : ''}

                <div class="demo-data-note">
                    <b>Demo skupina</b>
                    <span>Výsledky zahrnují 17 fiktivních respondentů + váš hlas.</span>
                </div>

                <small class="auto-next-note">
                    ${Number(state.stage) === 6
                        ? tr('Za několik sekund se zobrazí demo žebříček.')
                        : tr('Po zobrazení průběžného pořadí se automaticky načte další otázka.')}
                </small>
            </div>
        `;
        tickTimers();
    }

    function hallRows(limit = 8) {
        if (!Array.isArray(state.leaderboard) || !state.leaderboard.length) {
            return '';
        }

        return state.leaderboard.slice(0, limit).map(row => ui`
            <div class="hall-row ${row.is_me ? 'me' : ''}">
                <b>${Number(row.rank)}.</b>
                <span>${esc(row.nickname)}</span>
                <strong>${Number(row.score)} b.</strong>
                ${row.synthetic ? '<small>demo</small>' : ui`<small>vy</small>`}
            </div>
        `).join('');
    }

    function finalBadgeText() {
        const rank = Number(state.my_rank || 0);
        if (rank === 1) return tr('Skvělé – v demu jste první.');
        if (rank <= 3) return tr('Výborně – jste na demo pódiu.');
        if (rank <= 10) return tr('Patříte do demo Top 10.');
        return tr('V ostrém režimu byste tentokrát byli mimo Top 10.');
    }

    function renderHallOfFame() {
        const inviteCopy = Number(state.my_rank || 999) <= 10
            ? tr('V ostrém systému byste se nyní mohl(a) rozhodnout, jak se v Síni slávy zobrazíte.')
            : tr('Pro účely dema můžete i mimo Top 10 vyzkoušet, jak vypadá závěrečný krok se Síní slávy.');

        screen.innerHTML = ui`
            <div class="mobile-card hall-card polished-hall-card">
                <span class="mobile-kicker">FIKTIVNÍ SÍŇ SLÁVY</span>
                <h1>Finální pořadí</h1>
                <p>${finalBadgeText()}</p>

                <div class="hall-highlight">
                    <div>
                        <span>Vaše pozice</span>
                        <strong>${Number(state.my_rank || 0)}. místo</strong>
                    </div>
                    <div>
                        <span>Celkem bodů</span>
                        <strong>${Number(state.my_score || 0)} b.</strong>
                    </div>
                </div>

                <div class="hall-table">${hallRows(8)}</div>

                <div class="hall-invite">
                    <h2>Ukázka závěrečného rozhodnutí</h2>
                    <p>${inviteCopy}</p>

                    <button class="hall-choice primary" data-hall="nickname" type="button">
                        <span>Zapsat jako <b>${esc(state.nickname || tr('přezdívka'))}</b></span>
                        <strong>→</strong>
                    </button>

                    <button class="hall-choice" data-hall="anonymous" type="button">
                        <span>Zapsat anonymně</span>
                        <strong>→</strong>
                    </button>

                    <button class="hall-choice subtle" data-hall="skip" type="button">
                        Nezapisovat
                    </button>
                </div>

                <small class="fake-warning">
                    Ukázka obsahuje 17 fiktivních respondentů. Připojit se mohou i další skuteční účastníci.
                </small>
            </div>
        `;

        document.querySelectorAll('[data-hall]').forEach(button => {
            button.addEventListener('click', () => chooseHall(button.dataset.hall));
        });
    }

    function renderFinish() {
        pollingStopped = true;
        if (pollTimer !== null) {
            clearInterval(pollTimer);
            pollTimer = null;
        }

        const choiceText = {
            nickname: ui`V ostrém systému byste byl(a) zapsán(a) jako „${esc(state.nickname || tr('přezdívka'))}“.`,
            anonymous: tr('V ostrém systému byste byl(a) v Síni slávy anonymně.'),
            skip: tr('Zvolil(a) jste, že v Síni slávy být nechcete.')
        }[state.hall_choice] || '';

        screen.innerHTML = ui`
            <div class="mobile-card finish-mobile-card polished-finish-card">
                <div class="finish-check">✓</div>
                <span class="mobile-kicker">DEMO DOKONČENO</span>
                <h1>Hotovo.</h1>

                <p class="finish-lead">${choiceText}</p>

                <div class="hall-highlight final-highlight">
                    <div>
                        <span>Vaše finální pozice</span>
                        <strong>${Number(state.my_rank || 0)}. místo</strong>
                    </div>
                    <div>
                        <span>Finální skóre</span>
                        <strong>${Number(state.my_score || 0)} b.</strong>
                    </div>
                </div>

                <div class="demo-summary">
                    <span>✓ Připojení QR kódem</span>
                    <span>✓ 2 znalostní otázky s body</span>
                    <span>✓ anonymní anketa</span>
                    <span>✓ průběžné pořadí</span>
                    <span>✓ fiktivní Síň slávy</span>
                </div>

                <section class="final-hall-block">
                    <div class="mini-ranking-head">
                        <strong>Fiktivní Síň slávy</strong>
                        <span>Top 5</span>
                    </div>
                    <div class="hall-table compact">${hallRows(5)}</div>
                </section>

                <p class="fake-warning finish-warning">
                    Ukázka obsahovala 17 fiktivních respondentů a připojené skutečné účastníky.
                    Do ostrých dat se nic nezapsalo.
                </p>

                <button
                    type="button"
                    class="finish-restart-button"
                    aria-label="Spustit demo znovu"
                    onclick="window.location.href='/demo/'"
                    style="width:100%;min-height:56px;margin-top:8px;padding:15px 18px;display:flex!important;align-items:center;justify-content:space-between;gap:12px;border:0!important;border-radius:14px;background:#0f62d8!important;background-image:none!important;color:#ffffff!important;-webkit-text-fill-color:#ffffff!important;text-decoration:none!important;font-size:18px;font-weight:850;line-height:1.2;box-shadow:0 12px 28px rgba(15,98,216,.24);opacity:1!important;visibility:visible!important;appearance:none;-webkit-appearance:none;cursor:pointer;">
                    <span style="color:#ffffff!important;-webkit-text-fill-color:#ffffff!important;">Spustit demo znovu</span>
                    <b style="color:#ffffff!important;-webkit-text-fill-color:#ffffff!important;font-size:22px;line-height:1;">↻</b>
                </button>
                <a class="mobile-secondary-link" href="/demo/">Zpět na úvod dema</a>
                <p class="finish-session-note">
                    Tato dokončená obrazovka zůstane zobrazena. Po opětovném otevření
                    starého odkazu se automaticky vrátíte na úvod dema.
                </p>
            </div>
        `;
    }

    function formatTenths(ms) {
        return ui`${num(Math.max(0, ms / 1000),1)} s`;
    }

    function tickTimers() {
        const now = Date.now();
        document.querySelectorAll('[data-prestart-countdown]').forEach(el => {
            const deadline = Number(state?.question_starts_at || 0) * 1000;
            el.textContent = deadline > 0
                ? Math.max(0, Math.ceil((deadline - now) / 1000))
                : '0';
        });

        document.querySelectorAll('[data-question-countdown]').forEach(el => {
            const deadline = Number(state?.question_ends_at || 0) * 1000;
            el.textContent = deadline > 0 ? formatTenths(deadline - now) : `${num(0,1)} s`;
        });
        document.querySelectorAll('[data-question-progress]').forEach(el => {
            const deadline = Number(state?.question_ends_at || 0) * 1000;
            const duration = Number(state?.question_duration_seconds || 10) * 1000;
            const left = Math.max(0, deadline - now);
            const percent = duration > 0 ? Math.max(0, Math.min(100, (left / duration) * 100)) : 0;
            el.style.width = ui`${percent}%`;
            el.classList.toggle('urgent', left <= 3000);
        });
        document.querySelectorAll('[data-result-countdown]').forEach(el => {
            const deadline = Number(state?.auto_advance_at || 0) * 1000;
            el.textContent = deadline > 0 ? ui`${Math.max(0, Math.ceil((deadline - now) / 1000))} s` : '0 s';
        });
    }

    function render() {
        if (!state) return;

        if (!joined) {
            // DŮLEŽITÉ:
            // během pravidelného pollingu formulář znovu nevytváříme,
            // jinak by se smazal právě rozepsaný text v inputu.
            if (!document.getElementById('join-form')) {
                renderJoin();
            }
            return;
        }

        const stage = Number(state.stage || 0);
        if (stage === 0) renderWaiting();
        else if ([1,3,5].includes(stage)) renderQuestion();
        else if ([2,4,6].includes(stage)) renderResults();
        else if (stage === 7) renderHallOfFame();
        else renderFinish();
    }

    async function join(event) {
        event.preventDefault();
        if (sending) return;

        const input = document.getElementById('nickname');
        const errorBox = document.getElementById('form-error');
        const button = document.getElementById('join-button');
        const nickname = input.value.trim();

        errorBox.textContent = '';

        if (!nickname) {
            errorBox.textContent = tr('Nejdříve zadejte přezdívku.');
            input.focus();
            return;
        }

        sending = true;
        button.disabled = true;
        button.querySelector('span').textContent = tr('Připojuji…');

        try {
            const body = new URLSearchParams({
                action: 'join',
                s: session,
                p: participant,
                nickname
            });

            const response = await fetch('/demo/api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
                body
            });

            const data = await response.json();
            if (!response.ok || data.status !== 'ok') {
                throw new Error(data.message || tr('Připojení se nepodařilo.'));
            }

            joined = true;
            localStorage.setItem(ui`${storageKey}:joined`, '1');
            state = data;
            render();
        } catch (error) {
            errorBox.textContent = error.message || tr('Připojení se nepodařilo.');
            button.disabled = false;
            button.querySelector('span').textContent = tr('Připojit se');
        } finally {
            sending = false;
        }
    }

    async function answer(value) {
        if (sending) return;
        sending = true;

        document.querySelectorAll('.answer-button').forEach(button => {
            button.disabled = true;
        });

        try {
            const body = new URLSearchParams({
                action: 'answer',
                s: session,
                p: participant,
                answer: value
            });

            const response = await fetch('/demo/api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
                body
            });

            const data = await response.json();
            if (!response.ok || data.status !== 'ok') {
                throw new Error(data.message || tr('Odpověď se nepodařilo uložit.'));
            }

            state = data;
            render();
        } catch (error) {
            alert(error.message);
        } finally {
            sending = false;
        }
    }

    async function chooseHall(choice) {
        if (sending) return;
        sending = true;

        document.querySelectorAll('[data-hall]').forEach(button => {
            button.disabled = true;
        });

        try {
            const body = new URLSearchParams({
                action: 'hall',
                s: session,
                p: participant,
                choice
            });

            const response = await fetch('/demo/api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
                body
            });

            const data = await response.json();
            if (!response.ok || data.status !== 'ok') {
                throw new Error(data.message || tr('Volbu se nepodařilo uložit.'));
            }

            state = data;
            render();
        } catch (error) {
            alert(error.message);
            document.querySelectorAll('[data-hall]').forEach(button => {
                button.disabled = false;
            });
        } finally {
            sending = false;
        }
    }

    async function poll() {
        if (pollingStopped) {
            return;
        }

        try {
            const response = await fetch(
                ui`/demo/api.php?action=state&s=${encodeURIComponent(session)}&p=${encodeURIComponent(participant)}`,
                {cache: 'no-store'}
            );

            const data = await response.json();
            if (!response.ok || data.status !== 'ok') {
                window.location.replace('/demo/?ended=1');
                return;
            }

            state = data;
            render();

            if (Number(state.stage || 0) >= 8) {
                pollingStopped = true;
                if (pollTimer !== null) {
                    clearInterval(pollTimer);
                    pollTimer = null;
                }
            }
        } catch (error) {
            if (!pollingStopped) {
                window.location.replace('/demo/?ended=1');
            }
        }
    }

    poll();
    pollTimer = setInterval(poll, 750);
    setInterval(tickTimers, 100);
})();

}</script>
<?php endif; ?>
</body>
</html>
