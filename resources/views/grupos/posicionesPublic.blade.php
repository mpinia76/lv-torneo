@extends('layouts.appPublic')

@section('pageTitle', 'Posiciones')

@section('content')
    <div class="container">



        @foreach($arrPosiciones as $nombre => $posiciones)
            @php
                $i = 1;
            @endphp
            @if(count($arrPosiciones)>1)
            Grupo {{$nombre}}
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
                    <tr>
                        <td>{{$i++}}</td>
                        <td>
                            <a href="{{route('equipos.ver', array('equipoId' => $equipo->equipo_id))}}" >
                            @if($equipo->foto)
                                <img id="original" src="{{ url('images/'.$equipo->foto) }}" height="25">
                            @endif
                            </a>
                            {{$equipo->equipo}} <img id="original" src="{{ url('images/'.removeAccents($equipo->pais).'.gif') }}" alt="{{ $equipo->pais }}"></td>
                        <td>{{$equipo->puntaje}}</td>
                        <td>{{$equipo->jugados}}</td>
                        <td>{{$equipo->ganados}}</td>
                        <td>{{$equipo->empatados}}</td>
                        <td>{{$equipo->perdidos}}</td>
                        <td>{{$equipo->golesl}}</td>
                        <td>{{$equipo->golesv}}</td>
                        <td>{{$equipo->diferencia}}</td>




                    </tr>
                @endforeach
                </tbody>
            </table>
                @endforeach
            @if($incidencias->isNotEmpty())
            <div class="mt-4">
                <h5><strong>Incidencias</strong></h5>
                <ul>
                    @foreach($incidencias as $incidencia)
                        <li>
                            {{ $incidencia->observaciones }}
                        </li>
                    @endforeach
                </ul>
            </div>
            @endif

        <div class="d-flex">

            <a href="{{ route('torneos.ver',array('torneoId' => $torneo->id))}}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>


@endsection
