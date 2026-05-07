@extends('layouts.app')

@section('pageTitle', 'Competencias excluidas')

@section('content')
    <div class="container">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="display-6 mb-1">Competencias excluidas</h1>
                <p class="text-muted mb-0">
                    Patrones que descartan competencias al scrapear (equipos, técnicos, jugadores, CSV).
                </p>
            </div>
            <button type="button" class="btn btn-primary" id="btnNuevo">
                <i class="glyphicon glyphicon-plus"></i> Nueva regla
            </button>
        </div>

        {{-- Probador rápido --}}
        <div class="card mb-3">
            <div class="card-body">
                <label class="font-weight-bold mb-1">Probar un nombre de competencia:</label>
                <div class="input-group">
                    <input type="text" id="inputProbar" class="form-control"
                           placeholder="Ej: Segunda B 2024">
                    <div class="input-group-append">
                        <button class="btn btn-outline-primary" id="btnProbar" type="button">Probar</button>
                    </div>
                </div>
                <div id="resultadoProbar" class="mt-2"></div>
            </div>
        </div>

        {{-- Tabla --}}
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="tablaCompetencias">
                        <thead>
                        <tr>
                            <th>Patrón</th>
                            <th>Tipo de match</th>
                            <th>Motivo</th>
                            <th class="text-center">Activo</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal alta / edición --}}
    <div class="modal fade" id="modalCompetencia" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form id="formCompetencia">
                    <div class="modal-header">
                        <h5 class="modal-title">Regla de exclusión</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="comp_id">

                        <div class="form-group">
                            <label>Patrón <span class="text-danger">*</span></label>
                            <input type="text" name="patron" id="comp_patron" class="form-control" required>
                            <small class="text-muted">
                                Para tipo "contiene" se compara en minúsculas y sin acentos.
                            </small>
                        </div>

                        <div class="form-group">
                            <label>Tipo de match <span class="text-danger">*</span></label>
                            <select name="tipo_match" id="comp_tipo_match" class="form-control" required>
                                <option value="contiene">Contiene</option>
                                <option value="exacto">Exacto</option>
                                <option value="regex">Regex</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Motivo</label>
                            <input type="text" name="motivo" id="comp_motivo" class="form-control"
                                   placeholder="Ej: Segunda división española">
                        </div>

                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="comp_activo" name="activo" checked>
                            <label class="form-check-label" for="comp_activo">Activo</label>
                        </div>

                        <div id="formErrors" class="mt-2"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="btnGuardar">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        $(function () {
            var URL_BASE = "{{ url('admin/competencias-excluidas') }}";

            // CSRF para todas las llamadas
            $.ajaxSetup({
                headers: { 'X-CSRF-TOKEN': "{{ csrf_token() }}" }
            });

            /* ---------------------------------------
             | Cargar tabla
             |---------------------------------------*/
            function cargarTabla() {
                $.get(URL_BASE + '/listar', function (rows) {
                    var $tbody = $('#tablaCompetencias tbody');
                    $tbody.empty();

                    if (!rows.length) {
                        $tbody.append(
                            '<tr><td colspan="5" class="text-center text-muted py-4">' +
                            'No hay reglas cargadas todavía.</td></tr>'
                        );
                        return;
                    }

                    $.each(rows, function (i, r) {
                        var badgeTipo = '<span class="badge badge-secondary">' + r.tipo_match + '</span>';
                        var checked = r.activo ? 'checked' : '';
                        var motivo = r.motivo
                            ? $('<div>').text(r.motivo).html()
                            : '<span class="text-muted">—</span>';
                        var patron = $('<div>').text(r.patron).html();

                        $tbody.append(
                            '<tr data-id="' + r.id + '">' +
                            '<td><code>' + patron + '</code></td>' +
                            '<td>' + badgeTipo + '</td>' +
                            '<td>' + motivo + '</td>' +
                            '<td class="text-center">' +
                            '<input type="checkbox" class="chk-toggle" ' + checked + '>' +
                            '</td>' +
                            '<td class="text-right">' +
                            '<button type="button" class="btn btn-sm btn-primary btn-editar mr-1">' +
                            '<i class="glyphicon glyphicon-pencil"></i></button>' +
                            '<button type="button" class="btn btn-sm btn-danger btn-borrar">' +
                            '<i class="glyphicon glyphicon-trash"></i></button>' +
                            '</td>' +
                            '</tr>'
                        );
                    });
                });
            }

            cargarTabla();

            /* ---------------------------------------
             | Nuevo
             |---------------------------------------*/
            $('#btnNuevo').on('click', function () {
                $('#formCompetencia')[0].reset();
                $('#comp_id').val('');
                $('#comp_activo').prop('checked', true);
                $('#formErrors').empty();
                $('.modal-title').text('Nueva regla');
                $('#modalCompetencia').modal('show');
            });

            /* ---------------------------------------
             | Editar
             |---------------------------------------*/
            $(document).on('click', '.btn-editar', function () {
                var $tr = $(this).closest('tr');
                var id = $tr.data('id');

                $.get(URL_BASE + '/listar', function (rows) {
                    var item = null;
                    $.each(rows, function (i, r) {
                        if (r.id == id) { item = r; return false; }
                    });
                    if (!item) return;

                    $('#comp_id').val(item.id);
                    $('#comp_patron').val(item.patron);
                    $('#comp_tipo_match').val(item.tipo_match);
                    $('#comp_motivo').val(item.motivo || '');
                    $('#comp_activo').prop('checked', !!item.activo);
                    $('#formErrors').empty();
                    $('.modal-title').text('Editar regla');
                    $('#modalCompetencia').modal('show');
                });
            });

            /* ---------------------------------------
             | Guardar (alta o edición)
             |---------------------------------------*/
            $('#formCompetencia').on('submit', function (e) {
                e.preventDefault();

                var id = $('#comp_id').val();
                var url = URL_BASE + (id ? '/' + id : '');
                var method = id ? 'PUT' : 'POST';

                var data = {
                    patron:     $('#comp_patron').val(),
                    tipo_match: $('#comp_tipo_match').val(),
                    motivo:     $('#comp_motivo').val(),
                    activo:     $('#comp_activo').is(':checked') ? 1 : 0
                };

                $('#btnGuardar').prop('disabled', true);
                $('#formErrors').empty();

                $.ajax({
                    url: url,
                    method: method,
                    data: data
                })
                    .done(function (resp) {
                        if (resp.ok) {
                            $('#modalCompetencia').modal('hide');
                            cargarTabla();
                        }
                    })
                    .fail(function (xhr) {
                        var html = '';
                        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                            var errs = xhr.responseJSON.errors;
                            html += '<div class="alert alert-danger mb-0"><ul class="mb-0">';
                            $.each(errs, function (k, msgs) {
                                $.each(msgs, function (j, m) {
                                    html += '<li>' + $('<div>').text(m).html() + '</li>';
                                });
                            });
                            html += '</ul></div>';
                        } else {
                            html = '<div class="alert alert-danger mb-0">Error al guardar.</div>';
                        }
                        $('#formErrors').html(html);
                    })
                    .always(function () {
                        $('#btnGuardar').prop('disabled', false);
                    });
            });

            /* ---------------------------------------
             | Toggle activo
             |---------------------------------------*/
            $(document).on('change', '.chk-toggle', function () {
                var $tr = $(this).closest('tr');
                var id = $tr.data('id');
                var $chk = $(this);

                $.post(URL_BASE + '/' + id + '/toggle')
                    .fail(function () {
                        $chk.prop('checked', !$chk.is(':checked'));
                        alert('No se pudo cambiar el estado.');
                    });
            });

            /* ---------------------------------------
             | Borrar
             |---------------------------------------*/
            $(document).on('click', '.btn-borrar', function () {
                var $tr = $(this).closest('tr');
                var id = $tr.data('id');

                if (!confirm('¿Eliminar esta regla?')) return;

                $.ajax({
                    url: URL_BASE + '/' + id,
                    method: 'DELETE'
                })
                    .done(function (resp) {
                        if (resp.ok) {
                            $tr.remove();
                        }
                    })
                    .fail(function () {
                        alert('No se pudo eliminar.');
                    });
            });

            /* ---------------------------------------
             | Probador
             |---------------------------------------*/
            $('#btnProbar').on('click', function () {
                var nombre = $('#inputProbar').val();
                if (!nombre) return;

                $.post(URL_BASE + '/probar', { nombre: nombre })
                    .done(function (resp) {
                        if (!resp.ok) {
                            $('#resultadoProbar').html(
                                '<div class="alert alert-warning mb-0">' + resp.msg + '</div>'
                            );
                            return;
                        }

                        var clase = resp.excluido ? 'alert-danger' : 'alert-success';
                        var texto = resp.excluido
                            ? 'EXCLUIDO — alguna regla activa lo descarta.'
                            : 'INCLUIDO — ninguna regla lo descarta.';

                        $('#resultadoProbar').html(
                            '<div class="alert ' + clase + ' mb-0"><strong>' +
                            $('<div>').text(resp.nombre).html() + '</strong>: ' + texto + '</div>'
                        );
                    });
            });

            $('#inputProbar').on('keypress', function (e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $('#btnProbar').click();
                }
            });
        });
    </script>
@endsection
