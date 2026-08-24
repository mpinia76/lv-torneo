@props([
    'data',                  // colección de partidos
    'showNeutral' => false,  // marcar los jugados en cancha neutral
    'showTorneo'  => false,  // mostrar torneo y año
])

<div class="t-panel t-lista-partidos">
    @foreach($data as $partido)
        <x-partido :p="$partido" :torneo="$showTorneo" :neutral="$showNeutral"/>
    @endforeach
</div>
