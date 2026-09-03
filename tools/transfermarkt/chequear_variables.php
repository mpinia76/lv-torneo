<?php
/**
 * Busca variables usadas antes de definirse, método por método.
 *
 *     php tools/transfermarkt/chequear_variables.php app/Http/Controllers/ImportDetallesController.php
 *
 * Existe porque `php -l` no ve este error y en producción `APP_DEBUG` está
 * apagado: una variable sin definir sale como **500 Server Error en blanco**,
 * que es lo peor que puede pasar justo en una pantalla de diagnóstico. Pasó de
 * verdad: `$pais` se usaba en el formulario, que se arma arriba, y se definía
 * cincuenta líneas más abajo, al lado del segundo uso.
 *
 * Usa el tokenizer de PHP, así que no se confunde con comentarios ni con
 * variables adentro de strings.
 *
 * Es un detector conservador: ante la duda **no** avisa. Prefiere dejar pasar
 * un caso raro antes que llenar la salida de ruido, porque un chequeo con
 * falsos positivos no lo corre nadie.
 */

$archivo = $argv[1] ?? '';

if ($archivo === '' || !is_file($archivo)) {
    fwrite(STDERR, "Uso: php chequear_variables.php <archivo.php>\n");
    exit(2);
}

$tokens = token_get_all(file_get_contents($archivo));
$n      = count($tokens);

/** El token anterior que no es espacio ni comentario. */
$antesDe = function ($i) use ($tokens) {
    for ($j = $i - 1; $j >= 0; $j--) {
        if (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) continue;
        return $tokens[$j];
    }
    return null;
};

/** El token siguiente que no es espacio ni comentario. */
$despuesDe = function ($i) use ($tokens, $n) {
    for ($j = $i + 1; $j < $n; $j++) {
        if (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) continue;
        return $tokens[$j];
    }
    return null;
};

/**
 * Los `(` que ABREN una lista de parámetros, no una lista de argumentos.
 *
 * ESTE ERA EL AGUJERO. La versión anterior decidía "es un parámetro" mirando
 * si atrás había un `(` o una `,`, y `insert($clave + [...])` cumple eso igual
 * que `function f($clave)`. Resultado: toda variable usada como primer
 * argumento de una llamada se daba por definida. Así pasó `$clave` en
 * `TmBuscarGameId::anotar()`, que en producción fueron 68 partidos apareados y
 * 68 «Undefined variable: clave» — con el chequeo en verde.
 *
 * Un `(` declara cuando lo precede `function`, `fn`, el nombre de una función
 * declarada, `use`, `catch` o `list`. Cualquier otro `(` es una llamada.
 */
