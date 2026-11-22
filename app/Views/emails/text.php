<?php
/**
 * Version texte des e-mails : mêmes données que emails/layout.php.
 *
 * @var string                $heading
 * @var list<string>          $paragraphs
 * @var array<string, string> $details
 * @var string|null           $quote
 * @var string|null           $quoteBy
 * @var array|null            $button
 * @var list<string>          $after
 * @var string                $audience
 */
$contact = config('site.contact');
echo $heading, "\n", str_repeat('=', min(60, strlen($heading))), "\n\n";
foreach ($paragraphs as $paragraph) {
    echo $paragraph, "\n\n";
}
if ($quote !== null && $quote !== '') {
    echo $quoteBy ? $quoteBy . " :\n" : '', '> ', str_replace("\n", "\n> ", $quote), "\n\n";
}
foreach ($details as $label => $value) {
    echo $label, ' : ', str_replace("\n", ' ', $value), "\n";
}
if ($details !== []) {
    echo "\n";
}
if ($button !== null) {
    echo $button[0], ' : ', $button[1], "\n\n";
}
foreach ($after as $paragraph) {
    echo $paragraph, "\n\n";
}
echo "--\n";
echo $audience === 'client'
    ? 'Taysir Orientation Voyage · ' . $contact['address'] . ' · ' . $contact['phone'] . "\n" . absolute_url('/') . "\n"
    : "Alerte automatique du portail Taysir. Répondre à cet e-mail écrit directement à la personne concernée.\n";
