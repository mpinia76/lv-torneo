{{--
    Los botones de cada fila.

    Los primeros los declara cada chequeo en `Controles::definiciones()` y
    llevan a la pantalla donde se corrige a mano. Los dos últimos valen para
    cualquier chequeo, porque la fila siempre es un partido:

      TM       - la ficha DEL PARTIDO en Transfermarkt. Sale solo cuando
                 tenemos el gameId, porque es lo único que arma esa URL. Se
                 probó llenar el hueco con el calendario del club o con la
                 búsqueda del equipo y no sirve: un botón que dice TM tiene que
                 abrir el partido, no un lugar desde donde buscarlo.
      Rehacer  - vuelve a bajar el detalle desde TM, y está en TODAS las filas
                 de todos los controles. Siempre va a la vista previa del
                 importador, que muestra qué va a escribir antes de tocar nada.
                 Si el partido no tiene gameId, la vista previa lo busca sola en
                 TM (fixture de los clubes + partidos de los DTs) y lo deja
                 anotado; recién si no lo encuentra pide la URL a mano.

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
@endif

<a href="{{ route('import_detalles.ver', ['partido_id' => $partido]) }}" target="_blank" rel="noopener"
   class="ctrl-b-rehacer"
   title="Vuelve a bajar alineación, goles, tarjetas, cambios y árbitros desde Transfermarkt. Si el partido no tiene gameId, lo busca solo por los clubes y la fecha. Primero muestra qué va a escribir.">Rehacer</a>

{{--
    "Sin datos en TM": la incidencia de un click.

    Hay partidos que no tienen arreglo posible —la ficha de TM dice "no data
    available" para uno de los dos equipos— y Rehacer no cambia nada. Este
    botón carga la incidencia con equipo y puntos vacíos (así no se publica en
    el front ni toca las posiciones) y el partido sale de todos los controles.

    Sale solo en los chequeos que ya ofrecen "Incidencia": marcar una excepción
    tiene sentido donde el error puede venir del origen, no en un gol repetido.

    Es un POST, no un link: la pantalla no escribe sola, igual que los penales.
--}}
@if(in_array('incidencia', $def['acciones']))
    <form method="POST" action="{{ route('controles.sinDatos') }}" class="ctrl-form-inline"
          onsubmit="return confirm('El partido queda marcado como excepción y deja de aparecer en TODOS los controles. ¿Seguro?')">
        @csrf
        <input type="hidden" name="partido_id" value="{{ $partido }}">
        <button type="submit" class="ctrl-b-sindatos"
                title="Transfermarkt no tiene el detalle de este partido: se carga la incidencia y sale de los controles.">Sin datos en TM</button>
    </form>
@endif
