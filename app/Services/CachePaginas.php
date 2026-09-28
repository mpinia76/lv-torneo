<?php

namespace App\Services;

use Illuminate\Cache\FileStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Caché del sitio público.
 *
 * Guarda el HTML ya armado de las páginas públicas (ver
 * App\Http\Middleware\PaginaEnCache) y, para los listados pesados, el
 * resultado del cálculo antes de paginar (datos()).
 *
 * Cómo se mantiene al día sin tocar nada a mano:
 *
 *  - Toda consulta que escribe en la base (insert/update/delete…, venga de
 *    Eloquent, de DB::statement o de SQL crudo de los importadores) pasa por
 *    consulta(), que se engancha con DB::listen en AppServiceProvider.
 *  - La primera escritura de un request sube la "generación". La generación
 *    va dentro de todas las claves, así que lo guardado antes deja de usarse
 *    en el acto. Al terminar el request se sube otra vez (por si alguien armó
 *    una página con datos a medio cargar mientras tanto) y se vacía la
 *    carpeta, para que el disco no junte archivos que ya nadie va a leer.
 *  - Las escrituras que hacen las propias páginas públicas se ignoran: si no,
 *    cada visita borraría la caché.
 *  - Como red de seguridad, lo guardado vence solo (TTL) y además se tira todo
 *    al cambiar el día (hay páginas que dependen de la fecha de hoy). Para los
 *    cambios hechos por fuera de Laravel (phpMyAdmin) está el botón del admin.
 *
 * Vive en su propia carpeta (storage/framework/cache/paginas) y se arma acá
 * mismo, sin tocar config/cache.php: vaciarla nunca toca la caché general.
 */
class CachePaginas
{
    /** Vigencia de una página guardada, en segundos. */
    const TTL = 86400;

    const CLAVE_GENERACION = 'cache_paginas.generacion';
    const CLAVE_DIA        = 'cache_paginas.dia';

    /**
     * Tablas cuyas escrituras no cambian nada de lo que muestra el sitio
     * público: la propia caché, sesiones, colas, usuarios (el login guarda el
     * remember_token) y los tableros de trabajo de Verificar personas.
     */
    const TABLAS_IGNORADAS = [
        'cache', 'cache_locks', 'sessions', 'jobs', 'failed_jobs',
        'password_resets', 'users', 'persona_duplicados', 'persona_tokens',
    ];

    /** @var Repository|null */
    protected static $store;

    /** Hubo escrituras en este request. */
    protected static $huboCambio = false;

    /** Tablas escritas en este request (null = alguna que no se pudo leer). */
    protected static $tablas = [];

    /** Mientras se atiende una página pública, sus escrituras no cuentan. */
    protected static $ignorarEscrituras = false;

    public static function activa()
    {
        return filter_var(config('cache.paginas', true), FILTER_VALIDATE_BOOLEAN);
    }

    /** @return Repository */
    public static function store()
    {
        if (self::$store === null) {
            self::$store = new Repository(
                new FileStore(app('files'), storage_path('framework/cache/paginas'))
            );
        }

        return self::$store;
    }

    public static function generacion()
    {
        return (int) Cache::get(self::CLAVE_GENERACION, 0);
    }

    public static function ignorarEscrituras($si = true)
    {
        self::$ignorarEscrituras = (bool) $si;
    }

    /**
     * Resultado de un cálculo pesado, guardado mientras no cambien los datos.
     * $params son las entradas que cambian el resultado (filtros, año…).
     */
    public static function datos($nombre, array $params, \Closure $calcular)
    {
        if (!self::activa()) {
            return $calcular();
        }

        $gen   = self::generacion();
        $clave = 'datos.' . $nombre . '.' . md5(serialize($params)) . '.g' . $gen . '.' . date('Ymd');

        $valor = self::store()->get($clave);
        if ($valor !== null) {
            return $valor;
        }

        $valor = $calcular();

        // Si los datos cambiaron mientras se calculaba, no se guarda: podría
        // ser una foto a medio cargar.
        if (self::generacion() === $gen) {
            self::guardar($clave, $valor);
        }

        return $valor;
    }

    /** put() que nunca rompe la página: la caché es un extra. */
    public static function guardar($clave, $valor)
    {
        try {
            self::store()->put($clave, $valor, self::TTL);
        } catch (\Throwable $e) {
            Log::warning('CachePaginas: no se pudo guardar ' . $clave . ': ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Invalidación
    // ─────────────────────────────────────────────────────────────────────

    /** Llamado por DB::listen con cada consulta ejecutada. */
    public static function consulta($sql)
    {
        if (self::$ignorarEscrituras) {
            return;
        }

        if (!preg_match('/^\s*(insert|update|delete|replace|truncate|alter|drop|create|rename)\b/i', $sql)) {
            return;
        }

        $tabla = self::tabla($sql);
        if ($tabla !== null && in_array($tabla, self::TABLAS_IGNORADAS, true)) {
            return;
        }

        self::$tablas[] = $tabla;

        if (!self::$huboCambio) {
            self::$huboCambio = true;
            self::subirGeneracion();

            // Al terminar el request. terminating() no corre si el request muere
            // con un error fatal (tiempo máximo, memoria) y justo eso puede pasar
            // en una importación larga: por eso también el shutdown de PHP.
            // alTerminar() corre una sola vez aunque lo llamen los dos.
            app()->terminating(function () {
                CachePaginas::alTerminar();
            });
            register_shutdown_function(function () {
                CachePaginas::alTerminar();
            });
        }
    }

    /** Tabla afectada por un insert/update/delete simple, o null si no se entiende. */
    protected static function tabla($sql)
    {
        $patron = '/^\s*(?:insert(?:\s+ignore)?\s+into|replace\s+into|update(?:\s+ignore)?|delete\s+from|truncate(?:\s+table)?)\s+`?(\w+)`?(?:\s|\(|$)/i';

        if (preg_match($patron, $sql, $m)) {
            return strtolower($m[1]);
        }

        return null;
    }

    public static function alTerminar()
    {
        if (!self::$huboCambio) {
            return;
        }

        $tablas = self::$tablas;
        self::$huboCambio = false;
        self::$tablas = [];

        self::vaciarTodo(in_array('torneos', $tablas, true) || in_array(null, $tablas, true));
    }

    /**
     * Tira todo lo guardado. $menu: también el menú de torneos (solo hace
     * falta si cambió la tabla torneos; App\Torneo ya lo limpia al guardar).
     */
    public static function vaciarTodo($menu = true)
    {
        try {
            self::subirGeneracion();
            self::store()->flush();

            if ($menu) {
                MenuTorneos::olvidar();
            }
        } catch (\Throwable $e) {
            Log::warning('CachePaginas: no se pudo vaciar: ' . $e->getMessage());
        }
    }

    protected static function subirGeneracion()
    {
        try {
            Cache::forever(self::CLAVE_GENERACION, self::generacion() + 1);
        } catch (\Throwable $e) {
            Log::warning('CachePaginas: no se pudo subir la generación: ' . $e->getMessage());
        }
    }

    /**
     * Primer pedido de cada día: se tira lo del día anterior (la fecha ya va
     * en las claves; esto es para liberar el disco).
     */
    public static function cambioDeDia()
    {
        $hoy = date('Y-m-d');

        if (Cache::get(self::CLAVE_DIA) !== $hoy) {
            Cache::forever(self::CLAVE_DIA, $hoy);
            try {
                self::store()->flush();
            } catch (\Throwable $e) {
                Log::warning('CachePaginas: no se pudo vaciar por cambio de día: ' . $e->getMessage());
            }
        }
    }
}
