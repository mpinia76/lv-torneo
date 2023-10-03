@extends('layouts.app')

@section('pageTitle', 'Tarjetas')

@section('content')
    <div class="container">
    <h1 class="display-6">Tarjetas de {{$torneo->nombre}} {{$torneo->year}}</h1>

    <hr/>



    <table class="table" style="width: 50%">
        <thead>
        <th>#</th>
        <th>Jugador</th>
        <th>Amarillas</th>
        <th>Rojas</th>

        </thead>
        <tbody>

        @foreach($tarjetas as $jugador)
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
                <td>{{$jugador->amarillas}}</td>
                <td>{{$jugador->rojas}}</td>



            </tr>
        @endforeach
        </tbody>
    </table>
        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-9">
                {{ $tarjetas->links() }}
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                <strong>Total: {{ $tarjetas->total() }}</strong>
            </div>
        </div>
        <div class="d-flex">

            <a href="{{ route('torneos.show',$torneo->id) }}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>


@endsection
