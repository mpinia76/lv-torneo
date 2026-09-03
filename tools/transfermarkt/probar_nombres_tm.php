<?php
/**
 * Banco de pruebas de App\Services\NombreHelper::separarTM().
 *
 *     php tools/transfermarkt/probar_nombres_tm.php
 *
 * Nació de un desastre concreto: bajando Pyramids FC – Auckland City se
 * crearon 22 fichas con el nombre en árabe y una en chino, porque el
 * `passportName` de Transfermarkt viene en el alfabeto original para varios
 * países y era el candidato de mayor prioridad. En un sitio en castellano eso
 * es ilegible, no se puede buscar ni ordenar, y se duplica sin que nadie lo
 * note.
 *
 * Los casos latinos están para lo otro que importa: que el arreglo **no cambie
 * nada** de lo que ya funcionaba.
 */

require __DIR__ . '/../../app/Services/NombreHelper.php';

use App\Services\NombreHelper;

$fallos = 0;

function chequear($ok, $texto) {
    global $fallos;
    if (!$ok) { $fallos++; echo "  FALLA: $texto\n"; } else { echo "  ok: $texto\n"; }
}

function probar($titulo, array $datos, $nombre, $apellido, $name = null) {
    $r = NombreHelper::separarTM($datos);
    $bien = $r['nombre'] === $nombre && $r['apellido'] === $apellido
        && ($name === null || $r['name'] === $name);
    chequear($bien, $titulo . ' → ' . var_export($r['nombre'], true) . ' / '
        . var_export($r['apellido'], true) . ' / ' . var_export($r['name'], true));
}

echo "\n== Lo que ya andaba tiene que seguir igual\n";
probar('inicial + apellido',
    ['name' => 'Calvo', 'shortName' => 'F. Calvo', 'displayName' => 'Francisco Javier Calvo Quesada'],
    'Francisco Javier', 'Calvo Quesada', 'F. Calvo');

probar('passportName manda sobre displayName',
    ['name' => 'Uribe', 'shortName' => 'Tomás Uribe',
     'displayName' => 'Tomás Uribe', 'nationalityDetails' => ['passportName' => 'Tomás Andrés Uribe Pérez']],
    'Tomás Andrés', 'Uribe Pérez', 'Tomás Uribe');

probar('mononimo',
    ['name' => 'Marcão', 'shortName' => 'Marcão', 'displayName' => 'Marcão'],
    'Marcão', 'Marcão', 'Marcão');

echo "\n== El alfabeto: el caso que rompió\n";
// El passportName en árabe ya no gana: se usa la forma latina que TM igual trae.
probar('árabe en passportName, latino en displayName',
    ['name' => 'El Shenawy', 'shortName' => 'A. El Shenawy', 'displayName' => 'Ahmed El Shenawy',
     'nationalityDetails' => ['passportName' => 'احمد الشناوي']],
    'Ahmed', 'El Shenawy', 'A. El Shenawy');

probar('árabe también en displayName: cae al name',
    ['name' => 'Osama Galal', 'shortName' => 'Osama Galal', 'displayName' => 'أسامة جلال',
     'nationalityDetails' => ['passportName' => 'أسامة جلال']],
    'Osama', 'Galal', 'Osama Galal');

probar('chino en passportName y shortName, latino en name',
    ['name' => 'Tong Zhou', 'shortName' => '周通', 'displayName' => '周通',
     'nationalityDetails' => ['passportName' => '周通']],
    'Tong', 'Zhou', 'Tong Zhou');

echo "\n== Si TM no tiene ninguna forma latina, se carga igual\n";
// Mejor el nombre en su alfabeto que una ficha sin nombre: el import no se
// aborta, y después se puede corregir a mano.
$r = NombreHelper::separarTM(['name' => 'الكرتي', 'shortName' => 'الكرتي',
    'displayName' => 'وليد الكرتي', 'nationalityDetails' => ['passportName' => 'وليد الكرتي']]);
chequear($r['nombre'] !== '' && $r['apellido'] !== '', 'no devuelve vacío: ' . var_export($r['nombre'], true)
    . ' / ' . var_export($r['apellido'], true));

echo "\n== Mezclas\n";
probar('acentos y partículas siguen andando',
    ['name' => 'da Silva', 'shortName' => 'N. da Silva', 'displayName' => 'Nadson Juan Maia da Silva de Souza'],
    'Nadson Juan Maia', 'da Silva de Souza', 'N. da Silva');

echo "\n" . ($fallos ? "$fallos CHEQUEO(S) FALLIDO(S)\n" : "Todo en verde.\n");
exit($fallos ? 1 : 0);
