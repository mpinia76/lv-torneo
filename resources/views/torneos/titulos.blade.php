@extends('layouts.appPublic')

@section('pageTitle', 'Titulos')

@section('content')
    <div class="container">


        @php
            $tipoOrder = ($tipoOrder=='ASC')?'DESC':'ASC';
            $imgOrder = ($tipoOrder=='ASC')?'entra':'sale';

        @endphp
        <form class="form-inline" id="formulario">

            <input type="hidden" id="tipoOrder" name="tipoOrder" value="{{$tipoOrder}}">
            <input type="hidden" name="imgOrder" value="{{$imgOrder}}">

            <input type="checkbox" class="form-control" id="argentinos" name="argentinos" @if ($argentinos == 1) checked @endif onchange="enviarForm()">

            <strong>Argentinos</strong>
            </input>



        </form>
        <br>
        <table class="table">
            <thead>
            <th style="width: 1%;">#</th>
            <th style="width: 40%;">Equipo</th>
            <th style="width: 10%;"><a href="{{route('torneos.titulos', array('order'=>'titulos','tipoOrder'=>$tipoOrder))}}" > Titulos @if($order=='titulos') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
            <th style="width: 10%;"><a href="{{route('torneos.titulos', array('order'=>'internacionales','tipoOrder'=>$tipoOrder))}}" > Internacionales @if($order=='internacionales') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
            <th style="width: 10%;"><a href="{{route('torneos.titulos', array('order'=>'ligas','tipoOrder'=>$tipoOrder))}}" > Ligas @if($order=='ligas') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>
            <th ><a href="{{route('torneos.titulos', array('order'=>'copas','tipoOrder'=>$tipoOrder))}}" > Copas @if($order=='copas') <img id="original"  src="{{ url('images/'.$imgOrder.'.png') }}" height="15">@endif</a></th>

            </thead>
            <tbody>

            @foreach($posiciones as $equipo)
                <tr>
                    <td>{{$i++}}</td>
                    <td>
                        <a href="{{route('equipos.ver', array('equipoId' => $equipo->id))}}" >
                        @if($equipo->escudo)
                            <img id="original" src="{{ url('images/'.$equipo->escudo) }}" height="25">
                        @endif
                        </a>
                        {{$equipo->nombre}} <img id="original" src="{{ url('images/'.removeAccents($equipo->pais).'.gif') }}" alt="{{ $equipo->pais }}"></td>


                    <td>{{$equipo->titulos}}</td>
                    <td>{{$equipo->internacionales}}</td>
                    <td>{{$equipo->ligas}}</td>
                    <td>{{$equipo->copas}}</td>

                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-9">
                {{ $posiciones->links() }}
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                <strong>Total: {{ $posiciones->total() }}</strong>
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
