<?php

declare(strict_types=1);

namespace App\Controllers\Gestion;

use App\Core\Auth;
use App\Core\Session;
use App\Models\CaseFinance;
use App\Models\RequestRequirement;
use App\Models\ServiceRequest;
use App\Services\Audit;
use App\Services\FormInput;
use App\Services\ReceiptPdf;
use DomainException;

final class FinanceController extends EspaceController
{
    /** Soldes et échéances de tous les dossiers avec devis (/gestion/finance/), administrateur et comptable. */
    public function index(): void
    {
        $me = Auth::user();
        $zone = \App\Models\Appointment::zone(isset($me['agency_id']) ? (int) $me['agency_id'] : null);
        $today = (new \DateTimeImmutable('now', $zone))->format('Y-m-d');
        $filtre = in_array($_GET['vue'] ?? '', ['solde', 'retard'], true) ? (string) $_GET['vue'] : '';
        $recherche = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
        $toutes = CaseFinance::synthese($today);
        $this->page('finance-synthese', 'finance', 'Soldes et échéances', [
            'lignes' => $filtre === '' && $recherche === '' ? $toutes : CaseFinance::synthese($today, $filtre, $recherche),
            'totaux' => [
                'devis' => array_sum(array_column($toutes, 'amount')),
                'paye' => array_sum(array_column($toutes, 'paid')),
                'solde' => array_sum(array_column($toutes, 'balance')),
                'retard' => array_sum(array_column($toutes, 'overdue')),
                'nbRetard' => count(array_filter($toutes, static fn (array $l): bool => $l['overdue'] > 0)),
            ],
            'filtre' => $filtre,
            'recherche' => $recherche,
            'today' => $today,
        ]);
    }

    public function show(string $reference): void
    {
        $request = $this->request($reference);
        $this->page('finance', 'dossiers', 'Finances du dossier', [
            'request' => $request,
            'finance' => CaseFinance::forRequest((int) $request['id']),
            'paymentToken' => self::signPaymentToken($request['reference'], bin2hex(random_bytes(16))),
        ]);
    }

    public function save(string $reference): void
    {
        $request = $this->request($reference);
        $action = FormInput::text('action', 20);
        $back = '/gestion/dossiers/' . $request['reference'] . '/finance/';
        $actor = Auth::user();
        if ($action === 'cancel' && !Auth::hasRole('admin')) {
            $this->refuser('finance', $reference, 'annulation réservée à l’administration');
        }
        try {
            $changed = RequestRequirement::withLockedRequest((int) $request['id'], static function (array $locked) use ($actor, $action): bool {
                $id = (int) $locked['id'];
                if ($action === 'cancel') {
                    $reason = FormInput::text('reason', 501);
                    if ($reason === '' || mb_strlen($reason) > 500) {
                        throw new DomainException('Indiquez le motif de l’annulation.');
                    }
                    $receipt = FormInput::text('receipt', 40);
                    if (!CaseFinance::cancel($id, $receipt, (int) $actor['id'], $reason)) {
                        return false;
                    }
                    Audit::log('finance_dossier', 'dossier', $locked['reference'], ['action' => $action, 'receipt' => $receipt], required: true);
                    return true;
                }
                RequestRequirement::ensureOpen($locked);
                $amount = filter_var($_POST['amount'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1_000_000_000]]);
                if ($amount === false) {
                    throw new DomainException('Indiquez un montant entier positif en FCFA.');
                }
                if ($action === 'quote') {
                    $title = FormInput::text('title', 191);
                    if ($title === '' || mb_strlen($title) > 190) {
                        throw new DomainException('Indiquez un intitulé de devis.');
                    }
                    CaseFinance::saveQuote($id, $title, $amount);
                    Audit::log('finance_dossier', 'dossier', $locked['reference'], ['action' => $action], required: true);
                    return true;
                }
                $date = FormInput::text('date', 11);
                if (FormInput::date($date) === null) {
                    throw new DomainException('Indiquez une date valide.');
                }
                if ($action === 'installment') {
                    CaseFinance::saveInstallment($id, $date, $amount);
                    Audit::log('finance_dossier', 'dossier', $locked['reference'], ['action' => $action, 'date' => $date], required: true);
                    return true;
                }
                $method = FormInput::text('method', 20);
                $token = $_POST['form_token'] ?? null;
                if ($action !== 'payment' || !isset(CaseFinance::METHODS[$method]) || $date > gmdate('Y-m-d')
                    || !is_string($token) || !preg_match('/\A[a-f0-9]{64}\z/', $token)
                    || !hash_equals(self::signPaymentToken($locked['reference'], substr($token, 0, 32)), $token)) {
                    throw new DomainException('Vérifiez le mode, la date du règlement et le formulaire.');
                }
                $receipt = CaseFinance::record($locked, $actor, $token, $amount, $method, $date);
                if ($receipt === null) {
                    return false;
                }
                Audit::log('finance_dossier', 'dossier', $locked['reference'], ['action' => $action, 'receipt' => $receipt], required: true);
                return true;
            });
        } catch (DomainException $exception) {
            Session::flash('_errors', ['finance' => $exception->getMessage()]);
            redirect_to($back);
        }
        Session::flash('_success', $changed ? 'Opération enregistrée.' : 'Opération déjà enregistrée.');
        redirect_to($back);
    }

    public function receipt(string $reference, string $number): void
    {
        $request = $this->request($reference);
        ReceiptPdf::send(CaseFinance::payment((int) $request['id'], $number) ?? $this->abort(404));
    }

    private function request(string $reference): array
    {
        return ServiceRequest::findByReference($reference) ?? $this->abort(404);
    }

    private static function signPaymentToken(string $reference, string $nonce): string
    {
        return $nonce . substr(hash_hmac('sha256', $reference . ':' . Auth::id() . ':' . $nonce, Session::csrfToken()), 0, 32);
    }
}
