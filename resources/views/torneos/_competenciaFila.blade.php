{{-- Una competencia en /competiciones: nombre (va a la última temporada) y todas sus temporadas. --}}
@php $ultima = $c['ediciones'][0]; @endphp

<div class="t-mega-comp">
    <a class="t-mega-comp-nombre" href="{{ route('fechas.ver', ['torneoId' => $ultima['id']]) }}">
        <x-escudo :src="$c['escudo']" :nombre="$c['nombre']" tam="sm"/>
        <span class="t-mega-comp-txt"><span class="t-mega-comp-n">{{ $c['nombre'] }}</span></span>
    </a>
    <div class="t-mega-anios">
        @foreach($c['ediciones'] as $ed)
            <a class="t-mega-anio" href="{{ route('fechas.ver', ['torneoId' => $ed['id']]) }}"
               title="{{ $c['nombre'] }} {{ $ed['year'] }}">{{ $ed['year'] }}</a>
        @endforeach
    </div>
</div>
