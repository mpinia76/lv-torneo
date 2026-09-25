@extends('layouts.appPublic')

@section('pageTitle', $arbitro->persona->name ?: __('Ver árbitro'))

@section('content')
    @php
        $vaP = $arbitro->persona;
        $vaDatos = [
            __('Nacido') => $vaP->nacimiento ? trim($vaP->getAgeAttribute()) : '',
            __('Ciudad') => $vaP->ciudad,
        ];
    @endphp

    <div class="container t-ficha-pagina">

        <x-ficha-persona :persona="$vaP" :rol="__('Árbitro')" fallback="sin_foto_arbitro.png" :datos="$vaDatos"/>

        <div class="t-panel">
            <div class="t-vacio">
                <i class="bi bi-clipboard-x"></i>
                {{ __('Todavía no hay estadísticas de arbitraje cargadas para esta ficha.') }}
            </div>
        </div>

        <div class="d-flex justify-content-start my-4">
            <a href="{{ url()->previous() }}" class="btn btn-success btn-sm">
                <i class="bi bi-arrow-left"></i> {{ __('Volver') }}
            </a>
        </div>
    </div>
@endsection
