<?php
if (is_file(__DIR__ . '/config.php')) {
    require __DIR__ . '/config.php';
} else {
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');
    echo '<!doctype html><html lang="cs"><meta charset="utf-8"><title>Hlasuj! — nastavení</title><h1>Aplikace čeká na nastavení</h1><p>Správce musí připravit konfiguraci této instalace.</p></html>';
    exit;
}

$path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$parts = $path === '' ? array() : explode('/', $path);
$view = 'home';
$lecture = '';
$question = '';
$mode = 'live';
$subject = '';
$projectionToken = '';

if (count($parts) >= 3 && in_array($parts[0], array('q', 'r', 'test', 'test-results', 'poll', 'poll-results'), true)) {
    $view = in_array($parts[0], array('q', 'test', 'poll'), true) ? 'vote' : 'results';
    $mode = in_array($parts[0], array('test', 'test-results'), true) ? 'test' : (in_array($parts[0], array('poll','poll-results'), true) ? 'async' : 'live');
    $lecture = preg_replace('/[^a-zA-Z0-9\-_]/', '', $parts[1]);
    $question = preg_replace('/[^a-zA-Z0-9\-_]/', '', $parts[2]);
}
elseif (count($parts) >= 3 && $parts[0] === 'project') {
    $view = 'projection';
    $subject = preg_replace('/[^a-zA-Z0-9\-_]/', '', $parts[1]);
    $projectionToken = preg_replace('/[^a-zA-Z0-9]/', '', $parts[2]);
}
elseif (count($parts) >= 2 && $parts[0] === 'hall-of-fame') {
    $view = 'hall';
    $subject = preg_replace('/[^a-zA-Z0-9\-_]/', '', $parts[1]);
}
elseif (count($parts) >= 1 && $parts[0] === 'privacy') {
    $view = 'privacy';
    if (count($parts) >= 2) { $subject = preg_replace('/[^a-zA-Z0-9\-_]/', '', $parts[1]); }
    elseif (!empty($_GET['subject'])) { $subject = preg_replace('/[^a-zA-Z0-9\-_]/', '', (string) $_GET['subject']); }
}

$productName = defined('MHL_PRODUCT_NAME') ? MHL_PRODUCT_NAME : 'Hlasuj! by MiloslavHub';
$productTagline = defined('MHL_PRODUCT_TAGLINE') ? MHL_PRODUCT_TAGLINE : 'Interaktivní hlasování pro výuku';
$contactEmail = defined('MHL_CONTACT_EMAIL') ? MHL_CONTACT_EMAIL : 'miloslav@miloslavhub.cz';
$contactName = defined('MHL_CONTACT_NAME') ? MHL_CONTACT_NAME : 'Miloslav Hub';
$mainSite = defined('MHL_MAIN_SITE') ? MHL_MAIN_SITE : 'https://miloslavhub.cz';
$frontendBase = defined('MHL_FRONTEND_BASE') ? rtrim(MHL_FRONTEND_BASE, '/') : 'https://hlasuj.miloslavhub.cz';
$demoUrl = defined('MHL_DEMO_URL') ? MHL_DEMO_URL : $frontendBase . '/test/mhl-live-demo-lecture/demo-bezpecne-heslo';

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

if ($view === 'home') {
    header('Cache-Control: public, max-age=300');
} else {
    header('X-Robots-Tag: noindex, noarchive, nosnippet');
    header('Cache-Control: no-store, private');
}

