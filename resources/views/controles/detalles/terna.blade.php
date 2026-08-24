{{-- Qué rol de la terna falta. Las tres columnas vienen como EXISTS (0 o 1). --}}
@php
    $roles = [
        'Principal' => $fila->tiene_principal,
        'Línea 1'   => $fila->tiene_linea1,
        'Línea 2'   => $fila->tiene_linea2,
    ];
@endphp
@foreach($roles as $etiqueta => $tiene)
    @if(!$tiene)
        <span class="ctrl-chip mal">falta {{ $etiqueta }}</span>
    @endif
@endforeach
