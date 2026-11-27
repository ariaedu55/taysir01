<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Droits de l'espace équipe, en un seul endroit (docs/gestion-plan.md §3, docs/gestion-phase-1.md 1B).
 *
 * - Les 8 postes réels de GAOV, un poste par personne, plus des missions nominatives (user_missions).
 * - La matrice du §3.5 pour les actions présentes en 1B.
 * - Le filtre de périmètre, utilisé PARTOUT (listes, compteurs, recherche, fiche, pièce) : le menu n'est jamais
 *   la seule barrière.
 */
final class Access
{
    /** Postes internes : clé => libellé. */
    public const POSTES = [
        'admin' => 'Administrateur',
        'secretaire' => 'Secrétaire',
        'prospection' => 'Agent de prospection',
        'commerciale' => 'Commerciale',
        'conseiller' => 'Conseiller',
        'orientation' => 'Agent d’orientation',
        'traitement' => 'Agent de traitement',
        'comptable' => 'Comptable',
    ];

    /** Postes qui reçoivent une affectation de dossier (request_assignments.poste), dans l'ordre de la chaîne. */
    public const AFFECTATIONS = ['conseiller' => 'Conseiller', 'orientation' => 'Orientation', 'traitement' => 'Traitement'];

    /** Missions nominatives (§3.6) et le poste dont elles donnent les droits (null : réservé à une phase suivante). */
    public const MISSIONS = [
        'encaissement' => ['Encaissement au guichet', null],
        'orientation' => ['Orientation', 'orientation'],
        'prospection' => ['Prospection', 'prospection'],
        'traitement' => ['Traitement des dossiers', 'traitement'],
    ];

    /** Postes qui voient tous les dossiers (en-tête), sans pouvoir ouvrir les pièces (§3.5). */
    private const VUE_TRANSVERSALE = ['admin', 'secretaire', 'comptable'];

    /** Modules de l'espace équipe et postes autorisés (§3.5, actions présentes en 1B). */
    private const MODULES = [
        'gestion' => ['admin', 'secretaire', 'prospection', 'commerciale', 'conseiller', 'orientation', 'traitement', 'comptable'],
        'dossiers' => ['admin', 'secretaire', 'conseiller', 'orientation', 'traitement', 'comptable'],
        // Les rendez-vous n'ont pas encore de responsable (affectation en phase 2) : réservés à l'administrateur
        // et à la secrétaire, qui ont la vue transversale. Revue 1B, point 1.
        'rendez-vous' => ['admin', 'secretaire'],
        'messages' => ['admin', 'secretaire'],
        'e-mails' => ['admin'],
        'equipe' => ['admin'],
        'journal' => ['admin'],
    ];

    /** Qui peut affecter un dossier à quel poste (§3.5, ligne « Affecter ou réaffecter »). */
    private const AFFECTER = [
        'admin' => ['conseiller', 'orientation', 'traitement'],
        'secretaire' => ['conseiller'],
        'conseiller' => ['orientation'],
        'orientation' => ['traitement'],
    ];

    /** @var array<int, list<string>> Postes effectifs mis en cache pour la requête */
    private static array $postes = [];

    public static function isStaff(?array $user): bool
    {
        return $user !== null && isset(self::POSTES[$user['role'] ?? '']);
    }

    public static function libelle(string $poste): string
    {
        return self::POSTES[$poste] ?? ucfirst($poste);
    }

    /** Page d'accueil après connexion : /gestion/ pour l'équipe, /mon-espace/ pour un client (§2.4). */
    public static function accueil(?array $user): string
    {
        return self::isStaff($user) ? '/gestion/' : '/mon-espace/';
    }

    /**
     * Chemin de retour mémorisé (?retour=), gardé seulement s'il est interne ET permis à ce compte (§2.4) :
     * un client n'est jamais renvoyé vers /gestion/, un membre de l'équipe ne l'est pas vers un formulaire client.
     */
    public static function retourPermis(?array $user, mixed $path): ?string
    {
        if (!is_string($path) || !str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\') || preg_match('/[\r\n]/', $path)) {
            return null;
        }
        $gestion = str_starts_with($path, '/gestion') || str_starts_with($path, '/admin');
        if ($gestion !== self::isStaff($user)) {
            return null;
        }
        // Espace équipe : le MODULE demandé doit être permis à ce poste (revue 1B, point 3), sinon « Ma journée ».
        // Le contrôle de la ressource elle-même (dossier, pièce) reste fait à son ouverture.
        if ($gestion) {
            $module = self::moduleDuChemin($path);
            if ($module !== null && !self::peut($user, $module)) {
                return null;
            }
        }

        return $path;
    }