$apiOrigin = '';
$apiParts = parse_url(MHL_API_BASE);
if (is_array($apiParts) && !empty($apiParts['host'])) {
    $apiOrigin = (($apiParts['scheme'] ?? 'https') . '://' . $apiParts['host'] . (!empty($apiParts['port']) ? ':' . (int) $apiParts['port'] : ''));
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <?php if ($view === 'home'): ?>
        <title><?php echo e($productName); ?> – interaktivní hlasování pro výuku</title>
        <meta name="description" content="Hlasuj! by MiloslavHub – interaktivní hlasování, kvízy a ankety pro výuku. Připojení přes QR kód bez instalace a studentských účtů. Testováno ve vysokoškolské výuce na 100+ studentech.">
        <meta name="robots" content="index,follow,max-image-preview:large">
        <meta property="og:title" content="<?php echo e($productName); ?> – interaktivní hlasování pro výuku">
        <meta property="og:description" content="Zapojte studenty pomocí QR kódů, kvízů, anket, bodování a živých výsledků na mobilu i projektoru.">
        <meta property="og:type" content="website">
        <meta property="og:url" content="<?php echo e($frontendBase); ?>/">
        <meta name="theme-color" content="#0f62d8">
        <link rel="icon" type="image/png" href="/assets/brand/hlasuj-icon-512.png">
        <link rel="canonical" href="<?php echo e($frontendBase); ?>/">
        <link rel="stylesheet" href="/assets/landing.css?v=0.7.9">
        <script type="application/ld+json">
        <?php echo json_encode(array(
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => $productName,
            'applicationCategory' => 'EducationalApplication',
            'operatingSystem' => 'Web',
            'description' => 'Hlasuj! by MiloslavHub – interaktivní hlasování, kvízy a ankety pro výuku s připojením přes QR kód.',
            'url' => $frontendBase,
            'author' => array('@type' => 'Person', 'name' => $contactName, 'url' => $mainSite),
        ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
        </script>
    <?php else: ?>
        <meta name="robots" content="noindex,nofollow,noarchive,nosnippet">
        <title><?php echo e($productName); ?></title>
        <?php if ($apiOrigin): ?>
            <link rel="dns-prefetch" href="//<?php echo e((string) ($apiParts['host'] ?? '')); ?>">
            <link rel="preconnect" href="<?php echo e($apiOrigin); ?>" crossorigin>
        <?php endif; ?>
        <link rel="stylesheet" href="/assets/app.css?v=0.8.9">
    <?php endif; ?>
</head>
<body<?php echo $view === 'home' ? ' class="landing-page"' : ''; ?>>
<?php if ($view === 'home'): ?>
    <header class="landing-header">
        <div class="landing-wrap nav-wrap">
            <a class="product-logo" href="#top" aria-label="Hlasuj! by MiloslavHub – domů">
                <img class="product-logo-image" src="/assets/brand/hlasuj-logo.png" alt="Hlasuj! by MiloslavHub">
            </a>
            <nav class="landing-nav" aria-label="Hlavní navigace">
                <a href="#funkce">Funkce</a>
                <a href="#proc">Proč Hlasuj</a>
                <a href="#jak">Jak to funguje</a>
                <a href="#ukazky">Ukázky</a>
                <a href="#pro-koho">Pro koho</a>
                <a href="#sablony">Šablony</a>
                <a href="#kontakt">Kontakt</a>
            </nav>
            <a class="nav-cta" href="<?php echo e($demoUrl); ?>">Vyzkoušet demo</a>
        </div>
    </header>

    <main id="top">
        <section class="hero">
            <div class="landing-wrap hero-grid">
                <div class="hero-copy">
                    <div class="eyebrow">Interaktivní hlasování pro výuku</div>
                    <h1>Zapojte publikum.<br><em>Bez zdržování.</em></h1>
                    <p class="hero-lead">Hlasuj! by MiloslavHub je webová aplikace pro kvízy, ankety a interaktivní otázky ve výuce, školeních a prezentacích. Účastník naskenuje QR kód, odpoví z telefonu a výsledky se mohou okamžitě zobrazit na projektoru i v jeho zařízení.</p>
                    <div class="hero-actions">
                        <a class="btn btn-primary" href="<?php echo e($demoUrl); ?>"><span aria-hidden="true">▶</span> Vyzkoušet ukázku</a>
                        <a class="btn btn-ghost" href="mailto:<?php echo e($contactEmail); ?>?subject=Hlasuj%20by%20MiloslavHub%20–%20zájem%20o%20ukázku"><span aria-hidden="true">✉</span> Kontaktovat</a>
                    </div>
                    <div class="trust-row">
                        <span>✓ Bez instalace</span>
                        <span>✓ Bez studentských účtů</span>
                        <span>✓ Testováno ve vysokoškolské výuce na 100+ studentech</span>
                    </div>
                </div>

                <div class="hero-demo" aria-label="Ukázka hlasování na mobilu a projektoru">
                    <div class="projector-card">
                        <div class="mini-top"><span class="mini-brand"><b>▮▮▮</b> Hlasuj</span><span>32 hlasů</span></div>
                        <h2>Který přístup je podle vás nejúčinnější při učení?</h2>
                        <div class="demo-bar"><span class="demo-code blue">A</span><div><b>Aktivní opakování</b><i style="--w:62%"></i></div><strong>62 %</strong></div>
                        <div class="demo-bar"><span class="demo-code green">B</span><div><b>Skupinová práce</b><i style="--w:24%"></i></div><strong>24 %</strong></div>
                        <div class="demo-bar"><span class="demo-code yellow">C</span><div><b>Čtení poznámek</b><i style="--w:10%"></i></div><strong>10 %</strong></div>
                        <div class="demo-bar"><span class="demo-code violet">D</span><div><b>Jiný přístup</b><i style="--w:4%"></i></div><strong>4 %</strong></div>
                    </div>
                    <div class="phone-card">
                        <div class="phone-speaker"></div>
                        <span class="phone-kicker">Připojte se</span>
                        <strong>Hlasujte mobilem</strong>
                        <div class="fake-qr" aria-hidden="true">
                            <span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span>
                        </div>
                        <small>QR z prezentace</small>
                        <div class="phone-url">hlasuj.miloslavhub.cz</div>
                        <button type="button" tabindex="-1">Jsem připraven</button>
                    </div>
                    <div class="hero-note">Aplikace se přizpůsobuje výuce. Ne výuka aplikaci.</div>
                </div>
            </div>
        </section>

        <section class="feature-strip" id="funkce">
            <div class="landing-wrap">
                <div class="section-intro compact">
                    <span>Co systém umí</span>
                    <h2>Interakce bez rušení výkladu</h2>
                    <p>Učitel spouští a ukončuje otázky v ovládání výuky. Studenti se připojí QR kódem a další spuštěné otázky se jim zobrazí automaticky.</p>
                </div>
                <div class="feature-grid">
                    <article><div class="feature-icon"><svg class="ui-icon-svg" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4z"/><path d="M15 14h2v2h-2zM19 14v3h-3M14 19h3M19 19h1v1"/></svg></div><h3>QR hlasování</h3><p>Každá otázka může mít vlastní trvalý QR kód vložený přímo ve snímku.</p></article>
                    <article><div class="feature-icon"><svg class="ui-icon-svg" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3.5" width="16" height="17" rx="2.5"/><path d="M8 9l2 2 4-4M8 15h8"/></svg></div><h3>Kvízy i ankety</h3><p>Správná odpověď vytvoří kvíz. Bez správné odpovědi vznikne anonymní anketa.</p></article>
                    <article><div class="feature-icon"><svg class="ui-icon-svg" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7h8a4 4 0 0 1 4 4v1"/><path d="m16 9 3 3 3-3"/><path d="M17 17H9a4 4 0 0 1-4-4v-1"/><path d="m8 15-3-3-3 3"/></svg></div><h3>Automatické navazování</h3><p>Po prvním připojení nemusí studenti skenovat každý další QR kód.</p></article>
                    <article><div class="feature-icon"><svg class="ui-icon-svg" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20V9M10 20V4M16 20v-7M22 20H2"/></svg></div><h3>Výsledky všude</h3><p>Grafy se zobrazí na projektoru i&nbsp;v telefonu, takže výsledky vidí i&nbsp;studenti vzadu.</p></article>
                    <article><div class="feature-icon"><svg class="ui-icon-svg" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3h8l-1.5 6h-5z"/><circle cx="12" cy="15" r="4"/><path d="m10.5 18.5-1 2.5 2.5-1 2.5 1-1-2.5"/></svg></div><h3>Body a pořadí</h3><p>Správnost má hlavní váhu, rychlost přidává bonus. Pořadí lze vést v&nbsp;rámci předmětu.</p></article>
                    <article><div class="feature-icon"><svg class="ui-icon-svg" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3h6M10 3v5l-5 9a2 2 0 0 0 1.8 3h10.4A2 2 0 0 0 19 17l-5-9V3"/><path d="M8 14h8"/></svg></div><h3>Testovací režim</h3><p>Vyučující si může celý průběh vyzkoušet nanečisto bez ovlivnění ostrých výsledků.</p></article>
                    <article><div class="feature-icon"><svg class="ui-icon-svg" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M8 9v11M13 13h5M13 17h3"/></svg></div><h3>WordPress správa</h3><p>Předměty, přednášky a banka otázek se spravují v&nbsp;přehledném administrátorském rozhraní.</p></article>
                    <article><div class="feature-icon"><svg class="ui-icon-svg" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a9 9 0 1 0 0 18h1.2a2.3 2.3 0 0 0 0-4.6H12a1.8 1.8 0 0 1 0-3.6h2a7 7 0 0 0-2-9.8z"/><path d="M7.5 8h.01M11 6.5h.01M6.5 12h.01"/></svg></div><h3>Vlastní branding</h3><p>Hlasuj! by MiloslavHub, neutrální vzhled nebo vlastní vizuální identita konkrétního nasazení.</p></article>
                    <article><div class="feature-icon"><svg class="ui-icon-svg" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0"/><path d="M15 7h6v5h-6zM17 9.5h2"/></svg></div><h3>Přezdívka pro celý předmět</h3><p>Student si přezdívku zvolí jednou a používá ji napříč přednáškami stejného předmětu.</p></article>
                    <article><div class="feature-icon"><svg class="ui-icon-svg" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8M12 17v4"/></svg></div><h3>Projekce bez WordPressu</h3><p>Vyučující otevře trvalý neveřejný projekční odkaz a obrazovka sama sleduje aktivní otázku.</p></article>
                    <article><div class="feature-icon"><svg class="ui-icon-svg" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18"/><circle cx="15.5" cy="15.5" r="2.5"/><path d="M15.5 14v1.7l1 .6"/></svg></div><h3>Dlouhodobé ankety</h3><p>Samostatný odkaz může sbírat názory několik dní nebo týdnů, bez odpočtu a bez blokování živé výuky.</p></article>
                    <article class="hall-feature"><div class="feature-icon"><svg class="ui-icon-svg" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 4h8v4a4 4 0 0 1-8 0z"/><path d="M8 6H5a3 3 0 0 0 3 4M16 6h3a3 3 0 0 1-3 4M12 12v4M9 20h6M10 16h4"/></svg></div><h3>Dobrovolná Síň slávy</h3><p>Účastník v&nbsp;Top N sám rozhodne, zda zveřejnit přezdívku, zůstat anonymní, nebo se nezobrazit.</p><a class="feature-more" href="<?php echo e($frontendBase); ?>/privacy#hall-of-fame">Jak funguje zveřejnění pořadí</a></article>
                </div>
            </div>
        </section>

        <section class="difference-section" id="proc">
            <div class="landing-wrap">
                <div class="section-intro">
                    <span>Jiný přístup k interaktivnímu hlasování</span>
                    <h2>Aplikace se přizpůsobuje výuce. Ne výuka aplikaci.</h2>
                    <p>Na trhu existuje řada kvalitních nástrojů pro online hlasování a interaktivní prezentace. Hlasuj! by MiloslavHub se zaměřuje především na jednoduché a plynulé zapojení publika během skutečné výuky – bez zbytečných bariér a s důrazem na rychlé pokračování ve výkladu.</p>
                </div>

                <div class="difference-grid">
                    <article>
                        <div class="difference-icon"><svg class="ui-icon-svg" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4z"/><path d="M15 14h2v2h-2zM19 14v3h-3M14 19h3M19 19h1v1"/></svg></div>
                        <h3>Rychlé připojení</h3>
                        <p>Účastník se připojí přes QR kód a běžný webový prohlížeč. Pro běžné hlasování nepotřebuje instalovat speciální aplikaci ani vytvářet studentský účet.</p>
                    </article>
                    <article>
                        <div class="difference-icon"><svg class="ui-icon-svg" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7h8a4 4 0 0 1 4 4v1"/><path d="m16 9 3 3 3-3"/><path d="M17 17H9a4 4 0 0 1-4-4v-1"/><path d="m8 15-3-3-3 3"/></svg></div>
                        <h3>Plynulý průběh</h3>
                        <p>Po prvním připojení se další otázky zobrazí, jakmile je učitel spustí. Cílem je co nejméně přerušovat přednášku nebo školení technickou obsluhou.</p>
                    </article>
                    <article>
                        <div class="difference-icon"><svg class="ui-icon-svg" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20V9M10 20V4M16 20v-7M22 20H2"/></svg></div>
                        <h3>Výsledky pro výuku</h3>
                        <p>Kvízy, ankety, grafy, body, čas odpovědi a průběžné pořadí jsou součástí jednoho prostředí pro účastníka i&nbsp;projekci.</p>
                    </article>
                    <article>
                        <div class="difference-icon"><svg class="ui-icon-svg" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M7 6.5h.01M10 6.5h.01"/></svg></div>
                        <h3>Vlastní prostředí</h3>
                        <p>Systém lze provozovat pod vlastní doménou, s&nbsp;vlastním brandingem a&nbsp;s nastavením přizpůsobeným konkrétnímu způsobu výuky.</p>
                    </article>
                </div>

                <div class="evidence-panel" aria-label="Ověření v praxi">
                    <div class="evidence-number">100+</div>
                    <div class="evidence-copy">
                        <strong>Testováno v reálné vysokoškolské výuce</strong>
                        <span>Hlasuj! by MiloslavHub bylo testováno na více než 100 studentech.</span>
                    </div>
                    <div class="evidence-tags"><span>reálná výuka</span><span>studenti</span><span>praktické ověření</span></div>
                </div>
            </div>
        </section>

        <section class="how-section" id="jak">
            <div class="landing-wrap two-col">
                <div>
                    <div class="section-intro how-intro"><span class="how-label">Jak to funguje</span><h2>Tři kroky a studenti hlasují</h2></div>
                    <ol class="steps">
                        <li><b>1</b><div><strong>Vložte QR do prezentace</strong><p>QR kód je trvalý. Prezentaci můžete použít znovu i po změně pořadí otázek.</p></div></li>
                        <li><b>2</b><div><strong>Student se jednou připojí</strong><p>Bez registrace a bez instalace. V soutěžním režimu si zvolí přezdívku.</p></div></li>
                        <li><b>3</b><div><strong>Učitel spustí otázku</strong><p>Učitel řídí začátek i konec. Po ukončení se zobrazí výsledky; anketa nemá správnou odpověď.</p></div></li>
                    </ol>
                </div>
                <div class="score-card">
                    <div class="score-label">Ukázkový výsledek</div>
                    <h3>Správně!</h3>
                    <div class="score-pair"><span>Za tuto otázku</span><strong>957 b.</strong></div>
                    <div class="score-pair emphasized"><span>Celkem v předmětu</span><strong>4 812 b.</strong></div>
                    <div class="score-pair"><span>Pořadí</span><strong>3. místo</strong></div>
                    <div class="score-time">Čas odpovědi: 4,27 s</div>
                </div>
            </div>
        </section>

        <section class="screens-section" id="ukazky">
            <div class="landing-wrap">
                <div class="section-intro screenshots-intro">
                    <span>Skutečné rozhraní aplikace</span>
                    <h2>Takto hlasování vidí studenti</h2>
                    <p>Student nepotřebuje instalovat aplikaci ani vytvářet účet. Po načtení QR kódu zadá přezdívku, odpoví a po uzavření otázky vidí výsledek, body i průběžné pořadí. Níže jsou obrazovky předchozí verze; aktualizace této galerie je součástí přípravy pilotního vydání.</p>
                </div>
                <div class="screen-showcase">
                    <article class="phone-shot-card">
                        <div class="shot-label"><b>1</b><span>Připojení</span></div>
                        <a class="device-phone" href="/assets/screenshots/mobile-join.png" target="_blank" rel="noopener" aria-label="Otevřít screenshot připojení v plné velikosti">
                            <img src="/assets/screenshots/mobile-join.png" alt="Mobilní obrazovka pro zadání přezdívky a připojení k hlasování" loading="lazy" width="390" height="844">
                        </a>
                        <h3>Jedno připojení</h3>
                        <p>Přezdívka se uloží v prohlížeči. Při dalších otázkách už student nemusí opakovat celý vstup.</p>
                    </article>
                    <article class="phone-shot-card featured-shot">
                        <div class="shot-label"><b>2</b><span>Hlasování</span></div>
                        <a class="device-phone" href="/assets/screenshots/mobile-vote.png" target="_blank" rel="noopener" aria-label="Otevřít screenshot hlasování v plné velikosti">
                            <img src="/assets/screenshots/mobile-vote.png" alt="Mobilní obrazovka živé kvízové otázky s časovým limitem" loading="lazy" width="390" height="844">
                        </a>
                        <h3>Otázka přímo v mobilu</h3>
                        <p>Student vidí otázku, odpovědi a zbývající čas. Odpověď odešle jedním klepnutím.</p>
                    </article>
                    <article class="phone-shot-card">
                        <div class="shot-label"><b>3</b><span>Výsledky</span></div>
                        <a class="device-phone results-phone" href="/assets/screenshots/mobile-results.png" target="_blank" rel="noopener" aria-label="Otevřít screenshot mobilních výsledků v plné velikosti">
                            <img src="/assets/screenshots/mobile-results.png" alt="Mobilní výsledky otázky s body, časem odpovědi a grafem" loading="lazy" width="390" height="1223">
                        </a>
                        <h3>Body a zpětná vazba</h3>
                        <p>Po uzavření otázky se zobrazí správná odpověď, čas, body a graf hlasování ostatních.</p>
                    </article>
                    <article class="phone-shot-card">
                        <div class="shot-label"><b>4</b><span>Pořadí</span></div>
                        <a class="device-phone" href="/assets/screenshots/mobile-leaderboard.png" target="_blank" rel="noopener" aria-label="Otevřít screenshot pořadí v předmětu v plné velikosti">
                            <img src="/assets/screenshots/mobile-leaderboard.png" alt="Mobilní pořadí studentů v rámci předmětu" loading="lazy" width="390" height="844">
                        </a>
                        <h3>Pořadí v předmětu</h3>
                        <p>Student vidí svou průběžnou pozici a body v rámci celého předmětu. Přezdívka je jedinečná jen v daném předmětu.</p>
                    </article>
                </div>
                <div class="projection-showcase">
                    <div class="projection-copy">
                        <span class="projection-kicker">Pro celou posluchárnu</span>
                        <h3>Stejné výsledky na projektoru</h3>
                        <p>Vyučující může výsledky promítat v&nbsp;reálném čase přes neveřejný trvalý odkaz bez přihlášení do&nbsp;WordPressu. Na&nbsp;velké obrazovce je vidět průběh hlasování, správná odpověď i&nbsp;pořadí v&nbsp;předmětu.</p>
                        <ul>
                            <li>přehledné sloupcové grafy,</li>
                            <li>počet hlasujících,</li>
                            <li>správná odpověď a bodování,</li>
                            <li>QR kód pro pozdě příchozí.</li>
                        </ul>
                    </div>
                    <a class="projection-frame" href="/assets/screenshots/projection-results.png" target="_blank" rel="noopener" aria-label="Otevřít screenshot projekce v plné velikosti">
                        <img src="/assets/screenshots/projection-results.png" alt="Projekční obrazovka výsledků živého hlasování" loading="lazy" width="1440" height="1077">
                    </a>
                </div>
            </div>
        </section>

        <section class="audience-section" id="pro-koho">
            <div class="landing-wrap">
                <div class="section-intro"><span>Pro koho je vhodný</span><h2>Od semináře po velkou posluchárnu</h2></div>
                <div class="audience-grid">
                    <article><span class="audience-icon"><svg class="ui-icon-svg" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m3 10 9-5 9 5-9 5z"/><path d="M7 12.2V16c3 2 7 2 10 0v-3.8M21 10v5"/></svg></span><h3>Vysokoškolští vyučující</h3><p>Oživení přednášek, kontrola porozumění a větší zapojení studentů.</p></article>
                    <article><span class="audience-icon"><svg class="ui-icon-svg" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M2.5 20a5.5 5.5 0 0 1 11 0M14 20a4 4 0 0 1 7.5-2"/></svg></span><h3>Školitelé a lektoři</h3><p>Interaktivní workshopy, školení a rychlá zpětná vazba od účastníků.</p></article>
                    <article><span class="audience-icon"><svg class="ui-icon-svg" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 21V8l8-4 8 4v13M2 21h20"/><path d="M8 10h2M14 10h2M8 14h2M14 14h2M10 21v-4h4v4"/></svg></span><h3>Instituce a organizace</h3><p>Interní vzdělávání, odborné akce, konference a firemní školení.</p></article>
                    <article><span class="audience-icon"><svg class="ui-icon-svg" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m13 2-8 12h6l-1 8 9-13h-6z"/></svg></span><h3>Aktivní výuka</h3><p>Všude tam, kde chcete místo pasivního poslechu zapojit publikum.</p></article>
                </div>
            </div>
        </section>

        <section class="templates-section" id="sablony">
            <div class="landing-wrap">
                <div class="section-intro">
                    <span>Vzhled a identita</span>
                    <h2>Jedno jádro, různé identity</h2>
                    <p>Stejný hlasovací systém lze použít pod osobní značkou autora, v&nbsp;institucionálním vzhledu, neutrálně nebo pod vlastní značkou organizace.</p>
                </div>
                <div class="template-grid">
                    <button type="button" class="template-card template-card-button hub" data-template-preview="hub" data-template-title="Hlasuj! by MiloslavHub" data-template-description="Osobní produktová identita autora pro výuku, školení a prezentace.">
                        <div class="template-preview"><b>Hlasuj</b><small>by MiloslavHub</small><i></i></div>
                        <h3>Hlasuj! by MiloslavHub</h3>
                        <p>Osobní produktová identita autora pro výuku, školení a prezentace.</p>
                        <span class="template-action">Zobrazit ukázku</span>
                    </button>
                    <button type="button" class="template-card template-card-button fes" data-template-preview="institution" data-template-title="Institucionální" data-template-description="Vzhled lze přizpůsobit škole, fakultě, firmě nebo jiné organizaci.">
                        <div class="template-preview"><b>Instituce</b><small>Vlastní identita</small><i></i></div>
                        <h3>Institucionální</h3>
                        <p>Vzhled lze přizpůsobit škole, fakultě, firmě nebo jiné organizaci.</p>
                        <span class="template-action">Zobrazit ukázku</span>
                    </button>
                    <button type="button" class="template-card template-card-button neutral" data-template-preview="neutral" data-template-title="Neutrální" data-template-description="Čistý vzhled bez osobní nebo institucionální značky.">
                        <div class="template-preview"><b>Hlasuj</b><small>Interaktivní výuka</small><i></i></div>
                        <h3>Neutrální</h3>
                        <p>Čistý vzhled bez osobní nebo institucionální značky.</p>
                        <span class="template-action">Zobrazit ukázku</span>
                    </button>
                    <button type="button" class="template-card template-card-button custom" data-template-preview="custom" data-template-title="Vlastní značka" data-template-description="Pro školy, lektory a organizace s vlastním logem, barvami a doménou.">
                        <div class="template-preview"><b>Vaše značka</b><small>Vlastní logo a barvy</small><i></i></div>
                        <h3>Vlastní</h3>
                        <p>Pro školy, lektory a organizace s vlastním logem, barvami a doménou.</p>
                        <span class="template-action">Zobrazit ukázku</span>
                    </button>
                </div>
            </div>
        </section>

        <section class="contact-section" id="kontakt">
            <div class="landing-wrap contact-card">
                <div>
                    <span class="contact-kicker">Zájem o ukázku nebo nasazení?</span>
                    <h2>Rád systém předvedu a pomohu s nasazením.</h2>
                    <p>Software může běžet na vlastním webhostingu. Provozujete jej pod svou doménou a spravujete přes WordPress.</p>
                </div>
                <div class="contact-actions">
                    <a class="btn btn-light" href="mailto:<?php echo e($contactEmail); ?>?subject=Hlasuj%20by%20MiloslavHub%20–%20zájem%20o%20nasazení">✉ <?php echo e($contactEmail); ?></a>
                    <a class="contact-link" href="<?php echo e($mainSite); ?>"><?php echo e($mainSite); ?></a>
                    <span><?php echo e($contactName); ?></span>
                </div>
            </div>
        </section>

        <div class="template-modal" id="template-modal" hidden aria-hidden="true">
            <div class="template-modal-backdrop" data-template-close></div>
            <section class="template-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="template-modal-title">
                <button class="template-modal-close" type="button" data-template-close aria-label="Zavřít ukázku">×</button>
                <div class="template-modal-copy">
                    <span>Ukázka identity</span>
                    <h2 id="template-modal-title">Hlasuj! by MiloslavHub</h2>
                    <p id="template-modal-description"></p>
                </div>
                <div class="identity-preview identity-hub" id="identity-preview">
                    <div class="identity-preview-head">
                        <strong id="identity-preview-brand">Hlasuj</strong>
                        <small id="identity-preview-subtitle">by MiloslavHub</small>
                    </div>
                    <div class="identity-question">Která možnost podle vás nejlépe odpovídá?</div>
                    <div class="identity-option"><span>A</span><i style="--w:72%"></i><b>72 %</b></div>
                    <div class="identity-option"><span>B</span><i style="--w:18%"></i><b>18 %</b></div>
                    <div class="identity-option"><span>C</span><i style="--w:10%"></i><b>10 %</b></div>
                    <div class="identity-preview-foot">Ukázka vzhledu hlasování a výsledků</div>
                </div>
            </section>
        </div>
    </main>


    <script>
    (() => {
        const modal = document.getElementById('template-modal');
        if (!modal) return;
        const dialog = modal.querySelector('.template-modal-dialog');
        const title = document.getElementById('template-modal-title');
        const desc = document.getElementById('template-modal-description');
        const preview = document.getElementById('identity-preview');
        const brand = document.getElementById('identity-preview-brand');
        const subtitle = document.getElementById('identity-preview-subtitle');
        let lastTrigger = null;

        const themes = {
            hub: { cls: 'identity-hub', brand: 'Hlasuj', subtitle: 'by MiloslavHub' },
            institution: { cls: 'identity-institution', brand: 'Instituce', subtitle: 'Vlastní identita' },
            neutral: { cls: 'identity-neutral', brand: 'Hlasuj', subtitle: 'Interaktivní výuka' },
            custom: { cls: 'identity-custom', brand: 'Vaše značka', subtitle: 'Vlastní logo a barvy' }
        };

        function openModal(trigger) {
            const key = trigger.dataset.templatePreview || 'hub';
            const theme = themes[key] || themes.hub;
            lastTrigger = trigger;
            title.textContent = trigger.dataset.templateTitle || '';
            desc.textContent = trigger.dataset.templateDescription || '';
            preview.className = 'identity-preview ' + theme.cls;
            brand.textContent = theme.brand;
            subtitle.textContent = theme.subtitle;
            modal.hidden = false;
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('modal-open');
            setTimeout(() => dialog.focus?.(), 0);
        }

        function closeModal() {
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
            lastTrigger?.focus();
        }

        document.querySelectorAll('[data-template-preview]').forEach(el => {
            el.addEventListener('click', () => openModal(el));
        });
        modal.querySelectorAll('[data-template-close]').forEach(el => {
            el.addEventListener('click', closeModal);
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && !modal.hidden) closeModal();
        });
    })();
    </script>
    <footer class="landing-footer">
        <div class="landing-wrap footer-wrap">
            <span>© <?php echo date('Y'); ?> <?php echo e($contactName); ?> · <?php echo e($productName); ?></span>
            <span class="footer-links"><a href="<?php echo e($frontendBase); ?>/privacy">Soukromí a data</a> · <a href="<?php echo e($frontendBase); ?>/privacy#hall-of-fame">Pravidla Síně slávy</a> · <a href="<?php echo e($mainSite); ?>">miloslavhub.cz</a></span>
        </div>
    </footer>
<?php else: ?>
    <main id="mhl-app"
          data-view="<?php echo e($view); ?>"
          data-explanation-label="<?php echo e('Vysvětlení správné odpovědi'); ?>"
          data-mode="<?php echo e($mode); ?>"
          data-lecture="<?php echo e($lecture); ?>"
          data-question="<?php echo e($question); ?>"
          data-subject="<?php echo e($subject); ?>" data-projection-token="<?php echo e($projectionToken); ?>"
          data-api="<?php echo e(rtrim(MHL_API_BASE, '/')); ?>"
          data-brand="<?php echo e(MHL_BRAND_NAME); ?>"
          data-main-site="<?php echo e(MHL_MAIN_SITE); ?>"
          data-frontend="<?php echo e(rtrim(MHL_FRONTEND_BASE, '/')); ?>">
        <section class="shell" aria-live="polite">
            <header class="brandbar">
                <a class="brand" id="site-brand">Hlasuj</a>
                <span class="brand-sub" id="site-brand-sub">by MiloslavHub · Interaktivní hlasování pro výuku</span>
            </header>
            <div id="screen" class="screen"><div class="loading">Načítám…</div></div>
        </section>
    </main>
    <script src="/assets/vendor/qrcode.min.js" defer></script>
    <script src="/assets/app.js?v=0.8.9" defer></script>
<?php endif; ?>
</body>
</html>
