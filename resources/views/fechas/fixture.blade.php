@extends('layouts.appPublic')

@section('pageTitle', 'Partidos')

@section('content')
    <div class="container">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h1 class="h3 mb-4 text-center text-primary">📅 Partidos</h1>

                {{-- Navegación de fechas --}}
                <form class="mb-4" id="formFechas" method="GET" action="">
                    <div class="d-flex justify-content-center align-items-center flex-wrap gap-2">
                        <div class="btn-group" role="group" aria-label="Navegación fechas">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="actualizarFecha(0)">
                                ⬅ Anterior
                            </button>

                            <input type="date"
                                   name="dia"
                                   id="dia"
                                   class="form-control form-control-sm text-center"
                                   style="max-width: 200px;"
                                   value="{{ $dia }}"
                                   onchange="enviarFormulario()">

                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="actualizarFecha(2)">
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
                            $lastDate = null;
                            $lastTournament = null;
                            $lastFecha = null;
                        @endphp
                        @foreach($partidosAgrupados as $partidos)
                            @foreach($partidos as $partido)
                                {{-- Torneo --}}
                                @if ($partido->fecha->grupo->torneo->id != $lastTournament)
                                    <tr class="table-primary">
                                        <td colspan="6" class="fw-bold">
                                            {{ $partido->fecha->grupo->torneo->nombre }} {{ $partido->fecha->grupo->torneo->year }}
                                        </td>
                                    </tr>
                                    @php $lastTournament = $partido->fecha->grupo->torneo->id; @endphp
                                @endif

                                {{-- Fecha del partido --}}
                                @php
                                    $currentDate = $partido->dia ? date('Y-m-d', strtotime($partido->dia)) : 'sin_fecha';
                                @endphp
                                @if ($currentDate != $lastDate)
                                    <tr class="table-light">
                                        <td colspan="6" class="fw-semibold">
                                            {{ $currentDate != 'sin_fecha' ? strftime('%A %d de %B de %Y', strtotime($currentDate)) : '📌 Sin Fecha' }}
                                        </td>
                                    </tr>
                                @endif

                                {{-- Número de Fecha --}}
                                @if ($partido->fecha->numero != $lastFecha)
                                    <tr class="table-secondary">
                                        <td colspan="6" class="fw-bold">
                                            @if(is_numeric($partido->fecha->numero))
                                                Fecha {{ $partido->fecha->numero }}
                                            @else
                                                {{ $partido->fecha->numero }}
                                            @endif
                                        </td>
                                    </tr>
                                    @php $lastFecha = $partido->fecha->numero; @endphp
                                @endif

                                {{-- Partido --}}
                                <tr>
                                    <td class="text-muted">{{ $partido->dia ? date('H:i', strtotime($partido->dia)) : '' }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('equipos.ver', ['equipoId' => $partido->equipol->id]) }}" class="text-decoration-none">
                                            @if($partido->equipol && $partido->equipol->escudo)
                                                <img src="{{ url('images/' . $partido->equipol->escudo) }}" height="20" class="me-1">
                                            @endif
                                            {{ $partido->equipol->nombre }}
                                        </a>
                                        <img src="{{ $partido->equipol->bandera_url }}" alt="{{ $partido->equipol->pais }}" height="15">
                                    </td>
                                    <td class="fw-bold">{{ $partido->golesl }} @if($partido->penalesl) ({{ $partido->penalesl }}) @endif</td>
                                    <td class="fw-bold">{{ $partido->golesv }} @if($partido->penalesv) ({{ $partido->penalesv }}) @endif</td>
                                    <td class="text-start">
                                        <a href="{{ route('equipos.ver', ['equipoId' => $partido->equipov->id]) }}" class="text-decoration-none">
                                            @if($partido->equipov && $partido->equipov->escudo)
                                                <img src="{{ url('images/' . $partido->equipov->escudo) }}" height="20" class="me-1">
                                            @endif
                                            {{ $partido->equipov->nombre }}
                                        </a>
                                        <img src="{{ $partido->equipov->bandera_url }}" alt="{{ $partido->equipov->pais }}" height="15">
                                    </td>
                                    <td>
                                        <a href="{{ route('fechas.detalle', ['partidoId' => $partido->id]) }}" class="btn btn-success btn-sm">
                                            Detalles
                                        </a>
                                    </td>
                                </tr>
                                @php $lastDate = $currentDate; @endphp
                            @endforeach
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Scripts --}}
    <script>
        function enviarFormulario() {
            document.getElementById('formFechas').submit();
        }
        function actualizarFecha(dias) {
            let fechaHoy = document.getElementById('dia').value;
            let fecha = new Date(fechaHoy);
            fecha.setDate(fecha.getDate() + dias);
            let dia = String(fecha.getDate()).padStart(2, '0');
            let mes = String(fecha.getMonth() + 1).padStart(2, '0');
            let anio = fecha.getFullYear();
            let nuevaFecha = anio + '-' + mes + '-' + dia;
            document.getElementById('dia').value = nuevaFecha;
            enviarFormulario();
        }
    </script>
@endsection
