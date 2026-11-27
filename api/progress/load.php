<?php
require_once __DIR__ . '/../lib.php';

// GET api/progress?module=pohl: the logged-in user's progress in one module, for resuming.
requireMethod('GET');

$userId   = requireUser()['id'];
$moduleId = requireModule($_GET['module'] ?? null);

// DATETIME(3) in UTC → an ISO string the browser can't mistake for local time.
function toIso(string $datetime): string {
    return str_replace(' ', 'T', $datetime) . 'Z';
}

$pdo = getDb();

$s = $pdo->prepare(
    'SELECT route, query, duration_s, started_at FROM page_visits WHERE user_id = ? AND module_id = ? ORDER BY started_at, id'
);
$s->execute([$userId, $moduleId]);
$pageVisits = array_map(fn ($v) => [
    'page'       => $v['route'] . ($v['query'] !== '' ? '?' . $v['query'] : ''),
    'duration_s' => $v['duration_s'] === null ? null : (float) $v['duration_s'],
    'startedAt'  => toIso($v['started_at']),
], $s->fetchAll());

// The app resumes from the latest attempt per question. The earlier ones stay in the table for analysis.
$s = $pdo->prepare(
    'SELECT kind, unit_id, question_id, attempt_no, is_correct, points_awarded, max_points,
            answer, correct_answer, texts, answered_at
     FROM question_attempts WHERE user_id = ? AND module_id = ? ORDER BY attempt_no'
);
$s->execute([$userId, $moduleId]);
$latest = [];
foreach ($s->fetchAll() as $a) {
    $latest["{$a['unit_id']}|{$a['question_id']}"] = [
        'kind'          => $a['kind'],
        'unitId'        => $a['unit_id'],
        'questionId'    => $a['question_id'],
        'attemptNo'     => (int) $a['attempt_no'],
        'isCorrect'     => (bool) $a['is_correct'],
        'pointsAwarded' => $a['points_awarded'] === null ? null : (int) $a['points_awarded'],
        'maxPoints'     => $a['max_points'] === null ? null : (int) $a['max_points'],
        'answer'        => json_decode($a['answer'], true),
        'correctAnswer' => json_decode($a['correct_answer'], true),
        'texts'         => json_decode($a['texts'], true),
        'answeredAt'    => toIso($a['answered_at']),
    ];
}

// Units already rated, so the pop-up isn't shown again on another device.
$s = $pdo->prepare('SELECT unit_id FROM effort_ratings WHERE user_id = ? AND module_id = ?');
$s->execute([$userId, $moduleId]);
$ratedUnits = array_column($s->fetchAll(), 'unit_id');

// Marked passages: those created and not deleted, in the order they were made, each with its kind and, for an
// „unklar“ mark, when it was set to „geklärt“ (null while open). The events are applied in order.
$s = $pdo->prepare(
    'SELECT mark_id, action, kind, route, subpage, anchor, event_at FROM mark_events
     WHERE user_id = ? AND module_id = ? ORDER BY event_at, id'
);
$s->execute([$userId, $moduleId]);
$current = [];
$deleted = [];
foreach ($s->fetchAll() as $e) {
    $id = $e['mark_id'];
    if (isset($deleted[$id])) continue;
    switch ($e['action']) {
        case 'create':
            $current[$id] = [
                'id'         => $id,
                'kind'       => $e['kind'],
                'route'      => $e['route'],
                'page'       => $e['subpage'],
                'anchor'     => json_decode($e['anchor'], true),
                'createdAt'  => toIso($e['event_at']),
                'resolvedAt' => null,
            ];
            break;
        case 'delete':
            unset($current[$id]);
            $deleted[$id] = true;
            break;
        case 'resolve':
            if (isset($current[$id])) $current[$id]['resolvedAt'] = toIso($e['event_at']);
            break;
        case 'reopen':
            if (isset($current[$id])) $current[$id]['resolvedAt'] = null;
            break;
    }
}
$marks = array_values($current);

respond(200, [
    'pageVisits' => $pageVisits,
    'attempts'   => array_values($latest),
    'ratedUnits' => $ratedUnits,
    'marks'      => $marks,
]);
