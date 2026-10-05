<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

mhl_demo_no_cache_headers();
header('Content-Type: application/json; charset=UTF-8');

function demo_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function demo_input(string $key): string
{
    $value = $_POST[$key] ?? $_GET[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

try {
    mhl_demo_cleanup();

    $action = demo_input('action');
    $sessionId = demo_input('s');

    if ($sessionId === '') {
        demo_json(['status' => 'error', 'error' => 'SESSION_REQUIRED'], 400);
    }

    if ($action === 'state') {
        $participantId = demo_input('p');

        $session = mhl_demo_update_session(
            $sessionId,
            function (array $session): array {
                return mhl_demo_apply_auto_transition($session);
            }
        );

        demo_json(
            mhl_demo_public_state(
                $session,
                $participantId !== '' ? $participantId : null
            )
        );
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        demo_json(['status' => 'error', 'error' => 'POST_REQUIRED'], 405);
    }

    if ($action === 'join') {
        $participantId = demo_input('p');
        $nickname = mhl_demo_clean_nickname(demo_input('nickname'));

        if (!preg_match('/^[a-f0-9]{32}$/', $participantId)) {
            demo_json(['status' => 'error', 'error' => 'PARTICIPANT_INVALID'], 400);
        }

        $session = mhl_demo_update_session(
            $sessionId,
            function (array $session) use ($participantId, $nickname): array {
                $session['participants'][$participantId] = [
                    'nickname' => $nickname,
                    'joined_at' => $session['participants'][$participantId]['joined_at'] ?? time(),
                    'last_seen' => time(),
                    'hall_choice' => $session['participants'][$participantId]['hall_choice'] ?? null,
                ];

                // Ve veřejném prezentačním demu se první otázka spustí sama,
                // jakmile se připojí první telefon. Uživatel tak po zadání
                // přezdívky ihned vidí skutečnou otázku místo čekací obrazovky.
                if ((int)($session['stage'] ?? 0) === 0) {
                    $session['stage'] = 1;
                    $now = microtime(true);
                    $session['stage_started_at'] = $now;
                    $session['question_starts_at'] = $now + MHL_DEMO_PRESTART_SECONDS;
                    $session['question_ends_at'] = $session['question_starts_at'] + MHL_DEMO_QUESTION_SECONDS;
                    $session['auto_advance_at'] = null;
                }

                return $session;
            }
        );

        demo_json(mhl_demo_public_state($session, $participantId));
    }

    if ($action === 'answer') {
        $participantId = demo_input('p');
        $answer = strtoupper(demo_input('answer'));

        if (!preg_match('/^[a-f0-9]{32}$/', $participantId)) {
            demo_json(['status' => 'error', 'error' => 'PARTICIPANT_INVALID'], 400);
        }

        $session = mhl_demo_update_session(
            $sessionId,
            function (array $session) use ($participantId, $answer): array {
                $session = mhl_demo_apply_auto_transition($session);
                $stage = (int)$session['stage'];

                if (!mhl_demo_is_open_stage($stage)) {
                    throw new RuntimeException('Tato otázka už není otevřená.');
                }

                $questionStartsAt = (float)($session['question_starts_at'] ?? 0);
                if ($questionStartsAt > 0 && microtime(true) < $questionStartsAt) {
                    throw new RuntimeException('Otázka ještě nezačala. Počkejte na START.');
                }

                if (!isset($session['participants'][$participantId])) {
                    throw new RuntimeException('Nejdříve se připojte k demu.');
                }

                $question = mhl_demo_questions()[$stage] ?? null;
                if (!$question || !array_key_exists($answer, $question['options'])) {
                    throw new RuntimeException('Neplatná odpověď.');
                }

                if (!isset($session['answers'][(string)$stage][$participantId])) {
                    $session['answers'][(string)$stage][$participantId] = $answer;

                    if (($question['kind'] ?? '') === 'quiz') {
                        $answerTimeMs = (int)round(microtime(true) * 1000);
                        $questionStartMs = (int)round(((float)($session['question_starts_at'] ?? microtime(true))) * 1000);
                        $elapsedMs = max(0, $answerTimeMs - $questionStartMs);
                        $elapsed = $elapsedMs / 1000;

                        $points = 0;
                        if ($answer === ($question['correct'] ?? null)) {
                            // Ukázka výchozího principu: 800 bodů za správnost
                            // + až 200 za rychlost. Pro demo používáme 20s okno.
                            $speedWindow = MHL_DEMO_QUESTION_SECONDS;
                            $ratio = max(
                                0,
                                1 - (min($elapsed, $speedWindow) / $speedWindow)
                            );
                            $points = 800 + (int)round(200 * $ratio);
                        }

                        $session['scores'][(string)$stage][$participantId] = $points;
                        $session['answer_times'][(string)$stage][$participantId] = round($elapsed, 1);
                        $session['answer_times_ms'][(string)$stage][$participantId] = $elapsedMs;
                    }
                }

                $session['participants'][$participantId]['last_seen'] = time();

                // Odpověď se uloží, ale výsledky se neprozradí před uzavřením otázky.
                return $session;
            }
        );

        demo_json(mhl_demo_public_state($session, $participantId));
    }

    if ($action === 'hall') {
        $participantId = demo_input('p');
        $choice = demo_input('choice');

        if (!preg_match('/^[a-f0-9]{32}$/', $participantId)) {
            demo_json(['status' => 'error', 'error' => 'PARTICIPANT_INVALID'], 400);
        }

        if (!in_array($choice, ['nickname', 'anonymous', 'skip'], true)) {
            demo_json(['status' => 'error', 'error' => 'HALL_CHOICE_INVALID'], 400);
        }

        $session = mhl_demo_update_session(
            $sessionId,
            function (array $session) use ($participantId, $choice): array {
                if (!in_array((int)($session['stage'] ?? 0), [7, 8], true)) {
                    throw new RuntimeException('Síň slávy nyní není aktivní.');
                }

                if (!isset($session['participants'][$participantId])) {
                    throw new RuntimeException('Účastník nebyl nalezen.');
                }

                $session['participants'][$participantId]['hall_choice'] = $choice;
                $session['participants'][$participantId]['last_seen'] = time();
                $allAnswered = true;
                foreach ($session['participants'] as $participant) {
                    if (!in_array($participant['hall_choice'] ?? null, ['nickname', 'anonymous', 'skip'], true)) {
                        $allAnswered = false;
                        break;
                    }
                }
                if ($allAnswered) {
                    $session['stage'] = 8;
                    $session['stage_started_at'] = time();
                    $session['auto_advance_at'] = null;
                }

                return $session;
            }
        );

        demo_json(mhl_demo_public_state($session, $participantId));
    }

    if ($action === 'next') {
        $presenterToken = demo_input('pt');

        $session = mhl_demo_update_session(
            $sessionId,
            function (array $session) use ($presenterToken): array {
                if (
                    $presenterToken === ''
                    || !hash_equals(
                        (string)$session['presenter_token'],
                        $presenterToken
                    )
                ) {
                    throw new RuntimeException('Neplatné oprávnění prezentujícího.');
                }

                $stage = (int)$session['stage'];
                if ($stage >= 7) return $session;
                $next = min(7, $stage + 1);
                $session['stage'] = $next;
                $now = microtime(true);
                $session['stage_started_at'] = $now;
                $session['auto_advance_at'] = null;
                if (mhl_demo_is_open_stage($next)) {
                    $session['question_starts_at'] = $now + MHL_DEMO_PRESTART_SECONDS;
                    $session['question_ends_at'] = $session['question_starts_at'] + MHL_DEMO_QUESTION_SECONDS;
                } else {
                    $session['question_starts_at'] = null;
                    $session['question_ends_at'] = null;
                }
                return $session;
            }
        );

        demo_json(mhl_demo_public_state($session));
    }

    demo_json(['status' => 'error', 'error' => 'UNKNOWN_ACTION'], 400);

} catch (Throwable $e) {
    demo_json(
        [
            'status' => 'error',
            'error' => 'DEMO_ERROR',
            'message' => $e->getMessage(),
        ],
        400
    );
}
