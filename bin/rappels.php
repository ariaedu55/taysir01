<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Models\Appointment;
use App\Services\Notifier;

$scheduled = in_array('--planifie', $argv, true);
if ($scheduled && !in_array(config('mail.driver', 'log'), ['smtp', 'mail'], true)) {
    exit(0);
}

$lock = fopen(BASE_PATH . '/storage/cache/rappels.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}
$db = Database::connection();
$utc = new DateTimeZone('UTC');
$now = new DateTimeImmutable('now', $utc);
$fromHour = config('gestion.rappel_heure', '09:00');
$agencies = $db->query('SELECT id FROM agencies ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
$queued = 0;
$errors = 0;
foreach ([null, ...$agencies] as $agency) {
    $zone = Appointment::zone($agency === null ? null : (int) $agency);
    $local = $now->setTimezone($zone);
    if ($local->format('H:i') < $fromHour) {
        continue;
    }
    $tomorrow = $local->setTime(0, 0)->modify('+1 day');
    $expires = $tomorrow->setTimezone($utc)->format('Y-m-d H:i:s');
    $until = $tomorrow->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s');
    $filter = "agency_id <=> ? AND status = 'confirme' AND reminded_at IS NULL AND starts_at_utc >= ? AND starts_at_utc < ?";
    $list = $db->prepare("SELECT id FROM appointments WHERE {$filter} ORDER BY id");
    $list->execute([$agency, $expires, $until]);
    $ids = $list->fetchAll(PDO::FETCH_COLUMN);
    $lookup = $db->prepare("SELECT * FROM appointments WHERE id = ? AND {$filter} FOR UPDATE");
    $last = $db->prepare("SELECT MAX(created_at) FROM appointment_events WHERE appointment_id = ?
        AND ((type IN ('creation','statut') AND status = 'confirme') OR (type = 'deplacement' AND status = 'confirme'))");
    $mark = $db->prepare("UPDATE appointments SET reminded_at = UTC_TIMESTAMP(), updated_at = updated_at WHERE id = ? AND {$filter}");
    foreach ($ids as $id) {
        $db->beginTransaction();
        try {
            $lookup->execute([$id, $agency, $expires, $until]);
            $row = $lookup->fetch();
            if (!$row || gmdate('Y-m-d H:i:s') >= $expires) {
                $db->rollBack();
                continue;
            }
            $last->execute([$id]);
            $fixed = new DateTimeImmutable($last->fetchColumn() ?: $row['created_at'], $utc);
            $begin = new DateTimeImmutable($row['starts_at_utc'], $utc);
            if ($begin->getTimestamp() - $fixed->getTimestamp() < 86400) {
                $db->rollBack();
                continue;
            }
            $mark->execute([$id, $agency, $expires, $until]);
            $valid = $mark->rowCount() === 1 && filter_var($row['email'], FILTER_VALIDATE_EMAIL);
            if ($valid) {
                Notifier::appointmentScheduleMail($row, null, $expires);
            }
            $db->commit();
            $queued += (int) $valid;
        } catch (Throwable) {
            $db->rollBack();
            $errors++;
        }
    }
}
printf("%d rappel(s) mis en file, %d erreur(s).\n", $queued, $errors);
[$deadlineCount, $deadlineErrors] = \App\Models\RequestFollowUp::queueReminders($now);
printf("%d échéance(s) traitée(s), %d erreur(s).\n", $deadlineCount, $deadlineErrors);
if (!$scheduled) {
    require __DIR__ . '/send-mails.php';
}
exit($errors + $deadlineErrors > 0 ? 1 : 0);
