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
        <form class="form-inline">

            <input type="hidden" name="tipoOrder" value="{{$tipoOrder}}">
            <input type="hidden" name="imgOrder" value="{{$imgOrder}}">
            <select class="orm-control js-example-basic-single" id="torneoId" name="torneoId" onchange="this.form.submit()">
                @foreach($torneos as $torneo)

                    <option value="{{$torneo->id}}" @if($torneo->id==$torneoId)
                        selected

                        @endif />{{$torneo->nombre}} - {{$torneo->year}}</option>
                @endforeach

            </select>
            <input type="checkbox" class="orm-control" id="actuales" name="actuales" @if ($actuales == 1) checked @endif onchange="this.form.submit()">

            <strong>Jugando</strong>
            </input>



        </form>
        <br>

        <table class="table">
            <thead>
            <th>#</th>
            <th>Técnico</th>
            <th>Actual</th>
            <th><a href="{{route('torneos.tecnicos', array('order'=>'Jugados','tipoOrder'=>$tipoOrder, 'actuales'=>$actuales,'torneoId'=>$torneoId))}}" > J @if($order=='Jugados') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
            <th><a href="{{route('torneos.tecnicos', array('order'=>'Ganados','tipoOrder'=>$tipoOrder, 'actuales'=>$actuales,'torneoId'=>$torneoId))}}" > G @if($order=='Ganados') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
            <th><a href="{{route('torneos.tecnicos', array('order'=>'Empatados','tipoOrder'=>$tipoOrder, 'actuales'=>$actuales,'torneoId'=>$torneoId))}}" > E @if($order=='Empatados') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
            <th><a href="{{route('torneos.tecnicos', array('order'=>'Perdidos','tipoOrder'=>$tipoOrder, 'actuales'=>$actuales,'torneoId'=>$torneoId))}}" > P @if($order=='Perdidos') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
            <th><a href="{{route('torneos.tecnicos', array('order'=>'golesl','tipoOrder'=>$tipoOrder, 'actuales'=>$actuales,'torneoId'=>$torneoId))}}" > GF @if($order=='golesl') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
            <th><a href="{{route('torneos.tecnicos', array('order'=>'golesv','tipoOrder'=>$tipoOrder, 'actuales'=>$actuales,'torneoId'=>$torneoId))}}" > GC @if($order=='golesv') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
            <th><a href="{{route('torneos.tecnicos', array('order'=>'diferencia','tipoOrder'=>$tipoOrder, 'actuales'=>$actuales,'torneoId'=>$torneoId))}}" > Dif. @if($order=='diferencia') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
            <th><a href="{{route('torneos.tecnicos', array('order'=>'puntaje','tipoOrder'=>$tipoOrder, 'actuales'=>$actuales,'torneoId'=>$torneoId))}}" > Punt. @if($order=='puntaje') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
            <th><a href="{{route('torneos.tecnicos', array('order'=>'prom','tipoOrder'=>$tipoOrder, 'actuales'=>$actuales,'torneoId'=>$torneoId))}}" > % @if($order=='prom') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
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
                    <td>@if($tecnico->jugando)
                            @php
                                $escs = explode(',',$tecnico->jugando);
                            @endphp
                            @foreach($escs as $esc)

                                @if($esc!='')
                                    @php
                                        $escArr = explode('_',$esc);
                                    @endphp
                                    <a href="{{route('equipos.ver', array('equipoId' => $escArr[1]))}}" >
                                        <img id="original" src="{{ url('images/'.$escArr[0]) }}" height="25">
                                    </a>
                                @endif
                            @endforeach

                        @endif</td>
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
