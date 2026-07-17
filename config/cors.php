<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CORS — restrito à API v1 do app mobile (Diário)
    |--------------------------------------------------------------------------
    |
    | Só as rotas `api/v1/*` recebem cabeçalhos CORS. As rotas internas do
    | Inertia (`api/diario/*` etc.) são same-origin e ficam de fora de propósito.
    |
    | O app mobile autentica por TOKEN (Authorization: Bearer ...), não por
    | cookie de sessão — por isso `supports_credentials` é false e podemos
    | permitir origens amplas com segurança. Builds nativos do Expo não passam
    | por CORS (não é browser); isto atende Expo Web/dev e futuros clientes web.
    |
    | Restrinja as origens em produção via APP_CORS_ORIGINS (CSV).
    | Ex.: APP_CORS_ORIGINS="https://app.exemplo.gov.br,https://homolog.exemplo.gov.br"
    |
    */

    'paths' => ['api/v1/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(
        explode(',', (string) env('APP_CORS_ORIGINS', '*'))
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
