{{--
    Los botones de cada fila.

    Los primeros los declara cada chequeo en `Controles::definiciones()` y
    llevan a la pantalla donde se corrige a mano. Los dos últimos valen para
    cualquier chequeo, porque la fila siempre es un partido:

      TM       - la ficha del partido en Transfermarkt (solo si el partido pasó
                 por el importador y tenemos su gameId).
      Rehacer  - vuelve a bajar el detalle completo. Con gameId va a la vista
                 previa del importador (que muestra qué va a escribir antes de
                 tocar nada); sin gameId, a la pantalla de pegar la URL.

    Los dos abren en otra pestaña: la idea es no perder el lugar en la lista.
--}}
@php
    $partido = $fila->id;
    $mapa = [
        'alineaciones' => ['Alineaciones', 'ctrl-b-verde', route('alineaciones.index', ['partidoId' => $partido])],
        'goles'        => ['Goles',        'ctrl-b-azul',  route('goles.index', ['partidoId' => $partido, 'totalGoles' => (int) $fila->golesl + (int) $fila->golesv])],
        'tarjetas'     => ['Tarjetas',     'ctrl-b-azul',  route('tarjetas.index', ['partidoId' => $partido])],
        'cambios'      => ['Cambios',      'ctrl-b-azul',  route('cambios.index', ['partidoId' => $partido])],
        'penales'      => ['Penales',      'ctrl-b-azul',  route('penales.index', ['partidoId' => $partido])],
        'jueces'       => ['Jueces',       'ctrl-b-azul',  route('partidos.arbitros', ['partidoId' => $partido])],
        // Marcar el partido como excepción: con incidencia deja de aparecer
        // en los controles.
        'incidencia'   => ['Incidencia',   'ctrl-b-gris',  route('incidencias.create', ['torneoId' => $fila->torneo_id, 'partidoId' => $partido])],
    ];

    $gameId = $fila->tm_game_id ?? null;
@endphp

@foreach($def['acciones'] as $accion)
    @if(isset($mapa[$accion]))
        <a href="{{ $mapa[$accion][2] }}" class="{{ $mapa[$accion][1] }}">{{ $mapa[$accion][0] }}</a>
    @endif
@endforeach

@if($gameId)
    <a href="{{ \App\Services\Controles::TM_PARTIDO . $gameId }}" target="_blank" rel="noopener"
       class="ctrl-b-tm" title="Ver el partido en Transfermarkt (gameId {{ $gameId }})">TM</a>
    <a href="{{ route('import_detalles.ver', ['partido_id' => $partido]) }}" target="_blank" rel="noopener"
       class="ctrl-b-rehacer"
       title="Vuelve a bajar alineación, goles, tarjetas, cambios y árbitros desde Transfermarkt. Primero muestra qué va a escribir.">Rehacer</a>
@else
    <a href="{{ route('fechas.importarPartido', ['partidoId' => $partido]) }}" target="_blank" rel="noopener"
       class="ctrl-b-rehacer"
       title="Este partido no pasó por el importador, así que no tenemos su gameId: pegando la URL de Transfermarkt se le baja el detalle igual.">Rehacer</a>
@endif
