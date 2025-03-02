@extends('layouts.app')

@section('pageTitle', 'Verificar personas')
<style>
    /* Estilos personalizados para resaltar la pestaña activa */
    .nav-link.active {
        background-color: #007bff; /* Cambia el color de fondo de la pestaña activa */
        color: #fff; /* Cambia el color del texto de la pestaña activa */
        border-color: #007bff; /* Cambia el color del borde de la pestaña activa */
    }

    /* Agrega un espacio entre las pestañas y el contenido */
    .tab-content {
        margin: 20px; /* Ajusta el margen superior del contenido */
    }
</style>
@section('content')
    <div class="container">
        <h1 class="display-6">Posibles repetidos</h1>
        @if (\Session::has('error'))
            <div class="alert alert-danger">
                <ul>
                    <li>{!! \Session::get('error') !!}</li>
                </ul>
            </div>
        @endif
        @if (\Session::has('success'))
            <div class="alert alert-success">
                <ul>
                    <li>{!! \Session::get('success') !!}</li>
                </ul>
            </div>
        @endif
        <form class="form-inline" id="formulario">


            <input type="checkbox" class="form-control" id="verificados" name="verificados" @if ($verificados == 1) checked @endif onchange="enviarForm()">

            <strong>Verificados</strong>
            </input>



        </form>
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="principal-tab" data-toggle="tab" href="#principal" role="tab" aria-controls="principal" aria-selected="true">Nacimiento</a>
            </li>

            <li class="nav-item">
                <a class="nav-link" id="tres-tab" data-toggle="tab" href="#tres" role="tab" aria-controls="tres" aria-selected="false">Similares</a>
            </li>

            <li class="nav-item">
                <a class="nav-link" id="sin-tab" data-toggle="tab" href="#sin" role="tab" aria-controls="sin" aria-selected="false">Sin nacimiento</a>
            </li>


        </ul>
        <div class="tab-content" id="myTabContent">
            <div role="tabpanel" class="tab-pane active" id="principal">
        <table class="table">
            <thead>
            <th></th>
            <th>Id</th>
            <th>Apellido</th>
            <th>Nombre</th>

            <th>Edad</th>
            <th>Jugador</th>
            <th>Técnico</th>

            <th>Arbitro</th>
            <th></th>
            </thead>

            @php
                $i = 0;
            @endphp
            @foreach($personas as $persona)
                @php
                    $i++;
                @endphp
                <tr>
                    <td>{{$i}}@if($persona->foto)
                            <img id="original" class="imgCircle" src="{{ url('images/'.$persona->foto) }}" >
                        @else
                            @if($persona->jugador)
                                <img id="original" class="imgCircle" src="{{ url('images/sin_foto.png') }}" >
                            @elseif($persona->tecnico)
                                <img id="original" class="imgCircle" src="{{ url('images/sin_foto_tecnico.png') }}" >
                            @elseif($persona->arbitro)
                                <img id="original" class="imgCircle" src="{{ url('images/sin_foto_arbitro.png') }}" >
                            @endif
                        @endif
                    </td>
                    <td><img id="original" src="{{ $persona->bandera_url }}" alt="{{ $persona->nacionalidad }}"> {{$persona->id}}</td>
                    <td>{{$persona->apellido}}</td>
                    <td>{{$persona->nombre}}</td>


                    <td>{{($persona->nacimiento)?$persona->getAgeWithDateAttribute():''}}</td>
                    <td>{{($persona->jugador)?$persona->jugador->id:''}}</td>
                    <td>{{($persona->tecnico)?$persona->tecnico->id:''}}</td>
                    <td>{{($persona->arbitro)?$persona->arbitro->id:''}}</td>
                    <td>
                        <div class="d-flex">
                        @if($persona->jugador)
                            <a href="{{route('jugadores.reasignar', $persona->jugador->id)}}" class="btn btn-info m-1">Reasignar</a>
                            <a href="{{route('jugadores.edit', $persona->jugador->id)}}" class="btn btn-primary m-1">Editar</a>

                            <form action="{{ route('jugadores.destroy', $persona->jugador->id) }}" method="POST" onsubmit="return  ConfirmDelete()">
                                <input type="hidden" name="_method" value="DELETE">
                                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                <button class="btn btn-danger m-1">Eliminar</button>
                            </form>
                        @elseif($persona->tecnico)
                            <a href="{{route('tecnicos.edit', $persona->tecnico->id)}}" class="btn btn-primary m-1">Editar</a>
                        @elseif($persona->arbitro)
                            <a href="{{route('arbitros.edit', $persona->arbitro->id)}}" class="btn btn-primary m-1">Editar</a>
                        @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </table>

            </div>
            <div role="tabpanel" class="tab-pane" id="tres">

        <table class="table">
            <thead>
            <th></th>
            <th>Id</th>
            <th>Apellido</th>
            <th>Nombre</th>

            <th>Edad</th>
            <th>Jugador</th>
            <th>Técnico</th>

            <th>Arbitro</th>
            <th></th>
            </thead>
            @php
                $i = 0;
            @endphp
            @foreach($similaresNombreApellido as $personaSimilares)
                @php
                    $i++;
                @endphp
                <tr>
                    <td>{{$i}} @if($personaSimilares->foto)
                            <img id="original" class="imgCircle" src="{{ url('images/'.$personaSimilares->foto) }}" >
                        @else
                            @if($personaSimilares->jugador)
                                <img id="original" class="imgCircle" src="{{ url('images/sin_foto.png') }}" >
                            @elseif($personaSimilares->tecnico)
                                <img id="original" class="imgCircle" src="{{ url('images/sin_foto_tecnico.png') }}" >
                            @elseif($personaSimilares->arbitro)
                                <img id="original" class="imgCircle" src="{{ url('images/sin_foto_arbitro.png') }}" >
                            @endif
                        @endif
                    </td>
                    <td><img id="original" src="{{ $personaSimilares->bandera_url }}" alt="{{ $personaSimilares->nacionalidad }}">  {{$personaSimilares->id}}</td>
                    <td>{{$personaSimilares->apellido}}</td>
                    <td>{{$personaSimilares->nombre}}</td>


                    <td>{{($personaSimilares->nacimiento)?$personaSimilares->getAgeWithDateAttribute():''}}</td>
                    <td>{{($personaSimilares->jugador)?$personaSimilares->jugador->id:''}}</td>
                    <td>{{($personaSimilares->tecnico)?$personaSimilares->tecnico->id:''}}</td>
                    <td>{{($personaSimilares->arbitro)?$personaSimilares->arbitro->id:''}}</td>
                    <td>
                        @if($personaSimilares->jugador)
                            <a href="{{route('jugadores.edit', $personaSimilares->jugador->id)}}" class="btn btn-primary m-1">Editar</a>
                        @elseif($personaSimilares->tecnico)
                            <a href="{{route('tecnicos.edit', $personaSimilares->tecnico->id)}}" class="btn btn-primary m-1">Editar</a>
                        @elseif($personaSimilares->arbitro)
                            <a href="{{route('arbitros.edit', $personaSimilares->arbitro->id)}}" class="btn btn-primary m-1">Editar</a>
                        @endif

                    </td>
                </tr>
            @endforeach
        </table>


            </div>
            <div role="tabpanel" class="tab-pane" id="sin">

        <table class="table">
            <thead>
            <th></th>
            <th>Id</th>
            <th>Apellido</th>
            <th>Nombre</th>

            <th>Edad</th>
            <th>Jugador</th>
            <th>Técnico</th>

            <th>Arbitro</th>
            <th></th>
            </thead>
            @php
                $i = 0;
            @endphp
            @foreach($sinNacimiento as $personaSinNac)
                @php
                    $i++;
                @endphp
                <tr>
                    <td>{{$i}} @if($personaSinNac->foto)
                            <img id="original" class="imgCircle" src="{{ url('images/'.$personaSinNac->foto) }}" >
                        @else
                            @if($personaSinNac->jugador)
                                <img id="original" class="imgCircle" src="{{ url('images/sin_foto.png') }}" >
                            @elseif($personaSinNac->tecnico)
                                <img id="original" class="imgCircle" src="{{ url('images/sin_foto_tecnico.png') }}" >
                            @elseif($personaSinNac->arbitro)
                                <img id="original" class="imgCircle" src="{{ url('images/sin_foto_arbitro.png') }}" >
                            @endif
                        @endif
                    </td>
                    <td><img id="original" src="{{ $personaSinNac->bandera_url }}" alt="{{ $personaSinNac->nacionalidad }}">  {{$personaSinNac->id}}</td>
                    <td>{{$personaSinNac->apellido}}</td>
                    <td>{{$personaSinNac->nombre}}</td>


                    <td>{{($personaSinNac->nacimiento)?$personaSinNac->getAgeWithDateAttribute():''}}</td>
                    <td>{{($personaSinNac->jugador)?$personaSinNac->jugador->id:''}}</td>
                    <td>{{($personaSinNac->tecnico)?$personaSinNac->tecnico->id:''}}</td>
                    <td>{{($personaSinNac->arbitro)?$personaSinNac->arbitro->id:''}}</td>
                    <td>
                            @if($personaSinNac->jugador)
                            <a href="{{route('jugadores.edit', $personaSinNac->jugador->id)}}" class="btn btn-primary m-1">Editar</a>
                            @elseif($personaSinNac->tecnico)
                            <a href="{{route('tecnicos.edit', $personaSinNac->tecnico->id)}}" class="btn btn-primary m-1">Editar</a>
                            @elseif($personaSinNac->arbitro)
                                <a href="{{route('arbitros.edit', $personaSinNac->arbitro->id)}}" class="btn btn-primary m-1">Editar</a>
                            @endif

                    </td>
                </tr>
            @endforeach
        </table>
            </div>


    </div>
    </div>
    <script>


        function enviarForm() {

            $('#formulario').submit();
        }


        function ConfirmDelete()
        {
            var x = confirm("Está seguro?");
            if (x)
                return true;
            else
                return false;
        }

    </script>
@endsection
