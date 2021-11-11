@extends('layouts.appPublic')

@section('pageTitle', 'Goleadores')

@section('content')
    <div class="container">
        <h1 class="display-6">Histórico de técnicos</h1>

        <hr/>



        <table class="table">
            <thead>
            <th>#</th>
            <th>Técnico</th>

            <th>J</th>
            <th>G</th>
            <th>E</th>
            <th>P</th>
            <th>GF</th>
            <th>GC</th>
            <th>Dif.</th>
            <th>Punt.</th>
            <th>%</th>
            <th>Equipos</th>
            </thead>
            <tbody>

            @foreach($goleadores as $tecnico)
                <tr>
                    <td>{{$i++}}</td>
                    <td>
                        <a href="{{route('tecnicos.ver', array('tecnicoId' => $tecnico->tecnico_id))}}" >
                            @if($tecnico->fotoTecnico)
                                <img id="original" class="imgCircle" src="{{ url('images/'.$tecnico->fotoTecnico) }}" >
                            @else
                                <img id="original" class="imgCircle" src="{{ url('images/sin_foto_tecnico.png') }}" >
                            @endif
                        </a>
                        {{$tecnico->tecnico}}</td>

                    <td>{{$tecnico->jugados}}</td>
                    <td>{{$tecnico->ganados}}</td>
                    <td>{{$tecnico->empatados}}</td>
                    <td>{{$tecnico->perdidos}}</td>
                    <td>{{$tecnico->golesl}}</td>
                    <td>{{$tecnico->golesv}}</td>
                    <td>{{$tecnico->diferencia}}</td>
                    <td>{{$tecnico->puntaje}}</td>
                    <td>{{$tecnico->porcentaje}}</td>

                    <td>@if($tecnico->escudo)
                            @php
                                $escudos = explode(',',$tecnico->escudo);
                            @endphp
                            @foreach($escudos as $escudo)
                                @if($escudo!='')
                                    @php
                                        $escudoArr = explode('_',$escudo);
                                    @endphp
                                    <a href="{{route('equipos.ver', array('equipoId' => $escudoArr[1]))}}" >
                                        <img id="original" src="{{ url('images/'.$escudoArr[0]) }}" height="25">
                                    </a>
                                    Puntaje {{$escudoArr[2]}} - Porcentaje {{$escudoArr[3]}} <br>
                                @endif
                            @endforeach
                        @endif

                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        {{$goleadores->links()}}
        <div class="d-flex">

            <a href="{{ url()->previous() }}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>


@endsection
