@extends('layouts.appPublic')

@section('pageTitle', $torneo->nombre . ' ' . $torneo->year)

{{-- Página de paso (cinco botones). La página del torneo para los buscadores
     es el fixture (fechas.ver), que es la que está en el sitemap. --}}
@section('robots', 'noindex, follow')

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
