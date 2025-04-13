@extends('layouts.app')

@section('pageTitle', 'Listado de arbitros')

@section('content')
    <div class="container">
    <h1 class="display-6">Arbitros</h1>

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
        <a class="btn btn-success m-1" href="{{route('arbitros.create')}}">Nuevo</a>
        <nav class="navbar navbar-light float-right">
            <form class="form-inline">

                <input  value="{{ (isset($_GET['buscarpor']))?$_GET['buscarpor']:session('nombre_filtro_plantilla') }}" name="buscarpor" class="form-control mr-sm-2" type="search" placeholder="Buscar" aria-label="Search">

                <button class="btn btn-success m-1" type="submit">Buscar</button>
            </form>
        </nav>

    <table class="table">
        <thead>
        <th></th>
        <th>Mostrar</th>
        <th>Apellido</th>
        <th>Nombre</th>

        <th>Edad</th>

        <th colspan="3"></th>
        </thead>

        @foreach($arbitros as $arbitro)
            <tr>
                <td>@if($arbitro->persona->foto)
                        <img id="original" class="imgCircle" src="{{ url('images/'.$arbitro->persona->foto) }}" >
                    @else
                        <img id="original" class="imgCircle" src="{{ url('images/sin_foto_arbitro.png') }}" >
                    @endif <img id="original" src="{{ $arbitro->persona->bandera_url }}" alt="{{ $arbitro->persona->nacionalidad }}">
                </td>
                <td>{{$arbitro->name}}</td>
                <td>{{$arbitro->apellido}}</td>
                <td>{{$arbitro->nombre}}</td>


                <td>{!! ($arbitro->persona->fallecimiento)?'<img id="original" src="'.url('images/death.png').'">':'' !!} {{($arbitro->nacimiento)?$arbitro->persona->getAgeAttribute():''}}</td>

                <td>
                    <div class="d-flex">
                        <a href="{{route('arbitros.show', $arbitro->id)}}" class="btn btn-info m-1">Ver</a>
                        <a href="{{route('arbitros.edit', $arbitro->id)}}" class="btn btn-primary m-1">Editar</a>

                        <form action="{{ route('arbitros.destroy', $arbitro->id) }}" method="POST" onsubmit="return  ConfirmDelete()">
                            <input type="hidden" name="_method" value="DELETE">
                            <input type="hidden" name="_token" value="{{ csrf_token() }}">
                            <button class="btn btn-danger m-1">Eliminar</button>
                        </form>
                    </div>

                </td>
            </tr>
        @endforeach
    </table>

        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-9">
                {{ $arbitros->links() }}
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                <strong>Total: {{ $arbitros->total() }}</strong>
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
