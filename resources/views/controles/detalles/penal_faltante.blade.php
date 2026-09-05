{{-- Qué arquero se le va a asignar al penal cuando se apriete "Crear". --}}
@if(isset($fila->minuto) && $fila->minuto !== null)
    <span class="ctrl-chip neutro">minuto {{ \App\Services\MinutoHelper::texto($fila->minuto, $fila->adicionado ?? null) }}</span>
@endif
@if(!empty($fila->arquero_nombre))
    <span class="ctrl-chip ok">arquero: {{ $fila->arquero_nombre }}</span>
@else
    <span class="ctrl-motivo">{{ $fila->motivo ?? 'No se pudo determinar el arquero.' }}</span>
@endif
