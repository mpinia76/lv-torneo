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
 * ¿Está dentro de una lista de parámetros o de un `use (...)`?
 *
 * Se camina hacia atrás saltando el tipo (`Request`, `?array`, `\App\X`) hasta
 * dar con el `(` o la `,` que abre el parámetro.
 */
$esParametro = function ($i) use ($tokens) {
    // OJO CON PHP 8: `\Throwable` y `\DOMNode` ya no son T_NS_SEPARATOR +
    // T_STRING, son un solo token T_NAME_FULLY_QUALIFIED. Sin contemplarlo,
    // todo `catch (\Throwable $e)` y todo parámetro con tipo con namespace
    // salían como "usada sin definirse" — seis avisos, todos falsos.
    $saltables = [T_STRING, T_ARRAY, T_NS_SEPARATOR, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_CALLABLE];

    foreach (['T_NAME_FULLY_QUALIFIED', 'T_NAME_QUALIFIED', 'T_NAME_RELATIVE'] as $nuevoToken) {
        if (defined($nuevoToken)) $saltables[] = constant($nuevoToken);
    }

    for ($j = $i - 1; $j >= 0; $j--) {
        $t = $tokens[$j];
        if (is_array($t) && in_array($t[0], $saltables, true)) continue;
        if ($t === '?' || $t === '&' || $t === '|') continue;
        return $t === '(' || $t === ',';
    }
    return false;
};

$siempre = ['this' => 1, 'GLOBALS' => 1, '_GET' => 1, '_POST' => 1, '_SERVER' => 1,
    '_SESSION' => 1, '_COOKIE' => 1, '_FILES' => 1, '_ENV' => 1, '_REQUEST' => 1];

$problemas = 0;
$metodo    = '(raíz)';
$definidas = $siempre;

for ($i = 0; $i < $n; $i++) {
    $t = $tokens[$i];

    // Sólo las funciones CON NOMBRE reinician el ámbito. Las anónimas heredan
    // lo de afuera a propósito: no es exacto, pero evita el ruido de tratar
    // cada closure como un ámbito nuevo, que fue lo que hizo inservible la
    // primera versión de este chequeo.
    if (is_array($t) && $t[0] === T_FUNCTION) {
        $sigue = $despuesDe($i);
        if (is_array($sigue) && $sigue[0] === T_STRING) {
            $metodo    = $sigue[1];
            $definidas = $siempre;
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
        || $esParametro($i);

    if ($define) {
        $definidas[$nombre] = 1;
        continue;
    }

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
