@extends('layouts.appPublic')

@section('pageTitle', 'Ver equipo')

@section('content')
    <div class="container">


        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <dt>Nombre</dt>
                <dd>{{$equipo->nombre}}</dd>
            </div>


            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                <dt>Socios</dt>
                <dd>{{$equipo->socios}}</dd>
            </div>

        </div>
        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <dt>Fundación</dt>
                <dd>{{date('d/m/Y', strtotime($equipo->fundacion))}} - {{Carbon::parse($equipo->fundacion)->age}} años</dd>

            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <dt>Estadio</dt>
                <dd>{{$equipo->estadio}}</dd>
            </div>


        </div>

        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <div class="form-group">

                    @if($equipo->escudo)
                        <img id="original" src="{{ url('images/'.$equipo->escudo) }}" height="200">
                    @endif


                </div>
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-6">
                <dt>Historia</dt>
                <dd>{{$equipo->historia}}</dd>

            </div>

        </div>


		<table class="table">
            <thead>
            <th>#</th>
            <th>Torneo</th>
            <th>J</th>
            <th>G</th>
            <th>E</th>
            <th>P</th>
            <th>GF</th>
            <th>GC</th>
            <th>Dif.</th>
            <th>Punt.</th>
            <th>Prom.</th>

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
            @foreach($torneosEquipo as $torneo)
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
                    <td><a href="{{route('equipos.jugados', array('equipoId' => $equipo->id,'torneoId'=>$torneo->idTorneo))}}" >{{$torneo->jugados}}</a></td>
                    <td><a href="{{route('equipos.jugados', array('equipoId' => $equipo->id,'torneoId'=>$torneo->idTorneo,'tipo'=>'Ganados'))}}" >{{$torneo->ganados}}</a></td>
                    <td><a href="{{route('equipos.jugados', array('equipoId' => $equipo->id,'torneoId'=>$torneo->idTorneo,'tipo'=>'Empatados'))}}" >{{$torneo->empatados}}</a></td>
                    <td><a href="{{route('equipos.jugados', array('equipoId' => $equipo->id,'torneoId'=>$torneo->idTorneo,'tipo'=>'Perdidos'))}}" >{{$torneo->perdidos}}</a></td>
                    <td>{{$torneo->favor}}</td>
                    <td>{{$torneo->contra}}</td>
                    <td>{{$torneo->favor - $torneo->contra}}</td>
                    <td>{{$torneo->puntaje}}</td>
                    <td>{{$torneo->porcentaje}}</td>


                </tr>
            @endforeach
			<tr>
                <td></td>

                <td><strong>Totales</strong></td>
                <td><strong><a href="{{route('equipos.jugados', array('equipoId' => $equipo->id))}}" >{{ $totalJugados}}</a></strong></td>
                <td><strong><a href="{{route('equipos.jugados', array('equipoId' => $equipo->id,'tipo'=>'Ganados'))}}" >{{ $totalGanados}}</a></strong></td>
                <td><strong><a href="{{route('equipos.jugados', array('equipoId' => $equipo->id,'tipo'=>'Empatados'))}}" >{{ $totalEmpatados}}</a></strong></td>
                <td><strong><a href="{{route('equipos.jugados', array('equipoId' => $equipo->id,'tipo'=>'Perdidos'))}}" >{{ $totalPerdidos}}</a></strong></td>
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