    /** Module de l'espace équipe désigné par une adresse (/gestion/… ou ancienne /admin/…), null pour la maquette. */
    public static function moduleDuChemin(string $path): ?string
    {
        $path = (string) parse_url($path, PHP_URL_PATH);
        if (str_starts_with($path, '/gestion/maquette')) {
            return null;
        }
        $segment = explode('/', trim($path, '/'))[1] ?? '';
        $modules = [
            '' => 'gestion', 'dossiers' => 'dossiers', 'demandes' => 'dossiers', 'rendez-vous' => 'rendez-vous',
            'messages' => 'messages', 'e-mails' => 'e-mails', 'equipe' => 'equipe', 'journal' => 'journal',
        ];

        return $modules[$segment] ?? 'gestion';
    }

    /** Poste du compte et postes apportés par ses missions nominatives actives. */
    public static function postes(array $user): array
    {
        $id = (int) $user['id'];
        if (!isset(self::$postes[$id])) {
            $postes = isset(self::POSTES[$user['role']]) ? [$user['role']] : [];
            $missions = Database::connection()->prepare('SELECT mission FROM user_missions WHERE user_id = ? AND revoked_at IS NULL');
            $missions->execute([$id]);
            foreach ($missions->fetchAll(\PDO::FETCH_COLUMN) as $mission) {
                $poste = self::MISSIONS[$mission][1] ?? null;
                if ($poste !== null && !in_array($poste, $postes, true)) {
                    $postes[] = $poste;
                }
            }
            self::$postes[$id] = $postes;
        }

        return self::$postes[$id];
    }

    /** Vide le cache (après un changement de poste ou de mission dans la même requête). */
    public static function oublier(): void
    {
        self::$postes = [];
    }

    /** Accès à un module de l'espace équipe. */
    public static function peut(?array $user, string $module): bool
    {
        if (!self::isStaff($user) || !isset(self::MODULES[$module])) {
            return false;
        }

        return array_intersect(self::postes($user), self::MODULES[$module]) !== [];
    }

    /**
     * Filtre de périmètre sur les dossiers (alias de service_requests en paramètre).
     * Vue transversale : tout. Postes affectables : les dossiers qu'ils tiennent, plus ceux qui sont DANS LA FILE
     * de leur poste (voir fileSql). Autres postes : rien.
     *
     * @return array{0: string, 1: list<mixed>} Condition SQL et paramètres
     */
    public static function perimetre(array $user, string $alias = 'r'): array
    {
        $postes = self::postes($user);
        if (array_intersect($postes, self::VUE_TRANSVERSALE) !== []) {
            return ['1 = 1', []];
        }
        $conditions = [];
        $params = [];
        foreach (array_intersect($postes, array_keys(self::AFFECTATIONS)) as $poste) {
            [$file, $fileParams] = self::fileSql($poste, $alias);
            $conditions[] = "(EXISTS (SELECT 1 FROM request_assignments ra WHERE ra.request_id = {$alias}.id AND ra.poste = ? AND ra.user_id = ?) OR {$file})";
            array_push($params, $poste, (int) $user['id'], ...$fileParams);
        }

        return $conditions === [] ? ['1 = 0', []] : ['(' . implode(' OR ', $conditions) . ')', $params];
    }

    /**
     * Le dossier est-il dans la FILE de ce poste ? (revue 1B, point 2)
     * - conseiller : dès la création, et tant qu'aucun conseiller ne le tient ;
     * - orientation, traitement : seulement s'il y a été TRANSMIS (ligne du poste avec user_id NULL).
     *   Un poste vacant ne suffit pas.
     *
     * @return array{0: string, 1: list<mixed>}
     */
    public static function fileSql(string $poste, string $alias = 'r'): array
    {
        if ($poste === 'conseiller') {
            return ["NOT EXISTS (SELECT 1 FROM request_assignments rf WHERE rf.request_id = {$alias}.id AND rf.poste = ? AND rf.user_id IS NOT NULL)", [$poste]];
        }

        return ["EXISTS (SELECT 1 FROM request_assignments rf WHERE rf.request_id = {$alias}.id AND rf.poste = ? AND rf.user_id IS NULL)", [$poste]];
    }

    /** Même règle que fileSql(), sur les affectations déjà chargées d'un dossier. */
    public static function enFile(string $poste, array $affectations): bool
    {
        if ($poste === 'conseiller') {
            return empty($affectations[$poste]['user_id']);
        }

        return isset($affectations[$poste]) && empty($affectations[$poste]['user_id']);
    }

