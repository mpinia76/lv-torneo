@extends('layouts.appPublic')

@section('pageTitle', 'Listado de fechas')

@section('content')
    <div class="container">
        <div class="card shadow-sm border-0">
            <div class="card-body">


                {{-- Navegación de fechas --}}
                <form class="mb-4" id="formFechas" method="GET" action="">
                    <div class="d-flex justify-content-center align-items-center flex-wrap gap-2">


                        {{-- Selección de número de fecha --}}
                        <select class="form-control js-example-basic-single ms-2" id="fechaNumero" name="fechaNumero" onchange="this.form.submit()" style="width: 150px">
                            @foreach($fechas as $f)
                                <option value="{{ $f->numero }}" @if($f->numero == $fecha->numero) selected @endif>
                                    {{ is_numeric($f->numero) ? "Fecha $f->numero" : $f->numero }}
                                </option>
                            @endforeach
                        </select>
                        <input type="hidden" name="torneoId" value="{{ request()->get('torneoId', '') }}">
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
