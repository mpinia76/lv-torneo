@extends('layouts.app')

@section('pageTitle', 'Verificar personas')
<style>

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

        <h1 class="display-6">Sin nacimiento</h1>

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
        <h1 class="display-6">Sin foto</h1>

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
            @foreach($sinFoto as $personaSinFoto)
                @php
                    $i++;
                @endphp
                <tr>
                    <td>{{$i}} @if($personaSinFoto->foto)
                            <img id="original" class="imgCircle" src="{{ url('images/'.$personaSinFoto->foto) }}" >
                        @else
                            @if($personaSinFoto->jugador)
                                <img id="original" class="imgCircle" src="{{ url('images/sin_foto.png') }}" >
                            @elseif($personaSinFoto->tecnico)
                                <img id="original" class="imgCircle" src="{{ url('images/sin_foto_tecnico.png') }}" >
                            @elseif($personaSinFoto->arbitro)
                                <img id="original" class="imgCircle" src="{{ url('images/sin_foto_arbitro.png') }}" >
                            @endif
                        @endif
                    </td>
                    <td><img id="original" src="{{ $personaSinFoto->bandera_url }}" alt="{{ $personaSinFoto->nacionalidad }}">  {{$personaSinFoto->id}}</td>
                    <td>{{$personaSinFoto->apellido}}</td>
                    <td>{{$personaSinFoto->nombre}}</td>


                    <td>{{($personaSinFoto->nacimiento)?$personaSinFoto->getAgeWithDateAttribute():''}}</td>
                    <td>{{($personaSinFoto->jugador)?$personaSinFoto->jugador->id:''}}</td>
                    <td>{{($personaSinFoto->tecnico)?$personaSinFoto->tecnico->id:''}}</td>
                    <td>{{($personaSinFoto->arbitro)?$personaSinFoto->arbitro->id:''}}</td>
                    <td>
                        @if($personaSinFoto->jugador)
                            <a href="{{route('jugadores.edit', $personaSinFoto->jugador->id)}}" class="btn btn-primary m-1">Editar</a>
                        @elseif($personaSinFoto->tecnico)
                            <a href="{{route('tecnicos.edit', $personaSinFoto->tecnico->id)}}" class="btn btn-primary m-1">Editar</a>
                        @elseif($personaSinFoto->arbitro)
                            <a href="{{route('arbitros.edit', $personaSinFoto->arbitro->id)}}" class="btn btn-primary m-1">Editar</a>
                        @endif

                    </td>
                </tr>
            @endforeach
        </table>
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
