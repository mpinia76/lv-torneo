@extends('layouts.appPublic')

@section('pageTitle', 'Historiales')

@section('content')
    <div class="container">

        <hr/>

        <!-- Filtro de equipos -->
        <form class="form-inline d-flex justify-content-center align-items-center mb-3">
            <select class="form-control js-example-basic-single mr-2" id="equipo1" name="equipo1" onchange="this.form.submit()" style="width: 180px;">
                @foreach($equipos as $equipo)
                    <option value="{{$equipo->id}}" @if($equipo->id==$e1->id) selected @endif>
                        {{$equipo->nombre}}
                    </option>
                @endforeach
            </select>

            <span class="mx-2 font-weight-bold">VS.</span>

            <select class="form-control js-example-basic-single ml-2" id="equipo2" name="equipo2" onchange="this.form.submit()" style="width: 180px;">
                @foreach($equipos as $equipo)
                    <option value="{{$equipo->id}}" @if($equipo->id==$e2->id) selected @endif>
                        {{$equipo->nombre}}
                    </option>
                @endforeach
            </select>
        </form>

        <!-- Tabla de partidos -->
        <div class="table-responsive mb-4">
            <table class="table table-bordered table-hover">
                <thead class="thead-light text-center">
                <tr>
                    <th>Torneo</th>
                    <th>Fecha</th>
                    <th>Día</th>
                    <th>Local</th>
                    <th>GL</th>
                    <th>GV</th>
                    <th>Visitante</th>
                    <th>Cancha Neutral</th>
                    <th>Acciones</th>
                </tr>
                </thead>
                <tbody>
                @foreach($partidos as $partido)
                    <tr>
                        <td>{{$partido->nombreTorneo}} {{$partido->year}}</td>
                        <td>{{ is_numeric($partido->numero) ? "Fecha $partido->numero" : $partido->numero }}</td>
                        <td>{{ $partido->dia ? date('d/m/Y H:i', strtotime($partido->dia)) : '' }}</td>
                        <td class="text-left">
                            <a href="{{route('equipos.ver', ['equipoId' => $partido->equipol_id])}}">
                                @if($partido->fotoLocal)
                                    <img src="{{ url('images/'.$partido->fotoLocal) }}" height="20" class="mr-1">
                                @endif
                                {{$partido->local}}
                                <img src="{{ url('images/'.removeAccents($partido->paisLocal).'.gif') }}" alt="{{ $partido->paisLocal }}">
                            </a>
                        </td>
                        <td>{{$partido->golesl}} @if(isset($partido->penalesl)) ({{$partido->penalesl}}) @endif</td>
                        <td>{{$partido->golesv}} @if(isset($partido->penalesv)) ({{$partido->penalesv}}) @endif</td>
                        <td class="text-left">
                            <a href="{{route('equipos.ver', ['equipoId' => $partido->equipov_id])}}">
                                @if($partido->fotoVisitante)
                                    <img src="{{ url('images/'.$partido->fotoVisitante) }}" height="20" class="mr-1">
                                @endif
                                {{$partido->visitante}}
                                <img src="{{ url('images/'.removeAccents($partido->paisVisitante).'.gif') }}" alt="{{ $partido->paisVisitante }}">
                            </a>
                        </td>
                        <td>{{$partido->neutral}}</td>
                        <td>
                            <a href="{{route('fechas.detalle', ['partidoId' => $partido->partido_id])}}" class="btn btn-sm btn-success">Detalles</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <!-- Tabla de posiciones -->
        <div class="table-responsive">
            <table class="table table-bordered table-hover text-center">
                <thead class="thead-light">
                <tr>
                    <th>Equipo</th>
                    <th>Punt.</th>
                    <th>J</th>
                    <th>G</th>
                    <th>E</th>
                    <th>P</th>
                    <th>GF</th>
                    <th>GC</th>
                    <th>Dif.</th>
                </tr>
                </thead>
                <tbody>
                @foreach($posiciones as $equipo)
                    <tr>
                        <td class="text-left">
                            <a href="{{route('equipos.ver', ['equipoId' => $equipo->equipo_id])}}">
                                @if($equipo->foto)
                                    <img src="{{ url('images/'.$equipo->foto) }}" height="25" class="mr-1">
                                @endif
                                {{$equipo->equipo}}
                            </a>
                        </td>
                        <td>{{$equipo->puntaje}}</td>
                        <td>{{$equipo->jugados}}</td>
                        <td>{{$equipo->ganados}}</td>
                        <td>{{$equipo->empatados}}</td>
                        <td>{{$equipo->perdidos}}</td>
                        <td>{{$equipo->golesl}}</td>
                        <td>{{$equipo->golesv}}</td>
                        <td>{{$equipo->diferencia}}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-start mt-3">
            <a href="{{ url()->previous() }}" class="btn btn-success">Volver</a>
        </div>
    </div>
@endsection
