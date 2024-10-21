@extends('layouts.appPublic')

@section('pageTitle', 'Tecnicos')

@section('content')
    <div class="container">

        @php
            $tipoOrder = ($tipoOrder=='ASC')?'DESC':'ASC';
            $imgOrder = ($tipoOrder=='ASC')?'entra':'sale';

        @endphp
        <nav class="navbar navbar-light float-right">
            <form class="form-inline">
                <input type="hidden" id="torneoId" name="torneoId" value="{{$torneo_id}}">
                <input  value="{{ (isset($_GET['buscarpor']))?$_GET['buscarpor']:session('nombre_filtro_jugador') }}" name="buscarpor" class="form-control mr-sm-2" type="search" placeholder="Buscar" aria-label="Search">

                <button class="btn btn-success m-1" type="submit">Buscar</button>
            </form>
        </nav>

        <table class="table">
            <thead>
            <th>#</th>
            <th>Técnico</th>
            <th>Equipos</th>
            <th><a href="{{route('grupos.tecnicos', array('order'=>'puntaje','tipoOrder'=>$tipoOrder,'torneoId'=>$torneo_id))}}" > Punt. @if($order=='puntaje') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
            <th><a href="{{route('grupos.tecnicos', array('order'=>'Jugados','tipoOrder'=>$tipoOrder,'torneoId'=>$torneo_id))}}" > J @if($order=='Jugados') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
            <th><a href="{{route('grupos.tecnicos', array('order'=>'Ganados','tipoOrder'=>$tipoOrder,'torneoId'=>$torneo_id))}}" > G @if($order=='Ganados') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
            <th><a href="{{route('grupos.tecnicos', array('order'=>'Empatados','tipoOrder'=>$tipoOrder,'torneoId'=>$torneo_id))}}" > E @if($order=='Empatados') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
            <th><a href="{{route('grupos.tecnicos', array('order'=>'Perdidos','tipoOrder'=>$tipoOrder,'torneoId'=>$torneo_id))}}" > P @if($order=='Perdidos') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
            <th><a href="{{route('grupos.tecnicos', array('order'=>'golesl','tipoOrder'=>$tipoOrder,'torneoId'=>$torneo_id))}}" > GF @if($order=='golesl') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
            <th><a href="{{route('grupos.tecnicos', array('order'=>'golesv','tipoOrder'=>$tipoOrder,'torneoId'=>$torneo_id))}}" > GC @if($order=='golesv') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
            <th><a href="{{route('grupos.tecnicos', array('order'=>'diferencia','tipoOrder'=>$tipoOrder,'torneoId'=>$torneo_id))}}" > Dif. @if($order=='diferencia') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>

            <th><a href="{{route('grupos.tecnicos', array('order'=>'prom','tipoOrder'=>$tipoOrder,'torneoId'=>$torneo_id))}}" > % @if($order=='prom') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>


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
                        {{$tecnico->tecnico}} <img id="original" src="{{ url('images/'.removeAccents($tecnico->nacionalidadTecnico).'.gif') }}" alt="{{ $tecnico->nacionalidadTecnico }}"></td>
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
                                    Puntaje {{$escudoArr[2]}} - Porcentaje {{$escudoArr[3]}}
                                    @if(isset($escudoArr[4]) && $escudoArr[4] != '')
                                        <!-- Mostrar datos adicionales de $escudoArr[2] aquí -->
                                        {{  $escudoArr[4] }}
                                    @endif
                                    <br>
                                @endif
                            @endforeach
                        @endif

                    </td>
                    <td>{{$tecnico->puntaje}}</td>
                    <td><a href="{{route('tecnicos.jugados', array('tecnicoId' => $tecnico->tecnico_id,'torneoId'=>$torneo_id))}}" >{{$tecnico->jugados}}</a></td>
                    <td><a href="{{route('tecnicos.jugados', array('tecnicoId' => $tecnico->tecnico_id,'torneoId'=>$torneo_id,'tipo'=>'Ganados'))}}" >{{$tecnico->ganados}}</a></td>
                    <td><a href="{{route('tecnicos.jugados', array('tecnicoId' => $tecnico->tecnico_id,'torneoId'=>$torneo_id,'tipo'=>'Empatados'))}}" >{{$tecnico->empatados}}</a></td>
                    <td><a href="{{route('tecnicos.jugados', array('tecnicoId' => $tecnico->tecnico_id,'torneoId'=>$torneo_id,'tipo'=>'Perdidos'))}}" >{{$tecnico->perdidos}}</a></td>
                    <td>{{$tecnico->golesl}}</td>
                    <td>{{$tecnico->golesv}}</td>
                    <td>{{$tecnico->diferencia}}</td>

                    <td>{{$tecnico->porcentaje}}</td>


                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-9">
                {{ $goleadores->links() }}
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                <strong>Total: {{ $goleadores->total() }}</strong>
            </div>
        </div>
        <div class="d-flex">

            <a href="{{ url()->previous() }}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>
    <script>


        function enviarForm() {
            $('#tipoOrder').val('DESC');
            $('#formulario').submit();
        }
    </script>

@endsection
