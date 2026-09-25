@extends('layouts.appPublic')

@section('pageTitle', __('Ver torneo'))

@section('content')
    <div class="container">






        <!--<h1 class="display-6">{{$torneo->nombre}} - {{$torneo->year}}</h1>-->

        <div class="d-flex">

                <a href="{{route('fechas.ver',  array('torneoId' => $torneo->id))}}" class="btn btn-success m-1">{{ __('Fechas') }}</a>


                <a href="{{route('grupos.posicionesPublic',  array('torneoId' => $torneo->id))}}" class="btn btn-primary m-1">{{ __('Posiciones') }}</a>
            <a href="{{route('grupos.goleadoresPublic',  array('torneoId' => $torneo->id))}}" class="btn btn-info m-1">{{ __('Goleadores') }}</a>
            <a href="{{route('grupos.tarjetasPublic',  array('torneoId' => $torneo->id))}}" class="btn btn-primary m-1">{{ __('Tarjetas') }}</a>
                <a href="{{route('torneos.promediosPublic',  array('torneoId' => $torneo->id))}}" class="btn btn-success m-1">{{ __('Promedios') }}</a>
        </div>


    </div>

@endsection
