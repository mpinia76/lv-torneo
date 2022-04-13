@extends('layouts.appPublic')

@section('pageTitle', 'Ver tecnico')

@section('content')
    <div class="container">


        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                <div class="form-group col-xs-12 col-sm-6 col-md-4">
                    <div class="form-group">

                        @if($tecnico->foto)
                            <img id="original" src="{{ url('images/'.$tecnico->foto) }}" height="200">
                        @endif


                    </div>
                </div>
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-8">

                <div class="form-group col-xs-12 col-sm-6 col-md-3">
                    <dt>Nombre</dt>
                    <dd>{{$tecnico->nombre}}</dd>
                </div>

                <div class="form-group col-xs-12 col-sm-6 col-md-3">
                    <dt>Apellido</dt>
                    <dd>{{$tecnico->apellido}}</dd>
                </div>
                <div class="form-group col-xs-12 col-sm-6 col-md-3">
                    <dt>Ciudad Nacimiento</dt>
                    <dd>{{$tecnico->ciudad}}</dd>

                </div>
                <div class="form-group col-xs-12 col-sm-6 col-md-3">
                    <dt>Edad</dt>
                    <dd>{{($tecnico->nacimiento)?$tecnico->getAgeAttribute():''}}</dd>

                </div>
            </div>

        </div>


        <div class="row">


            <div class="form-group col-xs-12 col-sm-6 col-md-6">

                <dd>{{$tecnico->observaciones}}</dd>

            </div>

        </div>

        <table class="table">
            <thead>
            <th>#</th>
            <th>Torneo</th>
            <th>Equipos</th>

            <th>J</th>
            <th>G</th>
            <th>E</th>
            <th>P</th>
            <th>GF</th>
            <th>GC</th>
            <th>Dif.</th>
            <th>Punt.</th>
            <th>%</th>

            </thead>
            <tbody>

            @php
                $i = 1;
                $totalJugados = 0;
                $totalGanados = 0;
                $totalEmpatados = 0;
                $totalPerdidos = 0;
                $totalFavor = 0;
                $totalContra = 0;
                $totalPuntaje = 0;
            @endphp
            @foreach($torneosTecnico as $torneo)
                @php
                    $totalJugados += $torneo->jugados;
                    $totalGanados += $torneo->ganados;
                    $totalEmpatados += $torneo->empatados;
                    $totalPerdidos += $torneo->perdidos;
                    $totalFavor += $torneo->favor;
                    $totalContra += $torneo->contra;
                    $totalPuntaje += $torneo->puntaje;
                @endphp
                <tr>
                    <td>{{$i++}}</td>
                    <td>{{$torneo->nombreTorneo}}</td>
                    <td>@if($torneo->escudo)
                            @php
                                $escudos = explode(',',$torneo->escudo);
                            @endphp
                            @foreach($escudos as $escudo)

                                @if($escudo!='')
                                    @php
                                        $escudoArr = explode('_',$escudo);
                                    @endphp
                                    <a href="{{route('equipos.ver', array('equipoId' => $escudoArr[1]))}}" >
                                        <img id="original" src="{{ url('images/'.$escudoArr[0]) }}" height="25">
                                    </a>
                                @endif
                            @endforeach
                        @endif

                    </td>
                    <td>{{$torneo->jugados}}</td>
                    <td>{{$torneo->ganados}}</td>
                    <td>{{$torneo->empatados}}</td>
                    <td>{{$torneo->perdidos}}</td>
                    <td>{{$torneo->favor}}</td>
                    <td>{{$torneo->contra}}</td>
                    <td>{{$torneo->favor - $torneo->contra}}</td>
                    <td>{{$torneo->puntaje}}</td>
                    <td>{{$torneo->porcentaje}}</td>
                </tr>

            @endforeach
            <tr>
                <td></td>
                <td></td>
                <td><strong>Totales</strong></td>
                <td><strong>{{ $totalJugados}}</strong></td>
                <td><strong>{{ $totalGanados}}</strong></td>
                <td><strong>{{ $totalEmpatados}}</strong></td>
                <td><strong>{{ $totalPerdidos}}</strong></td>
                <td><strong>{{ $totalFavor}}</strong></td>
                <td><strong>{{ $totalContra}}</strong></td>
                <td><strong>{{ $totalFavor-$totalContra}}</strong></td>
                <td><strong>{{ $totalPuntaje}}</strong></td>
                <td><strong>{{ ROUND(
                (
                $totalPuntaje
                * 100/($totalJugados*3) ),
                2
                )}}%</strong></td>

            </tr>

            </tbody>
        </table>




        <div class="d-flex">

            <a href="{{ url()->previous() }}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>

@endsection
