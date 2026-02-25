<div class="container">
    <h3>Reasignar grupo</h3>

    <p><strong>Equipo:</strong> {{ $plantilla->equipo->nombre }}</p>

    <form method="POST" action="{{ route('plantillas.guardarGrupo', $plantilla->id) }}">
        @csrf

        <div class="mb-3">
            <label>Grupo</label>
            <select name="grupo_id" class="form-control">
                @foreach($grupos as $id => $nombre)
                    <option value="{{ $id }}"
                            @if($plantilla->grupo_id == $id) selected @endif>
                        {{ $nombre }}
                    </option>
                @endforeach
            </select>
        </div>

        <button class="btn btn-primary">Guardar</button>

        <a href="{{ url()->previous() }}" class="btn btn-secondary">Cancelar</a>
    </form>
</div>
