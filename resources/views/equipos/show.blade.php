@extends('layouts.app')

@section('pageTitle', 'Ver equipo')

@section('content')
    <div class="container">
    <h1 class="display-6">Ver equipo</h1>

        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <dt>Nombre</dt>
                <dd>{{$equipo->nombre}}</dd>
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <dt>Siglas</dt>
                <dd>{{$equipo->siglas}}</dd>
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                <dt>Socios</dt>
                <dd>{{$equipo->socios}}</dd>
            </div>

        </div>
        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <dt>Fundación</dt>
                <dd>{{date('Y-m-d', strtotime($equipo->fundacion))}}</dd>

            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <dt>Estadio</dt>
                <dd>{{$equipo->estadio}}</dd>
            </div>


        </div>

        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <div class="form-group">

                    @if($equipo->escudo)
                        <img id="original" src="{{ url('images/'.$equipo->escudo) }}" height="200">
                    @endif


                </div>
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-6">
                <dt>Historia</dt>
                <dd>{{$equipo->historia}}</dd>

            </div>

        </div>






    <div class="d-flex">
        <a href="{{route('equipos.edit', $equipo->id)}}" class="btn btn-primary m-1">Editar</a>

        <form action="{{ route('equipos.destroy', $equipo->id) }}" method="POST" onsubmit="return  ConfirmDelete()">
            <input type="hidden" name="_method" value="DELETE">
            <input type="hidden" name="_token" value="{{ csrf_token() }}">
            <button class="btn btn-danger m-1">Eliminar</button>
        </form>
        <a href="{{ route('equipos.index') }}" class="btn btn-success m-1">Volver</a>
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
