@extends('layouts.appPublic')

@section('pageTitle', 'Plantillas')

@section('content')
    <div class="container">
        <form class="form-inline">

<input type="hidden" id="torneoId" name="torneoId" value="{{$torneo->id}}">
            <select class="orm-control js-example-basic-single" id="equipo1" name="equipo1" onchange="this.form.submit()" style="width: 150px">
                <option value="">seleccionar...</option>
                @foreach($equipos as $id => $equipo)

                    <option value="{{$id}}" @if($id==$e1->id)
                        selected

                        @endif />{{$equipo}}</option>
                @endforeach

            </select>



        </form>
<hr>
            @if($e1->escudo)<img id="original" src="{{ url('images/'.$e1->escudo) }}" height="100">
            @endif



    <table class="table">
        <thead>
        <th>Dorsal</th>

        <th>Jugador</th>

        <th>Tipo</th>

        </thead>
        <tbody>
        @foreach($plantillaJugadors ?? '' as $titularl)
            <tr>
                <td>{{$titularl->dorsal}}</td>

                <td>
                    <a href="{{route('jugadores.ver', array('jugadorId' => $titularl->jugador->id))}}" >

                        @if($titularl->jugador->persona->foto)
                            <img id="original" class="imgCircle" src="{{ url('images/'.$titularl->jugador->persona->foto) }}" >
                        @else
                            <img id="original" class="imgCircle" src="{{ url('images/sin_foto.png') }}" >
                        @endif
                    </a>
                    <span style="font-weight: bold"> {{ $titularl->jugador->persona->full_name}}</span>
                </td>
                <td>{{$titularl->jugador->tipoJugador}}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
        <div class="d-flex">

            <a href="{{ route('torneos.ver',array('torneoId' => $torneo->id))}}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>


@endsection
