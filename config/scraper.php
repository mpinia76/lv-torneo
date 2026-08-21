<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ScraperAPI
    |--------------------------------------------------------------------------
    |
    | Transfermarkt (web y tmapi) está geo-bloqueado por CloudFront para
    | nuestro servidor, así que todo sale por ScraperAPI. Ver App\Services\HttpHelper.
    |
    | key         Clave principal. Se usa en el modo básico (1 crédito por
    |             request): tmapi, HTML de transfermarkt y fotos.
    | key_render  Clave para las llamadas con render+premium (25 créditos),
    |             que son las de livefutbol/footballdatabase. Si la dejás
    |             vacía se usa `key`.
    | country     País de salida. 'eu' es el que pasa el geo-block de tmapi.
    |             Geotargeting no cuesta créditos extra.
    |
    */

    'key'        => env('SCRAPERAPI_KEY', ''),
    'key_render' => env('SCRAPERAPI_KEY_RENDER', ''),
    'country'    => env('SCRAPERAPI_COUNTRY', 'eu'),

    /*
    |--------------------------------------------------------------------------
    | Proxy propio (desactivado)
    |--------------------------------------------------------------------------
    |
    | Respaldo para cuando se agotan los créditos. Descartado en ago-2026:
    | el bloqueo de tmapi parece ser por ASN de datacenter, no por país, así
    | que ningún VPS pasa. Dejar TM_PROXY_URL vacío lo mantiene apagado.
    |
    */

    'proxy_url'   => env('TM_PROXY_URL', ''),
    'proxy_token' => env('TM_PROXY_TOKEN', ''),

];
