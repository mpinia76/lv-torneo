@props(['p', 'torneo' => true, 'destacar' => null, 'neutral' => false])

@php
    // El sitio lista partidos en muchas pantallas y no siempre con la misma forma:
    // a veces llega el modelo Partido (con relaciones) y a veces una fila plana de
    // una consulta SQL (nombreTorneo, fotoLocal, local, ...). Acá se normaliza.
    $val = function ($obj, $campo) {
        return (is_object($obj) && isset($obj->$campo)) ? $obj->$campo : null;
    };

    $id = $val($p, 'partido_id') ?? $val($p, 'id');

    $fechaRel  = $val($p, 'fecha');
    $torneoRel = $fechaRel ? optional(optional($fechaRel)->grupo)->torneo : null;

    $torneoNombre = $val($p, 'nombreTorneo')  ?? optional($torneoRel)->nombre;
    $torneoYear   = $val($p, 'year')          ?? optional($torneoRel)->year;
    $torneoEscudo = $val($p, 'escudoTorneo')  ?? optional($torneoRel)->escudo;
    $numero       = $val($p, 'numero')        ?? optional($fechaRel)->numero;
    $numeroTxt    = $numero !== null && $numero !== ''
                        ? (is_numeric($numero) ? 'Fecha ' . $numero : $numero)
                        : '';

    $equipoL = $val($p, 'equipol');
    $equipoV = $val($p, 'equipov');

    $localId     = $val($p, 'equipol_id')     ?? optional($equipoL)->id;
    $localNombre = $val($p, 'local')          ?? optional($equipoL)->nombre;
    $localEscudo = $val($p, 'fotoLocal')      ?? optional($equipoL)->escudo;
    $localPais   = $val($p, 'paisLocal')      ?? optional($equipoL)->pais;

    $visitaId     = $val($p, 'equipov_id')    ?? optional($equipoV)->id;
    $visitaNombre = $val($p, 'visitante')     ?? optional($equipoV)->nombre;
    $visitaEscudo = $val($p, 'fotoVisitante') ?? optional($equipoV)->escudo;
    $visitaPais   = $val($p, 'paisVisitante') ?? optional($equipoV)->pais;

    $banderaL = optional($equipoL)->bandera_url
                ?? ($localPais ? url('images/' . removeAccents($localPais) . '.gif') : null);
    $banderaV = optional($equipoV)->bandera_url
                ?? ($visitaPais ? url('images/' . removeAccents($visitaPais) . '.gif') : null);

    $golesl   = $val($p, 'golesl');
    $golesv   = $val($p, 'golesv');
    $penalesl = $val($p, 'penalesl');
    $penalesv = $val($p, 'penalesv');
    // Ojo: algunas consultas devuelven 'SI'/'NO' en vez de 1/0, y (bool) 'NO'
    // da true. Por eso se normaliza en vez de castear.
    $neutralCrudo = $val($p, 'neutral');
    $esNeutral = is_string($neutralCrudo)
        ? in_array(mb_strtoupper(trim($neutralCrudo)), ['1', 'SI', 'SÍ', 'TRUE'], true)
        : (bool) $neutralCrudo;

    $sinJugar   = is_null($golesl) && is_null($golesv);
    $ganaLocal  = !$sinJugar && ($golesl > $golesv || ($golesl == $golesv && $penalesl > $penalesv));
    $ganaVisita = !$sinJugar && ($golesv > $golesl || ($golesl == $golesv && $penalesv > $penalesl));

    $dia = $val($p, 'dia');
@endphp

<div class="t-partido t-partido-ancho {{ $torneo ? '' : 'sin-torneo' }}"
     @if($id) data-href="{{ route('fechas.detalle', ['partidoId' => $id]) }}" @endif>

    @if($torneo)
        <span class="t-competencia">
            <x-escudo :src="$torneoEscudo" :nombre="$torneoNombre" tam="sm"/>
            <span class="t-competencia-txt">
                <span class="t-competencia-nombre">{{ $torneoNombre }} {{ $torneoYear }}</span>
                @if($numeroTxt)
                    <span class="t-competencia-fecha">{{ $numeroTxt }}</span>
                @endif
            </span>
        </span>
    @endif

    <span class="t-cuando">
        @if($dia)
            <span>{{ date('d/m/Y', strtotime($dia)) }}</span>
            <span class="t-cuando-hora">{{ date('H:i', strtotime($dia)) }}</span>
        @else
            <span>—</span>
        @endif
    </span>

    <span class="t-equipo local {{ $ganaLocal ? 'gana' : '' }} {{ $destacar && $destacar == $localId ? 'propio' : '' }}">
        @if($localId)
            <a href="{{ route('equipos.ver', ['equipoId' => $localId]) }}">{{ $localNombre }}</a>
        @else
            <span>{{ $localNombre }}</span>
        @endif
        <x-escudo :src="$localEscudo" :nombre="$localNombre"/>
        @if($banderaL)
            <img class="bandera" src="{{ $banderaL }}" alt="{{ $localPais }}" title="{{ $localPais }}">
        @endif
    </span>

    <span class="t-marcador t-num">
        @if($sinJugar)
            – –
        @else
            {{ $golesl }}&thinsp;–&thinsp;{{ $golesv }}
            @if($penalesl || $penalesv)
                <small>({{ $penalesl }}–{{ $penalesv }} p)</small>
            @endif
        @endif
    </span>

    <span class="t-equipo visita {{ $ganaVisita ? 'gana' : '' }} {{ $destacar && $destacar == $visitaId ? 'propio' : '' }}">
        @if($banderaV)
            <img class="bandera" src="{{ $banderaV }}" alt="{{ $visitaPais }}" title="{{ $visitaPais }}">
        @endif
        <x-escudo :src="$visitaEscudo" :nombre="$visitaNombre"/>
        @if($visitaId)
            <a href="{{ route('equipos.ver', ['equipoId' => $visitaId]) }}">{{ $visitaNombre }}</a>
        @else
            <span>{{ $visitaNombre }}</span>
        @endif
    </span>

    <span class="t-estado">
        @if($neutral && $esNeutral)
            <span class="t-chip">Neutral</span>
        @endif
        {{ $slot }}
        <i class="bi bi-chevron-right t-chevron"></i>
    </span>
</div>
