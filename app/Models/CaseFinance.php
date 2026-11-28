<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use DomainException;

final class CaseFinance extends Model
{
    public const METHODS = ['especes' => 'Espèces', 'wave' => 'Wave', 'orange-money' => 'Orange Money', 'virement' => 'Virement', 'autre' => 'Autre'];

    public static function forRequest(int $requestId): array
    {
        return [
            'quote' => static::query('SELECT * FROM finance_quotes WHERE request_id = ?', [$requestId])->fetch() ?: null,
            'installments' => static::query('SELECT * FROM finance_installments WHERE request_id = ? ORDER BY due_date', [$requestId])->fetchAll(),
            'payments' => static::query('SELECT p.*, c.cancelled_at, c.reason FROM finance_payments p LEFT JOIN finance_cancellations c ON c.payment_id = p.id
                WHERE p.request_id = ? ORDER BY p.id DESC', [$requestId])->fetchAll(),
        ];
    }

    /**
     * Synthèse pour le comptable (revue du 28/11/2026, écart 5) : un dossier par ligne avec devis, payé (règlements non
     * annulés), solde, montant échu non payé à la date du jour (échéances échues moins payé) et prochaine échéance.
     * $today : date du jour dans le fuseau de l'agence (calculée en PHP, jamais CURDATE()).
     */
    public static function synthese(string $today, string $filtre = '', string $recherche = '', int $limite = 300): array
    {
        $params = [$today, $today];
        $where = '';
        if ($recherche !== '') {
            $motif = '%' . addcslashes(mb_strtolower($recherche), '\\%_') . '%';
            $where = " WHERE LOWER(r.reference) LIKE ? OR LOWER(CONCAT(r.first_name, ' ', r.last_name)) LIKE ?";
            array_push($params, $motif, $motif);
        }
        $lignes = static::query(
            "SELECT r.reference, r.first_name, r.last_name, r.status, q.title, q.amount,
                    COALESCE((SELECT SUM(p.amount) FROM finance_payments p LEFT JOIN finance_cancellations c ON c.payment_id = p.id
                              WHERE p.request_id = r.id AND c.payment_id IS NULL), 0) AS paid,
                    COALESCE((SELECT SUM(i.amount) FROM finance_installments i WHERE i.request_id = r.id AND i.due_date <= ?), 0) AS due,
                    (SELECT MIN(i.due_date) FROM finance_installments i WHERE i.request_id = r.id AND i.due_date > ?) AS next_due,
                    (SELECT MAX(p.received_on) FROM finance_payments p WHERE p.request_id = r.id) AS last_payment
             FROM finance_quotes q JOIN service_requests r ON r.id = q.request_id{$where}
             ORDER BY r.updated_at DESC LIMIT " . max(1, $limite),
            $params
        )->fetchAll();
        foreach ($lignes as &$l) {
            $l['balance'] = max(0, (int) $l['amount'] - (int) $l['paid']);
            $l['overdue'] = max(0, (int) $l['due'] - (int) $l['paid']);
        }
        unset($l);

        return array_values(array_filter($lignes, static fn (array $l): bool => match ($filtre) {
            'solde' => $l['balance'] > 0,
            'retard' => $l['overdue'] > 0,
            default => true,
        }));
    }

    public static function payment(int $requestId, string $receipt): ?array
    {
        $row = static::query('SELECT p.*, c.cancelled_at FROM finance_payments p LEFT JOIN finance_cancellations c ON c.payment_id = p.id
            WHERE p.request_id = ? AND p.receipt = ?', [$requestId, $receipt])->fetch();
        return $row ?: null;
    }

    public static function saveQuote(int $requestId, string $title, int $amount): void
    {
        $data = self::forRequest($requestId);
        if ($data['payments'] || array_sum(array_column($data['installments'], 'amount')) > $amount) {
            throw new DomainException('Le devis est figé après un règlement. Son montant doit couvrir l’échéancier.');
        }
        static::query('INSERT INTO finance_quotes (request_id,title,amount) VALUES (?,?,?) ON DUPLICATE KEY UPDATE title = VALUES(title), amount = VALUES(amount)',
            [$requestId, $title, $amount]);
    }

    public static function saveInstallment(int $requestId, string $date, int $amount): void
    {
        $data = self::forRequest($requestId);
        $scheduled = 0;
        foreach ($data['installments'] as $installment) {
            if ($installment['due_date'] !== $date) {
                $scheduled += (int) $installment['amount'];
            }
        }
        if (!$data['quote'] || $scheduled + $amount > (int) $data['quote']['amount']) {
            throw new DomainException('L’échéancier doit rester dans la limite du devis.');
        }
        static::query('INSERT INTO finance_installments (request_id,due_date,amount) VALUES (?,?,?) ON DUPLICATE KEY UPDATE amount = VALUES(amount)',
            [$requestId, $date, $amount]);
    }

    public static function record(array $request, array $actor, string $token, int $amount, string $method, string $date): ?string
    {
        $existing = static::query('SELECT 1 FROM finance_payments WHERE form_token = ? AND request_id = ? AND recorded_by = ?',
            [$token, $request['id'], $actor['id']])->fetchColumn();
        if ($existing) {
            return null;
        }
        $data = self::forRequest((int) $request['id']);
        $paid = array_sum(array_map(static fn (array $p): int => $p['cancelled_at'] ? 0 : (int) $p['amount'], $data['payments']));
        if (!$data['quote'] || $paid + $amount > (int) $data['quote']['amount']) {
            throw new DomainException('Le règlement dépasse le solde du devis ou le devis est absent.');
        }
        $agency = static::query('SELECT id, code, timezone FROM agencies WHERE id = ? AND active = 1', [$actor['agency_id'] ?? null])->fetch();
        if (!$agency || !isset(['dakar' => 'DKR', 'ziguinchor' => 'ZIG', 'paris' => 'PAR'][$agency['code']])) {
            throw new DomainException('Un rattachement à une agence active est nécessaire.');
        }
        $prefix = ['dakar' => 'DKR', 'ziguinchor' => 'ZIG', 'paris' => 'PAR'][$agency['code']];
        $year = (int) (new \DateTimeImmutable('now', new \DateTimeZone($agency['timezone'])))->format('Y');
        static::query('INSERT INTO finance_sequences (agency_id,receipt_year,last_number) VALUES (?,?,1)
            ON DUPLICATE KEY UPDATE last_number = last_number + 1', [$agency['id'], $year]);
        $number = static::query('SELECT last_number FROM finance_sequences WHERE agency_id = ? AND receipt_year = ?', [$agency['id'], $year])->fetchColumn();
        $receipt = sprintf('%s-%04d-%06d', $prefix, $year, $number);
        static::query('INSERT INTO finance_payments (request_id,receipt,form_token,amount,method,received_on,recorded_by,customer_name,case_reference)
            VALUES (?,?,?,?,?,?,?,?,?)', [$request['id'], $receipt, $token, $amount, $method, $date, $actor['id'],
            $request['first_name'] . ' ' . $request['last_name'], $request['reference']]);
        return $receipt;
    }

    public static function cancel(int $requestId, string $receipt, int $actorId, string $reason): bool
    {
        $payment = self::payment($requestId, $receipt);
        if (!$payment) {
            throw new DomainException('Reçu introuvable.');
        }
        return static::query('INSERT INTO finance_cancellations (payment_id,reason,cancelled_by) VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE payment_id = payment_id', [$payment['id'], $reason, $actorId])->rowCount() === 1;
    }
}
