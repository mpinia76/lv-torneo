<div class="row mb-3">
    <div class="col-md-3"><strong>Títulos</strong><br>{{ $titulosJugadorLiga+$titulosJugadorCopa+$titulosJugadorInternacional }}</div>
    <div class="col-md-3"><strong>Ligas nacionales</strong><br>{{ $titulosJugadorLiga }}</div>
    <div class="col-md-3"><strong>Copas nacionales</strong><br>{{ $titulosJugadorCopa }}</div>
    <div class="col-md-3"><strong>Internacionales</strong><br>{{ $titulosJugadorInternacional }}</div>
</div>
<table class="table table-striped table-hover align-middle" style="font-size: 14px;">
    <thead class="table-dark">
    <th>#</th>
    <th>Torneo</th>
    <th>Equipos</th>
    <th>Jugados</th>
    <th>Goles</th>
    <th>Amarillas</th>
    <th>Rojas</th>
    <th>P. Errados</th>
    <th>P.atajados</th>
    <th>Arq. Recibidos</th>
    <th>Arq. V. Invictas</th>
    </thead>
    <tbody>
    @php
        $i=1; $totalJugados=$totalGoles=$totalAmarillas=$totalRojas=$totalAtajados=$totalErrados=$totalRecibidos=$totalInvictas=0;
    @endphp
    @foreach($torneosJugador as $torneo)
        @php
            $totalJugados+=$torneo->jugados;
            $totalGoles+=$torneo->goles;
            $totalAmarillas+=$torneo->amarillas;
            $totalRojas+=$torneo->rojas;
            $totalErrados+=$torneo->errados;
            $totalAtajados+=$torneo->atajados;
            $totalRecibidos+=$torneo->recibidos;
            $totalInvictas+=$torneo->invictas;
            $jugo = $torneo->jugados>0?1:0;
        @endphp
        <tr>
            <td>{{$i++}}</td>
            <td>@if($torneo->escudoTorneo)<img src="{{ url('images/'.$torneo->escudoTorneo) }}" height="25">@endif {{$torneo->nombreTorneo}}</td>
            <td>
                @if($torneo->escudo)
                    @foreach(explode(',',$torneo->escudo) as $escudo)
                        @if($escudo!='')
                            @php $escudoArr = explode('_',$escudo); @endphp
                            <a href="{{route('equipos.ver', ['equipoId'=>$escudoArr[1]])}}">
                                <img src="{{ url('images/'.$escudoArr[0]) }}" height="25">
                                @if(isset($escudoArr[2]) && $escudoArr[2]!='') Pos: {!!$escudoArr[2]!!} @endif
                            </a>
                        @endif
                    @endforeach
                @endif
            </td>
            <td>{{$torneo->jugados}}</td>
            <td>{{$torneo->goles}} ({{$jugo?round($torneo->goles/$torneo->jugados,2):0}})</td>
            <td>{{$torneo->amarillas}} ({{$jugo?round($torneo->amarillas/$torneo->jugados,2):0}})</td>
            <td>{{$torneo->rojas}} ({{$jugo?round($torneo->rojas/$torneo->jugados,2):0}})</td>
            <td>{{$torneo->errados}} </td>
            <td>{{$torneo->atajados}} </td>
            <td>{{$torneo->recibidos}} ({{$jugo?round($torneo->recibidos/$torneo->jugados,2):0}})</td>
            <td>{{$torneo->invictas}} ({{$jugo?round($torneo->invictas/$torneo->jugados,2):0}})</td>
        </tr>
    @endforeach
    <!-- Totales -->
    <tr>
        <td></td><td></td><td><strong>Totales</strong></td>
        <td>{{$totalJugados}}</td>
        <td>{{$totalGoles}} ({{round($totalGoles/$totalJugados,2)}})</td>
        <td>{{$totalAmarillas}} ({{round($totalAmarillas/$totalJugados,2)}})</td>
        <td>{{$totalRojas}} ({{round($totalRojas/$totalJugados,2)}})</td>
        <td>{{$totalErrados}}</td>
        <td>{{$totalAtajados}}</td>
        <td>{{$totalRecibidos}} ({{round($totalRecibidos/$totalJugados,2)}})</td>
        <td>{{$totalInvictas}} ({{round($totalInvictas/$totalJugados,2)}})</td>
    </tr>
    </tbody>
</table>
