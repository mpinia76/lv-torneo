<?php

namespace App\Http\Middleware;

use App\Services\UrlAmigable;
use Closure;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Completa los slugs de los links de una página pública con una consulta por
 * tipo (ver App\Services\UrlAmigable::completarTexto()).
 *
 * Va DESPUÉS de 'pagina.cache': la copia que se guarda ya tiene los links
 * completos, y una visita servida desde la caché no pasa por acá.
 */
class UrlAmigableSlugs
{
    public function handle($request, Closure $next)
    {
        UrlAmigable::diferir(true);
        try {
            $response = $next($request);
        } finally {
            UrlAmigable::diferir(false);
        }

        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return $response;
        }

        if ($response instanceof RedirectResponse) {
            $response->setTargetUrl(UrlAmigable::completarTexto($response->getTargetUrl()));
            return $response;
        }

        if ($response->headers->has('Location')) {
            $response->headers->set('Location', UrlAmigable::completarTexto($response->headers->get('Location')));
        }

        $contenido = $response->getContent();
        if (is_string($contenido) && strpos($contenido, '~ua.') !== false) {
            $response->setContent(UrlAmigable::completarTexto($contenido));
        }

        return $response;
    }
}
