{{-- Qué hay cargado y por qué se sabe mal.

     El marcador guardado es el `goalsTotal` de Transfermarkt, que en un partido
     con `gameState: penalty_shootout` viene con los penales SUMADOS: el 6:4 de
     Real Madrid-Atlético 2016 es 1:1 más la tanda 5-3.

     Se muestra el número tal como está guardado —ese es el dato equivocado— y
     no se arriesga una descomposición: el payload del motor DT no trae la
     tanda, y cuál es el reparto sólo lo sabe el detalle del partido.

     La consulta ya deja afuera los que tienen `penalesl` cargado: ésos están
     bien, el marcador es el de los 90' y la tanda va aparte. --}}
<span class="ctrl-chip mal">{{ (int) $fila->golesl }}:{{ (int) $fila->golesv }} con la tanda sumada</span>
<span class="ctrl-chip">sin tanda cargada</span>

<span class="ctrl-sub">el resultado real lo separa «Marcador», que baja el detalle del partido</span>
