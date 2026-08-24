{{--
    Cabecera de ficha de persona. La usan jugador, técnico y árbitro, así que
    cualquier cambio acá se ve en las tres.

    <x-ficha-persona :persona="$jugador->persona" rol="Jugador"
                     fallback="sin_foto.png" :datos="$datos"/>

    $datos es un array ['Etiqueta' => 'valor']; los vacíos no se muestran.
--}}
@props([
    'persona',
    'rol'      => '',
    'fallback' => 'sin_foto.png',
    'datos'    => [],
])

@php
    $fpFoto    = $persona->foto ? url('images/'.$persona->foto) : url('images/'.$fallback);
    $fpCorto   = trim($persona->name) ?: trim($persona->nombre.' '.$persona->apellido);
    $fpLargo   = trim($persona->nombre.' '.$persona->apellido);
    $fpMuestraLargo = $fpLargo !== '' && mb_strtolower($fpLargo) !== mb_strtolower($fpCorto);
@endphp

<div {{ $attributes->merge(['class' => 't-ficha']) }}>
    <img class="t-ficha-foto" src="{{ $fpFoto }}" alt="{{ $fpCorto }}" loading="lazy">

    <div class="t-ficha-cuerpo">
        @if($rol)
            <span class="t-eyebrow">{{ $rol }}</span>
        @endif

        <h1 class="t-ficha-nombre">
            {{ $fpCorto }}
            @if($persona->nacionalidad)
                <img class="bandera" src="{{ $persona->bandera_url }}"
                     alt="{{ $persona->nacionalidad }}" title="{{ $persona->nacionalidad }}">
            @endif
            @if($persona->fallecimiento)
                <img class="t-ficha-obito" src="{{ url('images/death.png') }}"
                     alt="Fallecido" title="Fallecido" height="18">
            @endif
        </h1>

        @if($fpMuestraLargo)
            <div class="t-ficha-sub">{{ $fpLargo }}</div>
        @endif

        @php
            $fpChips = array_filter($datos, function ($v) { return trim((string) $v) !== ''; });
        @endphp
        @if(count($fpChips))
            <div class="t-ficha-chips">
                @foreach($fpChips as $fpEtiqueta => $fpValor)
                    <span class="t-dato"><span>{{ $fpEtiqueta }}</span><b>{{ $fpValor }}</b></span>
                @endforeach
            </div>
        @endif

        @if(trim((string) $persona->observaciones) !== '')
            <p class="t-ficha-obs">{{ $persona->observaciones }}</p>
        @endif

        {{ $slot }}
    </div>
</div>
