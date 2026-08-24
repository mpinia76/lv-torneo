@extends('layouts.appPublic')

@section('pageTitle', $q ? 'Buscar: ' . $q : 'Buscar')

@section('content')

    <div class="t-cabecera">
        <div>
            <span class="t-eyebrow">Búsqueda</span>
            <h1>{{ $q ? '“' . $q . '”' : 'Buscar' }}</h1>
        </div>

        <form action="{{ route('buscar') }}" method="GET" class="d-flex gap-2">
            <input type="search" name="q" value="{{ $q }}" class="form-control form-control-sm" style="width: 260px"
                   placeholder="Equipo, jugador, técnico o árbitro" autofocus>
            <button class="btn btn-outline-secondary btn-sm" type="submit">Buscar</button>
        </form>
    </div>

    @if($demasiadoCorto)
        <div class="t-panel">
            <div class="t-panel-cuerpo">Escribí al menos dos letras para buscar.</div>
        </div>
    @elseif($total === 0)
        <div class="t-panel">
            <div class="t-panel-cuerpo">No hay equipos, jugadores, técnicos ni árbitros que coincidan con <strong>{{ $q }}</strong>.</div>
        </div>
    @else

        @if($equipos->count())
            <div class="t-panel">
                <div class="t-grupo"><span class="t-grupo-nombre">Equipos</span><span class="t-eyebrow">{{ $equipos->count() }}</span></div>
                <div class="t-resultados">
                    @foreach($equipos as $equipo)
                        <a class="t-resultado" href="{{ route('equipos.ver', ['equipoId' => $equipo->id]) }}">
                            <x-escudo :src="$equipo->escudo" :nombre="$equipo->nombre"/>
                            <span class="t-resultado-nombre">{{ $equipo->nombre }}</span>
                            @if($equipo->pais)
                                <span class="t-resultado-dato">{{ $equipo->pais }}</span>
                            @endif
                            <i class="bi bi-chevron-right t-chevron"></i>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        @php
            $bloques = [
                ['titulo' => 'Jugadores', 'items' => $jugadores, 'ruta' => 'jugadores.ver', 'param' => 'jugadorId'],
                ['titulo' => 'Técnicos',  'items' => $tecnicos,  'ruta' => 'tecnicos.ver',  'param' => 'tecnicoId'],
                ['titulo' => 'Árbitros',  'items' => $arbitros,  'ruta' => 'arbitros.ver',  'param' => 'arbitroId'],
            ];
        @endphp

        @foreach($bloques as $bloque)
            @if($bloque['items']->count())
                <div class="t-panel">
                    <div class="t-grupo">
                        <span class="t-grupo-nombre">{{ $bloque['titulo'] }}</span>
                        <span class="t-eyebrow">{{ $bloque['items']->count() }}</span>
                    </div>
                    <div class="t-resultados">
                        @foreach($bloque['items'] as $item)
                            @php $persona = $item->persona; @endphp
                            <a class="t-resultado" href="{{ route($bloque['ruta'], [$bloque['param'] => $item->id]) }}">
                                <img class="imgCircle" src="{{ url('images/' . ($persona && $persona->foto ? $persona->foto : 'sin_foto.png')) }}" alt="">
                                <span class="t-resultado-nombre">{{ $persona ? $persona->full_name : '' }}</span>
                                @if($persona && $persona->bandera_url)
                                    <img class="bandera" src="{{ $persona->bandera_url }}" alt="{{ $persona->nacionalidad }}" title="{{ $persona->nacionalidad }}">
                                @endif
                                @if($bloque['titulo'] === 'Jugadores' && $item->tipoJugador)
                                    <span class="t-resultado-dato">{{ $item->tipoJugador }}</span>
                                @endif
                                <i class="bi bi-chevron-right t-chevron"></i>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach

        <p class="t-eyebrow mt-3">Se muestran hasta 20 resultados por tipo</p>
    @endif

@endsection
