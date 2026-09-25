@extends('layouts.appPublic')

@section('pageTitle', $zonaActual ? 'Torneos de ' . $zonaActual['nombre'] : 'Competiciones')

@section('content')

    {{--
        Todas las competiciones por país / región (MenuTorneosController@explorar).
        Es el mismo contenido del menú «Torneos», como página común: anda sin JS,
        la leen los buscadores y se puede compartir el link de un país (?zona=).
    --}}

    <div class="t-cabecera">
        <div>
            <span class="t-eyebrow">Competiciones</span>
            <h1>{{ $zonaActual ? $zonaActual['nombre'] : 'Competiciones' }}</h1>
        </div>
    </div>

    @if(!$zonaActual)
        <div class="t-panel">
            <div class="t-panel-cuerpo">Todavía no hay torneos cargados.</div>
        </div>
    @else
        @php
            $titulosGrupo = ['local' => '', 'inter' => 'Internacional', 'paises' => 'Ligas del mundo'];
            $grupoPrevio  = null;

            $vigentes   = array_values(array_filter($zonaActual['competencias'], function ($c) { return !$c['historica']; }));
            $historicas = array_values(array_filter($zonaActual['competencias'], function ($c) { return $c['historica']; }));
            $tipos      = array_unique(array_column($vigentes, 'tipo'));
            $conTitulos = count($tipos) > 1;
            $tipoPrevio = null;
        @endphp

        <div class="t-explorar">

            <nav class="t-panel t-explorar-zonas" aria-label="Países y regiones">
                @foreach($zonas as $z)
                    @if($z['grupo'] !== $grupoPrevio)
                        @php $grupoPrevio = $z['grupo']; @endphp
                        @if($titulosGrupo[$z['grupo']])
                            <div class="t-mega-rot">{{ $titulosGrupo[$z['grupo']] }}</div>
                        @endif
                    @endif
                    <a class="t-mega-zona {{ $z['clave'] === $zonaActual['clave'] ? 'activo' : '' }}"
                       href="{{ route('torneos.explorar', ['zona' => $z['clave']]) }}"
                       @if($z['clave'] === $zonaActual['clave']) aria-current="page" @endif>
                        @if($z['bandera'])
                            <img class="bandera" src="{{ url('images/'.$z['bandera']) }}" alt="" onerror="this.remove()">
                        @else
                            <i class="bi bi-globe2"></i>
                        @endif
                        <span class="t-mega-zona-nombre">{{ $z['nombre'] }}</span>
                        <span class="t-mega-cuenta">{{ count($z['competencias']) }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="t-panel t-explorar-lista">
                @foreach($vigentes as $c)
                    @if($conTitulos && $c['tipo'] !== $tipoPrevio)
                        @php $tipoPrevio = $c['tipo']; @endphp
                        <div class="t-mega-rot">{{ $c['tipo'] === 'Liga' ? 'Ligas' : 'Copas' }}</div>
                    @endif
                    @include('torneos._competenciaFila', ['c' => $c])
                @endforeach

                @if(count($historicas))
                    <div class="t-mega-rot" style="margin-top: 10px">Torneos que ya no se juegan</div>
                    @foreach($historicas as $c)
                        @include('torneos._competenciaFila', ['c' => $c])
                    @endforeach
                @endif
            </div>
        </div>
    @endif

@endsection
