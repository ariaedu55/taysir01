<?php

declare(strict_types=1);

namespace App\Services;

/** Une limite anti-abus de la prise de rendez-vous est atteinte (BookingLimiter::proteger). */
final class LimiteAtteinte extends \RuntimeException
{
}
