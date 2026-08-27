{{--
    Celda de equipo para los listados: escudo, nombre y banderita del país.
    <x-celda-equipo :href="route('equipos.ver', ...)" :nombre="$e->nombre"
                    :escudo="$e->escudo" :pais="$e->pais"/>
--}}
@props([
    'href'   => null,
    'nombre' => '',
    'escudo' => null,
    'pais'   => null,
    'tam'    => null,
])

<span class="t-equipo-celda">
    <a href="{{ $href }}"><x-escudo :src="$escudo" :nombre="$nombre" :tam="$tam"/></a>
    <a href="{{ $href }}">{{ $nombre }}</a>
    @if($pais)
        <img class="bandera" src="{{ url('images/'.removeAccents($pais).'.gif') }}"
             alt="{{ $pais }}" title="{{ $pais }}">
    @endif
</span>
