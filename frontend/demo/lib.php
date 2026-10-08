<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/i18n.php';

const MHL_DEMO_TTL = 900; // 15 minut
const MHL_DEMO_PRESTART_SECONDS = 2; // společný férový start
const MHL_DEMO_QUESTION_SECONDS = 10; // krátké soutěžní hlasování
const MHL_DEMO_RESULT_SECONDS = 10; // čas na přečtení výsledků

function mhl_demo_no_cache_headers(): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('Referrer-Policy: same-origin');
}

function mhl_demo_storage_dir(): string
{
    $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'miloslavhub-live-demo';

    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }

    if (!is_dir($dir) || !is_writable($dir)) {
        throw new RuntimeException(mhl_ui_text('Úložiště dema není zapisovatelné.'));
    }

    return $dir;
}

function mhl_demo_questions(): array
{
    $questions = [
        1 => [
            'kind' => 'quiz',
            'title' => 'Které heslo je nejbezpečnější?',
            'subtitle' => 'Vyberte jednu odpověď.',
            'options' => [
                'A' => '12345678',
                'B' => 'Pardubice2026',
                'C' => 'xP7!qM2#vL',
                'D' => 'heslo123',
            ],
            'correct' => 'C',
            'explanation' => 'Varianta C je dlouhá, náhodná a kombinuje různé typy znaků.',
        ],
        3 => [
            'kind' => 'quiz',
            'title' => 'Co znamená zkratka VPN?',
            'subtitle' => 'Druhá otázka ukazuje, že telefon zůstává připojený.',
            'options' => [
                'A' => 'Virtual Private Network',
                'B' => 'Verified Public Network',
                'C' => 'Virtual Protected Node',
                'D' => 'Variable Private Network',
            ],
            'correct' => 'A',
            'explanation' => 'VPN znamená Virtual Private Network.',
        ],
        5 => [
            'kind' => 'poll',
            'title' => 'Používáte někdy veřejnou Wi‑Fi?',
            'subtitle' => 'Anketa nemá správnou odpověď ani bodování.',
            'options' => [
                'A' => 'Ano, často',
                'B' => 'Občas',
                'C' => 'Výjimečně',
                'D' => 'Nikdy',
            ],
            'correct' => null,
            'explanation' => null,
        ],
    ];
    if (mhl_ui_language()==='en') {
        // Owned demo examples have explicit language variants; teacher content is never translated.
        $questions[1]['title']='Which password is the safest?';
        $questions[1]['subtitle']='Choose one answer.';
        $questions[1]['options']['D']='password123';
        $questions[1]['explanation']='Option C is long, random and combines different types of characters.';
        $questions[3]['title']='What does VPN stand for?';
        $questions[3]['subtitle']='The second question shows that your phone stays connected.';
        $questions[3]['explanation']='VPN stands for Virtual Private Network.';
        $questions[5]['title']='Do you ever use public Wi-Fi?';
        $questions[5]['subtitle']='A poll has no correct answer or scoring.';
        $questions[5]['options']=array('A'=>'Yes, often','B'=>'Sometimes','C'=>'Rarely','D'=>'Never');
    }
    return $questions;
}

function mhl_demo_session_path(string $sessionId): string
{
    if (!preg_match('/^[a-f0-9]{32}$/', $sessionId)) {
        throw new RuntimeException(mhl_ui_text('Neplatný identifikátor relace.'));
    }
    return mhl_demo_storage_dir() . DIRECTORY_SEPARATOR . $sessionId . '.json';
}

function mhl_demo_lock_path(string $sessionId): string
{
    if (!preg_match('/^[a-f0-9]{32}$/', $sessionId)) {
        throw new RuntimeException(mhl_ui_text('Neplatný identifikátor relace.'));
    }
    return mhl_demo_storage_dir() . DIRECTORY_SEPARATOR . $sessionId . '.lock';
}

function mhl_demo_atomic_write(string $path, string $json): void
{
    $tmp = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
    if (file_put_contents($tmp, $json) === false) {
        throw new RuntimeException(mhl_ui_text('Demo relaci se nepodařilo uložit.'));
    }
    @chmod($tmp, 0600);
    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException(mhl_ui_text('Demo relaci se nepodařilo bezpečně uložit.'));
    }
}

