@extends('layouts.app')

@section('pageTitle', 'Listado de jugadores')

@section('content')
    <div class="container">
    <h1 class="display-6">Jugadores</h1>

    <hr/>
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
        <div class="d-flex flex-wrap align-items-center mb-2">
            <a class="btn btn-success m-1" href="{{route('jugadores.create')}}">Nuevo</a>
            <a class="btn btn-info m-1" href="{{route('jugadores.importar')}}">Importar</a>
            <a class="btn btn-primary m-1" href="{{route('jugadores.verificarPersonas')}}">Verificar Personas</a>
            <a class="btn btn-warning m-1"
               href="{{ route('jugadores.nameCompletoNoVerificado') }}">
                Verificar nombre largo
            </a>
            <form action="{{ route('jugadores.verificarNombreApellidoSimple') }}"
                  method="POST"
                  onsubmit="return confirm('¿Verificar automáticamente todos los jugadores con nombre y apellido simples?')"
                  class="d-inline-block">

                @csrf
                <button class="btn btn-success m-1">
                    Verificar nombres simples
                </button>
            </form>
        </div>

        <nav class="navbar navbar-light float-right">
            <form class="form-inline">

                <input  value="{{ (isset($_GET['buscarpor']))?$_GET['buscarpor']:session('nombre_filtro_jugador') }}" name="buscarpor" class="form-control mr-sm-2" type="search" placeholder="Buscar" aria-label="Search">

                <button class="btn btn-success m-1" type="submit">Buscar</button>
            </form>
        </nav>

    <table class="table">
        <thead>
        <th></th>
        <th>Posición</th>
        <th>Mostrar</th>
        <th>Apellido</th>
        <th>Nombre</th>

        <th>Edad</th>
        <th>Ciudad</th>


        <th colspan="3"></th>
        </thead>

        @foreach($jugadores as $jugador)

            <tr>
                <td>@if($jugador->foto)
                        <img id="original" class="imgCircle" src="{{ url('images/'.$jugador->foto) }}" >
                    @else
                        <img id="original" class="imgCircle" src="{{ url('images/sin_foto.png') }}" >
                    @endif <img id="original" src="{{ $jugador->persona->bandera_url }}" alt="{{ $jugador->persona->nacionalidad }}">
                </td>
                <td>{{$jugador->tipoJugador}}</td>
                <td>{{$jugador->name}}</td>
                <td>{{$jugador->apellido}}</td>
                <td>{{$jugador->nombre}}</td>


                <td>{{($jugador->nacimiento)?$jugador->persona->getAgeWithDateAttribute():''}}</td>
                <td>{{$jugador->ciudad}}</td>

                <td>
                    <div class="d-flex">
                        <a href="{{route('jugadores.show', $jugador->id)}}" class="btn btn-info m-1">Ver</a>
                        <a href="{{route('jugadores.edit', $jugador->id)}}" class="btn btn-primary m-1">Editar</a>

                        <form action="{{ route('jugadores.destroy', $jugador->id) }}" method="POST" onsubmit="return  ConfirmDelete()">
                            <input type="hidden" name="_method" value="DELETE">
                            <input type="hidden" name="_token" value="{{ csrf_token() }}">
                            <button class="btn btn-danger m-1">Eliminar</button>
                        </form>
                        <a href="{{route('jugadores.reasignar', $jugador->id)}}" class="btn btn-info m-1">Reasignar</a>
                    </div>

                </td>
            </tr>
        @endforeach
    </table>

        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-9">
                {{ $jugadores->appends($data)->links() }}
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                <strong>Total: {{ $jugadores->total() }}</strong>
            </div>
        </div>

    </div>

    <script>

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
