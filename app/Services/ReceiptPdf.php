<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\CaseFinance;

/**
 * Reçu de règlement en PDF (phase 5), écrit sans bibliothèque : A4, en-tête de l'agence avec son logo (JPEG embarqué
 * tel quel, public/assets/images/brand/logo-recu.jpg), numéro, montant mis en avant, détail, mention « ANNULÉ » si le
 * règlement a été annulé, pied de page. Polices standard Helvetica (WinAnsi) : aucun fichier de police à fournir.
 * Revue du 28/11/2026, écart 6 (docs/verifications/2026-11-28-revue-2d-a-phase-6.md).
 */
final class ReceiptPdf
{
    private const LOGO = BASE_PATH . '/public/assets/images/brand/logo-recu.jpg';

    public static function send(array $payment): never
    {
        $pdf = self::build($payment);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="recu-' . $payment['receipt'] . '.pdf"');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        echo $pdf;
        exit;
    }

    /** Contenu du PDF (séparé de l'envoi pour pouvoir le tester). */
    public static function build(array $payment): string
    {
        $contact = (array) config('site.contact', []);
        $montant = number_format((int) $payment['amount'], 0, ',', ' ') . ' FCFA';
        $agent = self::agent((int) ($payment['recorded_by'] ?? 0));
        $date = static fn (?string $d): string => $d ? date('d/m/Y', strtotime($d)) : '';

        $c = '';
        // En-tête : logo à gauche, coordonnées de l'agence à droite
        $logo = is_file(self::LOGO) ? getimagesize(self::LOGO) : false;
        if ($logo !== false) {
            $h = 46;
            $w = round($logo[0] * $h / $logo[1], 2);
            $c .= "q {$w} 0 0 {$h} 56 760 cm /Logo Do Q\n";
        }
        $droite = ['Taysir Orientation Voyage', (string) ($contact['address'] ?? ''), (string) ($contact['phone'] ?? ''), (string) ($contact['email'] ?? '')];
        foreach (array_values(array_filter($droite)) as $i => $ligne) {
            $c .= self::text($ligne, 372, 795 - $i * 13, $i === 0 ? 10 : 9, $i === 0, $i === 0 ? '0.04 0.04 0.04' : '0.35 0.35 0.35');
        }
        $c .= "0.86 0.88 0.92 RG 0.8 w 56 740 m 539 740 l S\n";

        // Titre et numéro
        $c .= self::text('REÇU DE RÈGLEMENT', 56, 700, 20, true, '0.04 0.04 0.04');
        $c .= self::text('N° ' . $payment['receipt'], 56, 678, 12, false, '0.35 0.35 0.35');

        // Montant mis en avant (surface teintée, sans bordure colorée)
        $c .= "0.93 0.96 1 rg 56 600 483 56 re f\n";
        $c .= self::text('Montant reçu', 72, 634, 10, false, '0.35 0.35 0.35');
        $c .= self::text($montant, 72, 612, 20, true, '0.04 0.04 0.04');

        // Détail
        $lignes = [
            ['Dossier', (string) $payment['case_reference']],
            ['Client', (string) $payment['customer_name']],
            ['Date de réception', $date($payment['received_on'] ?? null)],
            ['Mode de règlement', (string) (CaseFinance::METHODS[$payment['method']] ?? $payment['method'])],
            ['Devise', 'Franc CFA (XOF)'],
        ];
        if ($agent !== '') {
            $lignes[] = ['Enregistré par', $agent];
        }
        $y = 562;
        foreach ($lignes as [$libelle, $valeur]) {
            $c .= self::text($libelle, 56, $y, 10, false, '0.35 0.35 0.35');
            foreach (mb_str_split($valeur, 52) as $k => $morceau) {
                $c .= self::text($morceau, 210, $y - $k * 14, 11, true, '0.04 0.04 0.04');
            }
            // Filet sous la ligne (et non sur la suivante)
            $hauteur = 14 * max(1, (int) ceil(mb_strlen($valeur) / 52));
            $filet = $y - $hauteur + 6;
            $c .= "0.92 0.93 0.95 RG 0.5 w 56 {$filet} m 539 {$filet} l S\n";
            $y -= $hauteur + 12;
        }

        // Annulation : mention bien visible (le rouge signale le danger, charte du 27/11)
        if (!empty($payment['cancelled_at'])) {
            $c .= "1 0.92 0.92 rg 56 " . ($y - 46) . " 483 34 re f\n";
            $c .= self::text('ANNULÉ le ' . $date($payment['cancelled_at']) . ' : ce reçu n’a plus de valeur', 72, $y - 34, 12, true, '0.8 0.06 0.06');
            $y -= 56;
        }

        // Pied de page
        $c .= self::text('Ce reçu atteste d’un règlement reçu par Taysir Orientation Voyage. Conservez-le avec votre dossier.', 56, 90, 8.5, false, '0.35 0.35 0.35');
        $c .= self::text('Document généré le ' . gmdate('d/m/Y') . ' · ' . (string) ($contact['email'] ?? ''), 56, 76, 8.5, false, '0.55 0.55 0.55');

        return self::document($c, $logo !== false ? (string) file_get_contents(self::LOGO) : null, $logo);
    }

    /** Une ligne de texte ; $align « right » : $x est le bord droit (largeur estimée pour Helvetica). */
    private static function text(string $texte, float $x, float $y, float $taille, bool $gras, string $couleur, string $align = 'left'): string
    {
        $encode = (string) mb_convert_encoding($texte, 'Windows-1252', 'UTF-8');
        if ($align === 'right') {
            $x -= strlen($encode) * $taille * ($gras ? 0.56 : 0.51);
        }
        $echappe = strtr($encode, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '', "\n" => ' ']);

        return "BT {$couleur} rg /" . ($gras ? 'F2' : 'F1') . " {$taille} Tf " . round($x, 2) . " {$y} Td ({$echappe}) Tj ET\n";
    }

    private static function agent(int $id): string
    {
        if ($id < 1) {
            return '';
        }
        $statement = Database::connection()->prepare('SELECT first_name, last_name FROM users WHERE id = ?');
        $statement->execute([$id]);
        $user = $statement->fetch();

        return $user ? trim($user['first_name'] . ' ' . $user['last_name']) : '';
    }

    /** Assemble les objets PDF (catalogue, page, polices, logo JPEG, contenu) et la table des références. */
    private static function document(string $contenu, ?string $jpeg, array|false $logo): string
    {
        $ressources = '<< /Font << /F1 4 0 R /F2 5 0 R >>' . ($jpeg !== null ? ' /XObject << /Logo 7 0 R >>' : '') . ' >>';
        $objets = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources ' . $ressources . ' /Contents 6 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
            "<< /Length " . strlen($contenu) . " >>\nstream\n" . $contenu . "\nendstream",
        ];
        if ($jpeg !== null && $logo !== false) {
            $objets[] = '<< /Type /XObject /Subtype /Image /Width ' . $logo[0] . ' /Height ' . $logo[1]
                . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($jpeg) . " >>\nstream\n" . $jpeg . "\nendstream";
        }
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $positions = [];
        foreach ($objets as $i => $objet) {
            $positions[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n{$objet}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objets) + 1) . "\n0000000000 65535 f \n";
        foreach ($positions as $position) {
            $pdf .= sprintf("%010d 00000 n \n", $position);
        }

        return $pdf . "trailer\n<< /Size " . (count($objets) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
