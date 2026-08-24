@extends('layouts.appPublic')

@section('pageTitle', 'Fecha')

@section('content')

    <div class="t-cabecera">
        <div>
            <span class="t-eyebrow">Fecha</span>
            <h1>
                @if(is_numeric($fecha->numero))
                    Fecha {{ $fecha->numero }}
                @else
                    {{ $fecha->numero }}
                @endif
            </h1>
        </div>
    </div>

    <div class="t-panel t-lista-partidos">
        @foreach($fecha->partidos as $partido)
            @if($partido->dia)
                <x-partido :p="$partido" :torneo="false"/>
            @endif
        @endforeach
    </div>

    <div class="d-flex mt-3">
        <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm">Volver</a>
    </div>

@endsection
