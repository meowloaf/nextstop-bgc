<?php

function googleConfig(): array
{
    $localEnvironment = [];
    $environmentPath = __DIR__ . '/../.env';

    if (is_file($environmentPath)) {
        $localEnvironment = parse_ini_file($environmentPath, false, INI_SCANNER_RAW) ?: [];
    }

    $clientId = getenv('GOOGLE_CLIENT_ID') ?: ($localEnvironment['GOOGLE_CLIENT_ID'] ?? null);
    $clientSecret = getenv('GOOGLE_CLIENT_SECRET') ?: ($localEnvironment['GOOGLE_CLIENT_SECRET'] ?? null);
    $redirectUri = getenv('GOOGLE_REDIRECT_URI') ?: ($localEnvironment['GOOGLE_REDIRECT_URI'] ?? null);

    if (!$clientId || !$clientSecret || !$redirectUri) {
        throw new RuntimeException('Google login is not configured.');
    }

    return [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri,
    ];
}
