@extends('layouts.appPublic')

@section('pageTitle', 'Ver jugador')

@section('content')
    <div class="container">

        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                <div class="row">
                    <div class="form-group col-xs-12 col-sm-6 col-md-4">
                        <div class="form-group">

                            @if($jugador->foto)
                                <img id="original" src="{{ url('images/'.$jugador->foto) }}" height="200">
                            @endif


                        </div>
                    </div>
                </div>
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-8">
                <div class="row">
                    <div class="form-group col-xs-12 col-sm-6 col-md-3">
                        <dt>Nombre</dt>
                        <dd>{{$jugador->nombre}}</dd>
                    </div>

                    <div class="form-group col-xs-12 col-sm-6 col-md-3">
                        <dt>Apellido</dt>
                        <dd>{{$jugador->apellido}}</dd>
                    </div>
                    <div class="form-group col-xs-12 col-sm-6 col-md-3">
                        <dt>Ciudad Nacimiento</dt>
                        <dd>{{$jugador->ciudad}}</dd>

                    </div>

                    <div class="form-group col-xs-12 col-sm-6 col-md-3">
                        <dt>Edad</dt>
                        <dd>{{($jugador->nacimiento)?$jugador->getAgeAttribute():''}}</dd>

                    </div>
                </div>

                <div class="row">


                    <div class="form-group col-xs-12 col-sm-6 col-md-3">
                        <dt>Posición</dt>
                        <dd>{{$jugador->tipoJugador}}</dd>


                    </div>
                    <div class="form-group col-xs-12 col-sm-6 col-md-3">
                        <dt>Altura</dt>
                        <dd>{{$jugador->altura}} m.</dd>

                    </div>
                    <div class="form-group col-xs-12 col-sm-6 col-md-3">
                        <dt>Peso</dt>
                        <dd>{{$jugador->peso}} kg.</dd>

                    </div>
                </div>
            </div>

        </div>

        <div class="row">


            <div class="form-group col-xs-12 col-sm-6 col-md-6">

                <dd>{{$jugador->observaciones}}</dd>

            </div>

        </div>

        <table class="table">
            <thead>
            <th>#</th>
            <th>Torneo</th>
            <th>Equipos</th>
            <th>Jugados</th>
            <th>Goles</th>

            <th>Amarillas</th>

            <th>Rojas</th>

            </thead>
            <tbody>


            @php
                $i = 1;
                $totalJugados = 0;
                $totalGoles = 0;
                $totalAmarillas = 0;
                $totalRojas = 0;
            @endphp
            @foreach($torneosJugador as $torneo)
                @php
                    $totalJugados += $torneo->jugados;
                    $totalGoles += $torneo->goles;
                    $totalAmarillas += $torneo->amarillas;
                    $totalRojas += $torneo->rojas;
                    $jugo = 0;
                    if($torneo->jugados>0){
                        $jugo=1;
                    }


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
                    <td>{{$torneo->goles}}
                        @if($jugo)
                            ({{round($torneo->goles / $torneo->jugados,2)}})
                        @else
                            ({{round(0,2)}})
                        @endif
                    </td>
                    <td>{{$torneo->amarillas}}
                        @if($jugo)
                            ({{round($torneo->amarillas / $torneo->jugados,2)}})
                        @else
                            ({{round(0,2)}})
                        @endif

                    </td>
                    <td>{{$torneo->rojas}}
                        @if($jugo)
                            ({{round($torneo->rojas / $torneo->jugados,2)}})
                        @else
                            ({{round(0,2)}})
                        @endif
                    </td>
                </tr>

            @endforeach
            <tr>
                <td></td>
                <td></td>
                <td><strong>Totales</strong></td>
                <td><strong>{{ $totalJugados}}</strong></td>
                <td><strong>{{ $totalGoles}} ({{round($totalGoles / $totalJugados,2)}})</strong></td>
                <td><strong>{{ $totalAmarillas}} ({{round($totalAmarillas / $totalJugados,2)}})</strong></td>
                <td><strong>{{ $totalRojas}} ({{round($totalRojas / $totalJugados,2)}})</strong></td>
            </tr>
            </tbody>
        </table>


    <div class="d-flex">

        <a href="{{ url()->previous() }}" class="btn btn-success m-1">Volver</a>
    </div>
    </div>

@endsection
