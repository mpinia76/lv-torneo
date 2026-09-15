{{-- Qué hay cargado y por qué se sabe mal.

     El marcador guardado es el `goalsTotal` de Transfermarkt, que en un partido
     con `gameState: penalty_shootout` viene con los penales SUMADOS: el 6:4 de
     Real Madrid-Atlético 2016 es 1:1 más la tanda 5-3. Por eso el chip muestra
     el número tal como está guardado —ese es el dato equivocado— y no se
     arriesga una descomposición: el payload del motor DT no trae la tanda, y
     cuál es el reparto sólo lo sabe el detalle del partido.

     Si `penalesl` tiene algo, el partido ya pasó por «Marcador» o se corrigió a
     mano y no debería estar en esta lista: se avisa en vez de esconderlo. --}}
<span class="ctrl-chip mal">{{ (int) $fila->golesl }}:{{ (int) $fila->golesv }} con la tanda sumada</span>

@if($fila->penalesl !== null || $fila->penalesv !== null)
    <span class="ctrl-chip">ya tiene tanda {{ $fila->penalesl }}-{{ $fila->penalesv }}</span>
@endif

<span class="ctrl-sub">el resultado real lo separa «Marcador», que baja el detalle del partido</span>
