{{--
    Celda de persona para los listados de Protagonistas: foto redonda, nombre,
    banderita y, si corresponde, una segunda línea con los clubes actuales.

    <x-celda-persona :href="route('jugadores.ver', ...)" :nombre="$j->jugador"
                     :foto="$j->foto" :nacionalidad="$j->nacionalidad"
                     rot="Juega en" :clubes="$actuales"/>

    $clubes son arrays de clubesDesdeCadena(): escudo, id, nombre.
--}}
@props([
    'href'         => null,
    'nombre'       => '',
    'foto'         => null,
    'fotoDefecto'  => 'sin_foto.png',
    'nacionalidad' => null,
    'rot'          => 'Juega en',
    'clubes'       => [],
])

<span class="t-persona-celda">
    <a href="{{ $href }}">
        <img src="{{ url('images/'.($foto ?: $fotoDefecto)) }}" alt="{{ $nombre }}" loading="lazy">
    </a>
    <span class="t-lista-txt">
        <span class="t-lista-nombre">
            <a href="{{ $href }}">{{ $nombre }}</a>
            @if($nacionalidad)
                <img class="bandera" src="{{ url('images/'.removeAccents($nacionalidad).'.gif') }}"
                     alt="{{ $nacionalidad }}" title="{{ $nacionalidad }}">
            @endif
        </span>
        @if(count($clubes))
            <span class="t-lista-sub">
                <span class="t-lista-punto"></span> {{ $rot }}
                @foreach($clubes as $club)
                    <a href="{{ route('equipos.ver', ['equipoId' => $club['id']]) }}">
                        <x-escudo :src="$club['escudo']" :nombre="$club['nombre'] ?? ''" tam="sm"/>
                    </a>
                @endforeach
            </span>
        @endif
    </span>
</span>
