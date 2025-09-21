<div class="row mt-3">
    <div class="form-group col-md-3">
        <dt>Títulos</dt>
        <dd>{{$titulosTecnicoLiga + $titulosTecnicoCopa + $titulosTecnicoInternacional}}</dd>
    </div>
    <div class="form-group col-md-3">
        <dt>Ligas nacionales</dt>
        <dd>{{$titulosTecnicoLiga}}</dd>
    </div>
    <div class="form-group col-md-3">
        <dt>Copas nacionales</dt>
        <dd>{{$titulosTecnicoCopa}}</dd>
    </div>
    <div class="form-group col-md-3">
        <dt>Internacionales</dt>
        <dd>{{$titulosTecnicoInternacional}}</dd>
    </div>
</div>
<table class="table table-striped table-hover align-middle" style="font-size: 14px;">
    <thead class="table-dark">
    <th>#</th>
    <th>Torneo</th>
    <th>Equipos</th>
    <th>Punt.</th>
    <th>J</th>
    <th>G</th>
    <th>E</th>
    <th>P</th>
    <th>GF</th>
    <th>GC</th>
    <th>Dif.</th>
    <th>%</th>
    </thead>
    <tbody>
    @php
        $i = 1;
        $totalJugados = $totalGanados = $totalEmpatados = $totalPerdidos = $totalFavor = $totalContra = $totalPuntaje = 0;
    @endphp
    @foreach($torneosTecnico as $torneo)
        @php
            $totalJugados += $torneo->jugados;
            $totalGanados += $torneo->ganados;
            $totalEmpatados += $torneo->empatados;
            $totalPerdidos += $torneo->perdidos;
            $totalFavor += $torneo->favor;
            $totalContra += $torneo->contra;
            $totalPuntaje += $torneo->puntaje;
        @endphp
        <tr>
            <td>{{$i++}}</td>
            <td>
                @if($torneo->escudoTorneo)
                    <img src="{{ url('images/'.$torneo->escudoTorneo) }}" height="25">
                @endif
                {{$torneo->nombreTorneo}}
            </td>
            <td>
                @if($torneo->escudo)
                    @foreach(explode(',', $torneo->escudo) as $escudo)
                        @if($escudo != '')
                            @php $escudoArr = explode('_', $escudo); @endphp
                            <a href="{{route('equipos.ver', ['equipoId' => $escudoArr[1]])}}">
                                <img src="{{ url('images/'.$escudoArr[0]) }}" height="25">
                                @if(isset($escudoArr[2]) && $escudoArr[2] != '')
                                    Pos: {!! $escudoArr[2] !!}
                                @endif
                            </a>
                        @endif
                    @endforeach
                @endif
            </td>
            <td>{{$torneo->puntaje}}</td>
            <td><a href="{{route('tecnicos.jugados', ['tecnicoId'=>$torneo->idTecnico,'torneoId'=>$torneo->idTorneo])}}">{{$torneo->jugados}}</a></td>
            <td><a href="{{route('tecnicos.jugados', ['tecnicoId'=>$torneo->idTecnico,'torneoId'=>$torneo->idTorneo,'tipo'=>'Ganados'])}}">{{$torneo->ganados}}</a></td>
            <td><a href="{{route('tecnicos.jugados', ['tecnicoId'=>$torneo->idTecnico,'torneoId'=>$torneo->idTorneo,'tipo'=>'Empatados'])}}">{{$torneo->empatados}}</a></td>
            <td><a href="{{route('tecnicos.jugados', ['tecnicoId'=>$torneo->idTecnico,'torneoId'=>$torneo->idTorneo,'tipo'=>'Perdidos'])}}">{{$torneo->perdidos}}</a></td>
            <td>{{$torneo->favor}}</td>
            <td>{{$torneo->contra}}</td>
            <td>{{$torneo->favor - $torneo->contra}}</td>
            <td>{{$torneo->porcentaje}}</td>
        </tr>
    @endforeach
    <!-- Totales -->
    <tr>
        <td></td><td></td><td><strong>Totales</strong></td>
        <td><strong><a href="{{route('tecnicos.jugados', ['tecnicoId'=>$torneo->idTecnico])}}">{{$totalJugados}}</a></strong></td>
        <td><strong><a href="{{route('tecnicos.jugados', ['tecnicoId'=>$torneo->idTecnico,'tipo'=>'Ganados'])}}">{{$totalGanados}}</a></strong></td>
        <td><strong><a href="{{route('tecnicos.jugados', ['tecnicoId'=>$torneo->idTecnico,'tipo'=>'Empatados'])}}">{{$totalEmpatados}}</a></strong></td>
        <td><strong><a href="{{route('tecnicos.jugados', ['tecnicoId'=>$torneo->idTecnico,'tipo'=>'Perdidos'])}}">{{$totalPerdidos}}</a></strong></td>
        <td><strong>{{$totalFavor}}</strong></td>
        <td><strong>{{$totalContra}}</strong></td>
        <td><strong>{{$totalFavor-$totalContra}}</strong></td>
        <td><strong>{{$totalPuntaje}}</strong></td>
        <td><strong>{{round($totalPuntaje*100/($totalJugados*3),2)}}%</strong></td>
    </tr>
    </tbody>
</table>
