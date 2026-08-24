{{--
    Los botones de cada fila.

    Los primeros los declara cada chequeo en `Controles::definiciones()` y
    llevan a la pantalla donde se corrige a mano. Los dos últimos valen para
    cualquier chequeo, porque la fila siempre es un partido:

      TM       - Transfermarkt. Está SIEMPRE, en todos los controles y en todas
                 las filas. Adónde lleva depende de lo que sepamos del partido
                 (lo resuelve `Controles::agregarTransfermarkt()`):
                   partido  -> la ficha exacta, cuando hay gameId;
                   club     -> el calendario del club en esa temporada;
                   busqueda -> la búsqueda por el nombre del equipo local.
                 Los dos últimos van más apagados y con un `?` atrás, para que
                 se vea de una que no es la ficha del partido.
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

    // El link a TM lo arma el servicio. Si la fila viniera de otro lado y no lo
    // trajera, se arma acá el de último recurso antes que quedarse sin botón.
    $tmUrl    = $fila->tm_url ?? (\App\Services\Controles::TM_BUSQUEDA.rawurlencode((string) ($fila->equipo_local_nombre ?? '')));
    $tmNivel  = $fila->tm_nivel ?? 'busqueda';
    $tmTitulo = $fila->tm_titulo ?? 'Buscar en Transfermarkt.';
@endphp

@foreach($def['acciones'] as $accion)
    @if(isset($mapa[$accion]))
        <a href="{{ $mapa[$accion][2] }}" class="{{ $mapa[$accion][1] }}">{{ $mapa[$accion][0] }}</a>
    @endif
@endforeach

<a href="{{ $tmUrl }}" target="_blank" rel="noopener"
   class="ctrl-b-tm {{ $tmNivel === 'partido' ? '' : 'ctrl-b-tm-aprox' }}"
   title="{{ $tmTitulo }}">TM{{ $tmNivel === 'partido' ? '' : '?' }}</a>

@if($gameId)
    <a href="{{ route('import_detalles.ver', ['partido_id' => $partido]) }}" target="_blank" rel="noopener"
       class="ctrl-b-rehacer"
       title="Vuelve a bajar alineación, goles, tarjetas, cambios y árbitros desde Transfermarkt. Primero muestra qué va a escribir.">Rehacer</a>
@else
    <a href="{{ route('fechas.importarPartido', ['partidoId' => $partido]) }}" target="_blank" rel="noopener"
       class="ctrl-b-rehacer"
       title="Este partido no pasó por el importador, así que no tenemos su gameId: pegando la URL de Transfermarkt se le baja el detalle igual.">Rehacer</a>
@endif
