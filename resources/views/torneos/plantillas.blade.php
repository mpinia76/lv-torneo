@extends('layouts.appPublic')

@section('pageTitle', 'Plantillas')

@section('content')
    <div class="container">
        <form class="form-inline">

<input type="hidden" id="torneoId" name="torneoId" value="{{$torneo->id}}">
            <select class="form-control js-example-basic-single" id="equipo1" name="equipo1" onchange="this.form.submit()" style="width: 150px">

                @foreach($equipos as $id => $equipo)

                    <option value="{{$id}}" @if($id==$e1->id)
                        selected

                        @endif />{{$equipo}}</option>
                @endforeach

            </select>



        </form>
<hr>
            @if($e1->escudo)<img id="original" src="{{ url('images/'.$e1->escudo) }}" height="100">
            @endif

        @php
            $tipoOrder = ($tipoOrder=='ASC')?'DESC':'ASC';
            $imgOrder = ($tipoOrder=='ASC')?'entra':'sale';

        @endphp

    <table class="table">
        <thead>
        <th><a href="{{route('torneos.plantillas', array('torneoId' => $torneo->id,'order'=>'dorsal','tipoOrder'=>$tipoOrder,'equipo1'=>$e1->id))}}" > Dorsal @if($order=='dorsal') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>

        <th><a href="{{route('torneos.plantillas', array('torneoId' => $torneo->id,'order'=>'jugador','tipoOrder'=>$tipoOrder,'equipo1'=>$e1->id))}}" > Jugador @if($order=='jugador') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>

        <th><a href="{{route('torneos.plantillas', array('torneoId' => $torneo->id,'order'=>'nacimiento','tipoOrder'=>$tipoOrder,'equipo1'=>$e1->id))}}" > Edad @if($order=='nacimiento') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>

        <th><a href="{{route('torneos.plantillas', array('torneoId' => $torneo->id,'order'=>'tipoJugador','tipoOrder'=>$tipoOrder,'equipo1'=>$e1->id))}}" > Tipo @if($order=='tipoJugador') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>

        <th><a href="{{route('torneos.plantillas', array('torneoId' => $torneo->id,'order'=>'jugados','tipoOrder'=>$tipoOrder,'equipo1'=>$e1->id))}}" > Jugados @if($order=='jugados') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
        <th><a href="{{route('torneos.plantillas', array('torneoId' => $torneo->id,'order'=>'Goles','tipoOrder'=>$tipoOrder,'equipo1'=>$e1->id))}}" > Goles @if($order=='Goles') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
        <th><a href="{{route('torneos.plantillas', array('torneoId' => $torneo->id,'order'=>'amarillas','tipoOrder'=>$tipoOrder,'equipo1'=>$e1->id))}}" > Amarillas @if($order=='amarillas') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
        <th><a href="{{route('torneos.plantillas', array('torneoId' => $torneo->id,'order'=>'rojas','tipoOrder'=>$tipoOrder,'equipo1'=>$e1->id))}}" > Rojas @if($order=='rojas') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
        <th><a href="{{route('torneos.plantillas', array('torneoId' => $torneo->id,'order'=>'recibidos','tipoOrder'=>$tipoOrder,'equipo1'=>$e1->id))}}" >Arq. Recibidos @if($order=='recibidos') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
        <th><a href="{{route('torneos.plantillas', array('torneoId' => $torneo->id,'order'=>'invictas','tipoOrder'=>$tipoOrder,'equipo1'=>$e1->id))}}" > Arq. V. Invictas @if($order=='invictas') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>

        </thead>
        <tbody>
        @foreach($jugadores as $jugador)
            <tr>
                <td>{{$jugador->dorsal}}</td>
                <td>
                    <a href="{{route('jugadores.ver', array('jugadorId' => $jugador->jugador_id))}}" >
                        @if($jugador->foto)
                            <img id="original" class="imgCircle" src="{{ url('images/'.$jugador->foto) }}" >
                        @else
                            <img id="original" class="imgCircle" src="{{ url('images/sin_foto.png') }}" >
                        @endif
                    </a>
                    {{$jugador->jugador}} <img id="original" src="{{ url('images/'.removeAccents($jugador->nacionalidad).'.gif') }}" alt="{{ $jugador->nacionalidad }}"></td>
                <td>{{$jugador->edad}}

                </td>
                <td>{{$jugador->tipoJugador}}

                </td>
                <td><a href="{{route('jugadores.jugados', array('jugadorId' => $jugador->jugador_id,'torneoId' => $torneo->id))}}" >{{$jugador->jugados}} </a></td>
                <td><a href="{{route('jugadores.goles', array('jugadorId' => $jugador->jugador_id,'torneoId' => $torneo->id))}}" >{{$jugador->goles}}</a></td>
                <td><a href="{{route('jugadores.tarjetas', array('jugadorId' => $jugador->jugador_id,'torneoId' => $torneo->id,'tipo'=>'Amarillas'))}}" >{{$jugador->amarillas}}</a></td>

                <td><a href="{{route('jugadores.tarjetas', array('jugadorId' => $jugador->jugador_id,'torneoId' => $torneo->id,'tipo'=>'Rojas'))}}" >{{$jugador->rojas}}</a></td>
                <td>{{$jugador->recibidos}} (@if($jugador->jugados){{round($jugador->recibidos / $jugador->jugados,2)}} @else 0 @endif)</td>
                <td>{{$jugador->invictas}} (@if($jugador->jugados){{round($jugador->invictas / $jugador->jugados,2)}} @else 0 @endif)</td>
            </tr>
        @endforeach
        </tbody>
    </table>
        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-9">
                {{ $jugadores->links() }}
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                <strong>Total: {{ $jugadores->total() }}</strong>
            </div>
        </div>
        <table class="table">
            <thead>

            <th>Técnico</th>
            <th>Edad</th>
            <th>Punt. </th>
            <th> J </th>
            <th>G </th>
            <th> E </th>
            <th> P </th>
            <th>GF </th>
            <th>GC </th>
            <th> Dif. </th>

            <th> % </th>


            </thead>
            <tbody>

            @foreach($tecnicosEquipo as $tecnico)
                <tr>

                    <td>
                        <a href="{{route('tecnicos.ver', array('tecnicoId' => $tecnico->tecnico_id))}}" >
                            @if($tecnico->fotoTecnico)
                                <img id="original" class="imgCircle" src="{{ url('images/'.$tecnico->fotoTecnico) }}" >
                            @else
                                <img id="original" class="imgCircle" src="{{ url('images/sin_foto_tecnico.png') }}" >
                            @endif
                        </a>
                        {{$tecnico->tecnico}} <img id="original" src="{{ url('images/'.removeAccents($tecnico->nacionalidadTecnico).'.gif') }}" alt="{{ $tecnico->nacionalidadTecnico }}"></td>
                    <td>{{$tecnico->edad}}
                    <td>{{$tecnico->puntaje}}</td>
                    <td><a href="{{route('tecnicos.jugados', array('tecnicoId' => $tecnico->tecnico_id))}}" >{{$tecnico->jugados}}</a></td>
                    <td><a href="{{route('tecnicos.jugados', array('tecnicoId' => $tecnico->tecnico_id,'tipo'=>'Ganados'))}}" >{{$tecnico->ganados}}</a></td>
                    <td><a href="{{route('tecnicos.jugados', array('tecnicoId' => $tecnico->tecnico_id,'tipo'=>'Empatados'))}}" >{{$tecnico->empatados}}</a></td>
                    <td><a href="{{route('tecnicos.jugados', array('tecnicoId' => $tecnico->tecnico_id,'tipo'=>'Perdidos'))}}" >{{$tecnico->perdidos}}</a></td>
                    <td>{{$tecnico->golesl}}</td>
                    <td>{{$tecnico->golesv}}</td>
                    <td>{{$tecnico->diferencia}}</td>

                    <td>{{$tecnico->porcentaje}}</td>

                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="d-flex">

            <a href="{{ route('torneos.ver',array('torneoId' => $torneo->id))}}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>


@endsection
