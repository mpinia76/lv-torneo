@extends('layouts.app')

@section('pageTitle', 'Listado de títulos')

@section('content')
    <div class="container">
        <h1 class="display-6">Títulos</h1>
        <hr/>

        @if (Session::has('error'))
            <div class="alert alert-danger"><ul><li>{{ Session::get('error') }}</li></ul></div>
        @endif
        @if (Session::has('success'))
            <div class="alert alert-success"><ul><li>{{ Session::get('success') }}</li></ul></div>
        @endif

        <a class="btn btn-success m-1" href="{{route('titulos.create')}}">Nuevo</a>

        <nav class="navbar navbar-light float-right">
            <form class="form-inline">
                <input
                    value="{{ request('buscarpor') }}"
                    name="buscarpor"
                    class="form-control mr-sm-2"
                    type="search"
                    placeholder="Buscar título"
                    aria-label="Search">
                <button class="btn btn-success m-1" type="submit">Buscar</button>
            </form>
        </nav>

        <table class="table">
            <thead>
            <th>Nombre</th>
            <th>Tipo</th>
            <th>Ambito</th>
            <th>Equipo Campeón</th>

            <th></th>
            </thead>

            @foreach($titulos as $titulo)
                <tr>
                    <td>{{ $titulo->nombre }} {{ $titulo->year }}</td>
                    <td>{{ $titulo->tipo }} </td>
                    <td>{{ $titulo->ambito }} </td>
                    <td>
                        @if($titulo->equipo && $titulo->equipo->escudo)
                            <img src="{{ url('images/'.$titulo->equipo->escudo) }}" height="25">
                        @endif
                        {{ $titulo->equipo->nombre ?? 'N/D' }}
                    </td>

                    <td>
                        <div class="d-flex">
                            <a href="{{route('titulos.edit', $titulo->id)}}" class="btn btn-primary m-1">Editar</a>

                            <form action="{{ route('titulos.destroy', $titulo->id) }}" method="POST" onsubmit="return ConfirmDelete()">
                                @method('DELETE')
                                @csrf
                                <button class="btn btn-danger m-1">Eliminar</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach
        </table>

        {{ $titulos->links() }}
    </div>

    <script>
        function ConfirmDelete() {
            return confirm("Está seguro?");
        }
    </script>
@endsection
