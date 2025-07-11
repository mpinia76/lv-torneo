@extends('layouts.app')

@section('pageTitle', 'Listado de tecnicos')

@section('content')
    <div class="container">
    <h1 class="display-6">Tecnicos</h1>

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
        <a class="btn btn-success m-1" href="{{route('tecnicos.create')}}">Nuevo</a>
        <a class="btn btn-info m-1" href="{{route('tecnicos.importar')}}">Importar</a>
        <nav class="navbar navbar-light float-right">
            <form class="form-inline">

                <input  value="{{ (isset($_GET['buscarpor']))?$_GET['buscarpor']:session('nombre_filtro_fecha') }}" name="buscarpor" class="form-control mr-sm-2" type="search" placeholder="Buscar" aria-label="Search">

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

        @foreach($tecnicos as $tecnico)
            <tr>
                <td>@if($tecnico->persona->foto)
                        <img id="original" class="imgCircle" src="{{ url('images/'.$tecnico->persona->foto) }}" > <img id="original" src="{{ $tecnico->persona->bandera_url }}" alt="{{ $tecnico->persona->nacionalidad }}">
                    @else
                        <img id="original" class="imgCircle" src="{{ url('images/sin_foto_tecnico.png') }}" > <img id="original" src="{{ $tecnico->persona->bandera_url }}" alt="{{ $tecnico->persona->nacionalidad }}">
                    @endif
                </td>
                <td>{{$tecnico->name}}</td>
                <td>{{$tecnico->apellido}}</td>
                <td>{{$tecnico->nombre}}</td>


                <td>{!! ($tecnico->persona->fallecimiento)?'<img id="original" src="'.url('images/death.png').'">':'' !!} {{($tecnico->nacimiento)?$tecnico->persona->getAgeAttribute():''}}</td>

                <td>
                    <div class="d-flex">
                        <a href="{{route('tecnicos.show', $tecnico->id)}}" class="btn btn-info m-1">Ver</a>
                        <a href="{{route('tecnicos.edit', $tecnico->id)}}" class="btn btn-primary m-1">Editar</a>

                        <form action="{{ route('tecnicos.destroy', $tecnico->id) }}" method="POST" onsubmit="return  ConfirmDelete()">
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
                {{ $tecnicos->links() }}
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                <strong>Total: {{ $tecnicos->total() }}</strong>
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
