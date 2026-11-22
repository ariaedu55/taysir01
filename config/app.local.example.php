<?php

declare(strict_types=1);

return array_replace(require __DIR__ . '/app.php', [
    'url' => 'https://taysir.example.com',
    'debug' => false,
]);
