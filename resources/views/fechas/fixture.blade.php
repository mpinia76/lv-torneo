@extends('layouts.appPublic')

@section('pageTitle', 'Partidos')

@section('content')

    <div class="t-cabecera">
        <div>
            <span class="t-eyebrow">Fixture</span>
            <h1>Partidos</h1>
        </div>
    </div>

    <div class="t-panel">

        {{-- Navegación de días --}}
        <div class="t-panel-cuerpo">
            <form id="formFechas" method="GET" action="" class="t-navdia">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="actualizarFecha(-1)">
                    <i class="bi bi-chevron-left"></i> Anterior
                </button>

                <input type="date"
                       name="dia"
                       id="dia"
                       class="form-control form-control-sm"
                       value="{{ $dia }}"
                       onchange="enviarFormulario()">

                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="actualizarFecha(1)">
                    Siguiente <i class="bi bi-chevron-right"></i>
                </button>
            </form>
        </div>

        {{-- Partidos del día --}}
        @php
            $lastDate = null;
            $lastTournament = null;
            $lastFecha = null;
            $hayPartidos = false;
        @endphp

        @foreach($partidosAgrupados as $partidos)
            @foreach($partidos as $partido)
                @php $hayPartidos = true; @endphp

                {{-- Torneo --}}
                @if ($partido->fecha->grupo->torneo->id != $lastTournament)
                    <div class="t-grupo">
                        <x-escudo :src="$partido->fecha->grupo->torneo->escudo"
                                  :nombre="$partido->fecha->grupo->torneo->nombre"/>
                        <span class="t-grupo-nombre">
                            {{ $partido->fecha->grupo->torneo->nombre }} {{ $partido->fecha->grupo->torneo->year }}
                        </span>
                        @if($partido->fecha->numero)
                            <span class="t-sep">·</span>
                            <span class="t-eyebrow">
                                {{ is_numeric($partido->fecha->numero) ? 'Fecha ' . $partido->fecha->numero : $partido->fecha->numero }}
                            </span>
                        @endif
                    </div>
                    @php
                        $lastTournament = $partido->fecha->grupo->torneo->id;
                        $lastFecha = $partido->fecha->numero;
                        $lastDate = null;
                    @endphp
                @elseif ($partido->fecha->numero != $lastFecha)
                    <div class="t-subgrupo">
                        <span class="t-eyebrow">
                            {{ is_numeric($partido->fecha->numero) ? 'Fecha ' . $partido->fecha->numero : $partido->fecha->numero }}
                        </span>
                    </div>
                    @php $lastFecha = $partido->fecha->numero; @endphp
                @endif

                {{-- Día --}}
                @php
                    $currentDate = $partido->dia ? date('Y-m-d', strtotime($partido->dia)) : 'sin_fecha';
                @endphp
                @if ($currentDate != $lastDate)
                    <div class="t-subgrupo">
                        <span class="t-dia-nombre">
                            {{ $currentDate != 'sin_fecha'
                                ? strftime('%A %d de %B de %Y', strtotime($currentDate))
                                : 'Sin fecha confirmada' }}
                        </span>
                    </div>
                @endif

                {{-- Partido --}}
                @php
                    $sinJugar = is_null($partido->golesl) && is_null($partido->golesv);
                    $localGana   = !$sinJugar && ($partido->golesl > $partido->golesv
                                    || ($partido->golesl == $partido->golesv && $partido->penalesl > $partido->penalesv));
                    $visitaGana  = !$sinJugar && ($partido->golesv > $partido->golesl
                                    || ($partido->golesl == $partido->golesv && $partido->penalesv > $partido->penalesl));
                @endphp

                <div class="t-partido" data-href="{{ route('fechas.detalle', ['partidoId' => $partido->id]) }}">

                    <span class="t-hora">{{ $partido->dia ? date('H:i', strtotime($partido->dia)) : '' }}</span>

                    <span class="t-equipo local {{ $localGana ? 'gana' : '' }}">
                        <a href="{{ route('equipos.ver', ['equipoId' => $partido->equipol->id]) }}">{{ $partido->equipol->nombre }}</a>
                        <x-escudo :src="$partido->equipol->escudo" :nombre="$partido->equipol->nombre"/>
                        @if($partido->equipol->bandera_url)
                            <img class="bandera" src="{{ $partido->equipol->bandera_url }}" alt="{{ $partido->equipol->pais }}" title="{{ $partido->equipol->pais }}">
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
                        @if($partido->equipov->bandera_url)
                            <img class="bandera" src="{{ $partido->equipov->bandera_url }}" alt="{{ $partido->equipov->pais }}" title="{{ $partido->equipov->pais }}">
                        @endif
                        <x-escudo :src="$partido->equipov->escudo" :nombre="$partido->equipov->nombre"/>
                        <a href="{{ route('equipos.ver', ['equipoId' => $partido->equipov->id]) }}">{{ $partido->equipov->nombre }}</a>
                    </span>

                    <span class="t-estado">
                        <span class="t-chip">{{ $sinJugar ? 'Programado' : 'Final' }}</span>
                        <i class="bi bi-chevron-right t-chevron"></i>
                    </span>
                </div>

                @php $lastDate = $currentDate; @endphp
            @endforeach
        @endforeach

        @if(!$hayPartidos)
            <div class="t-panel-cuerpo text-center" style="padding: 34px 14px">
                <div class="t-eyebrow">Sin partidos</div>
                <p class="mb-0 mt-2">No hay partidos cargados para este día. Probá con otra fecha.</p>
            </div>
        @endif

        <div class="t-panel-pie">
            <span>Clic en cualquier fila para ver el detalle del partido</span>
        </div>
    </div>

@endsection

@section('scripts')
    <script>
        function enviarFormulario() {
            document.getElementById('formFechas').submit();
        }

        function actualizarFecha(dias) {
            const fechaHoy = document.getElementById('dia').value;
            if (!fechaHoy) return;

            let [anio, mes, dia] = fechaHoy.split('-').map(Number);
            const fecha = new Date(Date.UTC(anio, mes - 1, dia)); // fuerza UTC
            fecha.setUTCDate(fecha.getUTCDate() + dias);

            document.getElementById('dia').value = fecha.toISOString().slice(0, 10);
            enviarFormulario();
        }
    </script>
@endsection
