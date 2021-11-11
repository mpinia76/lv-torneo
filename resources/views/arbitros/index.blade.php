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

                <input  value="{{ (isset($_GET['buscarpor']))?$_GET['buscarpor']:'' }}" name="buscarpor" class="form-control mr-sm-2" type="search" placeholder="Buscar" aria-label="Search">

                <button class="btn btn-success m-1" type="submit">Buscar</button>
            </form>
        </nav>

    <table class="table">
        <thead>
        <th></th>
        <th>Apellido</th>
        <th>Nombre</th>
        <th>E-mail</th>
        <th>Edad</th>

        <th colspan="3"></th>
        </thead>

        @foreach($arbitros as $arbitro)
            <tr>
                <td>@if($arbitro->foto)
                        <img id="original" class="imgCircle" src="{{ url('images/'.$arbitro->foto) }}" >
                    @else
                        <img id="original" class="imgCircle" src="{{ url('images/sin_foto_arbitro.png') }}" >
                    @endif
                </td>
                <td>{{$arbitro->apellido}}</td>
                <td>{{$arbitro->nombre}}</td>

                <td>{{$arbitro->email}}</td>
                <td>{{($arbitro->nacimiento)?$arbitro->getAgeAttribute():''}}</td>

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
        <?php echo $arbitros->links(); ?>
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
