@extends('layouts.appPublic')

@section('pageTitle', $jugador->persona->name ?: __('Ver jugador'))

@section('content')
    @php
        $vjP   = $jugador->persona;
        $vjDoble = count($torneosTecnico) > 0;   // también dirigió: hay dos carreras
        $vjDatos = [
            __('Posición') => trad_dato($jugador->tipoJugador),
            __('Pie')      => trad_dato($jugador->pie ?? ''),
            __('Nacido')   => $vjP->nacimiento ? trim($vjP->getAgeAttribute()) : '',
            __('Ciudad')   => $vjP->ciudad,
            __('Altura')   => $vjP->altura ? $vjP->altura.' m' : '',
            __('Peso')     => $vjP->peso ? $vjP->peso.' kg' : '',
        ];
    @endphp

    <div class="container t-ficha-pagina">

        <x-ficha-persona :persona="$vjP" :rol="__('Jugador')" fallback="sin_foto.png" :datos="$vjDatos"/>

        @if($vjDoble)
            <ul class="nav nav-tabs" id="jugadorTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="jugador-tab" data-bs-toggle="tab"
                            data-bs-target="#jugador" type="button" role="tab">{{ __('Como jugador') }}</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tecnico-tab" data-bs-toggle="tab"
                            data-bs-target="#tecnico" type="button" role="tab">{{ __('Como técnico') }}</button>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="jugador" role="tabpanel">
                    @include('jugadores.tabla')
                </div>
                <div class="tab-pane fade" id="tecnico" role="tabpanel">
                    @include('tecnicos.tabla')
                </div>
            </div>
        @else
            @include('jugadores.tabla')
        @endif

        <div class="d-flex justify-content-start my-4">
            <a href="{{ url()->previous() }}" class="btn btn-success btn-sm">
                <i class="bi bi-arrow-left"></i> {{ __('Volver') }}
            </a>
        </div>
    </div>
@endsection
