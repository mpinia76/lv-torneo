<?php

namespace App\Services;

/**
 * El minuto de una incidencia: el del reloj y el del descuento.
 *
 * En la base una incidencia guarda DOS números:
 *
 *   · `minuto`     — el del reloj: 45, 90, 105, 120, o cualquiera anterior.
 *   · `adicionado` — el descuento, si lo hubo: el «+6» del 90+6. NULL o 0 es
 *                    lo mismo, y significa que el minuto es redondo.
 *
 * Nunca se suman al guardar. Sumarlos pierde información: 45+2 se confundiría
 * con el minuto 47 del segundo tiempo, y 90+6 con el 96 del primer tiempo
 * suplementario. Todo lo que necesite un solo número para comparar u ordenar
 * usa `orden()`, que es el criterio único de todo el sistema.
 *
 * El período NO se guarda: sale del minuto del reloj. Es la única forma de que
 * no pueda quedar en contra del minuto cuando alguien edita la incidencia a
 * mano.
 */
class MinutoHelper
{
    const PT  = 'PT';    // primer tiempo
    const ST  = 'ST';    // segundo tiempo
    const TS1 = 'TS1';   // primer tiempo suplementario
    const TS2 = 'TS2';   // segundo tiempo suplementario

    /**
     * Dónde cierra cada período. El minuto del reloj cae en el primer período
     * cuyo cierre no supera, y sólo estos cuatro minutos pueden llevar
     * descuento: el descuento existe porque el árbitro alarga un período.
     */
    private static $cierres = [
        45  => self::PT,
        90  => self::ST,
        105 => self::TS1,
        120 => self::TS2,
    ];

    private static $nombres = [
        self::PT  => 'primer tiempo',
        self::ST  => 'segundo tiempo',
        self::TS1 => '1º suplementario',
        self::TS2 => '2º suplementario',
    ];

    /**
     * El minuto tal como se escribe y se lee: «90», «90+6», «105+2».
     *
     * @param int|string|null $minuto
     * @param int|string|null $adicionado
     * @param string          $vacio  qué devolver cuando no hay minuto cargado
     */
    public static function texto($minuto, $adicionado = null, $vacio = '—')
    {
        if ($minuto === null || $minuto === '') return $vacio;
        $m = (int) $minuto;
        $a = ($adicionado === null || $adicionado === '') ? 0 : (int) $adicionado;
        return $a > 0 ? ($m . '+' . $a) : (string) $m;
    }

    /**
     * Lo mismo pero aclarando el período cuando la aclaración agrega algo.
     * En el primer y el segundo tiempo el minuto ya lo dice solo; en la
     * prórroga no, porque «105+2» no le dice nada a cualquiera.
     */
    public static function textoLargo($minuto, $adicionado = null, $vacio = '—')
    {
        $t = self::texto($minuto, $adicionado, $vacio);
        if ($t === $vacio) return $t;
        $p = self::periodo($minuto);
        if ($p === self::PT || $p === self::ST) return $t;
        return $t . ' (' . self::$nombres[$p] . ')';
    }

    /** PT / ST / TS1 / TS2 a partir del minuto del reloj. */
    public static function periodo($minuto)
    {
        if ($minuto === null || $minuto === '') return null;
        $m = (int) $minuto;
        foreach (self::$cierres as $cierre => $periodo) {
            if ($m <= $cierre) return $periodo;
        }
        return self::TS2;   // más de 120: no debería pasar, pero no inventamos un quinto período
    }

    /** «segundo tiempo», «1º suplementario»… */
    public static function periodoNombre($minuto)
    {
        $p = self::periodo($minuto);
        return $p === null ? null : self::$nombres[$p];
    }

    /** ¿El minuto está en la prórroga? */
    public static function esProrroga($minuto)
    {
        $p = self::periodo($minuto);
        return $p === self::TS1 || $p === self::TS2;
    }

    /**
     * El número con el que se compara y se ordena: minuto × 100 + descuento.
     *
     * Deja el 90 antes del 90+6, el 90+6 antes del 90+9 y los tres antes del
     * 91. Sumar el descuento al minuto no lo lograría: 90+6 daría 96, que
     * es más que el 91.
     *
     * Lo que no tiene minuto va al final (PHP_INT_MAX), que es donde va en
     * todas las listas del sistema.
     */
    public static function orden($minuto, $adicionado = null)
    {
        if ($minuto === null || $minuto === '') return PHP_INT_MAX;
        $a = ($adicionado === null || $adicionado === '') ? 0 : (int) $adicionado;
        return ((int) $minuto) * 100 + $a;
    }

    /** El mismo criterio para SQL: `(gols.minuto * 100 + COALESCE(gols.adicionado,0))`. */
    public static function sqlOrden($tabla = null)
    {
        $p = $tabla ? ($tabla . '.') : '';
        return '(' . $p . 'minuto * 100 + COALESCE(' . $p . 'adicionado, 0))';
    }

    /**
     * Lee lo que se escribe a mano: «90+6», «90 + 6», «90'», «90».
     *
     * Devuelve `['minuto' => int|null, 'adicionado' => int|null]`. Un texto que
     * no tenga ningún número devuelve los dos en NULL: sin minuto, que es un
     * estado válido en las cuatro tablas.
     */
    public static function parse($texto)
    {
        $vacio = ['minuto' => null, 'adicionado' => null];
        if ($texto === null) return $vacio;
        $t = trim((string) $texto);
        if ($t === '') return $vacio;

        if (!preg_match('/(\d+)\s*(?:\+\s*(\d+))?/', $t, $m)) return $vacio;

        $minuto = (int) $m[1];
        $adic   = (isset($m[2]) && $m[2] !== '') ? (int) $m[2] : null;

        return self::normalizar($minuto, $adic);
    }

    /**
     * Acomoda un par (minuto, descuento) al criterio de la base.
     *
     * Dos cosas:
     *   · El descuento 0 se guarda como NULL — un solo valor para «sin
     *     descuento», así las comparaciones no dependen de cuál se escribió.
     *   · Un descuento colgado de un minuto que no cierra ningún período
     *     («70+3») se suma, porque el que lo escribió quiso decir el minuto 73.
     *     El descuento sólo existe en el 45, el 90, el 105 y el 120.
     */
    public static function normalizar($minuto, $adicionado = null)
    {
        if ($minuto === null || $minuto === '') return ['minuto' => null, 'adicionado' => null];

        $m = (int) $minuto;
        $a = ($adicionado === null || $adicionado === '') ? 0 : (int) $adicionado;

        if ($a > 0 && !isset(self::$cierres[$m])) {
            $m += $a;
            $a  = 0;
        }

        return ['minuto' => $m, 'adicionado' => $a > 0 ? $a : null];
    }

    /**
     * Las formas en las que ESTE minuto pudo haber quedado guardado antes de
     * que existiera la columna `adicionado`. Sirve para aparear una acción de
     * Transfermarkt contra una fila vieja:
     *
     *   · el importador de TM tiraba el descuento  → 90+6 quedó como 90
     *   · el scraper de promiedos lo sumaba        → 90+6 quedó como 96
     *     (salvo el del primer tiempo, que colapsaba: 45+2 quedó como 45)
     *
     * Devuelve los minutos del reloj que hay que aceptar como equivalentes.
     */
    public static function formasViejas($minuto, $adicionado = null)
    {
        if ($minuto === null || $minuto === '') return [];
        $m = (int) $minuto;
        $a = ($adicionado === null || $adicionado === '') ? 0 : (int) $adicionado;

        $formas = [$m => true];
        if ($a > 0) $formas[$m + $a] = true;

        return array_keys($formas);
    }
}
