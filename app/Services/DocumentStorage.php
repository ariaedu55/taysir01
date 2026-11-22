<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Documents joints aux demandes : stockés hors de la racine web, sous un nom aléatoire,
 * après contrôle du vrai type (fileinfo) et de la taille. Voir docs/demandes-rendez-vous.md.
 */
final class DocumentStorage
{
    public const MAX_FILES = 3;
    public const MAX_SIZE = 5 * 1024 * 1024;
    /** Type réel => extension enregistrée. */
    public const ALLOWED = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    public static function directory(): string
    {
        return BASE_PATH . '/storage/uploads/requests';
    }

    /**
     * Liste les fichiers envoyés dans le champ $field (input multiple), sans les enregistrer.
     * Retourne [fichiers valides, message d'erreur ou null].
     *
     * @return array{0: list<array>, 1: ?string}
     */
    public static function collect(string $field): array
    {
        $upload = $_FILES[$field] ?? null;
        if (!is_array($upload) || !is_array($upload['name'] ?? null)) {
            return [[], null];
        }
        $files = [];
        foreach ($upload['name'] as $i => $name) {
            $error = (int) $upload['error'][$i];
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE || $upload['size'][$i] > self::MAX_SIZE) {
                return [[], 'Chaque document doit peser 5 Mo au maximum.'];
            }
            if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'][$i])) {
                return [[], 'Un document n’a pas pu être envoyé. Réessayez.'];
            }
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name'][$i]) ?: '';
            if (!isset(self::ALLOWED[$mime])) {
                return [[], 'Formats acceptés : PDF, JPG et PNG.'];
            }
            $files[] = ['tmp' => $upload['tmp_name'][$i], 'name' => self::cleanName((string) $name), 'mime' => $mime, 'size' => (int) $upload['size'][$i]];
        }
        if (count($files) > self::MAX_FILES) {
            return [[], 'Vous pouvez joindre ' . self::MAX_FILES . ' documents au maximum.'];
        }

        return [$files, null];
    }

    /** Enregistre un fichier validé par collect() et retourne son nom de stockage. */
    public static function store(array $file): string
    {
        $directory = self::directory();
        if (!is_dir($directory) && !mkdir($directory, 0770, true)) {
            throw new RuntimeException('Dossier de stockage indisponible.');
        }
        $stored = bin2hex(random_bytes(32)) . '.' . self::ALLOWED[$file['mime']];
        if (!move_uploaded_file($file['tmp'], $directory . '/' . $stored)) {
            throw new RuntimeException('Enregistrement du document impossible.');
        }

        return $stored;
    }

    /** Envoie un document au navigateur (après contrôle d'accès par l'appelant). */
    public static function send(array $document): never
    {
        $path = self::directory() . '/' . basename($document['stored_name']);
        if (!is_file($path)) {
            \App\Core\Router::abort(404);
        }
        header('Content-Type: ' . $document['mime']);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $document['original_name']) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($path);
        exit;
    }

    private static function cleanName(string $name): string
    {
        $name = preg_replace('/[^\p{L}\p{N}._ -]+/u', '_', basename($name)) ?? 'document';

        return substr($name, 0, 150) ?: 'document';
    }
}
