@extends('layouts.appPublic')

@section('pageTitle', 'Ver torneo')

@section('content')
    <div class="container">






        <!--<h1 class="display-6">{{$torneo->nombre}} - {{$torneo->year}}</h1>-->

        <div class="d-flex">

                <a href="{{route('fechas.ver',  array('torneoId' => $torneo->id))}}" class="btn btn-success m-1">Fechas</a>


                <a href="{{route('grupos.posicionesPublic',  array('torneoId' => $torneo->id))}}" class="btn btn-primary m-1">Posiciones</a>
            <a href="{{route('grupos.goleadoresPublic',  array('torneoId' => $torneo->id))}}" class="btn btn-info m-1">Goleadores</a>
            <a href="{{route('grupos.tarjetasPublic',  array('torneoId' => $torneo->id))}}" class="btn btn-primary m-1">Tarjetas</a>
                <a href="{{route('torneos.promediosPublic',  array('torneoId' => $torneo->id))}}" class="btn btn-success m-1">Promedios</a>
        </div>


    </div>

@endsection
