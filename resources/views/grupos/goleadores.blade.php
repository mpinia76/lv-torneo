@extends('layouts.app')

@section('pageTitle', 'Goleadores')

@section('content')
    <div class="container">
    <h1 class="display-6">Goleadores de {{$torneo->nombre}} {{$torneo->year}}</h1>

    <hr/>



    <table class="table">
        <thead>
        <th>#</th>
        <th>Jugador</th>
        <th>Goles</th>
        <th>Jugada</th>
        <th>Cabeza</th>
        <th>Penal</th>
        <th>Tiro Libre</th>
        </thead>
        <tbody>

        @foreach($goleadores as $jugador)
            <tr>
                <td>{{$i++}}</td>
                <td>@if($jugador->foto)
                        @php
                            $fotos = explode(',',$jugador->foto);
                        @endphp
                        @foreach($fotos as $foto)
                            @if($foto!='')
                                <img id="original" src="{{ url('images/'.$foto) }}" height="25">
                            @endif
                        @endforeach
                    @endif

                {{$jugador->jugador}}</td>
                <td>{{$jugador->goles}}</td>
                <td>{{$jugador->Jugada}}</td>
                <td>{{$jugador->Cabeza}}</td>
                <td>{{$jugador->Penal}}</td>
                <td>{{$jugador->Tiro_Libre}}</td>


            </tr>
        @endforeach
        </tbody>
    </table>
        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-9">
                {{ $goleadores->links() }}
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                <strong>Total: {{ $goleadores->total() }}</strong>
            </div>
        </div>

        <div class="d-flex">

            <a href="{{ route('torneos.show',$torneo->id) }}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>


@endsection
