<?php

declare(strict_types=1);

$mail = require __DIR__ . '/mail.php';
$mail['driver'] = 'smtp';
$mail['smtp'] = array_replace($mail['smtp'], [
    'host' => 'smtp.example.com',
    'port' => 587,
    'encryption' => 'tls',
    'username' => '',
    'password' => '',
]);
$mail['send_immediately'] = false;

return $mail;
