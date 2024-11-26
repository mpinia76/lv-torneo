@extends('layouts.appPublic')

@section('pageTitle', 'Partidos')

@section('content')
    <div class="container">
        <h1>Partidos</h1>
        <hr/>

        <form class="form-inline" id="formFechas" method="GET" action="">
            <div class="form-group">
                <button type="button" class="btn btn-info" onclick="actualizarFecha(0)">Anterior</button>
                <input type="date" name="dia" id="dia" class="form-control" value="{{ $dia }}" onchange="enviarFormulario()">

                <button type="button" class="btn btn-info" onclick="actualizarFecha(2)">Siguiente</button>
            </div>
        </form>

        <br>

        <div class="row">
            <div class="form-group col-md-12">
                <table class="table {{ $hayIdaVuelta ? 'table-full-width' : '' }}" style="{{ $hayIdaVuelta ? 'width: 100%' : 'width: 70%' }}">
                    <tbody>
                    @php
                        $lastDate = null;
                         $lastTournament = null;
                         $lastFecha = null;
                    @endphp
                    @foreach($partidosAgrupados as $partidos)

                            @if($partidos->count() == 1)


                                @foreach($partidos as $partido)

                                    @if ($partido->fecha->grupo->torneo->id != $lastTournament)
                                        <tr>
                                            <td colspan="6" style="text-align: center;">
                                                <strong>{{ $partido->fecha->grupo->torneo->nombre }} {{ $partido->fecha->grupo->torneo->year }}</strong>
                                            </td>
                                        </tr>
                                        @php
                                            $lastTournament = $partido->fecha->grupo->torneo->id;
                                        @endphp
                                    @endif

                                    @php
                                        //dd($partido->fecha->grupo->torneo) ;
                                        $currentDate = $partido->dia ? date('Y-m-d', strtotime($partido->dia)) : 'sin_fecha';
                                    @endphp

                                    @if ($currentDate != $lastDate)
                                        <tr><td colspan="6" style="text-align: center;">
                                                {{ $currentDate != 'sin_fecha' ? strftime('%A %d de %B de %Y', strtotime($currentDate)) : 'Sin Fecha' }}
                                            </td></tr>
                                    @endif
                                    @if ($partido->fecha->numero != $lastFecha)
                                        <tr>
                                            <td colspan="6" style="text-align: center;">
                                                <strong>
                                                    @if(is_numeric($partido->fecha->numero))
                                                        Fecha {{ $partido->fecha->numero }}
                                                    @else
                                                        {{ $partido->fecha->numero }}
                                                    @endif
                                                    </strong>
                                            </td>
                                        </tr>
                                        @php
                                            $lastFecha = $partido->fecha->numero;
                                        @endphp
                                    @endif
                                    <tr><td>{{ $partido->dia ? date(' H:i', strtotime($partido->dia)) : '' }}</td>
                                    <td>
                                        <a href="{{ route('equipos.ver', ['equipoId' => $partido->equipol->id]) }}">
                                            @if($partido->equipol)
                                                @if($partido->equipol->escudo)
                                                    <img src="{{ url('images/' . $partido->equipol->escudo) }}" height="20">
                                                @endif
                                            @endif
                                        </a>
                                        {{ $partido->equipol->nombre }} <img src="{{ $partido->equipol->bandera_url }}" alt="{{ $partido->equipol->pais }}">
                                    </td>
                                    <td>{{ $partido->golesl }}@if($partido->penalesl) ({{ $partido->penalesl }}) @endif</td>
                                    <td>{{ $partido->golesv }}@if($partido->penalesv) ({{ $partido->penalesv }}) @endif</td>
                                    <td>
                                        <a href="{{ route('equipos.ver', ['equipoId' => $partido->equipov->id]) }}">
                                            @if($partido->equipov)
                                                @if($partido->equipov->escudo)
                                                    <img src="{{ url('images/' . $partido->equipov->escudo) }}" height="20">
                                                @endif
                                            @endif
                                        </a>
                                        {{ $partido->equipov->nombre }} <img src="{{ $partido->equipov->bandera_url }}" alt="{{ $partido->equipov->pais }}">
                                    </td>
                                    <td>
                                        <div class="d-flex">
                                            <a href="{{ route('fechas.detalle', ['partidoId' => $partido->id]) }}" class="btn btn-success m-1">Detalles</a>
                                        </div>
                                    </td></tr>
                                    @php
                                        $lastDate = $currentDate;
                                    @endphp
                                @endforeach
                            @else

                                @foreach($partidos as $partido)
                                    @if ($partido->fecha->grupo->torneo->id != $lastTournament)
                                        <tr>
                                            <td colspan="6" style="text-align: center;">
                                                <strong>{{ $partido->fecha->grupo->torneo->nombre }} {{ $partido->fecha->grupo->torneo->year }}</strong>
                                            </td>
                                        </tr>
                                        @php
                                            $lastTournament = $partido->fecha->grupo->torneo->id;
                                        @endphp
                                    @endif
                                    <tr>
                                    <td>{{ $partido->dia ? date('d/m/Y H:i', strtotime($partido->dia)) : '' }}</td>
                                    <td>
                                        <a href="{{ route('equipos.ver', ['equipoId' => $partido->equipol->id]) }}">
                                            @if($partido->equipol)
                                                @if($partido->equipol->escudo)
                                                    <img src="{{ url('images/' . $partido->equipol->escudo) }}" height="20">
                                                @endif
                                            @endif
                                        </a>
                                        {{ $partido->equipol->nombre }} <img src="{{ $partido->equipol->bandera_url }}" alt="{{ $partido->equipol->pais }}">
                                    </td>
                                    <td>{{ $partido->golesl }}@if($partido->penalesl) ({{ $partido->penalesl }}) @endif</td>
                                    <td>{{ $partido->golesv }}@if($partido->penalesv) ({{ $partido->penalesv }}) @endif</td>
                                    <td>
                                        <a href="{{ route('equipos.ver', ['equipoId' => $partido->equipov->id]) }}">
                                            @if($partido->equipov)
                                                @if($partido->equipov->escudo)
                                                    <img src="{{ url('images/' . $partido->equipov->escudo) }}" height="20">
                                                @endif
                                            @endif
                                        </a>
                                        {{ $partido->equipov->nombre }} <img src="{{ $partido->equipov->bandera_url }}" alt="{{ $partido->equipov->pais }}">
                                    </td>
                                    <td>
                                        <div class="d-flex">
                                            <a href="{{ route('fechas.detalle', ['partidoId' => $partido->id]) }}" class="btn btn-success m-1">Detalles</a>
                                        </div>
                                    </td>
                                    </tr>
                                @endforeach
                            @endif

                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>


    </div>

    <script>
        function enviarFormulario() {
            document.getElementById('formFechas').submit();
        }
        function actualizarFecha(dias) {
            let fechaHoy = document.getElementById('dia').value;
            let fecha = new Date(fechaHoy);
            console.log(dias);
            fecha.setDate(fecha.getDate() + dias);
            console.log(fecha);
            let dia = String(fecha.getDate()).padStart(2, '0');
            let mes = String(fecha.getMonth() + 1).padStart(2, '0');
            let anio = fecha.getFullYear();

            let nuevaFecha = anio + '-' + mes + '-' + dia;

            console.log(nuevaFecha);
            // Actualizar el valor del input
            document.getElementById('dia').value = nuevaFecha;

            // Enviar el formulario
            enviarFormulario();
        }
    </script>
@endsection
