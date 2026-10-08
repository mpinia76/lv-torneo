@extends('layouts.appPublic')

@php
    // Título y descripción para los buscadores, con los números de la ficha.
    $seoNombre   = $tecnico->persona->name ?: __('Técnico');
    $seoT        = collect($torneosTecnico);
    $seoPartidos = (int) $seoT->sum('jugados');
    $seoTitulos  = $titulosTecnicoLiga + $titulosTecnicoCopa + $titulosTecnicoInternacional;
    $seoDesc = $seoPartidos > 0
        ? $seoNombre . ': ' . cantidad($seoPartidos, 'partido dirigido', 'partidos dirigidos')
            . ' (' . lista_y([
                cantidad($seoT->sum('ganados'), 'ganado', 'ganados'),
                cantidad($seoT->sum('empatados'), 'empatado', 'empatados'),
                cantidad($seoT->sum('perdidos'), 'perdido', 'perdidos'),
            ]) . ') ' . __('en') . ' ' . cantidad($seoT->count(), 'torneo', 'torneos')
            . ($seoTitulos > 0 ? ', ' . cantidad($seoTitulos, 'título', 'títulos') : '') . '. '
            . __('Todos sus partidos como entrenador.')
        : __(':nombre: ficha de director técnico con sus partidos dirigidos y títulos.', ['nombre' => $seoNombre]);
@endphp
@section('pageTitle', __(':nombre — partidos dirigidos y títulos', ['nombre' => $seoNombre]))
@section('pageDescription', $seoDesc)
@if($tecnico->persona->foto)
    @section('pageImage', url_imagen($tecnico->persona->foto))
@endif

@section('content')
    @php
        $vtP     = $tecnico->persona;
        $vtDoble = count($torneosJugador) > 0;   // también jugó: hay dos carreras
        $vtDatos = [
            __('Nacido') => $vtP->nacimiento ? trim($vtP->getAgeAttribute()) : '',
            __('Ciudad') => $vtP->ciudad,
            __('Altura') => $vtP->altura ? $vtP->altura.' m' : '',
            __('Peso')   => $vtP->peso ? $vtP->peso.' kg' : '',
        ];
    @endphp

    <div class="container t-ficha-pagina">

        <x-ficha-persona :persona="$vtP" :rol="__('Técnico')" fallback="sin_foto_tecnico.png" :datos="$vtDatos"/>

        @if($vtDoble)
            <ul class="nav nav-tabs" id="tecnicoTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tecnico-tab" data-bs-toggle="tab"
                            data-bs-target="#tecnico" type="button" role="tab">{{ __('Como técnico') }}</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="jugador-tab" data-bs-toggle="tab"
                            data-bs-target="#jugador" type="button" role="tab">{{ __('Como jugador') }}</button>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="tecnico" role="tabpanel">
                    @include('tecnicos.tabla')
                </div>
                <div class="tab-pane fade" id="jugador" role="tabpanel">
                    @include('jugadores.tabla')
                </div>
            </div>
        @else
            @include('tecnicos.tabla')
        @endif

        <div class="d-flex justify-content-start my-4">
            <a href="{{ url_volver() }}" class="btn btn-success btn-sm">
                <i class="bi bi-arrow-left"></i> {{ __('Volver') }}
            </a>
        </div>
    </div>
@endsection
