{{-- Roles cargados más de una vez en el mismo partido. --}}
@foreach(explode(', ', (string) $fila->roles) as $rol)
    <span class="ctrl-chip mal">{{ $rol }}</span>
@endforeach
