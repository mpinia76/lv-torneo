<?php

namespace App\Services;

/**
 * Imágenes livianas en las páginas públicas.
 *
 * Las fotos y escudos se suben desde el admin con su tamaño original (hay
 * escudos de 70 KB y fotos de 150 KB que se muestran a 22-116 px). El comando
 * `php artisan imagenes:miniaturas` deja una copia de 240 px como máximo en
 * public/img-min/ (mismo nombre, mismas subcarpetas), y acá:
 *
 *  1. Los <img> del HTML público que apuntan a /images/x.png|jpg pasan a
 *     /img-min/x.png|jpg. nginx sirve la miniatura si existe y, si no (imagen
 *     recién subida, todavía sin procesar), la original: nunca queda un hueco.
 *  2. Los <img> que no dicen nada de carga pasan a loading="lazy": la mayoría
 *     (formaciones, tablas) quedan fuera de la pantalla al abrir la página.
 *     Los que sí importan al abrir (marcador, foto de la ficha) llevan
 *     fetchpriority="high" en la vista y no se tocan.
 *
 * Solo HTML público (lo llama UrlAmigableSlugs): el admin sigue viendo las
 * originales (las usa para recortar) y el og:image no es un <img>, no cambia.
 * La cabecera (entre las marcas cp:cabecera) no se toca: se arma de nuevo en
 * cada visita servida desde la caché, y está arriba de todo.
 */
class ImagenesLivianas
{
    const MARCA_INI = '<!--cp:cabecera-->';
    const MARCA_FIN = '<!--/cp:cabecera-->';

    public static function html($html)
    {
        if (!is_string($html) || stripos($html, '<img') === false) {
            return $html;
        }

        // La cabecera queda como está.
        $ini = strpos($html, self::MARCA_INI);
        $fin = $ini !== false ? strpos($html, self::MARCA_FIN, $ini) : false;
        if ($ini !== false && $fin !== false) {
            $fin += strlen(self::MARCA_FIN);
            return self::procesar(substr($html, 0, $ini))
                . substr($html, $ini, $fin - $ini)
                . self::procesar(substr($html, $fin));
        }

        return self::procesar($html);
    }

    protected static function procesar($html)
    {
        return preg_replace_callback('/<img\b[^>]*>/i', function ($m) {
            $tag = $m[0];

            // 1. Miniatura (solo png/jpg de public/images; gif y svg ya son chicos).
            $tag = preg_replace(
                '#(\ssrc=")((?:https?://[^/"]+)?/)images/([^"]+\.(?:png|jpe?g)")#i',
                '$1$2img-min/$3',
                $tag
            );

            // 2. Carga diferida si la vista no decidió otra cosa.
            if (stripos($tag, 'loading=') === false && stripos($tag, 'fetchpriority=') === false) {
                $tag = preg_replace('#\s*/?>$#', ' loading="lazy" decoding="async"$0', $tag, 1);
            }

            return $tag;
        }, $html);
    }
}
