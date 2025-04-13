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

        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="principal-tab" data-toggle="tab" href="#principal" role="tab" aria-controls="principal" aria-selected="true">Similares</a>
            </li>

            <li class="nav-item">
                <a class="nav-link" id="tres-tab" data-toggle="tab" href="#tres" role="tab" aria-controls="tres" aria-selected="false">Sin nombre/apellido</a>
            </li>

            <li class="nav-item">
                <a class="nav-link" id="sin-tab" data-toggle="tab" href="#sin" role="tab" aria-controls="sin" aria-selected="false">Problema en nacionalidad</a>
            </li>


        </ul>
        <div class="tab-content" id="myTabContent">
            <div role="tabpanel" class="tab-pane active" id="principal">

                <div class="row">

                    <div class="form-group col-md-12">
        <table class="table">
            <thead>
            <th></th>
            <th>Id</th>
            <th>Mostrar</th>
            <th>Apellido</th>
            <th>Nombre</th>

            <th>Edad</th>
            <th>Posición</th>
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

//dd($personaSimilares);
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
                        <img id="original" src="{{ $personaSimilares->bandera_url }}" alt="{{ $personaSimilares->nacionalidad }}">
                    </td>
                    <td>  {{$personaSimilares->id}}</td>
                    <td>{{$personaSimilares->name}}</td>
                    <td>{{$personaSimilares->apellido}}</td>
                    <td>{{$personaSimilares->nombre}}</td>


                    <td>{{($personaSimilares->nacimiento)?$personaSimilares->getAgeWithDateAttribute():''}}</td>
                    <td>{{($personaSimilares->jugador)?$personaSimilares->jugador->tipoJugador:''}}</td>
                    <td>{{($personaSimilares->jugador)?$personaSimilares->jugador->id:''}}</td>
                    <td>{{($personaSimilares->tecnico)?$personaSimilares->tecnico->id:''}}</td>
                    <td>{{($personaSimilares->arbitro)?$personaSimilares->arbitro->id:''}}</td>
                    <td>
                        <div class="d-flex" style="align-items: center;">
                        @if($personaSimilares->jugador)
                            <a href="{{route('jugadores.reasignar', $personaSimilares->jugador->id)}}" class="btn btn-info m-1">Reasignar</a>
                            <a href="{{route('jugadores.edit', $personaSimilares->jugador->id)}}" class="btn btn-primary m-1">Editar</a>

                            <form action="{{ route('jugadores.destroy', $personaSimilares->jugador->id) }}" method="POST" onsubmit="return  ConfirmDelete()" style="margin: 0;">
                                <input type="hidden" name="_method" value="DELETE">
                                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                <button class="btn btn-danger m-1">Eliminar</button>
                            </form>
                        @elseif($personaSimilares->tecnico)
                            <a href="{{route('tecnicos.edit', $personaSimilares->tecnico->id)}}" class="btn btn-primary m-1">Editar</a>
                        @elseif($personaSimilares->arbitro)
                            <a href="{{route('arbitros.edit', $personaSimilares->arbitro->id)}}" class="btn btn-primary m-1">Editar</a>
                        @endif
                        @if($personaSimilares->simil_id)
                            <!-- Botón para verificar similitud -->
                            <form action="{{ route('jugadores.verificarSimilitud') }}" method="POST" onsubmit="return  ConfirmDelete()" style="margin: 0;">
                                @csrf
                                <input type="hidden" name="persona_id" value="{{ $personaSimilares->id }}">
                                <input type="hidden" name="simil_id" value="{{ $personaSimilares->simil_id }}">

                                <button type="submit" class="btn btn-success m-1">Verificado</button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </table>
                    </div>
                    <div class="row">
                        <div class="form-group col-xs-12 col-sm-6 col-md-9">
                            {{ $personas->links() }}
                        </div>
                        <div class="form-group col-xs-12 col-sm-6 col-md-2">
                            <strong>Total: {{ $personas->total() }}</strong>
                        </div>
                    </div>

            </div>
            </div>

            <div role="tabpanel" class="tab-pane" id="tres">

                <div class="row">

                    <div class="form-group col-md-12">
                        <table class="table">
                            <thead>
                            <th></th>
                            <th>Id</th>
                            <th>Mostrar</th>
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
                            @foreach($personasSinNombreApellido as $sinNombreApellido)
                                @php
                                    $i++;

                //dd($sinNombreApellido);
                                @endphp

                                <tr>
                                    <td>{{$i}} @if($sinNombreApellido->foto)
                                            <img id="original" class="imgCircle" src="{{ url('images/'.$sinNombreApellido->foto) }}" >
                                        @else
                                            @if($sinNombreApellido->jugador)
                                                <img id="original" class="imgCircle" src="{{ url('images/sin_foto.png') }}" >
                                            @elseif($sinNombreApellido->tecnico)
                                                <img id="original" class="imgCircle" src="{{ url('images/sin_foto_tecnico.png') }}" >
                                            @elseif($sinNombreApellido->arbitro)
                                                <img id="original" class="imgCircle" src="{{ url('images/sin_foto_arbitro.png') }}" >
                                            @endif
                                        @endif
                                        <img id="original" src="{{ $sinNombreApellido->bandera_url }}" alt="{{ $sinNombreApellido->nacionalidad }}">
                                    </td>
                                    <td>  {{$sinNombreApellido->id}}</td>
                                    <td>{{$sinNombreApellido->name}}</td>
                                    <td>{{$sinNombreApellido->apellido}}</td>
                                    <td>{{$sinNombreApellido->nombre}}</td>


                                    <td>{{($sinNombreApellido->nacimiento)?$sinNombreApellido->getAgeWithDateAttribute():''}}</td>


                                    <td>{{($sinNombreApellido->jugador)?$sinNombreApellido->jugador->id:''}}</td>
                                    <td>{{($sinNombreApellido->tecnico)?$sinNombreApellido->tecnico->id:''}}</td>
                                    <td>{{($sinNombreApellido->arbitro)?$sinNombreApellido->arbitro->id:''}}</td>
                                    <td>
                                        <div class="d-flex" style="align-items: center;">
                                            @if($sinNombreApellido->jugador)
                                                <a href="{{route('jugadores.reasignar', $sinNombreApellido->jugador->id)}}" class="btn btn-info m-1">Reasignar</a>
                                                <a href="{{route('jugadores.edit', $sinNombreApellido->jugador->id)}}" class="btn btn-primary m-1">Editar</a>

                                                <form action="{{ route('jugadores.destroy', $sinNombreApellido->jugador->id) }}" method="POST" onsubmit="return  ConfirmDelete()" style="margin: 0;">
                                                    <input type="hidden" name="_method" value="DELETE">
                                                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                                    <button class="btn btn-danger m-1">Eliminar</button>
                                                </form>
                                            @elseif($sinNombreApellido->tecnico)
                                                <a href="{{route('tecnicos.edit', $sinNombreApellido->tecnico->id)}}" class="btn btn-primary m-1">Editar</a>
                                            @elseif($sinNombreApellido->arbitro)
                                                <a href="{{route('arbitros.edit', $sinNombreApellido->arbitro->id)}}" class="btn btn-primary m-1">Editar</a>
                                            @endif

                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </div>


                </div>
            </div>

            <div role="tabpanel" class="tab-pane" id="sin">

                <div class="row">

                    <div class="form-group col-md-12">
                        <table class="table">
                            <thead>
                            <th></th>
                            <th>Id</th>
                            <th>Mostrar</th>
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
                            @foreach($personasSinBandera as $SinBandera)
                                @php
                                    $i++;

                //dd($SinBandera);
                                @endphp

                                <tr>
                                    <td>{{$i}} @if($SinBandera->foto)
                                            <img id="original" class="imgCircle" src="{{ url('images/'.$SinBandera->foto) }}" >
                                        @else
                                            @if($SinBandera->jugador)
                                                <img id="original" class="imgCircle" src="{{ url('images/sin_foto.png') }}" >
                                            @elseif($SinBandera->tecnico)
                                                <img id="original" class="imgCircle" src="{{ url('images/sin_foto_tecnico.png') }}" >
                                            @elseif($SinBandera->arbitro)
                                                <img id="original" class="imgCircle" src="{{ url('images/sin_foto_arbitro.png') }}" >
                                            @endif
                                        @endif
                                        <img id="original" src="{{ $SinBandera->bandera_url }}" alt="{{ $SinBandera->nacionalidad }}">
                                    </td>
                                    <td>  {{$SinBandera->id}}</td>
                                    <td>{{$SinBandera->name}}</td>
                                    <td>{{$SinBandera->apellido}}</td>
                                    <td>{{$SinBandera->nombre}}</td>


                                    <td>{{($SinBandera->nacimiento)?$SinBandera->getAgeWithDateAttribute():''}}</td>


                                    <td>{{($SinBandera->jugador)?$SinBandera->jugador->id:''}}</td>
                                    <td>{{($SinBandera->tecnico)?$SinBandera->tecnico->id:''}}</td>
                                    <td>{{($SinBandera->arbitro)?$SinBandera->arbitro->id:''}}</td>
                                    <td>
                                        <div class="d-flex" style="align-items: center;">
                                            @if($SinBandera->jugador)
                                                <a href="{{route('jugadores.reasignar', $SinBandera->jugador->id)}}" class="btn btn-info m-1">Reasignar</a>
                                                <a href="{{route('jugadores.edit', $SinBandera->jugador->id)}}" class="btn btn-primary m-1">Editar</a>

                                                <form action="{{ route('jugadores.destroy', $SinBandera->jugador->id) }}" method="POST" onsubmit="return  ConfirmDelete()" style="margin: 0;">
                                                    <input type="hidden" name="_method" value="DELETE">
                                                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                                    <button class="btn btn-danger m-1">Eliminar</button>
                                                </form>
                                            @elseif($SinBandera->tecnico)
                                                <a href="{{route('tecnicos.edit', $SinBandera->tecnico->id)}}" class="btn btn-primary m-1">Editar</a>
                                            @elseif($SinBandera->arbitro)
                                                <a href="{{route('arbitros.edit', $SinBandera->arbitro->id)}}" class="btn btn-primary m-1">Editar</a>
                                            @endif

                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </div>


                </div>
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
