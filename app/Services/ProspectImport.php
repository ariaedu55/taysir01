<?php

declare(strict_types=1);

namespace App\Services;

use DomainException;
use SimpleXMLElement;
use ZipArchive;

final class ProspectImport
{
    public const MAX_ROWS = 500;
    private const MAX_BYTES = 2_000_000;
    private const HEADERS = ['prenom' => 'first_name', 'nom' => 'last_name', 'telephone' => 'phone', 'email' => 'email', 'niveau' => 'level'];

    public static function read(array $file): array
    {
        $path = $file['tmp_name'] ?? null;
        if (($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($path) || !is_uploaded_file($path) || filesize($path) > self::MAX_BYTES) {
            throw new DomainException('Choisissez un fichier CSV ou XLSX de 2 Mo maximum.');
        }
        $rows = match (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION))) {
            'csv' => self::csv($path),
            'xlsx' => self::xlsx($path),
            default => throw new DomainException('Enregistrez votre fichier au format XLSX ou CSV UTF-8.'),
        };
        $header = array_shift($rows) ?? [];
        $columns = [];
        foreach ($header as $index => $value) {
            $name = strtr(mb_strtolower(trim(str_replace("\xEF\xBB\xBF", '', (string) $value))), ['é' => 'e', 'è' => 'e', '-' => '']);
            if (isset(self::HEADERS[$name])) {
                $key = self::HEADERS[$name];
                if (isset($columns[$key])) {
                    throw new DomainException('Chaque colonne attendue doit apparaître une seule fois.');
                }
                $columns[$key] = $index;
            }
        }
        if (array_diff(['first_name', 'last_name', 'level'], array_keys($columns)) || (!isset($columns['email']) && !isset($columns['phone']))) {
            throw new DomainException('Colonnes attendues : prénom, nom, niveau, puis téléphone ou email.');
        }
        $valid = $issues = [];
        foreach ($rows as [$line, $cells]) {
            if (!array_filter($cells, static fn ($value): bool => trim((string) $value) !== '')) {
                continue;
            }
            try {
                $data = array_map(static fn ($index): string => (string) ($cells[$index] ?? ''), $columns);
                $valid[$line] = self::validate($data);
            } catch (DomainException $exception) {
                $issues[$line] = $exception->getMessage();
            }
        }
        return [$valid, $issues];
    }

    public static function validate(array $data): array
    {
        $fields = [];
        foreach (['first_name' => 80, 'last_name' => 80, 'level' => 80, 'email' => 190, 'phone' => 30] as $key => $max) {
            $value = trim((string) ($data[$key] ?? ''));
            if (!mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $max || preg_match('/[\x00-\x1f]/', $value)) {
                throw new DomainException('Valeur trop longue ou caractères invalides.');
            }
            $fields[$key] = $value;
        }
        if ($fields['first_name'] === '' || $fields['last_name'] === '' || $fields['level'] === '') {
            throw new DomainException('Le prénom, le nom et le niveau sont requis.');
        }
        $email = mb_strtolower($fields['email']);
        $phone = preg_replace('/\D/', '', $fields['phone']);
        if (($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))
            || ($fields['phone'] !== '' && (!preg_match('/\A[+0-9(). -]+\z/', $fields['phone']) || strlen($phone) < 8 || strlen($phone) > 15))) {
            throw new DomainException('Adresse e-mail ou téléphone invalide.');
        }
        if ($email === '' && $phone === '') {
            throw new DomainException('Renseignez un téléphone ou un e-mail.');
        }
        return array_replace($fields, ['email' => $email, 'phone' => str_starts_with($fields['phone'], '+') ? '+' . $phone : $phone]);
    }

    private static function csv(string $path): array
    {
        $stream = fopen($path, 'rb');
        try {
            $first = fgets($stream) ?: '';
            $delimiters = array_map(static fn (string $separator): int => count(str_getcsv($first, $separator, '"', '')), [';', ',', "\t"]);
            $separator = [';', ',', "\t"][array_search(max($delimiters), $delimiters, true)];
            rewind($stream);
            $rows = [fgetcsv($stream, null, $separator, '"', '') ?: []];
            for ($line = 2; ($row = fgetcsv($stream, null, $separator, '"', '')) !== false; $line++) {
                self::checkSize($line - 1);
                $rows[] = [$line, $row];
            }
            return $rows;
        } finally {
            fclose($stream);
        }
    }

    private static function xlsx(string $path): array
    {
        if (!class_exists(ZipArchive::class) || !function_exists('simplexml_load_string')) {
            throw new DomainException('La lecture XLSX est indisponible. Utilisez un CSV UTF-8 dans Excel.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new DomainException('Impossible de lire ce classeur XLSX.');
        }
        try {
            $book = self::xml($zip, 'xl/workbook.xml');
            $relations = self::xml($zip, 'xl/_rels/workbook.xml.rels');
            $sheet = $book->sheets->sheet[0] ?? null;
            $attributes = $sheet?->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $sheetId = (string) ($attributes['id'] ?? '');
            $target = '';
            foreach ($relations->Relationship as $relation) {
                if ((string) $relation['Id'] === $sheetId && str_ends_with((string) $relation['Type'], '/worksheet') && (string) $relation['TargetMode'] !== 'External') {
                    $target = (string) $relation['Target'];
                    break;
                }
            }
            $target = str_starts_with($target, '/') ? substr($target, 1) : 'xl/' . $target;
            if (!preg_match('~\Axl/worksheets/[A-Za-z0-9_-]+\.xml\z~', $target)) {
                throw new DomainException('La première feuille du classeur est illisible.');
            }
            $sheet = self::xml($zip, $target);
            $shared = [];
            if ($zip->locateName('xl/sharedStrings.xml') !== false) {
                foreach (self::xml($zip, 'xl/sharedStrings.xml')->si as $item) {
                    $shared[] = self::cellText($item);
                }
            }
            $header = null;
            $rows = [];
            foreach ($sheet->sheetData->row ?? [] as $row) {
                self::checkSize(count($rows) + ($header === null ? 0 : 1));
                $cells = [];
                foreach ($row->c as $cell) {
                    if (!preg_match('/\A([A-Z]{1,3})\d+\z/', (string) $cell['r'], $coordinate)) {
                        continue;
                    }
                    $cells[$coordinate[1]] = match ((string) $cell['t']) {
                        's' => $shared[(int) $cell->v] ?? '',
                        'inlineStr' => self::cellText($cell->is),
                        default => (string) $cell->v,
                    };
                    if (isset($cell->f)) {
                        $cells[$coordinate[1]] = "\x01";
                    }
                }
                if ($header === null) {
                    $header = $cells;
                } else {
                    $rows[] = [(int) $row['r'] ?: count($rows) + 2, $cells];
                }
            }
            return [$header ?? [], ...$rows];
        } finally {
            $zip->close();
        }
    }

    private static function cellText(SimpleXMLElement $cell): string
    {
        $text = (string) $cell->t;
        foreach ($cell->r as $part) {
            $text .= (string) $part->t;
        }
        return $text;
    }

    private static function xml(ZipArchive $zip, string $name): SimpleXMLElement
    {
        $stats = $zip->statName($name);
        $data = $stats && $stats['size'] <= 4_000_000 ? $zip->getFromName($name) : false;
        $xml = is_string($data) && !str_contains($data, "\0") && !preg_match('/<!\s*(DOCTYPE|ENTITY)\b/i', $data)
            ? simplexml_load_string($data, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING) : false;
        if ($xml === false) {
            throw new DomainException('Le classeur contient une feuille ou un index illisible.');
        }
        return $xml;
    }

    private static function checkSize(int $rows): void
    {
        if ($rows > self::MAX_ROWS) {
            throw new DomainException('Limitez chaque fichier à 500 lignes de prospects.');
        }
    }
}
