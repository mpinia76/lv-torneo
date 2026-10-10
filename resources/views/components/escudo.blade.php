{{-- prioridad: el escudo es lo principal de la página (el marcador del partido). Va sin
     carga diferida y con prioridad alta: con loading="lazy" el navegador lo pide tarde y
     es justo lo que mide el LCP. --}}
@props(['src' => null, 'nombre' => '', 'tam' => null, 'prioridad' => false])

@php
    $clase = 'escudo' . ($tam ? ' escudo-' . $tam : '');

    // Sin archivo cargado mostramos las iniciales en la misma caja, para que
    // la fila no quede con un hueco ni se descoloque.
    $iniciales = '';
    if (!$src) {
        $palabras = preg_split('/\s+/', trim(strip_tags($nombre)));
        foreach (array_slice(array_filter($palabras), 0, 2) as $palabra) {
            $iniciales .= mb_substr($palabra, 0, 1);
        }
        $iniciales = mb_strtoupper($iniciales);
    }
@endphp

@if($src)
    <img {{ $attributes->merge(['class' => $clase]) }}
         src="{{ url('images/' . $src) }}"
         alt="{{ $nombre }}"
         title="{{ $nombre }}"
         @if($prioridad) fetchpriority="high" @else loading="lazy" @endif>
@else
    <span {{ $attributes->merge(['class' => $clase . ' escudo-txt']) }} title="{{ $nombre }}">{{ $iniciales }}</span>
@endif
