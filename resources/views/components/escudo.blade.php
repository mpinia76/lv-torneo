@props(['src' => null, 'nombre' => '', 'tam' => null])

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
         loading="lazy">
@else
    <span {{ $attributes->merge(['class' => $clase . ' escudo-txt']) }} title="{{ $nombre }}">{{ $iniciales }}</span>
@endif
