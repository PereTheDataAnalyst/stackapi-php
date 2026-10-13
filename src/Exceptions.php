<?php
declare(strict_types=1);

namespace StackApi;

/** A non-2xx answer from StackApi (or no answer: status 0, code NETWORK_ERROR). */
class StackApiException extends \RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly ?string $errorCode,
        string $message,
        public readonly mixed $body = null
    ) {
        parent::__construct($message, $status);
    }
}

/** The request never reached StackApi (thrown by the transport, turned into StackApiException by the client). */
class NetworkException extends \RuntimeException
{
}

/** A webhook whose signature or timestamp is not right. */
class WebhookVerificationException extends \RuntimeException
{
}