$declara = [];
for ($i = 0; $i < $n; $i++) {
    if ($tokens[$i] !== '(') continue;

    $prev = $antesDe($i);
    if ($prev === null) continue;

    $abre = is_array($prev) && in_array($prev[0], [T_FUNCTION, T_USE, T_CATCH, T_LIST], true);
    if (!$abre && defined('T_FN') && is_array($prev) && $prev[0] === constant('T_FN')) $abre = true;

    // `function nombre(` → el token de atrás es el nombre, y atrás de ese, la
    // palabra `function`. Sin este paso, ningún método declararía nada.
    if (!$abre && is_array($prev) && $prev[0] === T_STRING) {
        for ($j = $i - 1; $j >= 0; $j--) {
            if (is_array($tokens[$j]) && in_array($tokens[$j][0],
                [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) continue;
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) continue;
            $abre = is_array($tokens[$j]) && $tokens[$j][0] === T_FUNCTION;
            break;
        }
    }

    if ($abre) $declara[$i] = 1;
}

/**
 * Funciones que definen un argumento en vez de leerlo (lo toman por
 * referencia). `preg_match($re, $txt, $m)` no usa `$m`: lo crea. Sin esta
 * lista, cada `preg_match` del proyecto salía como aviso.
 */
$porReferencia = ['preg_match' => 1, 'preg_match_all' => 1, 'sscanf' => 1,
    'similar_text' => 1, 'str_replace' => 1, 'str_ireplace' => 1,
    'preg_replace' => 1, 'parse_str' => 1, 'settype' => 1, 'exec' => 1,
    'array_multisort' => 1, 'openssl_sign' => 1];

/** El nombre de la función que abre este `(`, o '' si no es una llamada. */
$nombreLlamada = function ($abre) use ($tokens, $antesDe) {
    $prev = $antesDe($abre);
    return (is_array($prev) && $prev[0] === T_STRING) ? strtolower($prev[1]) : '';
};

/** El `(` o `[` que encierra al token $i, o null si está suelto. */
$encierra = function ($i) use ($tokens) {
    $hondo = 0;
    for ($j = $i - 1; $j >= 0; $j--) {
        $t = $tokens[$j];
        if ($t === ')' || $t === ']') { $hondo++; continue; }
        if ($t === '(' || $t === '[') {
            if ($hondo === 0) return [$j, $t];
            $hondo--;
            continue;
        }
        // Un `;` o una llave a nivel cero cortan: ya salimos de la expresión.
        if ($hondo === 0 && ($t === ';' || $t === '{' || $t === '}')) return null;
    }
    return null;
};

/**
 * ¿Es un destino de asignación desestructurada? `[$a, $b] = loQueSea()`.
 *
 * Define las dos variables, aunque por dentro parezcan un uso cualquiera.
 */
$esDestructuring = function ($abre) use ($tokens, $n, $despuesDe) {
    $hondo = 0;
    for ($j = $abre; $j < $n; $j++) {
        $t = $tokens[$j];
        if ($t === '[' || $t === '(') { $hondo++; continue; }
        if ($t === ')') { $hondo--; continue; }
        if ($t === ']') {
            $hondo--;
            if ($hondo === 0) return $despuesDe($j) === '=';
        }
    }
    return false;
};

$siempre = ['this' => 1, 'GLOBALS' => 1, 'argv' => 1, 'argc' => 1,
    '_GET' => 1, '_POST' => 1, '_SERVER' => 1,
    '_SESSION' => 1, '_COOKIE' => 1, '_FILES' => 1, '_ENV' => 1, '_REQUEST' => 1];

$problemas = 0;
$metodo    = '(raíz)';
$definidas = $siempre;

// Ámbitos anidados, contando llaves. `FechaController` declara funciones
// ADENTRO de un método, y sin llevar la cuenta el chequeo se quedaba con el
// ámbito de la función interna para todo el resto del archivo: nueve avisos
// falsos seguidos, que es la forma más segura de que nadie corra el chequeo.
$pila      = [];
$llaves    = 0;
$pendiente = null;   // vimos `function nombre` y estamos juntando sus parámetros
$params    = [];

for ($i = 0; $i < $n; $i++) {
    $t = $tokens[$i];

    // Abre llave: si venimos de declarar una función con nombre, acá empieza
    // su cuerpo, y con él un ámbito nuevo que hay que apilar.
    if ($t === '{' || (is_array($t)
            && in_array($t[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
        $llaves++;
        if ($pendiente !== null) {
            $pila[]    = [$metodo, $definidas, $llaves];
            $metodo    = $pendiente;
            $definidas = $siempre + $params;
            $pendiente = null;
            $params    = [];
        }
        continue;
    }

    if ($t === '}') {
        $llaves--;
        if ($pila && $llaves < $pila[count($pila) - 1][2]) {
            $ultimo    = array_pop($pila);
            $metodo    = $ultimo[0];
            $definidas = $ultimo[1];
        }
        continue;
    }

    // Método abstracto o de interfaz: se declara y se cierra con `;`, sin cuerpo.
    if ($t === ';' && $pendiente !== null) {
        $pendiente = null;
        $params    = [];
        continue;
    }

    // Sólo las funciones CON NOMBRE abren ámbito. Las anónimas heredan lo de
    // afuera a propósito: no es exacto, pero evita el ruido de tratar cada
    // closure como un ámbito nuevo, que fue lo que hizo inservible la primera
    // versión de este chequeo.
    if (is_array($t) && $t[0] === T_FUNCTION) {
        $sigue = $despuesDe($i);
        if (is_array($sigue) && $sigue[0] === T_STRING) {
            $pendiente = $sigue[1];
            $params    = [];
        }
        continue;
    }

    if (!is_array($t) || $t[0] !== T_VARIABLE) {
        continue;
    }

    $nombre = ltrim($t[1], '$');
    $sigue  = $despuesDe($i);
    $antes  = $antesDe($i);

    // `self::$loQueSea` es una propiedad estática de la clase, no una variable
    // local: no tiene nada que ver con este chequeo.
    if (is_array($antes) && $antes[0] === T_DOUBLE_COLON) {
        continue;
    }

    $iguales = [T_PLUS_EQUAL, T_MINUS_EQUAL, T_MUL_EQUAL, T_DIV_EQUAL, T_CONCAT_EQUAL,
        T_MOD_EQUAL, T_AND_EQUAL, T_OR_EQUAL, T_XOR_EQUAL, T_SL_EQUAL, T_SR_EQUAL];
    if (defined('T_COALESCE_EQUAL')) $iguales[] = T_COALESCE_EQUAL;

    $define = ($sigue === '=')
        || (is_array($sigue) && in_array($sigue[0], $iguales, true))
        || (is_array($sigue) && in_array($sigue[0], [T_INC, T_DEC], true))
        || (is_array($antes) && in_array($antes[0], [T_AS, T_DOUBLE_ARROW, T_LIST, T_CATCH,
            T_GLOBAL, T_STATIC, T_INC, T_DEC], true))
        || $antes === '&'
        // PHP 8.1 partió el `&` en dos tokens según el contexto: en
        // `array &$informe` ya no llega como el carácter '&'.
        || (is_array($antes) && defined('T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG')
            && $antes[0] === constant('T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG'))
        || (function () use ($i, $encierra, $declara, $esDestructuring,
                $porReferencia, $nombreLlamada) {
            $env = $encierra($i);
            if (!$env) return false;
            if ($env[1] === '(') {
                if (isset($declara[$env[0]])) return true;
                return isset($porReferencia[$nombreLlamada($env[0])]);
            }
            return $esDestructuring($env[0]);
        })();

    if ($define) {
        // Si todavía no entramos al cuerpo, esto es un parámetro: va al ámbito
        // que está por abrirse, no al de afuera.
        if ($pendiente !== null) $params[$nombre] = 1;
        else                     $definidas[$nombre] = 1;
        continue;
    }

    // Adentro de la lista de parámetros no hay usos que mirar.
    if ($pendiente !== null) continue;

    if (!isset($definidas[$nombre])) {
        printf("  %s(): \$%s en la línea %d\n", $metodo, $nombre, $t[2]);
        $problemas++;
        $definidas[$nombre] = 1;   // no repetir el mismo aviso
    }
}

echo $problemas
    ? "\n$problemas variable(s) usada(s) antes de definirse en $archivo\n"
    : "\nNinguna variable se usa antes de definirse en $archivo\n";

exit($problemas ? 1 : 0);
