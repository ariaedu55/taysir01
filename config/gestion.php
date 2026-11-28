<?php

declare(strict_types=1);

$hour = env('AGENDA_RAPPEL_HEURE', '09:00');

return ['rappel_heure' => is_string($hour) && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $hour) ? $hour : '09:00'];
