<?php
/**
 * StackApi PHP SDK (Packagist: stackapi/stackapi-php). PHP 8.1+ with the curl and json extensions; no other dependencies.
 *
 *   $stackapi = new StackApi\StackApi(getenv('STACKAPI_SECRET_KEY'), getenv('STACKAPI_PUBLIC_KEY'));
 *   $sms = $stackapi->notifications->sendSms(['to' => '08031234567', 'message' => 'Your code is 123456']);
 *
 * Every method sends the array you give it as JSON and returns the API's JSON answer as an array. A non-2xx answer
 * throws StackApiException with the HTTP status, StackApi's error code (for example INSUFFICIENT_BALANCE) and message.
 * Reads (GET) are retried on network errors, 429 and 502/503/504 with backoff; writes are never retried by the SDK
 * (send your own `reference` and repeat the call: the API answers the first result again).
 */
declare(strict_types=1);

namespace StackApi;

final class StackApi
{
    public const VERSION = '1.0.0';
    public const DEFAULT_BASE_URL = 'https://api.stackapi.app';

    public readonly Resources\Notifications $notifications;
    public readonly Resources\Payments $payments;
    public readonly Resources\Identity $identity;
    public readonly Resources\Vtu $vtu;
    public readonly Resources\Payouts $payouts;
    public readonly Resources\Banking $banking;
    public readonly Resources\Fx $fx;
    public readonly Resources\Cards $cards;
    public readonly Resources\Logistics $logistics;
    public readonly Resources\AddressVerifications $addressVerifications;

    private string $baseUrl;
    /** @var callable(string, string, array<string,string>, ?string, int): array{0:int,1:array<string,string>,2:string} */
    private $transport;

    /**
     * @param array{base_url?: string, timeout?: int, max_retries?: int, transport?: callable} $options
     */
    public function __construct(
        private readonly string $secretKey,
        private readonly string $publicKey,
        private readonly array $options = []
    ) {
        if ($secretKey === '' || $publicKey === '') {
            throw new \InvalidArgumentException('StackApi needs the secret key and the public key (create them on the API keys page).');
        }
        $this->baseUrl = rtrim($options['base_url'] ?? self::DEFAULT_BASE_URL, '/');
        $this->transport = $options['transport'] ?? [self::class, 'curlTransport'];
        $this->notifications = new Resources\Notifications($this);
        $this->payments = new Resources\Payments($this);
        $this->identity = new Resources\Identity($this);
        $this->vtu = new Resources\Vtu($this);
        $this->payouts = new Resources\Payouts($this);
        $this->banking = new Resources\Banking($this);
        $this->fx = new Resources\Fx($this);
        $this->cards = new Resources\Cards($this);
        $this->logistics = new Resources\Logistics($this);
        $this->addressVerifications = new Resources\AddressVerifications($this);
    }

    public function isTest(): bool
    {
        return str_contains($this->secretKey, '-test-');
    }

    /**
     * Any endpoint the API key can call, for the APIs without a helper here (commerce, ledger, tax, ...).
     *
     * @param array<string, mixed>|null $query
     * @return mixed the decoded JSON answer
     */
    public function request(string $method, string $path, ?array $query = null, mixed $body = null): mixed
    {
        $m = strtoupper($method);
        $url = $this->baseUrl . (str_starts_with($path, '/') ? $path : '/' . $path);
        if ($query) {
            $q = [];
            foreach ($query as $k => $v) {
                if ($v === null) {
                    continue;
                }
                $q[$k] = is_bool($v) ? ($v ? 'true' : 'false') : (string) $v;
            }
            if ($q) {
                $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($q, '', '&', PHP_QUERY_RFC3986);
            }
        }
        $headers = [
            'X-API-Key' => $this->secretKey,
            'X-Public-Key' => $this->publicKey,
            'Accept' => 'application/json',
            'User-Agent' => 'stackapi-php/' . self::VERSION,
        ];
        $payload = null;
        if ($m !== 'GET' && ($body !== null || in_array($m, ['POST', 'PUT', 'PATCH'], true))) {
            $headers['Content-Type'] = 'application/json';
            $payload = json_encode($body ?? new \stdClass(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }
        $retries = $m === 'GET' ? max(0, (int) ($this->options['max_retries'] ?? 2)) : 0;
        $timeout = (int) ($this->options['timeout'] ?? 30);
        for ($attempt = 0; ; $attempt++) {
            try {
                [$status, $respHeaders, $text] = ($this->transport)($m, $url, $headers, $payload, $timeout);
            } catch (NetworkException $e) {
                if ($attempt < $retries) {
                    usleep((int) (500000 * (2 ** $attempt)));
                    continue;
                }
                throw new StackApiException(0, 'NETWORK_ERROR', 'Could not reach StackApi: ' . $e->getMessage(), null);
            }
            $data = $text === '' ? null : json_decode($text, true);
            if ($text !== '' && json_last_error() !== JSON_ERROR_NONE) {
                $data = $text;
            }
            if ($status >= 200 && $status < 300) {
                return $data;
            }
            if ($attempt < $retries && in_array($status, [429, 502, 503, 504], true)) {
                $ra = null;
                foreach ($respHeaders as $k => $v) {
                    if (strtolower((string) $k) === 'retry-after') {
                        $ra = (float) $v;
                    }
                }
                usleep((int) (($ra !== null && $ra > 0 ? min($ra, 30.0) : 0.5 * (2 ** $attempt)) * 1000000));
                continue;
            }
            $code = is_array($data) ? ($data['code'] ?? null) : null;
            $msg = is_array($data) ? ($data['error'] ?? $data['message'] ?? null) : null;
            throw new StackApiException($status, $code === null ? null : (string) $code, (string) ($msg ?? 'HTTP ' . $status), $data);
        }
    }

    /** @internal */
    public function get(string $path, ?array $query = null): mixed
    {
        return $this->request('GET', $path, $query);
    }

    /** @internal */
    public function post(string $path, ?array $body = null): mixed
    {
        return $this->request('POST', $path, null, $body === null || $body === [] ? new \stdClass() : $body);
    }

    /** @internal */
    public function put(string $path, ?array $body = null): mixed
    {
        return $this->request('PUT', $path, null, $body === null || $body === [] ? new \stdClass() : $body);
    }

    /** @internal */
    public function delete(string $path): mixed
    {
        return $this->request('DELETE', $path);
    }

    /**
     * @param array<string,string> $headers
     * @return array{0:int,1:array<string,string>,2:string}
     */
    public static function curlTransport(string $method, string $url, array $headers, ?string $body, int $timeout): array
    {
        $ch = curl_init($url);
        $lines = [];
        foreach ($headers as $k => $v) {
            $lines[] = $k . ': ' . $v;
        }
        $respHeaders = [];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$respHeaders): int {
                $p = strpos($line, ':');
                if ($p !== false) {
                    $respHeaders[trim(substr($line, 0, $p))] = trim(substr($line, $p + 1));
                }
                return strlen($line);
            },
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $text = curl_exec($ch);
        if ($text === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new NetworkException($err);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return [$status, $respHeaders, (string) $text];
    }

    /** @internal */
    public static function seg(string $v): string
    {
        return rawurlencode($v);
    }
}