function mhl_demo_cleanup(): void
{
    $dir = mhl_demo_storage_dir();
    $now = time();
    foreach (glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $file) {
        $sessionId = basename($file, '.json');
        if (!preg_match('/^[a-f0-9]{32}$/', $sessionId)) continue;
        $lockPath = mhl_demo_lock_path($sessionId);
        $lock = @fopen($lockPath, 'c+');
        if (!$lock) continue;
        try {
            if (!flock($lock, LOCK_EX | LOCK_NB)) continue;
            $raw = @file_get_contents($file);
            $data = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($data)) {
                if ((int)($data['expires_at'] ?? 0) < $now) {
                    @unlink($file);
                    @unlink($lockPath);
                }
                continue;
            }
            $mtime = @filemtime($file);
            if ($mtime !== false && $mtime < ($now - 60)) {
                @unlink($file);
                @unlink($lockPath);
            }
        } finally {
            @flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}

function mhl_demo_create_session(): array
{
    mhl_demo_cleanup();
    $session = [
        'id' => bin2hex(random_bytes(16)),
        'presenter_token' => bin2hex(random_bytes(32)),
        'created_at' => time(),
        'expires_at' => time() + MHL_DEMO_TTL,
        'stage' => 0,
        'stage_started_at' => time(),
        'auto_advance_at' => null,
        'question_starts_at' => null,
        'question_ends_at' => null,
        'participants' => [],
        'answers' => ['1' => [], '3' => [], '5' => []],
        'scores' => ['1' => [], '3' => []],
        'answer_times' => ['1' => [], '3' => []],
        'answer_times_ms' => ['1' => [], '3' => []],
    ];
    mhl_demo_write_session($session);
    return $session;
}

function mhl_demo_read_session(string $sessionId): array
{
    $path = mhl_demo_session_path($sessionId);
    $lockPath = mhl_demo_lock_path($sessionId);
    if (!is_file($path)) throw new RuntimeException(mhl_ui_text('Demo relace nebyla nalezena.'));
    $lock = @fopen($lockPath, 'c+');
    if (!$lock) throw new RuntimeException(mhl_ui_text('Demo relaci nelze otevřít.'));
    try {
        if (!flock($lock, LOCK_SH)) throw new RuntimeException(mhl_ui_text('Demo relaci nelze číst.'));
        $raw = @file_get_contents($path);
    } finally {
        @flock($lock, LOCK_UN);
        fclose($lock);
    }
    $data = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($data)) throw new RuntimeException(mhl_ui_text('Demo relace je poškozená. Spusťte nové demo.'));
    if ((int)($data['expires_at'] ?? 0) < time()) {
        @unlink($path);
        @unlink($lockPath);
        throw new RuntimeException(mhl_ui_text('Demo relace vypršela. Spusťte nové demo.'));
    }
    return $data;
}

