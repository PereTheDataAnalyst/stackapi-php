<?php
declare(strict_types=1);

namespace StackApi\Resources;

use StackApi\StackApi;

abstract class Resource
{
    public function __construct(protected readonly StackApi $c)
    {
    }

    protected static function s(string $v): string
    {
        return StackApi::seg($v);
    }
}

final class Notifications extends Resource
{
    public function sendSms(array $body): mixed { return $this->c->post('/v1/notifications/sms', $body); }
    public function sendBulkSms(array $body): mixed { return $this->c->post('/v1/notifications/sms/bulk', $body); }
    public function smsStatus(string $id): mixed { return $this->c->get('/v1/notifications/sms/' . self::s($id)); }
    public function sendOtp(array $body): mixed { return $this->c->post('/v1/notifications/sms/otp/send', $body); }
    public function verifyOtp(string $otpId, string $code): mixed { return $this->c->post('/v1/notifications/sms/otp/verify', ['otp_id' => $otpId, 'code' => $code]); }
    /** 'from' on one of your verified domains sends from that domain. */
    public function sendEmail(array $body): mixed { return $this->c->post('/v1/notifications/email', $body); }

    public function listEmailDomains(): mixed { return $this->c->get('/v1/notifications/email/domains'); }
    public function createEmailDomain(string $domain, bool $receiving = false): mixed
    {
        return $this->c->post('/v1/notifications/email/domains', ['domain' => $domain, 'receiving' => $receiving]);
    }
    public function getEmailDomain(string $idOrDomain): mixed { return $this->c->get('/v1/notifications/email/domains/' . self::s($idOrDomain)); }
    public function verifyEmailDomain(string $idOrDomain): mixed { return $this->c->post('/v1/notifications/email/domains/' . self::s($idOrDomain) . '/verify'); }
    public function setEmailDomainReceiving(string $idOrDomain, bool $enabled): mixed
    {
        return $this->c->put('/v1/notifications/email/domains/' . self::s($idOrDomain) . '/receiving', ['enabled' => $enabled]);
    }
    public function removeEmailDomain(string $idOrDomain): mixed { return $this->c->delete('/v1/notifications/email/domains/' . self::s($idOrDomain)); }
    public function simulateInbound(string $idOrDomain, array $body = []): mixed
    {
        return $this->c->post('/v1/notifications/email/domains/' . self::s($idOrDomain) . '/simulate-inbound', $body);
    }
    public function listInbound(array $query = []): mixed { return $this->c->get('/v1/notifications/email/inbound', $query); }
    public function getInbound(string $id): mixed { return $this->c->get('/v1/notifications/email/inbound/' . self::s($id)); }
}

final class Payments extends Resource
{
    public function initiate(array $body): mixed { return $this->c->post('/v1/payments/initiate', $body); }
    public function get(string $reference): mixed { return $this->c->get('/v1/payments/ref/' . self::s($reference)); }
}

final class Identity extends Resource
{
    /** Any check by its path name, for example verify('verify-tin', ['tin' => '...']). */
    public function verify(string $check, array $body): mixed { return $this->c->post('/v1/identity/' . ltrim($check, '/'), $body); }
    public function verifyBvn(array $body): mixed { return $this->c->post('/v1/identity/verify-bvn', $body); }
    public function verifyNin(array $body): mixed { return $this->c->post('/v1/identity/verify-nin', $body); }
    public function verifyPhone(array $body): mixed { return $this->c->post('/v1/identity/verify-phone', $body); }
    public function verifyCac(array $body): mixed { return $this->c->post('/v1/identity/verify-cac', $body); }
    public function verifyBankAccount(array $body): mixed { return $this->c->post('/v1/identity/verify-bank-account', $body); }
    public function companySearch(string $companyName, ?string $country = null): mixed
    {
        return $this->c->post('/v1/identity/company-search', array_filter(['company_name' => $companyName, 'country' => $country], fn ($v) => $v !== null));
    }
    public function pricing(): mixed { return $this->c->get('/v1/identity/pricing'); }
}

final class Vtu extends Resource
{
    public function airtime(array $body): mixed { return $this->c->post('/v1/vtu/airtime', $body); }
    public function data(array $body): mixed { return $this->c->post('/v1/vtu/data', $body); }
    public function plans(array $query = []): mixed { return $this->c->get('/v1/vtu/plans', $query); }
    public function electricity(array $body): mixed { return $this->c->post('/v1/vtu/electricity', $body); }
    public function cableTv(array $body): mixed { return $this->c->post('/v1/vtu/cable-tv', $body); }
    public function betting(array $body): mixed { return $this->c->post('/v1/vtu/betting', $body); }
    public function education(array $body): mixed { return $this->c->post('/v1/vtu/education', $body); }
    public function verifyCustomer(array $body): mixed { return $this->c->post('/v1/vtu/verify', $body); }
    public function transaction(string $id): mixed { return $this->c->get('/v1/vtu/transactions/' . self::s($id)); }
}

final class Payouts extends Resource
{
    public function banks(): mixed { return $this->c->get('/v1/payouts/banks'); }
    public function fees(string|int|float|null $amount = null): mixed { return $this->c->get('/v1/payouts/fees', ['amount' => $amount]); }
    public function resolve(string $bankCode, string $accountNumber): mixed
    {
        return $this->c->post('/v1/payouts/resolve', ['bank_code' => $bankCode, 'account_number' => $accountNumber]);
    }
    public function create(array $body): mixed { return $this->c->post('/v1/payouts', $body); }
    public function get(string $id): mixed { return $this->c->get('/v1/payouts/' . self::s($id)); }
    public function list(array $query = []): mixed { return $this->c->get('/v1/payouts', $query); }
}

