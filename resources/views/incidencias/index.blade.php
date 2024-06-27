@extends('layouts.app')

@section('pageTitle', 'Listado de incidencias')

@section('content')
    <div class="container">
    <h1 class="display-6">Incidencias</h1>

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
        <a class="btn btn-success m-1" href="{{route('incidencias.create',  array('torneoId' => $torneo_id))}}">Nueva</a>


    <table class="table">
        <thead>

        <th>Torneo</th>
        <th>Equipo</th>

        <th>Partido</th>
        <th>Puntos</th>
        <th>Observaciones</th>

        <th colspan="3"></th>
        </thead>

        @foreach($incidencias as $incidencia)
            <tr>

                <td>{{$incidencia->torneo->nombre}} {{$incidencia->torneo->year}}</td>

                <td>@if($incidencia->equipo)
                    <a href="{{ route('equipos.ver', ['equipoId' => $incidencia->equipo->id]) }}">

                            @if($incidencia->equipo->escudo)
                                <img src="{{ url('images/' . $incidencia->equipo->escudo) }}" height="20">
                            @endif

                    </a>

                    {{ $incidencia->equipo->nombre }} <img src="{{ $incidencia->equipo->bandera_url }}" alt="{{ $incidencia->equipo->pais }}">


                    @endif
                </td>
                <td>@if($incidencia->partido)
                    {{($incidencia->partido->dia)?date('d/m/Y H:i', strtotime($incidencia->partido->dia)):''}}
                @if($incidencia->partido->equipol)
                        @if($incidencia->partido->equipol->escudo)<img id="original" src="{{ url('images/'.$incidencia->partido->equipol->escudo) }}" height="20">
                        @endif
                        {{$incidencia->partido->equipol->nombre}}
                    @endif

                {{$incidencia->partido->golesl}}
                    @if($incidencia->partido->penalesl)
                        ({{$incidencia->partido->penalesl}})
                    @endif

                {{$incidencia->partido->golesv}}
                    @if($incidencia->partido->penalesv)
                        ({{$incidencia->partido->penalesv}})
                    @endif

                @if($incidencia->partido->equipov)
                        @if($incidencia->partido->equipov->escudo)<img id="original" src="{{ url('images/'.$incidencia->partido->equipov->escudo) }}" height="20">
                        @endif
                        {{$incidencia->partido->equipov->nombre}}
                    @endif

                    @endif
                </td>
                <td>{{$incidencia->puntos}}</td>
                <td>{{$incidencia->observaciones}}</td>
                <td>
                    <div class="d-flex">

                        <a href="{{route('incidencias.edit', $incidencia->id)}}" class="btn btn-primary m-1">Editar</a>

                        <form action="{{ route('incidencias.destroy', $incidencia->id) }}" method="POST" onsubmit="return  ConfirmDelete()">
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
                {{ $incidencias->links() }}
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                <strong>Total: {{ $incidencias->total() }}</strong>
            </div>
        </div>

    </div>
    <a href="{{ route('torneos.show',$torneo_id) }}" class="btn btn-success m-1">Volver</a>
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
