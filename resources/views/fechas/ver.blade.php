@extends('layouts.appPublic')

@section('pageTitle', 'Listado de fechas')

@section('content')
    <div class="container">
        <div class="card shadow-sm border-0">
            <div class="card-body">

                {{-- Navegación de fechas --}}
                <form class="mb-4" id="formFechas" method="GET" action="">
                    <div class="d-flex justify-content-center align-items-center flex-wrap gap-2">
                        <div class="btn-group" role="group" aria-label="Navegación fechas">

                            {{-- Botón Anterior --}}
                            <button type="button" class="btn btn-outline-primary btn-sm"
                                    onclick="cambiarFecha(-1)">
                                ⬅ Anterior
                            </button>

                            {{-- Mostrar fecha actual --}}
                            <input type="text"
                                   readonly
                                   class="form-control form-control-sm text-center fw-bold"
                                   style="max-width: 200px;"
                                   value="{{ is_numeric($fecha->numero) ? 'Fecha ' . $fecha->numero : $fecha->numero }}">

                            {{-- Campo oculto que viaja en el form --}}
                            <input type="hidden" id="fechaNumero" name="fechaNumero" value="{{ $fecha->numero }}">
                            <input type="hidden" name="torneoId" value="{{ request()->get('torneoId', '') }}">

                            {{-- Botón Siguiente --}}
                            <button type="button" class="btn btn-outline-primary btn-sm"
                                    onclick="cambiarFecha(1)">
                                Siguiente ➡
                            </button>
                        </div>
                    </div>
                </form>

                {{-- Tabla de partidos --}}
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle text-center">
                        <tbody>
                        @php
                            use Carbon\Carbon;
                            $lastDate = null;
                            $lastFecha = null;
                            // Array de todos los números disponibles
                            $fechasArray = $fechas->pluck('numero')->toArray();
                            $indiceActual = array_search($fecha->numero, $fechasArray);
                        @endphp

                        @foreach($partidosAgrupados as $partidos)
                            @foreach($partidos as $partido)
                                @php
                                    $currentDate = $partido->dia
                                        ? Carbon::parse($partido->dia)->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY')
                                        : '📌 Sin Fecha';
                                @endphp

                                {{-- Fila de fecha --}}
                                @if($currentDate != $lastDate)
                                    <tr class="table-light">
                                        <td colspan="6" class="fw-semibold">{{ $currentDate }}</td>
                                    </tr>
                                    @php $lastDate = $currentDate; @endphp
                                @endif

                                {{-- Fila de número de fecha --}}
                                @if($partido->fecha->numero != $lastFecha)
                                    <tr class="table-secondary">
                                        <td colspan="6" class="fw-bold">
                                            {{ is_numeric($partido->fecha->numero) ? "Fecha {$partido->fecha->numero}" : $partido->fecha->numero }}
                                        </td>
                                    </tr>
                                    @php $lastFecha = $partido->fecha->numero; @endphp
                                @endif

                                {{-- Partido --}}
                                <tr>
                                    <td class="text-muted">{{ $partido->dia ? Carbon::parse($partido->dia)->format('H:i') : '' }}</td>
                                    <td class="text-end">
                                        @if($partido->equipol)
                                            <a href="{{ route('equipos.ver', ['equipoId' => $partido->equipol->id]) }}" class="text-decoration-none">
                                                @if($partido->equipol->escudo)
                                                    <img src="{{ url('images/' . $partido->equipol->escudo) }}" height="20" class="me-1">
                                                @endif
                                                {{ $partido->equipol->nombre }}
                                            </a>
                                            <img src="{{ $partido->equipol->bandera_url }}" alt="{{ $partido->equipol->pais }}" height="15">
                                        @endif
                                    </td>
                                    <td class="fw-bold">{{ $partido->golesl }}@if($partido->penalesl) ({{ $partido->penalesl }}) @endif</td>
                                    <td class="fw-bold">{{ $partido->golesv }}@if($partido->penalesv) ({{ $partido->penalesv }}) @endif</td>
                                    <td class="text-start">
                                        @if($partido->equipov)
                                            <a href="{{ route('equipos.ver', ['equipoId' => $partido->equipov->id]) }}" class="text-decoration-none">
                                                @if($partido->equipov->escudo)
                                                    <img src="{{ url('images/' . $partido->equipov->escudo) }}" height="20" class="me-1">
                                                @endif
                                                {{ $partido->equipov->nombre }}
                                            </a>
                                            <img src="{{ $partido->equipov->bandera_url }}" alt="{{ $partido->equipov->pais }}" height="15">
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('fechas.detalle', ['partidoId' => $partido->id]) }}" class="btn btn-success btn-sm">Detalles</a>
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex mt-3">
                    <a href="{{ route('torneos.ver', ['torneoId' => $torneo->id]) }}" class="btn btn-success">Volver</a>
                </div>
            </div>
        </div>
    </div>

    {{-- Scripts --}}
    <script>
        const fechasDisponibles = @json($fechas->pluck('numero'));
        let indiceActual = {{ $indiceActual }};

        function cambiarFecha(direccion) {
            indiceActual += direccion;
            if (indiceActual < 0) indiceActual = 0;
            if (indiceActual >= fechasDisponibles.length) indiceActual = fechasDisponibles.length - 1;

            document.getElementById('fechaNumero').value = fechasDisponibles[indiceActual];
            document.getElementById('formFechas').submit();
        }
    </script>
@endsection
