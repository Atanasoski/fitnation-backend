<?php

namespace App\Webhooks\RevenueCat;

use Illuminate\Http\Request;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;

class RevenueCatSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        $header = $request->header($config->signatureHeaderName);

        if (! is_string($header) || $header === '') {
            return false;
        }

        $secret = $config->signingSecret;

        if (! is_string($secret) || $secret === '') {
            return false;
        }

        // RevenueCat sends the secret as `Authorization: Bearer <secret>`.
        $provided = preg_replace('/^Bearer\s+/i', '', $header);

        return hash_equals($secret, $provided);
    }
}
