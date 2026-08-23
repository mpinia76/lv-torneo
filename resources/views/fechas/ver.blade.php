@extends('layouts.appPublic')

@section('pageTitle', 'Fixture')

@section('content')

    @php
        use Carbon\Carbon;
        $lastDate = null;
        $lastFecha = null;
        $fechasArray = $fechas->pluck('orden')->toArray();
        $indiceActual = array_search($fecha->orden, $fechasArray);
    @endphp

    <div class="t-cabecera">
        <div>
            <span class="t-eyebrow">
                <x-escudo :src="$torneo->escudo" :nombre="$torneo->nombre" tam="sm"/>
                {{ $torneo->nombre }} {{ $torneo->year }}
            </span>
            <h1>Fixture</h1>
        </div>
    </div>

    <div class="t-panel">

        {{-- Navegación de fechas --}}
        <div class="t-panel-cuerpo">
            <form id="formFechas" method="GET" action="" class="t-navdia">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="cambiarFecha(-1)">
                    <i class="bi bi-chevron-left"></i> Anterior
                </button>

                <select id="fechaSelect" class="form-select form-select-sm" style="max-width: 220px" onchange="seleccionarFecha()">
                    @foreach($fechas as $f)
                        <option value="{{ $f->orden }}" {{ $f->orden === $fecha->orden ? 'selected' : '' }}>
                            {{ is_numeric($f->numero) ? 'Fecha ' . $f->numero : $f->numero }}
                        </option>
                    @endforeach
                </select>

                <input type="hidden" id="fechaOrden" name="fechaOrden" value="{{ $fecha->orden }}">
                <input type="hidden" name="torneoId" value="{{ request()->get('torneoId', '') }}">

                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="cambiarFecha(1)">
                    Siguiente <i class="bi bi-chevron-right"></i>
                </button>
            </form>
        </div>

        {{-- Partidos --}}
        @php $hayPartidos = false; @endphp

        @foreach($partidosAgrupados as $partidos)
            @foreach($partidos as $partido)
                @php
                    $hayPartidos = true;
                    $currentDate = $partido->dia
                        ? Carbon::parse($partido->dia)->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY')
                        : 'Sin fecha confirmada';
                @endphp

                {{-- Número de fecha --}}
                @if($partido->fecha->numero != $lastFecha)
                    <div class="t-grupo">
                        <span class="t-grupo-nombre">
                            {{ is_numeric($partido->fecha->numero) ? "Fecha {$partido->fecha->numero}" : $partido->fecha->numero }}
                        </span>
                    </div>
                    @php
                        $lastFecha = $partido->fecha->numero;
                        $lastDate = null;
                    @endphp
                @endif

                {{-- Día --}}
                @if($currentDate != $lastDate)
                    <div class="t-subgrupo">
                        <span class="t-dia-nombre">{{ $currentDate }}</span>
                    </div>
                    @php $lastDate = $currentDate; @endphp
                @endif

                {{-- Partido --}}
                @php
                    $sinJugar = is_null($partido->golesl) && is_null($partido->golesv);
                    $localGana  = !$sinJugar && ($partido->golesl > $partido->golesv
                                   || ($partido->golesl == $partido->golesv && $partido->penalesl > $partido->penalesv));
                    $visitaGana = !$sinJugar && ($partido->golesv > $partido->golesl
                                   || ($partido->golesl == $partido->golesv && $partido->penalesv > $partido->penalesl));
                @endphp

                <div class="t-partido" data-href="{{ route('fechas.detalle', ['partidoId' => $partido->id]) }}">

                    <span class="t-hora">{{ $partido->dia ? Carbon::parse($partido->dia)->format('H:i') : '' }}</span>

                    <span class="t-equipo local {{ $localGana ? 'gana' : '' }}">
                        @if($partido->equipol)
                            <a href="{{ route('equipos.ver', ['equipoId' => $partido->equipol->id]) }}">{{ $partido->equipol->nombre }}</a>
                            <x-escudo :src="$partido->equipol->escudo" :nombre="$partido->equipol->nombre"/>
                            @if($partido->equipol->bandera_url)
                                <img class="bandera" src="{{ $partido->equipol->bandera_url }}" alt="{{ $partido->equipol->pais }}" title="{{ $partido->equipol->pais }}">
                            @endif
                        @endif
                    </span>

                    <span class="t-marcador t-num">
                        @if($sinJugar)
                            – –
                        @else
                            {{ $partido->golesl }}&thinsp;–&thinsp;{{ $partido->golesv }}
                            @if($partido->penalesl || $partido->penalesv)
                                <small>({{ $partido->penalesl }}–{{ $partido->penalesv }} p)</small>
                            @endif
                        @endif
                    </span>

                    <span class="t-equipo visita {{ $visitaGana ? 'gana' : '' }}">
                        @if($partido->equipov)
                            @if($partido->equipov->bandera_url)
                                <img class="bandera" src="{{ $partido->equipov->bandera_url }}" alt="{{ $partido->equipov->pais }}" title="{{ $partido->equipov->pais }}">
                            @endif
                            <x-escudo :src="$partido->equipov->escudo" :nombre="$partido->equipov->nombre"/>
                            <a href="{{ route('equipos.ver', ['equipoId' => $partido->equipov->id]) }}">{{ $partido->equipov->nombre }}</a>
                        @endif
                    </span>

                    <span class="t-estado">
                        <span class="t-chip">{{ $sinJugar ? 'Programado' : 'Final' }}</span>
                        <i class="bi bi-chevron-right t-chevron"></i>
                    </span>
                </div>
            @endforeach
        @endforeach

        @if(!$hayPartidos)
            <div class="t-panel-cuerpo text-center" style="padding: 34px 14px">
                <div class="t-eyebrow">Sin partidos</div>
                <p class="mb-0 mt-2">Esta fecha todavía no tiene partidos cargados.</p>
            </div>
        @endif

        <div class="t-panel-pie">
            <span>Clic en cualquier fila para ver el detalle del partido</span>
        </div>
    </div>

    <div class="d-flex mt-3">
        <a href="{{ route('torneos.ver', ['torneoId' => $torneo->id]) }}" class="btn btn-outline-secondary btn-sm">Volver al torneo</a>
    </div>

@endsection

@section('scripts')
    <script>
        const fechasDisponibles = @json($fechas->pluck('orden'));
        let indiceActual = {{ $indiceActual }};

        function cambiarFecha(direccion) {
            indiceActual += direccion;
            if (indiceActual < 0) indiceActual = 0;
            if (indiceActual >= fechasDisponibles.length) indiceActual = fechasDisponibles.length - 1;

            document.getElementById('fechaOrden').value = fechasDisponibles[indiceActual];
            document.getElementById('formFechas').submit();
        }

        function seleccionarFecha() {
            document.getElementById('fechaOrden').value = document.getElementById('fechaSelect').value;
            document.getElementById('formFechas').submit();
        }
    </script>
@endsection
