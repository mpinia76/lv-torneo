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
        <!-- Tabla de partidos -->
        <div class="table-responsive mb-4">
            <div class="t-panel t-lista-partidos">
                        @foreach($partidos as $partido)
                            <x-partido :p="$partido" :neutral="true"/>
                        @endforeach
                    </div>
        </div>



        <div class="d-flex justify-content-start mt-3">
            <a href="{{ url()->previous() }}" class="btn btn-success">Volver</a>
        </div>
    </div>
@endsection
