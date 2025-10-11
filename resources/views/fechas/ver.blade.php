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

                            {{-- Select para elegir fecha directamente --}}
                            <select id="fechaSelect" class="form-control form-control-sm text-center fw-bold" style="max-width: 200px;" onchange="seleccionarFecha()">
                                @foreach($fechas as $f)
                                    <option value="{{ $f->orden }}" {{ $f->orden === $fecha->orden ? 'selected' : '' }}>
                                        {{ is_numeric($f->numero) ? 'Fecha ' . $f->numero : $f->numero }}
                                    </option>
                                @endforeach
                            </select>

                            {{-- Campos ocultos --}}
                            <input type="hidden" id="fechaOrden" name="fechaOrden" value="{{ $fecha->orden }}">
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
                            $fechasArray = $fechas->pluck('orden')->toArray();
                            $indiceActual = array_search($fecha->orden, $fechasArray);
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
                                <tr class="clickable-row"
                                    data-href="{{ route('fechas.detalle', ['partidoId' => $partido->id]) }}">
                                    <td class="text-muted">{{ $partido->dia ? Carbon::parse($partido->dia)->format('H:i') : '' }}</td>
                                    <td class="text-end">
                                        @if($partido->equipol)
                                            <a href="{{ route('equipos.ver', ['equipoId' => $partido->equipol->id]) }}" class="text-decoration-none" onclick="event.stopPropagation();">
                                                @if($partido->equipol->escudo)
                                                    <img src="{{ url('images/' . $partido->equipol->escudo) }}" height="20" class="me-1">
                                                @endif
                                                {{ $partido->equipol->nombre }}
                                            </a>
                                            <img src="{{ $partido->equipol->bandera_url }}" alt="{{ $partido->equipol->pais }}" height="15">
                                        @endif
                                    </td>
                                <tr class="clickable-row" data-href="{{ route('fechas.detalle', ['partidoId' => $partido->id]) }}">
                                    <td class="text-muted">{{ $partido->dia ? Carbon::parse($partido->dia)->format('H:i') : '' }}</td>
                                    <td class="text-end">
                                        @if($partido->equipol)
                                            <a href="{{ route('equipos.ver', ['equipoId' => $partido->equipol->id]) }}" class="text-decoration-none" onclick="event.stopPropagation();">
                                                @if($partido->equipol->escudo)
                                                    <img src="{{ url('images/' . $partido->equipol->escudo) }}" height="20" class="me-1">
                                                @endif
                                                {{ $partido->equipol->nombre }}
                                            </a>
                                        @endif
                                    </td>

                                    {{-- Resultado centrado --}}
                                    <td class="fw-bold text-center" colspan="2">
                                        {{ $partido->golesl }}
                                        @if($partido->penalesl)
                                            ({{ $partido->penalesl }})
                                        @endif
                                        -
                                        {{ $partido->golesv }}
                                        @if($partido->penalesv)
                                            ({{ $partido->penalesv }})
                                        @endif
                                    </td>

                                    <td class="text-start">
                                        @if($partido->equipov)
                                            <a href="{{ route('equipos.ver', ['equipoId' => $partido->equipov->id]) }}" class="text-decoration-none" onclick="event.stopPropagation();">
                                                @if($partido->equipov->escudo)
                                                    <img src="{{ url('images/' . $partido->equipov->escudo) }}" height="20" class="me-1">
                                                @endif
                                                {{ $partido->equipov->nombre }}
                                            </a>
                                        @endif
                                    </td>
                                </tr>

                                <td class="text-start">
                                        @if($partido->equipov)
                                            <a href="{{ route('equipos.ver', ['equipoId' => $partido->equipov->id]) }}" class="text-decoration-none" onclick="event.stopPropagation();">
                                                @if($partido->equipov->escudo)
                                                    <img src="{{ url('images/' . $partido->equipov->escudo) }}" height="20" class="me-1">
                                                @endif
                                                {{ $partido->equipov->nombre }}
                                            </a>
                                            <img src="{{ $partido->equipov->bandera_url }}" alt="{{ $partido->equipov->pais }}" height="15">
                                        @endif
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
            const selected = document.getElementById('fechaSelect').value;
            document.getElementById('fechaOrden').value = selected;
            document.getElementById('formFechas').submit();
        }

        // Redirigir al hacer clic en fila
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.clickable-row').forEach(row => {
                row.addEventListener('click', () => {
                    window.location = row.dataset.href;
                });
            });
        });
    </script>

    <style>
        .clickable-row {
            cursor: pointer;
            transition: background-color 0.15s ease-in-out;
        }
        .clickable-row:hover {
            background-color: #e6ffe6 !important;
        }
    </style>
@endsection
