@extends('layouts.app')

@section('pageTitle', 'Controlar penales')

<style>
    .imgCircle {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        margin-right: 5px;
    }
</style>

@section('content')
    <div class="container">
        <h1 class="display-6">Controlar penales</h1>
        <hr/>

        {{-- Tabla de penales cargados --}}
        <h4>Penales cargados</h4>
        <table class="table table-striped" style="font-size: 14px;">
            <thead>
            <tr>
                <th>Arquero</th>
                <th>Jugador (Ejecutor)</th>
                <th>Torneo</th>
                <th>Fecha</th>
                <th>Día</th>
                <th>Local</th>
                <th>GL</th>
                <th>GV</th>
                <th>Visitante</th>
                <th>Minuto</th>
            </tr>
            </thead>
            <tbody>
            @forelse($penalesCargados as $p)
                @php $partido = $p['partido']; @endphp
                <tr>
                    <td>
                        <img class="imgCircle" src="{{ $p['arquero']->persona->foto ? url('images/'.$p['arquero']->persona->foto) : url('images/sin_foto.png') }}">
                        {{ $p['arquero']->persona->name }}
                    </td>
                    <td>
                        <img class="imgCircle" src="{{ $p['ejecutor']->persona->foto ? url('images/'.$p['ejecutor']->persona->foto) : url('images/sin_foto.png') }}">
                        {{ $p['ejecutor']->persona->name }}
                    </td>
                    <td>{{ $partido->fecha->grupo->torneo->nombre ?? '-' }} {{ $partido->fecha->grupo->torneo->year ?? '-' }}</td>
                    <td>{{ $partido->fecha->numero ?? '-' }}</td>
                    <td>{{ $partido->dia ? date('d/m/Y H:i', strtotime($partido->dia)) : '-' }}</td>
                    <td>
                        @if($partido->equipol)
                            @if($partido->equipol->escudo)
                                <img src="{{ url('images/'.$partido->equipol->escudo) }}" height="20" alt="{{$partido->equipol->nombre}}">
                            @endif
                            {{ $partido->equipol->nombre }}
                        @endif
                    </td>
                    <td>{{ $partido->golesl ?? 0 }}</td>
                    <td>{{ $partido->golesv ?? 0 }}</td>
                    <td>
                        @if($partido->equipov)
                            @if($partido->equipov->escudo)
                                <img src="{{ url('images/'.$partido->equipov->escudo) }}" height="20" alt="{{$partido->equipov->nombre}}">
                            @endif
                            {{ $partido->equipov->nombre }}
                        @endif
                    </td>
                    <td>{{ $p['minuto'] }}'</td>
                </tr>
            @empty
                <tr><td colspan="10">No se cargaron penales nuevos.</td></tr>
            @endforelse
            </tbody>
        </table>

        {{-- Tabla de penales cargados con suplente en este script --}}
        <h4>Penales cargados con arquero suplente (este script)</h4>
        <table class="table table-bordered" style="font-size: 14px;">
            <thead>
            <tr>
                <th>Partido ID</th>
                <th>Minuto</th>
                <th>Arquero ID</th>
                <th>Ejecutor ID</th>
            </tr>
            </thead>
            <tbody>
            @forelse($penalesMalCargados as $p)
                <tr>
                    <td>{{ $p['partido_id'] }}</td>
                    <td>{{ $p['minuto'] }}'</td>
                    <td>{{ $p['arquero_id'] }}</td>
                    <td>{{ $p['ejecutor_id'] }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No se cargaron penales con arquero suplente en este script.</td></tr>
            @endforelse
            </tbody>
        </table>

        {{-- Tabla de penales existentes previamente cargados con suplente --}}
        <h4>Penales existentes previamente cargados con arquero suplente</h4>
        <table class="table table-bordered" style="font-size: 14px;">
            <thead>
            <tr>
                <th>Penal ID</th>

                <th>Partido</th>
                <th>Penales</th>
            </tr>
            </thead>
            <tbody>
            @forelse($penalesExistentesMalCargados as $p)
                <tr>
                    <td>{{ $p->id }}</td>
                    <td><a href="{{ route('fechas.detalle', ['partidoId' => $p->partido_id]) }}'" class="btn btn-primary m-1">Partido</a></td>
                    <td><a href="{{route('penales.index', array('partidoId' => $p->partido_id))}}" class="btn btn-warning m-1">Penales</a></td>
                </tr>
            @empty
                <tr><td colspan="4">No hay penales previamente cargados con arquero suplente.</td></tr>
            @endforelse
            </tbody>
        </table>

        <a href="{{ route('torneos.index') }}" class="btn btn-success m-1">Volver</a>
    </div>
@endsection
