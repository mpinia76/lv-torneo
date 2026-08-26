{{--
    Columna "Equipos" de los listados: sólo escudos (el dato va en el tooltip) y
    un botón que abre la fila de detalle. Se usa junto con <x-clubes-detalle>,
    que tiene que llevar el MISMO id.
--}}
@props(['clubes' => [], 'id' => null, 'max' => 6])

@if(count($clubes))
    <span class="t-lista-escudos">
        @foreach(array_slice($clubes, 0, $max) as $club)
            <a href="{{ route('equipos.ver', ['equipoId' => $club['id']]) }}">
                <x-escudo :src="$club['escudo']"
                          :nombre="trim(($club['nombre'] ?? '').(!empty($club['dato']) ? ' · '.$club['dato'] : ''), ' ·')"/>
            </a>
        @endforeach

        <button type="button" class="t-lista-mas" data-lista-abre="{{ $id }}"
                aria-expanded="false" aria-controls="{{ $id }}" title="Ver el detalle por club">
            @if(count($clubes) > $max)
                +{{ count($clubes) - $max }}
            @else
                <i class="bi bi-chevron-down"></i>
            @endif
        </button>
    </span>
@endif