    /** Le dossier est-il dans le périmètre de ce compte ? (même règle que perimetre()) */
    public static function peutOuvrir(array $user, array $request): bool
    {
        [$sql, $params] = self::perimetre($user, 'r');
        $statement = Database::connection()->prepare("SELECT 1 FROM service_requests r WHERE r.id = ? AND {$sql}");
        $statement->execute(array_merge([(int) $request['id']], $params));

        return (bool) $statement->fetchColumn();
    }

    /**
     * Pièces, statut et notes : l'administrateur, ou un poste affectable À QUI le dossier est affecté.
     * La secrétaire et le comptable voient l'en-tête mais pas les pièces (§3.5) ; un dossier non attribué
     * doit d'abord être pris.
     *
     * @param array<string, array> $affectations Poste => affectation (Assignment::forRequest)
     */
    public static function estResponsable(array $user, array $affectations): bool
    {
        if (in_array('admin', self::postes($user), true)) {
            return true;
        }
        foreach (array_intersect(self::postes($user), array_keys(self::AFFECTATIONS)) as $poste) {
            if ((int) ($affectations[$poste]['user_id'] ?? 0) === (int) $user['id']) {
                return true;
            }
        }

        return false;
    }

    /**
     * PRENDRE pour soi : ses postes affectables où le dossier est dans la file (revue 1B, point 2).
     *
     * @param array<string, array> $affectations
     * @return list<string>
     */
    public static function prenables(array $user, array $affectations): array
    {
        return array_values(array_filter(
            array_intersect(array_keys(self::AFFECTATIONS), self::postes($user)),
            static fn (string $poste): bool => self::enFile($poste, $affectations)
        ));
    }

    /**
     * TRANSMETTRE au poste suivant (dans sa file ou à une personne) : selon la matrice AFFECTER, et seulement
     * par le RESPONSABLE actuel du dossier (l'administrateur et la secrétaire ont la vue transversale).
     * Un poste déjà tenu par quelqu'un ne se réaffecte que par l'administrateur.
     *
     * @param array<string, array> $affectations
     * @return list<string>
     */
    public static function transmissibles(array $user, array $affectations): array
    {
        $postes = self::postes($user);
        $admin = in_array('admin', $postes, true);
        $cibles = [];
        foreach ($postes as $poste) {
            $tient = in_array($poste, self::VUE_TRANSVERSALE, true)
                || (int) ($affectations[$poste]['user_id'] ?? 0) === (int) $user['id'];
            if (!$tient) {
                continue;
            }
            foreach (self::AFFECTER[$poste] ?? [] as $cible) {
                if ($admin || empty($affectations[$cible]['user_id'])) {
                    $cibles[$cible] = true;
                }
            }
        }

        return array_values(array_intersect(array_keys(self::AFFECTATIONS), array_keys($cibles)));
    }

    /**
     * REMETTRE DANS LA FILE un poste tenu : par celui qui le tient, ou par l'administrateur.
     *
     * @param array<string, array> $affectations
     * @return list<string>
     */
    public static function liberables(array $user, array $affectations): array
    {
        $admin = in_array('admin', self::postes($user), true);

        return array_values(array_filter(array_keys(self::AFFECTATIONS), static fn (string $poste): bool => !empty($affectations[$poste]['user_id'])
            && ($admin || (int) $affectations[$poste]['user_id'] === (int) $user['id'])));
    }

    /** Navigation de l'espace équipe : groupes par métier, entrées permises seulement. */
    public static function navigation(array $user): array
    {
        $groupes = [
            ['', 'jour', [['Ma journée', 'sun', '/gestion/', 'gestion']]],
            ['Relation client', 'rdv', [
                ['Dossiers', 'folder', '/gestion/dossiers/', 'dossiers'],
                ['Rendez-vous', 'calendar', '/gestion/rendez-vous/', 'rendez-vous'],
                ['Messages', 'inbox', '/gestion/messages/', 'messages'],
            ]],
            ['Administration', 'neutre', [
                ['Équipe', 'team', '/gestion/equipe/', 'equipe'],
                ['Journal d’audit', 'lock', '/gestion/journal/', 'journal'],
                ['E-mails', 'send', '/gestion/e-mails/', 'e-mails'],
            ]],
        ];
        $nav = [];
        foreach ($groupes as [$titre, $famille, $items]) {
            $items = array_values(array_filter($items, static fn (array $item): bool => self::peut($user, $item[3])));
            if ($items !== []) {
                $nav[] = [$titre, $famille, $items];
            }
        }

        return $nav;
    }
}
