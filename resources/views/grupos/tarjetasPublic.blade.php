@extends('layouts.appPublic')

@section('pageTitle', 'Tarjetas')

@section('content')
    <div class="container">




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
        {{$tarjetas->links()}}
        <div class="d-flex">

            <a href="{{ route('torneos.ver',array('torneoId' => $torneo->id)) }}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>


@endsection
