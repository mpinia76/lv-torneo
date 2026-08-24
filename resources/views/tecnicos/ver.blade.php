@extends('layouts.appPublic')

@section('pageTitle', $tecnico->persona->name ?: 'Ver técnico')

@section('content')
    @php
        $vtP     = $tecnico->persona;
        $vtDoble = count($torneosJugador) > 0;   // también jugó: hay dos carreras
        $vtDatos = [
            'Nacido' => $vtP->nacimiento ? trim($vtP->getAgeAttribute()) : '',
            'Ciudad' => $vtP->ciudad,
            'Altura' => $vtP->altura ? $vtP->altura.' m' : '',
            'Peso'   => $vtP->peso ? $vtP->peso.' kg' : '',
        ];
    @endphp

    <div class="container t-ficha-pagina">

        <x-ficha-persona :persona="$vtP" rol="Técnico" fallback="sin_foto_tecnico.png" :datos="$vtDatos"/>

        @if($vtDoble)
            <ul class="nav nav-tabs" id="tecnicoTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tecnico-tab" data-bs-toggle="tab"
                            data-bs-target="#tecnico" type="button" role="tab">Como técnico</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="jugador-tab" data-bs-toggle="tab"
                            data-bs-target="#jugador" type="button" role="tab">Como jugador</button>
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
            <a href="{{ url()->previous() }}" class="btn btn-success btn-sm">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>
@endsection
