@extends('layouts.appPublic')

@section('pageTitle', __('Fecha'))

@section('content')

    <div class="t-cabecera">
        <div>
            <span class="t-eyebrow">{{ __('Fecha') }}</span>
            <h1>
                @if(is_numeric($fecha->numero))
                    {{ __('Fecha :numero', ['numero' => $fecha->numero]) }}
                @else
                    {{ trad_dato($fecha->numero) }}
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
        <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm">{{ __('Volver') }}</a>
    </div>

@endsection
