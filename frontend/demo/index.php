<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

mhl_demo_no_cache_headers();

try {
    $session = mhl_demo_create_session();
} catch (Throwable $e) {
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><title>Demo – chyba</title>';
    echo mhl_ui_text('<p style="font-family:sans-serif;padding:40px">Demo nelze spustit: ')
        . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
        . '</p>';
    exit;
}

$sessionId = (string)$session['id'];
$presenterToken = (string)$session['presenter_token'];

$joinUrl = '/demo/mobile.php?s=' . rawurlencode($sessionId);
?>
<!doctype html>
<html lang="<?php echo mhl_ui_language(); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title><?php echo mhl_ui_html('Interaktivní demo – Hlasuj! by MiloslavHub'); ?></title>
    <link rel="stylesheet" href="/demo/assets/demo.css?v=0.8.9">
    <?php mhl_ui_bootstrap(); ?>
</head>
<body class="demo-host">
<div class="demo-shell">
    <header class="demo-topbar">
        <a class="demo-brand demo-brand-logo" href="/"><img src="/demo/assets/hlasuj-logo.png" alt="Hlasuj! by MiloslavHub"></a>
        <div class="demo-status">
            <span id="participant-pill"><?php echo mhl_ui_html('0 připojených'); ?></span>
            <span id="expiry-pill">15:00</span>
        </div>
    </header>

    <main class="demo-stage">
        <section id="host-screen" class="host-screen" aria-live="polite"></section>
    </main>

    <footer class="demo-controls">
        <div class="demo-control-left">
            <a class="demo-link" href="/"><?php echo mhl_ui_html('← Domů'); ?></a>
            <a class="demo-link" target="_blank" rel="noopener"
               href="<?php echo htmlspecialchars($joinUrl, ENT_QUOTES, 'UTF-8'); ?>"><?php echo mhl_ui_html('
               Vyzkoušet na tomto zařízení
            '); ?></a>
        </div>
        <div class="demo-control-right">
            <span id="step-label"><?php echo mhl_ui_html('Úvod'); ?></span>
            <button id="next-button" class="demo-primary" type="button"><?php echo mhl_ui_html('Začít první otázku →'); ?></button>
        </div>
    </footer>
</div>

<script src="/assets/vendor/qrcode.min.js"></script>
<script>{
const tr=globalThis.MHLUI?.text||(v=>v),ui=globalThis.MHLUI?.html||((p,...v)=>p.reduce((o,s,i)=>o+s+(i<v.length?v[i]:''),''));
const num=globalThis.MHLUI?.number||((v,d=0)=>Number(v).toLocaleString(document.documentElement.lang==='en'?'en-US':'cs-CZ',{minimumFractionDigits:d,maximumFractionDigits:d}));

(() => {
    'use strict';

    const session = <?php echo json_encode($sessionId, JSON_UNESCAPED_SLASHES); ?>;
    const presenterToken = <?php echo json_encode($presenterToken, JSON_UNESCAPED_SLASHES); ?>;
    const joinUrl = new URL(<?php echo json_encode($joinUrl, JSON_UNESCAPED_SLASHES); ?>, location.origin).href;
    const returnedFromEndedSession =
        <?php echo !empty($_GET['ended']) ? 'true' : 'false'; ?>;

    const isMobileDemoDevice =
        window.matchMedia('(max-width: 900px)').matches &&
        (
            window.matchMedia('(pointer: coarse)').matches ||
            /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent)
        );

    const screen = document.getElementById('host-screen');
    const nextButton = document.getElementById('next-button');
    const stepLabel = document.getElementById('step-label');
    const participantPill = document.getElementById('participant-pill');
    const expiryPill = document.getElementById('expiry-pill');
    const demoControls = document.querySelector('.demo-controls');

    let state = null;
    let busy = false;

    const esc = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    function fakeQrMarkup() {
        const size = 15;
        const cells = [];

        const finderValue = (r, c, top, left) => {
            const rr = r - top;
            const cc = c - left;
            if (rr < 0 || cc < 0 || rr >= 5 || cc >= 5) return null;
            if (rr === 0 || rr === 4 || cc === 0 || cc === 4) return true;
            if (rr === 1 || rr === 3 || cc === 1 || cc === 3) return false;
            return true;
        };

        for (let r = 0; r < size; r++) {
            for (let c = 0; c < size; c++) {
                let on = null;

                for (const [top, left] of [[0,0], [0,10], [10,0]]) {
                    const v = finderValue(r, c, top, left);
                    if (v !== null) {
                        on = v;
                        break;
                    }
                }

                if (on === null) {
                    on = ((r * 7 + c * 11 + r * c * 3 + c * c) % 13) < 5;
                    if (r >= 5 && r <= 9 && c >= 4 && c <= 10) {
                        on = ((r + c) % 3) === 0;
                    }
                }

                cells.push(ui`<i class="${on ? 'on' : ''}"></i>`);
            }
        }

        return ui`<div class="fake-qr-grid" aria-hidden="true">${cells.join('')}</div>`;
    }

    function renderRealQr(target) {
        if (!target) return;

        target.innerHTML = '';
        if (window.QRCode) {
            new QRCode(target, {
                text: joinUrl,
                width: 240,
                height: 240,
                correctLevel: QRCode.CorrectLevel.M
            });
        } else {
            target.innerHTML = ui`
                <div class="qr-fallback">
                    QR knihovna se nenačetla.<br>
                    <a href="${esc(joinUrl)}">Otevřít připojení přímo</a>
                </div>
            `;
        }
    }

    function bindMobileIntroActions() {
        const direct = document.getElementById('mobile-direct-start');
        const showQr = document.getElementById('show-real-qr');
        const back = document.getElementById('hide-real-qr');
        const directPanel = document.getElementById('mobile-direct-panel');
        const qrPanel = document.getElementById('mobile-real-qr-panel');

        if (direct) {
            direct.addEventListener('click', () => {
                window.location.href = joinUrl;
            });
        }

        if (showQr && directPanel && qrPanel) {
            showQr.addEventListener('click', () => {
                directPanel.hidden = true;
                qrPanel.hidden = false;
                renderRealQr(document.getElementById('mobile-real-qr'));
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        }

        if (back && directPanel && qrPanel) {
            back.addEventListener('click', () => {
                qrPanel.hidden = true;
                directPanel.hidden = false;
            });
        }
    }

    function optionRows(question, results, correct) {
        const total = results
            ? Object.values(results).reduce((sum, item) => sum + Number(item.count || 0), 0)
            : 0;

        return Object.entries(question.options).map(([key, value]) => {
            const result = results?.[key];
            const percent = result ? Number(result.percent || 0) : 0;
            const isCorrect = correct && key === correct;

            const count = result ? Number(result.count || 0) : 0;

            return ui`
                <div class="result-row vote-chart-row ${isCorrect ? 'correct' : ''}">
                    <div class="vote-chart-head">
                        <span class="option-key">${esc(key)}</span>
                        <strong>${esc(value)}</strong>
                        ${results ? ui`
                            <span class="vote-chart-stats">
                                <b>${count}</b> ${count === 1 ? tr('hlas') : (count >= 2 && count <= 4 ? tr('hlasy') : tr('hlasů'))}
                                <em>${num(percent, Number.isInteger(percent) ? 0 : 1)} %</em>
                            </span>
                        ` : ''}
                    </div>
                    ${results ? ui`
                        <div class="vote-chart-track" role="img" aria-label="${esc(value)}: ${num(percent, Number.isInteger(percent) ? 0 : 1)} procent">
                            <i style="width:${Math.max(0, Math.min(100, percent))}%"></i>
                        </div>
                    ` : ''}
                </div>
            `;
        }).join('');
    }

    function finalLeaderboard(limit = 10) {
        if (!Array.isArray(state.leaderboard) || !state.leaderboard.length) {
            return tr('<div class="host-demo-note">Žebříček ještě není dostupný.</div>');
        }

        return ui`
            <div class="host-final-board">
                <strong>Fiktivní Síň slávy</strong>
                ${state.leaderboard.slice(0, limit).map(row => ui`
                    <div class="host-rank-row ${row.is_me ? 'me' : ''}">
                        <b>${Number(row.rank)}.</b>
                        <span>${esc(row.nickname)}</span>
                        <em>${Number(row.score)} b.</em>
                    </div>
                `).join('')}
            </div>
        `;
    }

    function renderIntro() {
        if (isMobileDemoDevice) {
            screen.innerHTML = ui`
                <div class="intro-grid mobile-demo-intro">
                    <div class="intro-copy mobile-intro-copy">
                        <span class="demo-kicker">INTERAKTIVNÍ PREZENTAČNÍ DEMO</span>
                        <h1>Vyzkoušejte si hlasování přímo na tomto telefonu.</h1>
                        <p>
                            Na mobilu není potřeba skenovat QR kód.
                            Klepněte na kartu a připojíte se rovnou jako student do této demo relace.
                        </p>
                        ${returnedFromEndedSession ? ui`
                            <div class="demo-return-note">
                                Předchozí ukázka skončila. Níže můžete rovnou spustit novou.
                            </div>
                        ` : ''}
                        <div class="demo-points mobile-demo-points">
                            <span>1. Klepněte na QR kartu</span>
                            <span>2. Zadejte přezdívku</span>
                            <span>3. První otázka se spustí sama</span>
                        </div>
                        <p class="host-demo-note">Ukázka obsahuje 17 fiktivních respondentů. Připojit se mohou i další skuteční účastníci.</p>
                        <div class="demo-tested-note">Testováno ve vysokoškolské výuce na 100+ studentech.</div>

                    </div>

                    <div class="mobile-demo-entry">
                        <div id="mobile-direct-panel">
                            <button id="mobile-direct-start" class="mobile-direct-qr-card" type="button">
                                <div class="mobile-direct-qr-art">
                                    ${fakeQrMarkup()}
                                    <span class="mobile-direct-overlay">
                                        <b>KLEPNĚTE SEM</b>
                                        <small>spustit demo</small>
                                    </span>
                                </div>
                                <strong>Vyzkoušet demo na tomto telefonu</strong>
                                <span>Klepněte a připojte se jako student.</span>
                            </button>

                            <button id="show-real-qr" class="mobile-real-qr-toggle" type="button">
                                Mám druhý telefon – zobrazit skutečný QR kód
                            </button>
                        </div>

                        <div id="mobile-real-qr-panel" class="mobile-real-qr-panel" hidden>
                            <div id="mobile-real-qr"></div>
                            <strong>Naskenujte druhým telefonem</strong>
                            <p>Tento QR kód připojí druhé zařízení do stejné demo relace.</p>
                            <button id="hide-real-qr" class="mobile-real-qr-toggle" type="button">
                                ← Zpět na spuštění na tomto telefonu
                            </button>
                        </div>

                        <small class="mobile-demo-expiry">
                            Relace je dočasná a za 15 minut automaticky zanikne.
                        </small>
                    </div>
                </div>
            `;

            bindMobileIntroActions();
            return;
        }

        screen.innerHTML = ui`
            <div class="intro-grid">
                <div class="intro-copy">
                    <span class="demo-kicker">INTERAKTIVNÍ PREZENTAČNÍ DEMO</span>
                    <h1>Vyzkoušejte si hlasování<br>tak, jak ho uvidí studenti.</h1>
                    <p>
                        Naskenujte QR kód mobilem a zadejte přezdívku.
                        První otázka i další kroky probíhají automaticky. Na této obrazovce sledujete výsledky.
                    </p>
                    <div class="demo-points">
                        <span>1. Připojte mobil</span>
                        <span>2. První otázka se spustí sama</span>
                        <span>3. Výsledky a další otázky navazují samy</span>
                    </div>
                        <p class="host-demo-note">Ukázka obsahuje 17 fiktivních respondentů. Připojit se mohou i další skuteční účastníci.</p>
                        <div class="demo-tested-note">Testováno ve vysokoškolské výuce na 100+ studentech.</div>

                </div>
                <div class="join-card">
                    <div id="qr"></div>
                    <strong>Naskenujte QR kód mobilem</strong>
                    <p class="join-hint">Telefon se připojí k této demo relaci.</p>

                    <a class="desktop-join-fallback" href="${esc(joinUrl)}" target="_blank" rel="noopener">
                        QR nejde načíst? Otevřít připojení přímo
                    </a>

                    <small>Relace je dočasná a za 15 minut automaticky zanikne.</small>
                </div>
            </div>
        `;

        renderRealQr(document.getElementById('qr'));
    }

    function renderQuestion() {
        const q = state.question;
        const nowSeconds = Date.now() / 1000;
        const startsAt = Number(state.question_starts_at || 0);

        if (startsAt > nowSeconds) {
            screen.innerHTML = ui`
                <div class="finish-card host-prestart-card">
                    <span class="demo-kicker">PŘIPRAVTE SE</span>
                    <h1>Otázka začne za <span data-prestart-countdown>2</span>…</h1>
                    <p>Všichni mají stejný okamžik START. Od něj se měří čas odpovědi.</p>
                </div>
            `;
            tickTimers();
            return;
        }

        screen.innerHTML = ui`
            <div class="question-layout">
                <div class="question-main">
                    <div class="host-question-head">
                        <span class="demo-kicker">${q.kind === 'poll' ? tr('ANONYMNÍ ANKETA') : tr('KVÍZ')}</span>
                        <div class="host-countdown"><span>Zbývá</span><strong data-question-countdown>—</strong></div>
                    </div>
                    <h1>${esc(q.title)}</h1>
                    <p class="question-sub">${esc(q.subtitle)}</p>
                    <div class="question-time-track host-time-track"><i data-question-progress></i></div>
                    <div class="option-list">${optionRows(q, null, null)}</div>
                </div>
                <aside class="live-card">
                    <span>Odpovědělo</span>
                    <strong>${Number(state.answer_count || 0)}</strong>
                    <small>z ${Number(state.participant_count || 0)} připojených</small>
                    <div class="pulse-dot"><i></i> Hlasování je otevřené</div>
                </aside>
            </div>
        `;
    }

    function renderResults() {
        const q = state.question;

        const hostRanking = q.kind === 'quiz' && Array.isArray(state.leaderboard)
            ? ui`
                <div class="host-ranking">
                    <strong>Průběžné pořadí</strong>
                    ${state.leaderboard.slice(0, 5).map(row => ui`
                        <div class="host-rank-row ${row.is_me ? 'me' : ''}">
                            <b>${Number(row.rank)}.</b>
                            <span>${esc(row.nickname)}</span>
                            <em>${Number(row.score)} b. · ${Number(row.response_time_ms || 0) > 0 ? ui`${num(Number(row.response_time_ms) / 1000,1)} s` : '—'}</em>
                        </div>
                    `).join('')}
                    <small>17 účastníků je fiktivních; skutečný návštěvník je zvýrazněn.</small>
                </div>
            `
            : '';

        screen.innerHTML = ui`
            <div class="question-layout results-layout">
                <div class="question-main">
                    <div class="host-question-head">
                        <span class="demo-kicker">${q.kind === 'poll' ? tr('VÝSLEDKY ANKETY') : tr('VÝSLEDKY KVÍZU')}</span>
                        <div class="host-countdown result"><span>${Number(state.stage) === 6 ? tr('Síň slávy za') : tr('Další otázka za')}</span><strong data-result-countdown>—</strong></div>
                    </div>
                    <h1>${esc(q.title)}</h1>
                    <div class="option-list">
                        ${optionRows(q, state.results, state.correct)}
                    </div>
                    ${state.explanation ? ui`<p class="explanation">${esc(state.explanation)}</p>` : ''}
                </div>
                <aside class="live-card result-summary">
                    <span>Celkem hlasů</span>
                    <strong>${Number(state.answer_count || 0)}</strong>
                    <small>${q.kind === 'poll' ? tr('bez správné odpovědi') : tr('správná odpověď je zvýrazněna')}</small>
                    <div class="host-demo-note">17 respondentů je fiktivních – pouze pro ukázku.</div>
                    ${hostRanking}
                </aside>
            </div>
        `;
    }

    function renderHall() {
        const hallSummary = state.hall_summary || {};
        screen.innerHTML = ui`
            <div class="finish-card host-hall-card polished-host-hall">
                <span class="demo-kicker">FIKTIVNÍ SÍŇ SLÁVY</span>
                <h1>Na mobilu teď probíhá<br>závěrečná volba.</h1>
                <p>
                    Účastník vidí finální pořadí a volí, zda chce být v Síni slávy
                    zveřejněn pod přezdívkou, anonymně, nebo vůbec.
                </p>
                ${finalLeaderboard(10)}
                <div class="host-hall-summary">
                    <span>Pod přezdívkou: <b>${Number(hallSummary.nickname || 0)}</b></span>
                    <span>Anonymně: <b>${Number(hallSummary.anonymous || 0)}</b></span>
                    <span>Bez zápisu: <b>${Number(hallSummary.skip || 0)}</b></span>
                </div>
                <div class="host-demo-note large">
                    Ukázka obsahuje 17 fiktivních respondentů a připojené skutečné účastníky.
                </div>
            </div>
        `;
    }

    function renderFinish() {
        const hallSummary = state.hall_summary || {};

        let hallChoiceText = tr('Volba Síně slávy nebyla dokončena.');
        if (Number(hallSummary.nickname || 0) > 0) {
            hallChoiceText = tr('Zvolený způsob zápisu: pod přezdívkou.');
        } else if (Number(hallSummary.anonymous || 0) > 0) {
            hallChoiceText = tr('Zvolený způsob zápisu: anonymně.');
        } else if (Number(hallSummary.skip || 0) > 0) {
            hallChoiceText = tr('Účastník zvolil, že se do Síně slávy nezapíše.');
        }

        screen.innerHTML = ui`
            <div class="finish-card polished-host-finish">
                <span class="demo-kicker">HOTOVO</span>
                <h1>Takto může vypadat<br>interaktivní část přednášky.</h1>
                <p>
                    Telefon zůstal připojený, otázky i výsledky se střídaly automaticky
                    a na závěr se zobrazila i fiktivní Síň slávy.
                </p>

                ${finalLeaderboard(10)}

                <div style="max-width:680px;margin:18px auto 0;padding:13px 16px;border-radius:14px;background:#f3f7fc;color:#425a78;font-weight:750;">
                    ${esc(hallChoiceText)}
                </div>

                <div class="finish-actions" style="display:flex;justify-content:center;gap:12px;margin-top:28px;flex-wrap:wrap;">
                    <button
                        type="button"
                        onclick="window.location.href='/demo/'"
                        style="min-width:220px;min-height:52px;padding:14px 20px;border:0;border-radius:12px;background:#0f62d8;color:#ffffff;-webkit-text-fill-color:#ffffff;font-size:16px;font-weight:850;cursor:pointer;box-shadow:0 10px 24px rgba(15,98,216,.18);">
                        Spustit nové demo
                    </button>
                    <button
                        type="button"
                        onclick="window.location.href='/'"
                        style="min-width:160px;min-height:52px;padding:14px 20px;border:0;border-radius:12px;background:#eef3f9;color:#0d2242;-webkit-text-fill-color:#0d2242;font-size:16px;font-weight:850;cursor:pointer;">
                        Zpět na web
                    </button>
                </div>
            </div>
        `;
    }

    function updateControls() {
        const stage = Number(state.stage || 0);
        const labels = [
            tr('Úvod'),
            tr('Otázka 1'),
            tr('Výsledky 1'),
            tr('Otázka 2'),
            tr('Výsledky 2'),
            tr('Anketa'),
            tr('Výsledky ankety'),
            tr('Síň slávy'),
            tr('Dokončeno')
        ];

        stepLabel.textContent = labels[stage] || 'Demo';

        if (demoControls) {
            demoControls.style.display =
                stage === 8 || (isMobileDemoDevice && stage === 0)
                    ? 'none'
                    : '';
        }

        // Demo je plně automatické. Spodní akční tlačítko je redundantní
        // a v některých kombinacích cache/stylů se vykreslovalo jako prázdný obdélník.
        if (nextButton) {
            nextButton.style.display = 'none';
        }

        if (stage === 0) {
            nextButton.textContent = tr('Čekám na připojení mobilu…');
            nextButton.disabled = true;
            nextButton.onclick = null;
        } else if ([1,3,5,7].includes(stage)) {
            nextButton.textContent = stage === 7
                ? tr('Dokončete volbu na mobilu')
                : tr('Odpovězte na mobilu');
            nextButton.disabled = true;
            nextButton.onclick = null;
        } else if ([2,4,6].includes(stage)) {
            const seconds = state.auto_advance_at
                ? Math.max(0, Number(state.auto_advance_at) - Math.floor(Date.now()/1000))
                : 0;
            nextButton.textContent = stage === 6
                ? ui`Žebříček za ${seconds} s`
                : ui`Další otázka za ${seconds} s`;
            nextButton.disabled = true;
            nextButton.onclick = null;
        } else {
            nextButton.textContent = tr('Spustit nové demo');
            nextButton.disabled = false;
            nextButton.onclick = () => { window.location.href = '/demo/'; };
        }
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
            el.textContent = deadline > 0 ? formatTenths(deadline - now) : '0,0 s';
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

        participantPill.textContent = Number(state.stage || 0) > 0
            ? ui`${Number(state.real_participant_count || 0)} skutečný + ${Number(state.synthetic_participant_count || 0)} demo`
            : ui`${Number(state.real_participant_count || 0)} připojených`;

        const sec = Math.max(0, Number(state.seconds_left || 0));
        const mm = String(Math.floor(sec / 60)).padStart(2, '0');
        const ss = String(sec % 60).padStart(2, '0');
        expiryPill.textContent = ui`${mm}:${ss}`;

        if (state.stage === 0) renderIntro();
        else if ([1, 3, 5].includes(Number(state.stage))) renderQuestion();
        else if ([2, 4, 6].includes(Number(state.stage))) renderResults();
        else if (Number(state.stage) === 7) renderHall();
        else renderFinish();

        updateControls();
        tickTimers();
    }

    async function fetchState() {
        try {
            const response = await fetch(
                ui`/demo/api.php?action=state&s=${encodeURIComponent(session)}`,
                { cache: 'no-store' }
            );
            const data = await response.json();

            if (!response.ok || data.status !== 'ok') {
                throw new Error(data.message || data.error || tr('Demo se nepodařilo načíst.'));
            }

            state = data;
            render();
        } catch (error) {
            screen.innerHTML = ui`
                <div class="finish-card error-card">
                    <h1>Demo se nepodařilo načíst.</h1>
                    <p>${esc(error.message)}</p>
                    <a class="demo-primary anchor-button error-restart" href="/demo/">Spustit nové demo</a>
                </div>
            `;
        }
    }

    async function nextStage() {
        if (busy) return;
        busy = true;
        nextButton.disabled = true;

        try {
            const body = new URLSearchParams({
                action: 'next',
                s: session,
                pt: presenterToken
            });

            const response = await fetch('/demo/api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
                body
            });
            const data = await response.json();

            if (!response.ok || data.status !== 'ok') {
                throw new Error(data.message || tr('Přechod se nepodařil.'));
            }

            state = data;
            render();
        } catch (error) {
            alert(error.message);
        } finally {
            busy = false;
            nextButton.disabled = false;
        }
    }

    fetchState();
    setInterval(fetchState, 750);
    setInterval(tickTimers, 100);
})();

}</script>
</body>
</html>
