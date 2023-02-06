@extends('layouts.appPublic')

@section('pageTitle', 'Goleadores')

@section('content')
    <div class="container">
        <h1 class="display-6">Histórico de técnicos</h1>

        <hr/>
        @php
            $tipoOrder = ($tipoOrder=='ASC')?'DESC':'ASC';
            $imgOrder = ($tipoOrder=='ASC')?'entra':'sale';

        @endphp


        <table class="table">
            <thead>
            <th>#</th>
            <th>Técnico</th>

            <th><a href="{{route('torneos.tecnicos', array('order'=>'Jugados','tipoOrder'=>$tipoOrder))}}" > J @if($order=='Jugados') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
            <th><a href="{{route('torneos.tecnicos', array('order'=>'Ganados','tipoOrder'=>$tipoOrder))}}" > G @if($order=='Ganados') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
            <th><a href="{{route('torneos.tecnicos', array('order'=>'Empatados','tipoOrder'=>$tipoOrder))}}" > E @if($order=='Empatados') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
            <th><a href="{{route('torneos.tecnicos', array('order'=>'Perdidos','tipoOrder'=>$tipoOrder))}}" > P @if($order=='Perdidos') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
            <th><a href="{{route('torneos.tecnicos', array('order'=>'golesl','tipoOrder'=>$tipoOrder))}}" > GF @if($order=='golesl') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
            <th><a href="{{route('torneos.tecnicos', array('order'=>'golesv','tipoOrder'=>$tipoOrder))}}" > GC @if($order=='golesv') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
            <th><a href="{{route('torneos.tecnicos', array('order'=>'diferencia','tipoOrder'=>$tipoOrder))}}" > Dif. @if($order=='diferencia') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
            <th><a href="{{route('torneos.tecnicos', array('order'=>'puntaje','tipoOrder'=>$tipoOrder))}}" > Punt. @if($order=='puntaje') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
            <th><a href="{{route('torneos.tecnicos', array('order'=>'prom','tipoOrder'=>$tipoOrder))}}" > % @if($order=='prom') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
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

                    <td><a href="{{route('tecnicos.jugados', array('tecnicoId' => $tecnico->tecnico_id))}}" >{{$tecnico->jugados}}</a></td>
                    <td><a href="{{route('tecnicos.jugados', array('tecnicoId' => $tecnico->tecnico_id,'tipo'=>'Ganados'))}}" >{{$tecnico->ganados}}</a></td>
                    <td><a href="{{route('tecnicos.jugados', array('tecnicoId' => $tecnico->tecnico_id,'tipo'=>'Empatados'))}}" >{{$tecnico->empatados}}</a></td>
                    <td><a href="{{route('tecnicos.jugados', array('tecnicoId' => $tecnico->tecnico_id,'tipo'=>'Perdidos'))}}" >{{$tecnico->perdidos}}</a></td>
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
