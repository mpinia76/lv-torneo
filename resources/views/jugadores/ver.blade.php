@extends('layouts.appPublic')

@section('pageTitle', 'Ver jugador')

@section('content')
    <div class="container py-4">

        {{-- Info Jugador --}}
        <div class="row mb-4 align-items-center">
            <div class="col-md-3 text-center">
                <img src="{{ $jugador->persona->foto ? url('images/'.$jugador->persona->foto) : url('images/sin_foto.png') }}"
                     alt="Foto jugador" class="img-fluid mb-2" style="max-height: 200px;">
                <div class="mb-2">
                    <img src="{{ $jugador->persona->bandera_url }}" alt="{{ $jugador->persona->nacionalidad }}" height="25">
                </div>
            </div>

            <div class="col-md-9">
                <div class="row mb-2">
                    <div class="col-md-3"><strong>Nombre</strong><br>{{ $jugador->persona->name }}</div>
                    <div class="col-md-3"><strong>Completo</strong><br>{{ $jugador->persona->nombre }} {{ $jugador->persona->apellido }}</div>
                    <div class="col-md-3"><strong>Ciudad Nacimiento</strong><br>{{ $jugador->persona->ciudad }}</div>
                    <div class="col-md-3"><strong>Edad</strong><br>
                        {!! $jugador->persona->fallecimiento ? '<img src="'.url('images/death.png').'" alt="Fallecido" height="20">' : '' !!}
                        {{ $jugador->persona->nacimiento ? $jugador->persona->getAgeAttribute() : '' }}
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-md-3"><strong>Posición</strong><br>{{ $jugador->tipoJugador }}</div>
                    <div class="col-md-3"><strong>Altura</strong><br>{{ $jugador->persona->altura }} m</div>
                    <div class="col-md-3"><strong>Peso</strong><br>{{ $jugador->persona->peso }} kg</div>
                </div>
                @if($jugador->persona->observaciones)
                    <div class="row mb-2">
                        <div class="col-12"><strong>Observaciones:</strong><br>{{ $jugador->persona->observaciones }}</div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Tabs --}}
        <ul class="nav nav-tabs mb-3" id="jugadorTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="jugador-tab" data-bs-toggle="tab" data-bs-target="#jugador" type="button" role="tab">Jugador</button>
            </li>
            @if(count($torneosTecnico) > 0)
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tecnico-tab" data-bs-toggle="tab" data-bs-target="#tecnico" type="button" role="tab">Técnico</button>
                </li>
            @endif
        </ul>

        <div class="tab-content">
            {{-- Tab Jugador --}}
            <div class="tab-pane fade show active" id="jugador" role="tabpanel">


                @include('jugadores.tabla')
            </div>

            {{-- Tab Técnico --}}
            @if(count($torneosTecnico) > 0)
                <div class="tab-pane fade" id="tecnico" role="tabpanel">


                    @include('tecnicos.tabla')
                </div>
            @endif
        </div>

        <div class="d-flex justify-content-start mt-4">
            <a href="{{ url()->previous() }}" class="btn btn-success">Volver</a>
        </div>
    </div>
@endsection
