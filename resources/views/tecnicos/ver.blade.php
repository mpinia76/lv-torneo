@extends('layouts.appPublic')

@section('pageTitle', 'Ver tecnico')

@section('content')
    <div class="container">


        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                <div class="form-group col-xs-12 col-sm-6 col-md-4">
                    <div class="form-group">

                        @if($tecnico->persona->foto)
                            <img id="original" src="{{ url('images/'.$tecnico->persona->foto) }}" height="200">
                        @else
                            <img id="original" src="{{ url('images/sin_foto_tecnico.png') }}" height="200">
                        @endif


                    </div>
                </div>
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-8">

                <div class="form-group col-xs-12 col-sm-6 col-md-3">
                    <dt>Nombre</dt>
                    <dd>{{$tecnico->persona->nombre}}</dd>
                </div>

                <div class="form-group col-xs-12 col-sm-6 col-md-3">
                    <dt>Apellido</dt>
                    <dd>{{$tecnico->persona->apellido}}</dd>
                </div>
                <div class="form-group col-xs-12 col-sm-6 col-md-3">
                    <dt>Ciudad Nacimiento</dt>
                    <dd>{{$tecnico->persona->ciudad}}</dd>

                </div>
                <div class="form-group col-xs-12 col-sm-6 col-md-3">
                    <dt>Edad</dt>
                    <dd>{{($tecnico->persona->nacimiento)?$tecnico->persona->getAgeAttribute():''}}</dd>

                </div>
            </div>

        </div>


        <div class="row">


            <div class="form-group col-xs-12 col-sm-6 col-md-6">

                <dd>{{$tecnico->persona->observaciones}}</dd>

            </div>

        </div>
        <h1 class="display-6">Técnico</h1>
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
        @if(count($torneosJugador)>0)
        <h1 class="display-6">Jugador</h1>
        <table class="table">
            <thead>
            <th>#</th>
            <th>Torneo</th>
            <th>Equipos</th>
            <th>Jugados</th>
            <th>Goles</th>

            <th>Amarillas</th>

            <th>Rojas</th>

            <th>Arq. Recibidos</th>
            <th>Arq. V. Invictas</th>

            </thead>
            <tbody>


            @php
                $i = 1;
                $totalJugados = 0;
                $totalGoles = 0;
                $totalAmarillas = 0;
                $totalRojas = 0;
                $totalRecibidos = 0;
                $totalInvictas = 0;
            @endphp
            @foreach($torneosJugador as $torneo)
                @php
                    $totalJugados += $torneo->jugados;
                    $totalGoles += $torneo->goles;
                    $totalAmarillas += $torneo->amarillas;
                    $totalRojas += $torneo->rojas;
                    $totalRecibidos += $torneo->recibidos;
                    $totalInvictas += $torneo->invictas;
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
                    <td>{{$torneo->recibidos}}
                        @if($jugo)
                            ({{round($torneo->recibidos / $torneo->jugados,2)}})
                        @else
                            ({{round(0,2)}})
                        @endif
                    </td>
                    <td>{{$torneo->invictas}}
                        @if($jugo)
                            ({{round($torneo->invictas / $torneo->jugados,2)}})
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
                <td><strong>{{ $totalRecibidos}} ({{round($totalRecibidos / $totalJugados,2)}})</strong></td>
                <td><strong>{{ $totalInvictas}} ({{round($totalInvictas / $totalJugados,2)}})</strong></td>
            </tr>
            </tbody>
        </table>
        @endif

        <div class="d-flex">

            <a href="{{ url()->previous() }}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>

@endsection
