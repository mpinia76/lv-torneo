@extends('layouts.app')

@section('pageTitle', 'Listado de cruces')

@section('content')
    <div class="container">
    <h1 class="display-6">Cruces @if($torneo) - {{ $torneo->nombre }} {{ $torneo->year }} @endif</h1>


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
        @if($torneo)<a class="btn btn-success m-1" href="{{route('cruces.create', ['torneo_id' => $torneo->id])}}">Nuevo</a>@endif
        <nav class="navbar navbar-light float-right">
            <form class="form-inline">

                <input  value="{{ (isset($_GET['buscarpor']))?$_GET['buscarpor']:session('nombre_filtro_cruce') }}" name="buscarpor" class="form-control mr-sm-2" type="search" placeholder="Buscar" aria-label="Search">

                <button class="btn btn-success m-1" type="submit">Buscar</button>
            </form>
        </nav>

    <table class="table">
        <thead>

        <th>Torneo</th>
        <th>Fase</th>
        <th>Orden</th>
        <th>Clasificado 1</th>
        <th>Clasificado 2</th>
        <th colspan="3"></th>
        </thead>

        @foreach($cruces as $cruce)
            <tr>


                <td>{{$cruce->torneo->nombre}} {{$cruce->torneo->year}}</td>
                <td>{{$cruce->fase}}</td>

                <td>{{ $cruce->orden }}</td>
                <td>{{ $cruce->clasificado_1 }}</td>
                <td>{{ $cruce->clasificado_2 }}</td>
                <td>
                    <div class="d-flex">

                        <a href="{{route('cruces.edit', $cruce->id)}}" class="btn btn-primary m-1">Editar</a>

                        <form action="{{ route('cruces.destroy', $cruce->id) }}" method="POST" onsubmit="return  ConfirmDelete()">
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
                {{ $cruces->links() }}
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                <strong>Total: {{ $cruces->total() }}</strong>
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
