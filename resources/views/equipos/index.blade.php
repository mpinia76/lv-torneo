@extends('layouts.app')

@section('pageTitle', 'Listado de equipos')

@section('content')
    <div class="container">
    <h1 class="display-6">Equipos</h1>

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
        <a class="btn btn-success m-1" href="{{route('equipos.create')}}">Nuevo</a>
        <nav class="navbar navbar-light float-right">
            <form class="form-inline">

                <input  value="{{ (isset($_GET['buscarpor']))?$_GET['buscarpor']:'' }}" name="buscarpor" class="form-control mr-sm-2" type="search" placeholder="Buscar" aria-label="Search">

                <button class="btn btn-success m-1" type="submit">Buscar</button>
            </form>
        </nav>

    <table class="table">
        <thead>
        <th></th>

        <th>Nombre</th>
        <th>Estadio</th>
        <th>Socios</th>
        <th>Años</th>

        <th colspan="3"></th>
        </thead>

        @foreach($equipos as $equipo)
            <tr>
                <td>@if($equipo->escudo)
                        <img id="original" src="{{ url('images/'.$equipo->escudo) }}" height="25">
                    @endif
                </td>

                <td>{{$equipo->nombre}}</td>
                <td>{{$equipo->estadio}}</td>
                <td>{{$equipo->socios}}</td>
                <td>{{Carbon::parse($equipo->fundacion)->age}}</td>

                <td>
                    <div class="d-flex">
                        <a href="{{route('equipos.show', $equipo->id)}}" class="btn btn-info m-1">Ver</a>
                        <a href="{{route('equipos.edit', $equipo->id)}}" class="btn btn-primary m-1">Editar</a>

                        <form action="{{ route('equipos.destroy', $equipo->id) }}" method="POST" onsubmit="return  ConfirmDelete()">
                            <input type="hidden" name="_method" value="DELETE">
                            <input type="hidden" name="_token" value="{{ csrf_token() }}">
                            <button class="btn btn-danger m-1">Eliminar</button>
                        </form>
                    </div>

                </td>
            </tr>
        @endforeach
    </table>
        <?php echo $equipos->links(); ?>
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