final class Banking extends Resource
{
    public function createAccount(array $body): mixed { return $this->c->post('/v1/banking/accounts', $body); }
    public function listAccounts(array $query = []): mixed { return $this->c->get('/v1/banking/accounts', $query); }
    public function getAccount(string $idOrReference): mixed { return $this->c->get('/v1/banking/accounts/' . self::s($idOrReference)); }
    public function payments(string $idOrReference, array $query = []): mixed { return $this->c->get('/v1/banking/accounts/' . self::s($idOrReference) . '/payments', $query); }
    public function simulatePayment(string $idOrReference, array $body): mixed
    {
        return $this->c->post('/v1/banking/accounts/' . self::s($idOrReference) . '/simulate-payment', $body);
    }
    public function banks(): mixed { return $this->c->get('/v1/banking/banks'); }
    public function resolve(string $bankCode, string $accountNumber): mixed
    {
        return $this->c->post('/v1/banking/resolve', ['bank_code' => $bankCode, 'account_number' => $accountNumber]);
    }
}

final class Fx extends Resource
{
    public function rates(?string $base = null, ?string $symbols = null): mixed { return $this->c->get('/v1/fx/rates', ['base' => $base, 'symbols' => $symbols]); }
    public function convert(string $from, string $to, string|int|float $amount): mixed { return $this->c->get('/v1/fx/convert', ['from' => $from, 'to' => $to, 'amount' => $amount]); }
    public function history(string $date, ?string $base = null, ?string $symbols = null): mixed
    {
        return $this->c->get('/v1/fx/history', ['date' => $date, 'base' => $base, 'symbols' => $symbols]);
    }
    public function currencies(): mixed { return $this->c->get('/v1/fx/currencies'); }
}

final class Cards extends Resource
{
    public function createCardholder(array $body): mixed { return $this->c->post('/v1/cards/cardholders', $body); }
    public function listCardholders(array $query = []): mixed { return $this->c->get('/v1/cards/cardholders', $query); }
    public function getCardholder(string $id): mixed { return $this->c->get('/v1/cards/cardholders/' . self::s($id)); }
    public function create(array $body): mixed { return $this->c->post('/v1/cards', $body); }
    public function list(array $query = []): mixed { return $this->c->get('/v1/cards', $query); }
    public function get(string $idOrReference): mixed { return $this->c->get('/v1/cards/' . self::s($idOrReference)); }
    public function fund(string $id, string|int|float $amount, ?string $reference = null): mixed
    {
        return $this->c->post('/v1/cards/' . self::s($id) . '/fund', self::money($amount, $reference));
    }
    public function withdraw(string $id, string|int|float $amount, ?string $reference = null): mixed
    {
        return $this->c->post('/v1/cards/' . self::s($id) . '/withdraw', self::money($amount, $reference));
    }
    public function freeze(string $id): mixed { return $this->c->post('/v1/cards/' . self::s($id) . '/freeze'); }
    public function unfreeze(string $id): mixed { return $this->c->post('/v1/cards/' . self::s($id) . '/unfreeze'); }
    public function terminate(string $id): mixed { return $this->c->post('/v1/cards/' . self::s($id) . '/terminate'); }
    public function transactions(string $id, array $query = []): mixed { return $this->c->get('/v1/cards/' . self::s($id) . '/transactions', $query); }
    /** A short-lived link that shows the full number and CVV in the cardholder's browser. */
    public function reveal(string $id): mixed { return $this->c->post('/v1/cards/' . self::s($id) . '/reveal'); }
    public function simulateTransaction(string $id, array $body): mixed { return $this->c->post('/v1/cards/' . self::s($id) . '/simulate-transaction', $body); }

    private static function money(string|int|float $amount, ?string $reference): array
    {
        $b = ['amount' => (string) $amount];
        if ($reference !== null && $reference !== '') {
            $b['reference'] = $reference;
        }
        return $b;
    }
}

final class Logistics extends Resource
{
    public function createAddress(array $body): mixed { return $this->c->post('/v1/logistics/addresses', $body); }
    public function listAddresses(array $query = []): mixed { return $this->c->get('/v1/logistics/addresses', $query); }
    public function getAddress(string $id): mixed { return $this->c->get('/v1/logistics/addresses/' . self::s($id)); }
    public function categories(): mixed { return $this->c->get('/v1/logistics/categories'); }
    public function createQuote(array $body): mixed { return $this->c->post('/v1/logistics/quotes', $body); }
    public function createShipment(array $body): mixed { return $this->c->post('/v1/logistics/shipments', $body); }
    public function listShipments(array $query = []): mixed { return $this->c->get('/v1/logistics/shipments', $query); }
    public function getShipment(string $idOrReference): mixed { return $this->c->get('/v1/logistics/shipments/' . self::s($idOrReference)); }
    public function cancelShipment(string $idOrReference): mixed { return $this->c->post('/v1/logistics/shipments/' . self::s($idOrReference) . '/cancel'); }
    public function simulateShipment(string $id, string $status): mixed { return $this->c->post('/v1/logistics/shipments/' . self::s($id) . '/simulate', ['status' => $status]); }
}

final class AddressVerifications extends Resource
{
    public function create(array $body): mixed { return $this->c->post('/v1/identity/address-verifications', $body); }
    public function list(array $query = []): mixed { return $this->c->get('/v1/identity/address-verifications', $query); }
    public function get(string $idOrReference): mixed { return $this->c->get('/v1/identity/address-verifications/' . self::s($idOrReference)); }
    public function simulateComplete(string $id, ?string $outcome = null): mixed
    {
        return $this->c->post('/v1/identity/address-verifications/' . self::s($id) . '/simulate-complete', $outcome === null ? [] : ['outcome' => $outcome]);
    }
}