function mhl_demo_write_session(array $session): void
{
    $sessionId = (string)$session['id'];
    $path = mhl_demo_session_path($sessionId);
    $lockPath = mhl_demo_lock_path($sessionId);
    $lock = @fopen($lockPath, 'c+');
    if (!$lock) throw new RuntimeException(mhl_ui_text('Demo relaci nelze vytvořit.'));
    try {
        if (!flock($lock, LOCK_EX)) throw new RuntimeException(mhl_ui_text('Demo relaci nelze uzamknout.'));
        $json = json_encode($session, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        mhl_demo_atomic_write($path, $json);
    } finally {
        @flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function mhl_demo_update_session(string $sessionId, callable $mutator): array
{
    $path = mhl_demo_session_path($sessionId);
    $lockPath = mhl_demo_lock_path($sessionId);
    // Do not create unbounded lock files for made-up session IDs.
    if (!is_file($path)) throw new RuntimeException(mhl_ui_text('Demo relace nebyla nalezena.'));
    $lock = @fopen($lockPath, 'c+');
    if (!$lock) throw new RuntimeException(mhl_ui_text('Demo relaci nelze otevřít.'));
    try {
        if (!flock($lock, LOCK_EX)) throw new RuntimeException(mhl_ui_text('Demo relaci nelze uzamknout.'));
        if (!is_file($path)) throw new RuntimeException(mhl_ui_text('Demo relace nebyla nalezena.'));
        $raw = @file_get_contents($path);
        $session = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($session)) throw new RuntimeException(mhl_ui_text('Demo relace je poškozená. Spusťte nové demo.'));
        if ((int)($session['expires_at'] ?? 0) < time()) {
            @unlink($path);
            throw new RuntimeException(mhl_ui_text('Demo relace vypršela. Spusťte nové demo.'));
        }
        $updated = $mutator($session);
        if (!is_array($updated)) $updated = $session;
        $json = json_encode($updated, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        mhl_demo_atomic_write($path, $json);
        return $updated;
    } finally {
        @flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function mhl_demo_question_stage(int $stage): ?int
{
    if ($stage === 1 || $stage === 2) return 1;
    if ($stage === 3 || $stage === 4) return 3;
    if ($stage === 5 || $stage === 6) return 5;
    return null;
}

function mhl_demo_is_open_stage(int $stage): bool
{
    return in_array($stage, [1, 3, 5], true);
}

function mhl_demo_is_result_stage(int $stage): bool
{
    return in_array($stage, [2, 4, 6], true);
}

function mhl_demo_synthetic_answers(int $questionStage): array
{
    // 17 fiktivních respondentů. Jde výhradně o demo data.
    $sets = [
        1 => [
            'sim01'=>'C','sim02'=>'C','sim03'=>'B','sim04'=>'C','sim05'=>'A',
            'sim06'=>'C','sim07'=>'C','sim08'=>'D','sim09'=>'B','sim10'=>'C',
            'sim11'=>'C','sim12'=>'B','sim13'=>'C','sim14'=>'C','sim15'=>'A',
            'sim16'=>'C','sim17'=>'B',
        ],
        3 => [
            'sim01'=>'A','sim02'=>'A','sim03'=>'B','sim04'=>'A','sim05'=>'A',
            'sim06'=>'A','sim07'=>'C','sim08'=>'A','sim09'=>'B','sim10'=>'A',
            'sim11'=>'D','sim12'=>'A','sim13'=>'A','sim14'=>'C','sim15'=>'B',
            'sim16'=>'A','sim17'=>'A',
        ],
        5 => [
            'sim01'=>'B','sim02'=>'A','sim03'=>'B','sim04'=>'C','sim05'=>'B',
            'sim06'=>'B','sim07'=>'D','sim08'=>'A','sim09'=>'B','sim10'=>'C',
            'sim11'=>'B','sim12'=>'A','sim13'=>'C','sim14'=>'B','sim15'=>'A',
            'sim16'=>'C','sim17'=>'B',
        ],
    ];

    return $sets[$questionStage] ?? [];
}

function mhl_demo_synthetic_names(): array
{
    return [
        'sim01'=>'Klára',
        'sim02'=>'Petr',
        'sim03'=>'Lukáš',
        'sim04'=>'Eliška',
        'sim05'=>'David',
        'sim06'=>'Anna',
        'sim07'=>'Martin',
        'sim08'=>'Tereza',
        'sim09'=>'Ondřej',
        'sim10'=>'Barbora',
        'sim11'=>'Tomáš',
        'sim12'=>'Lucie',
        'sim13'=>'Jakub',
        'sim14'=>'Veronika',
        'sim15'=>'Adam',
        'sim16'=>'Natálie',
        'sim17'=>'Filip',
    ];
}

function mhl_demo_synthetic_response_times_ms(int $questionStage): array
{
    // Skupina je záměrně rozvrstvená:
    // - Klára a Petr jsou velmi rychlí,
    // - další správní respondenti jsou pomalejší,
    // - návštěvník, který odpovídá správně zhruba za 3–5 s,
    //   se tak typicky pohybuje kolem 3. místa.
    if ($questionStage === 1) {
        return [
            'sim01'=>1500, 'sim02'=>2700, 'sim03'=>4700, 'sim04'=>5000,
            'sim05'=>5200, 'sim06'=>6100, 'sim07'=>7300, 'sim08'=>6200,
            'sim09'=>5800, 'sim10'=>8200, 'sim11'=>9000, 'sim12'=>6900,
            'sim13'=>9400, 'sim14'=>9700, 'sim15'=>7600, 'sim16'=>9900,
            'sim17'=>8800,
        ];
    }

    return [
        'sim01'=>1800, 'sim02'=>3000, 'sim03'=>6400, 'sim04'=>6500,
        'sim05'=>6500, 'sim06'=>7600, 'sim07'=>7100, 'sim08'=>7200,
        'sim09'=>8500, 'sim10'=>8400, 'sim11'=>7600, 'sim12'=>8000,
        'sim13'=>9000, 'sim14'=>8200, 'sim15'=>9100, 'sim16'=>9600,
        'sim17'=>9200,
    ];
}

function mhl_demo_synthetic_scores(int $questionStage): array
{
    $questions = mhl_demo_questions();
    $answers = mhl_demo_synthetic_answers($questionStage);
    $times = mhl_demo_synthetic_response_times_ms($questionStage);
    $scores = [];

    // Body za jednu znalostní otázku: 800 za správnost + až 200 za rychlost.
    $questionScores = [];
    foreach ($answers as $id => $answer) {
        $correct = (string)($questions[$questionStage]['correct'] ?? '');
        $points = 0;

        if ($correct !== '' && $answer === $correct) {
            $elapsedMs = max(0, min(MHL_DEMO_QUESTION_SECONDS * 1000, (int)($times[$id] ?? 0)));
            $ratio = max(
                0,
                1 - ($elapsedMs / (MHL_DEMO_QUESTION_SECONDS * 1000))
            );
            $points = 800 + (int)round(200 * $ratio);
        }

        $questionScores[$id] = $points;
    }

    if ($questionStage === 1) {
        return $questionScores;
    }

    // Po druhém kvízu vracíme kumulativní skóre z otázek 1 a 3.
    $firstAnswers = mhl_demo_synthetic_answers(1);
    $firstTimes = mhl_demo_synthetic_response_times_ms(1);
    $firstCorrect = (string)($questions[1]['correct'] ?? '');

    foreach (mhl_demo_synthetic_names() as $id => $nickname) {
        $firstPoints = 0;

        if (($firstAnswers[$id] ?? null) === $firstCorrect) {
            $elapsedMs = max(0, min(MHL_DEMO_QUESTION_SECONDS * 1000, (int)($firstTimes[$id] ?? 0)));
            $ratio = max(
                0,
                1 - ($elapsedMs / (MHL_DEMO_QUESTION_SECONDS * 1000))
            );
            $firstPoints = 800 + (int)round(200 * $ratio);
        }

        $scores[$id] = $firstPoints + (int)($questionScores[$id] ?? 0);
    }

    return $scores;
}

function mhl_demo_user_score(array $session, string $participantId, ?int $throughQuestionStage = null): int
{
    $stages = $throughQuestionStage === 1 ? [1] : [1, 3];
    $score = 0;

    foreach ($stages as $stage) {
        $score += (int)($session['scores'][(string)$stage][$participantId] ?? 0);
    }

    return $score;
}

function mhl_demo_leaderboard(
    array $session,
    string $participantId,
    int $throughQuestionStage = 3
): array {
    $participant = $session['participants'][$participantId] ?? null;
    if (!is_array($participant)) {
        return [];
    }

    $names = mhl_demo_synthetic_names();
    $scores = mhl_demo_synthetic_scores($throughQuestionStage);
    $rows = [];

    foreach ($names as $id => $nickname) {
        $rows[] = [
            'nickname' => $nickname,
            'score' => (int)($scores[$id] ?? 0),
            'synthetic' => true,
            'is_me' => false,
            'response_time_ms' => (int)(mhl_demo_synthetic_response_times_ms($throughQuestionStage)[$id] ?? 0),
        ];
    }

    $rows[] = [
        'nickname' => (string)($participant['nickname'] ?? 'Host'),
        'score' => mhl_demo_user_score($session, $participantId, $throughQuestionStage),
        'synthetic' => false,
        'is_me' => true,
        'response_time_ms' => (int)($session['answer_times_ms'][(string)$throughQuestionStage][$participantId] ?? 0),
    ];

    usort(
        $rows,
        static function (array $a, array $b): int {
            $byScore = ((int)$b['score']) <=> ((int)$a['score']);
            if ($byScore !== 0) {
                return $byScore;
            }
            // Stabilní pořadí při shodě bodů.
            if (!empty($a['is_me']) && empty($b['is_me'])) return -1;
            if (empty($a['is_me']) && !empty($b['is_me'])) return 1;
            return strcmp((string)$a['nickname'], (string)$b['nickname']);
        }
    );

    foreach ($rows as $i => &$row) {
        $row['rank'] = $i + 1;
    }
    unset($row);

    return $rows;
}

function mhl_demo_my_rank(array $leaderboard): ?array
{
    foreach ($leaderboard as $row) {
        if (!empty($row['is_me'])) {
            return $row;
        }
    }
    return null;
}

function mhl_demo_overall_leaderboard(array $session, ?string $highlightParticipantId = null): array
{
    $rows = [];

    foreach (mhl_demo_synthetic_names() as $id => $nickname) {
        $rows[] = [
            'nickname' => $nickname,
            'score' => (int)(mhl_demo_synthetic_scores(3)[$id] ?? 0),
            'synthetic' => true,
            'is_me' => false,
            'hall_choice' => 'nickname',
            'average_response_time_ms' => (int)round(((int)(mhl_demo_synthetic_response_times_ms(1)[$id] ?? 0) + (int)(mhl_demo_synthetic_response_times_ms(3)[$id] ?? 0)) / 2),
        ];
    }

    foreach (($session['participants'] ?? []) as $participantId => $participant) {
        $rows[] = [
            'nickname' => (string)($participant['nickname'] ?? 'Host'),
            'score' => mhl_demo_user_score($session, (string)$participantId, 3),
            'synthetic' => false,
            'is_me' => $highlightParticipantId !== null && hash_equals((string)$participantId, $highlightParticipantId),
            'hall_choice' => $participant['hall_choice'] ?? null,
            'average_response_time_ms' => (function () use ($session, $participantId): int {
                $times = [];
                foreach ([1, 3] as $stage) {
                    $value = (int)($session['answer_times_ms'][(string)$stage][(string)$participantId] ?? 0);
                    if ($value > 0) $times[] = $value;
                }
                return $times ? (int)round(array_sum($times) / count($times)) : 0;
            })(),
        ];
    }

    usort(
        $rows,
        static function (array $a, array $b): int {
            $byScore = ((int)$b['score']) <=> ((int)$a['score']);
            if ($byScore !== 0) {
                return $byScore;
            }
            if (!empty($a['is_me']) && empty($b['is_me'])) return -1;
            if (empty($a['is_me']) && !empty($b['is_me'])) return 1;
            return strcmp((string)$a['nickname'], (string)$b['nickname']);
        }
    );

    foreach ($rows as $i => &$row) {
        $row['rank'] = $i + 1;
    }
    unset($row);

    return $rows;
}

function mhl_demo_hall_choice_summary(array $session): array
{
    $summary = [
        'nickname' => 0,
        'anonymous' => 0,
        'skip' => 0,
        'unanswered' => 0,
    ];

    foreach (($session['participants'] ?? []) as $participant) {
        $choice = $participant['hall_choice'] ?? null;
        if (!is_string($choice) || !isset($summary[$choice])) {
            $summary['unanswered']++;
            continue;
        }
        $summary[$choice]++;
    }

    return $summary;
}

function mhl_demo_results(array $session, int $questionStage): array
{
    $question = mhl_demo_questions()[$questionStage] ?? null;
    if (!$question) return [];

    $counts = array_fill_keys(array_keys($question['options']), 0);

    // Nejdříve pevná demo skupina.
    foreach (mhl_demo_synthetic_answers($questionStage) as $answer) {
        if (isset($counts[$answer])) {
            $counts[$answer]++;
        }
    }

    // Poté reální návštěvníci dema.
    foreach (($session['answers'][(string)$questionStage] ?? []) as $answer) {
        if (isset($counts[$answer])) {
            $counts[$answer]++;
        }
    }

    $total = array_sum($counts);
    $out = [];

    foreach ($counts as $key => $count) {
        $out[$key] = [
            'count' => $count,
            'percent' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
        ];
    }

    return $out;
}

function mhl_demo_apply_auto_transition(array $session): array
{
    $now = microtime(true);
    $stage = (int)($session['stage'] ?? 0);

    if (mhl_demo_is_open_stage($stage)) {
        $endsAt = (float)($session['question_ends_at'] ?? 0);
        if ($endsAt > 0 && $now >= $endsAt) {
            $session['stage'] = $stage + 1;
            $session['stage_started_at'] = $now;
            $session['question_starts_at'] = null;
            $session['question_ends_at'] = null;
            $session['auto_advance_at'] = $now + MHL_DEMO_RESULT_SECONDS;
            return $session;
        }
    }

    if (mhl_demo_is_result_stage($stage)) {
        $autoAt = (float)($session['auto_advance_at'] ?? 0);
        if ($autoAt > 0 && $now >= $autoAt) {
            $map = [2 => 3, 4 => 5, 6 => 7];
            if (isset($map[$stage])) {
                $nextStage = $map[$stage];
                $session['stage'] = $nextStage;
                $session['stage_started_at'] = $now;
                $session['auto_advance_at'] = null;

                if (mhl_demo_is_open_stage($nextStage)) {
                    $start = $now + MHL_DEMO_PRESTART_SECONDS;
                    $session['question_starts_at'] = $start;
                    $session['question_ends_at'] = $start + MHL_DEMO_QUESTION_SECONDS;
                } else {
                    $session['question_starts_at'] = null;
                    $session['question_ends_at'] = null;
                }
                return $session;
            }
        }
    }

    return $session;
}

function mhl_demo_public_state(array $session, ?string $participantId = null): array
{
    $stage = (int)($session['stage'] ?? 0);
    $questionStage = mhl_demo_question_stage($stage);
    $questions = mhl_demo_questions();
    $question = $questionStage !== null ? ($questions[$questionStage] ?? null) : null;

    $participants = $session['participants'] ?? [];
    $answersForQuestion = $questionStage !== null
        ? ($session['answers'][(string)$questionStage] ?? [])
        : [];

    $myAnswer = null;
    if ($participantId && isset($answersForQuestion[$participantId])) {
        $myAnswer = $answersForQuestion[$participantId];
    }

    $syntheticCount = 17;
    $realCount = count($participants);

    $payload = [
        'status' => 'ok',
        'session' => (string)$session['id'],
        'stage' => $stage,
        'stage_started_at' => (float)($session['stage_started_at'] ?? 0),
        'auto_advance_at' => $session['auto_advance_at'] ?? null,
        'question_starts_at' => $session['question_starts_at'] ?? null,
        'question_ends_at' => $session['question_ends_at'] ?? null,
        'prestart_seconds_left' => mhl_demo_is_open_stage($stage) ? max(0, (float)($session['question_starts_at'] ?? 0) - microtime(true)) : null,
        'question_seconds_left' => mhl_demo_is_open_stage($stage) ? max(0, (float)($session['question_ends_at'] ?? 0) - microtime(true)) : null,
        'result_seconds_left' => mhl_demo_is_result_stage($stage) ? max(0, (float)($session['auto_advance_at'] ?? 0) - microtime(true)) : null,
        'question_duration_seconds' => MHL_DEMO_QUESTION_SECONDS,
        'expires_at' => (int)$session['expires_at'],
        'seconds_left' => max(0, (int)$session['expires_at'] - time()),
        'real_participant_count' => $realCount,
        'synthetic_participant_count' => $syntheticCount,
        'participant_count' => $stage > 0 ? ($realCount + $syntheticCount) : $realCount,
        'answer_count' => mhl_demo_is_result_stage($stage)
            ? (count($answersForQuestion) + $syntheticCount)
            : count($answersForQuestion),
        'open' => mhl_demo_is_open_stage($stage),
        'results_visible' => mhl_demo_is_result_stage($stage),
        'my_answer' => $myAnswer,
        'question_stage' => $questionStage,
        'demo_data_notice' => mhl_ui_text('Výsledky obsahují 17 fiktivních respondentů určených pouze pro ukázku.'),
    ];

    if ($question !== null) {
        $payload['question'] = [
            'kind' => $question['kind'],
            'title' => $question['title'],
            'subtitle' => $question['subtitle'],
            'options' => $question['options'],
        ];

        if (mhl_demo_is_result_stage($stage)) {
            $payload['results'] = mhl_demo_results($session, $questionStage);
            $payload['correct'] = $question['correct'];
            $payload['explanation'] = $question['explanation'];

            if ($question['kind'] === 'quiz') {
                if ($participantId && isset($participants[$participantId])) {
                    $leaderboard = mhl_demo_leaderboard($session, $participantId, $questionStage);
                } else {
                    $leaderboard = [];
                    $names = mhl_demo_synthetic_names();
                    $scores = mhl_demo_synthetic_scores($questionStage);
                    $times = mhl_demo_synthetic_response_times_ms($questionStage);
                    foreach ($names as $id => $nickname) {
                        $leaderboard[] = [
                            'nickname' => $nickname,
                            'score' => (int)($scores[$id] ?? 0),
                            'synthetic' => true,
                            'is_me' => false,
                            'response_time_ms' => (int)($times[$id] ?? 0),
                        ];
                    }
                    usort($leaderboard, static fn(array $a, array $b): int => ((int)$b['score']) <=> ((int)$a['score']));
                    foreach ($leaderboard as $i => &$row) $row['rank'] = $i + 1;
                    unset($row);
                }
                $payload['leaderboard'] = $leaderboard;
                $payload['rank_total'] = count($leaderboard);

                if ($participantId && isset($participants[$participantId])) {
                    $me = mhl_demo_my_rank($leaderboard);
                    $payload['my_question_points'] = (int)($session['scores'][(string)$questionStage][$participantId] ?? 0);
                    $payload['my_answer_time_ms'] = (int)($session['answer_times_ms'][(string)$questionStage][$participantId] ?? 0);
                    $payload['my_answer_time'] = round($payload['my_answer_time_ms'] / 1000, 1);
                    $payload['my_total_points'] = mhl_demo_user_score($session, $participantId, $questionStage);
                    $payload['my_rank'] = $me['rank'] ?? null;
                }
            }
        }
    }

    if ($stage === 7 || $stage === 8) {
        $leaderboard = mhl_demo_overall_leaderboard(
            $session,
            $participantId !== null && isset($participants[$participantId]) ? $participantId : null
        );

        $payload['leaderboard'] = mhl_demo_public_hall($leaderboard);
        $payload['hall_summary'] = mhl_demo_hall_choice_summary($session);

        if ($participantId && isset($participants[$participantId])) {
            $me = null;
            foreach ($leaderboard as $row) {
                if (!empty($row['is_me'])) {
                    $me = $row;
                    break;
                }
            }

            $payload['my_rank'] = $me['rank'] ?? null;
            $payload['my_score'] = $me['score'] ?? 0;
            $payload['nickname'] = (string)($participants[$participantId]['nickname'] ?? 'Host');
            $payload['hall_choice'] = $participants[$participantId]['hall_choice'] ?? null;
            $payload['hall_eligible'] = (($me['rank'] ?? 999) <= 10);
            // Finishing is personal: one student's choice cannot finish another's demo.
            $payload['stage'] = in_array($payload['hall_choice'], ['nickname', 'anonymous', 'skip'], true) ? 8 : 7;
        }
    }

    return $payload;
}

function mhl_demo_public_hall(array $rows): array
{
    $public = [];
    foreach ($rows as $row) {
        if (empty($row['synthetic'])) {
            $choice = $row['hall_choice'] ?? null;
            if (!in_array($choice, ['nickname', 'anonymous'], true)) continue;
            if ($choice === 'anonymous') $row['nickname'] = mhl_ui_text('Anonymní účastník');
        }
        $row['rank'] = count($public) + 1;
        $public[] = $row;
    }
    return $public;
}

function mhl_demo_clean_nickname(string $nickname): string
{
    $nickname = trim(preg_replace('/\s+/u', ' ', $nickname) ?? '');
    if ($nickname === '') {
        return 'Host';
    }

    if (function_exists('mb_substr')) {
        return mb_substr($nickname, 0, 40);
    }

    return substr($nickname, 0, 40);
}
