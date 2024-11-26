@extends('layouts.appPublic')

@section('pageTitle', 'Listado de fechas')

@section('content')
    <div class="container">
        <hr/>

        <form class="form-inline">
            <input type="hidden" name="torneoId" value="{{ request()->get('torneoId', '') }}">
            <select class="form-control js-example-basic-single" id="fechaNumero" name="fechaNumero" onchange="this.form.submit()" style="width: 150px">
                @foreach($fechas as $f)
                    <option value="{{ $f->numero }}" @if($f->numero == $fecha->numero) selected @endif>
                        @if(is_numeric($f->numero))
                            Fecha {{ $f->numero }}
                        @else
                            {{ $f->numero }}
                        @endif
                    </option>

                @endforeach
            </select>
        </form>

        <br>

        <div class="row">
            <div class="form-group col-md-12">
                <table class="table {{ $hayIdaVuelta ? 'table-full-width' : '' }}" style="{{ $hayIdaVuelta ? 'width: 100%' : 'width: 70%' }}">
                    <tbody>
                    @php
                        $lastDate = null;
                    @endphp
                    @foreach($partidosAgrupados as $partidos)

                            @if($partidos->count() == 1)


                                @foreach($partidos as $partido)
                                    @php
                                        $currentDate = $partido->dia ? date('Y-m-d', strtotime($partido->dia)) : 'sin_fecha';
                                    @endphp

                                    @if ($currentDate != $lastDate)
                                        <tr><td colspan="6" style="text-align: center;">
                                                {{ $currentDate != 'sin_fecha' ? strftime('%A %d de %B de %Y', strtotime($currentDate)) : 'Sin Fecha' }}
                                            </td></tr>
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
                                <tr>
                                @foreach($partidos as $partido)
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
                                @endforeach
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex">
            <a href="{{ route('torneos.ver', ['torneoId' => $torneo->id]) }}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>
@endsection
