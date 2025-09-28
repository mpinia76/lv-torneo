@extends('layouts.appPublic')

@section('pageTitle', 'Posiciones')

@section('content')
    <div class="container">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h1 class="h3 mb-4 text-center text-primary">🏆 Tabla de Posiciones</h1>

                @foreach($arrPosiciones as $nombre => $data)
                    @php
                        $i = 1;
                        $posiciones = $data['equipos'];
                        $numClasificados = $data['clasificados'] ?? 0;
                    @endphp

                    @if(count($arrPosiciones) > 1)
                        <h5>Grupo {{$nombre}}</h5>
                    @endif

                    <table class="table table-striped table-hover align-middle" style="font-size: 14px;">
                        <thead class="table-dark">
                        <th>#</th>
                        <th style="width: 300px;">Equipo</th>
                        <th>Punt.</th>
                        <th>J</th>
                        <th>G</th>
                        <th>E</th>
                        <th>P</th>
                        <th>GF</th>
                        <th>GC</th>
                        <th>Dif.</th>
                        </thead>
                        <tbody>
                        @foreach($posiciones as $equipo)
                            <tr @if($i <= $numClasificados) class="table-success" @endif>
                                <td>{{$i}}</td>
                                <td>
                                    <a href="{{ route('equipos.ver', ['equipoId' => $equipo->equipo_id]) }}">
                                        @if($equipo->foto)
                                            <img src="{{ url('images/'.$equipo->foto) }}" height="25">
                                        @endif
                                    </a>
                                    {{$equipo->equipo}}
                                    <img src="{{ url('images/'.removeAccents($equipo->pais).'.gif') }}" alt="{{ $equipo->pais }}">
                                </td>
                                <td>{{$equipo->puntaje}}</td>
                                <td>{{$equipo->jugados}}</td>
                                <td>{{$equipo->ganados}}</td>
                                <td>{{$equipo->empatados}}</td>
                                <td>{{$equipo->perdidos}}</td>
                                <td>{{$equipo->golesl}}</td>
                                <td>{{$equipo->golesv}}</td>
                                <td>{{$equipo->diferencia}}</td>
                            </tr>
                            @php $i++; @endphp
                        @endforeach
                        </tbody>
                    </table>
                @endforeach

                @if($incidencias->isNotEmpty())
                    <div class="mt-4">
                        <h5><strong>Incidencias</strong></h5>
                        <ul>
                            @foreach($incidencias as $incidencia)
                                <li>{{ $incidencia->observaciones }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="d-flex">
                    <a href="{{ route('torneos.ver', ['torneoId' => $torneo->id]) }}" class="btn btn-success m-1">Volver</a>
                </div>
            </div>
        </div>
    </div>
@endsection
