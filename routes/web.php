<?php

use Illuminate\Support\Facades\Route;

use Illuminate\Support\Facades\Soap;
use App\Http\Controllers\AlineacionController;
use App\Http\Controllers\ApellidosPartidosController;
use App\Http\Controllers\ArbitroController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BuscadorController;
use App\Http\Controllers\CambioController;
use App\Http\Controllers\CompetenciaExcluidaController;
use App\Http\Controllers\ControlController;
use App\Http\Controllers\ControlTorneoController;
use App\Http\Controllers\CruceController;
use App\Http\Controllers\EquipoController;
use App\Http\Controllers\EquipoEstadisticaManualController;
use App\Http\Controllers\EquipoExcluidoController;
use App\Http\Controllers\FechaController;
use App\Http\Controllers\GolController;
use App\Http\Controllers\GrupoController;
use App\Http\Controllers\ImportDetallesController;
use App\Http\Controllers\ImportPartidosController;
use App\Http\Controllers\IncidenciaController;
use App\Http\Controllers\JugadorController;
use App\Http\Controllers\JugadorEstadisticaManualController;
use App\Http\Controllers\MenuTorneosController;
use App\Http\Controllers\PartidoArbitroController;
use App\Http\Controllers\PartidoController;
use App\Http\Controllers\PenalController;
use App\Http\Controllers\PersonaDuplicadoController;
use App\Http\Controllers\PlantillaController;
use App\Http\Controllers\PollController;
use App\Http\Controllers\ScraperController;
use App\Http\Controllers\TarjetaController;
use App\Http\Controllers\TecnicoController;
use App\Http\Controllers\TecnicoEstadisticaManualController;
use App\Http\Controllers\TernaSondeoController;
use App\Http\Controllers\TituloController;
use App\Http\Controllers\TorneoController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/



// Sin tabla de roles, cualquier usuario registrado tiene los mismos permisos
// que el administrador. El alta se hace a mano en la base.
Auth::routes(['register' => false]);




Route::group(['prefix' => 'admin', 'middleware' => 'auth'], function()
{
    Route::get('/', function () {
        return view('/home');
    });
    Route::get('/home', [TorneoController::class, 'index'])->name('index');

    // Método Paenza: sólo para el admin (salió del menú público). Vive acá y no
    // en $rutasPublicas para que no pase por la caché de páginas, que serviría
    // la copia guardada sin mirar el login.
    Route::get('metodo', [GrupoController::class, 'metodo'])->name('grupos.metodo');
    Route::resource('torneos', TorneoController::class);

    Route::get('/plantillas/{id}/reasignar-grupo', [PlantillaController::class, 'reasignarGrupo'])
        ->name('plantillas.reasignarGrupo');

    Route::post('/plantillas/{id}/guardar-grupo', [PlantillaController::class, 'guardarGrupo'])
        ->name('plantillas.guardarGrupo');

    Route::resource('jugadores', JugadorController::class);
    Route::resource('equipos', EquipoController::class);
    Route::resource('fechas', FechaController::class);
    Route::resource('partidos', PartidoController::class);
    Route::resource('plantillas', PlantillaController::class);
    Route::resource('goles', GolController::class);
    Route::resource('arbitros', ArbitroController::class);
    Route::resource('tecnicos', TecnicoController::class);
    Route::resource('tarjetas', TarjetaController::class);
    Route::resource('partidoarbitros', PartidoArbitroController::class);
    Route::resource('alineaciones', AlineacionController::class);
    Route::resource('cambios', CambioController::class);
    Route::resource('incidencias', IncidenciaController::class);
    Route::resource('cruces', CruceController::class);
    Route::resource('penales', PenalController::class);

    Route::get('importarJugador', [JugadorController::class, 'importar'])->name('jugadores.importar');
    Route::post('importarJugadorProcess', [JugadorController::class, 'importarProcess']);
    // Verificar personas / detectar repetidos.
    // La pantalla ahora lee los candidatos precalculados de `persona_duplicados`
    // en vez de escanear la tabla entera en cada carga.
    Route::get('verificarPersonas', [PersonaDuplicadoController::class, 'index'])->name('jugadores.verificarPersonas');
    Route::post('personas/duplicados/recalcular', [PersonaDuplicadoController::class, 'recalcular'])->name('personas.duplicados.recalcular');
    Route::get('personas/duplicados/contencion', [PersonaDuplicadoController::class, 'contencion'])->name('personas.duplicados.contencion');
    Route::post('personas/duplicados/contencion', [PersonaDuplicadoController::class, 'contencionGuardar'])->name('personas.duplicados.contencion.guardar');
    Route::post('personas/duplicados/descartar', [PersonaDuplicadoController::class, 'descartar'])->name('personas.duplicados.descartar');
    Route::post('personas/duplicados/reabrir', [PersonaDuplicadoController::class, 'reabrir'])->name('personas.duplicados.reabrir');
    Route::post('personas/duplicados/fusionar', [PersonaDuplicadoController::class, 'fusionar'])->name('personas.duplicados.fusionar');
    Route::post('personas/duplicados/lote', [PersonaDuplicadoController::class, 'lote'])->name('personas.duplicados.lote');

    // Árbitros con el apellido doble partido al medio (el JSON de /referees no
    // trae shortName). El GET sólo muestra; el POST guarda lo tildado.
    Route::get('personas/apellidos', [ApellidosPartidosController::class, 'index'])->name('personas.apellidos');
    Route::post('personas/apellidos', [ApellidosPartidosController::class, 'aplicar'])->name('personas.apellidos.aplicar');

    // Traspaso PARCIAL de carrera entre dos personas DISTINTAS: mover un tramo
    // (un club y sus temporadas) de una ficha a la otra sin fusionar nada. El GET
    // solo previsualiza; el POST vuelve a calcular la lista y se queda con la
    // interseccion, asi un id escrito a mano no puede mover otro club.
    Route::get('personas/mover', [PersonaDuplicadoController::class, 'moverForm'])->name('personas.mover.form');
    Route::post('personas/mover', [PersonaDuplicadoController::class, 'mover'])->name('personas.mover');

    // Borra personas que no tienen ningun registro asociado. El controller vuelve
    // a contar con la fila bloqueada antes de borrar: nunca confia en el "0 reg."
    // que vio el navegador.
    Route::post('personas/eliminar', [PersonaDuplicadoController::class, 'eliminar'])->name('personas.eliminar');

    // Completa las fichas sin fecha de nacimiento con el perfil de Transfermarkt
    // (una llamada cada 50 personas). Escribe SOLO los campos vacios: la fecha es
    // el desempate de la pantalla de repetidos, y las que TM manda mal ya estan
    // corregidas a mano.
    Route::post('personas/fechas/completar', [PersonaDuplicadoController::class, 'completarFechas'])->name('personas.fechas.completar');
    Route::post('personas/fechas/desconocidas', [PersonaDuplicadoController::class, 'fechasDesconocidas'])->name('personas.fechas.desconocidas');

    // Fotos rotas: vuelve a bajar de Transfermarkt el retrato de las fichas cuyo
    // archivo no está en public/images o no es una imagen. La URL del retrato es
    // gratis (de a 50 por llamada); bajar cada foto sale un crédito de ScraperAPI.
    Route::post('personas/fotos/completar', [PersonaDuplicadoController::class, 'completarFotos'])->name('personas.fotos.completar');

    // Se mantiene el nombre de ruta por compatibilidad, pero apunta al controller
    // nuevo: JugadorController@verificarSimilitud escribia SOLO en
    // `personas_verificadas`, asi que un par descartado por ahi reaparecia como
    // pendiente en `persona_duplicados`. descartar() actualiza las dos tablas.
    Route::post('verificar-similitud', [PersonaDuplicadoController::class, 'descartar'])->name('jugadores.verificarSimilitud');



    Route::get('importarPartido', [FechaController::class, 'importarPartido'])->name('fechas.importarPartido');
    Route::post('importarPartidoProcess', [FechaController::class, 'importarPartidoProcess']);
    Route::get('importfechas', [FechaController::class, 'import'])->name('fechas.import');
    Route::post('importprocess', [FechaController::class, 'importprocess']);
    Route::get('torneos/{torneo}/plantillas-buscar', [PlantillaController::class, 'buscarPorTorneo'])->name('plantillas.buscarPorTorneo');

    Route::get('importplantillas', [PlantillaController::class, 'import'])->name('plantillas.import');
    Route::post('importplantillaprocess', [PlantillaController::class, 'importprocess']);
    Route::get('importarplantilla', [PlantillaController::class, 'importar'])->name('plantilla.importar');
    Route::post('importarplantillaprocess', [PlantillaController::class, 'importarProcess']);
    Route::get('controlarplantillas', [PlantillaController::class, 'controlar'])->name('plantillas.controlar');
    Route::delete('/eliminar-jugador/{id}', [PlantillaController::class, 'eliminarJugador'])->name('plantilla.destroy');
    Route::delete('/eliminar-jugadores-seleccionados', [PlantillaController::class, 'eliminarJugadoresSeleccionados'])->name('plantilla.eliminarSeleccionados');

    Route::get('/reasignar/{id}', [JugadorController::class, 'reasignar'])->name('jugadores.reasignar');
    Route::put('guardarReasignar', [JugadorController::class, 'guardarReasignar']);

    // Panel unificado de controles de carga. Reemplaza a las siete pantallas
    // "Controlar ..." que habia antes: son chequeos dentro de la misma vista y
    // se ejecuta solo la consulta del que se esta mirando.
    Route::get('controles', [ControlController::class, 'index'])->name('controles.index');
    Route::get('controles/conteo', [ControlController::class, 'conteo'])->name('controles.conteo');
    // Control de torneos: equipos de más / de menos y completos sin posiciones.
    Route::get('controles/torneos', [ControlTorneoController::class, 'index'])->name('controles.torneos');
    Route::post('controles/recalcular', [ControlController::class, 'recalcular'])->name('controles.recalcular');
    Route::post('controles/penales/aplicar', [ControlController::class, 'aplicarPenales'])->name('controles.penales.aplicar');
    Route::post('controles/cambios/unir', [ControlController::class, 'unirCambios'])->name('controles.cambios.unir');
    Route::post('controles/sin-datos', [ControlController::class, 'marcarSinDatos'])->name('controles.sinDatos');
    Route::post('controles/rehacer', [ControlController::class, 'rehacerSeleccionados'])->name('controles.rehacer');
    Route::post('controles/sin-datos-lote', [ControlController::class, 'marcarSinDatosSeleccionados'])->name('controles.sinDatosLote');

    // Las URLs viejas siguen andando (links guardados, favoritos) pero caen en
    // el chequeo equivalente del panel nuevo.
    Route::redirect('controlarAlineaciones', '/admin/controles?check=alineaciones.faltan')->name('partidos.controlarAlineaciones');
    Route::redirect('controlarTarjetas', '/admin/controles?check=tarjetas.sin_jugar')->name('partidos.controlarTarjetas');
    Route::redirect('controlarGoles', '/admin/controles?check=goles.sin_jugar')->name('partidos.controlarGoles');
    Route::redirect('controlarCambios', '/admin/controles?check=cambios.sin_jugar')->name('partidos.controlarCambios');
    Route::redirect('controlarArbitros', '/admin/controles?check=arbitros.terna')->name('partidos.controlarArbitros');
    Route::redirect('controlarTecnicos', '/admin/controles?check=tecnicos.faltan')->name('partidos.controlarTecnicos');


    Route::get('/reassign/{id}', [TecnicoController::class, 'reasignar'])->name('tecnicos.reasignar');
    Route::put('saveReassign', [TecnicoController::class, 'guardarReasignar']);

    Route::get('/reasignarArbitro/{id}', [ArbitroController::class, 'reasignar'])->name('arbitros.reasignar');
    Route::put('saveReasignarArbitro', [ArbitroController::class, 'guardarReasignar']);


    Route::get('importgolesfecha', [FechaController::class, 'importgolesfecha'])->name('fechas.importgolesfecha');
    Route::get('importpenalesfecha', [FechaController::class, 'importpenalesfecha'])->name('fechas.importpenalesfecha');

    Route::redirect('controlarPenales', '/admin/controles?check=penales.faltantes')->name('torneos.controlarPenales');

    Route::get('finalizar', [TorneoController::class, 'finalizar'])->name('torneos.finalizar');
    Route::put('guardarFinalizar', [TorneoController::class, 'guardarFinalizar']);

    Route::get('dorsal', [TorneoController::class, 'dorsal'])->name('torneos.dorsal');
    Route::put('guardarDorsal', [TorneoController::class, 'guardarDorsal']);

    Route::get('importarArbitro', [ArbitroController::class, 'importar'])->name('arbitros.importar');
    Route::post('importarArbitroProcess', [ArbitroController::class, 'importarProcess']);

    Route::get('importarTecnico', [TecnicoController::class, 'importar'])->name('tecnicos.importar');
    Route::post('importarTecnicoProcess', [TecnicoController::class, 'importarProcess']);

    Route::get('importpoll', [PollController::class, 'importPoll'])->name('polls.importPoll');
    Route::post('importpollprocess', [PollController::class, 'importpollprocess']);
    Route::get('plantillasearch', [PlantillaController::class, 'search'])
        ->name('plantilla.search');

    Route::get('torneos/{torneo}/clasificados', [TorneoController::class, 'clasificados'])->name('torneos.clasificados');
    Route::post('torneos/{torneo}/clasificados', [TorneoController::class, 'updateClasificados'])->name('torneos.updateClasificados');

    Route::get('importargoles', [TorneoController::class, 'importargoles'])->name('torneos.importargoles');

    Route::resource('titulos', TituloController::class);
    Route::resource('jugador-estadisticas', JugadorEstadisticaManualController::class);

    Route::get(
        'jugadores/{jugador}/jugador-estadisticas',
        [JugadorEstadisticaManualController::class, 'indexPorJugador']
    )->name('jugador-estadisticas.indexPorJugador');
    Route::get('jugador-estadisticas/createPorJugador/{jugadorId}', [JugadorEstadisticaManualController::class, 'createPorJugador'])
        ->name('jugador-estadisticas.createPorJugador');
    // Mass insert: save several scraped tournaments from a single scrape
    Route::post('jugador-estadisticas/masivo', [JugadorEstadisticaManualController::class, 'storeMasivo'])
        ->name('jugador-estadisticas.storeMasivo');

    Route::resource('tecnico-estadisticas', TecnicoEstadisticaManualController::class);

    Route::get(
        'tecnicos/{tecnico}/tecnico-estadisticas',
        [TecnicoEstadisticaManualController::class, 'indexPorTecnico']
    )->name('tecnico-estadisticas.indexPorTecnico');
    Route::get('tecnico-estadisticas/createPorTecnico/{tecnicoId}', [TecnicoEstadisticaManualController::class, 'createPorTecnico'])
        ->name('tecnico-estadisticas.createPorTecnico');


    Route::post('tecnico-estadisticas/masivo', [TecnicoEstadisticaManualController::class, 'storeMasivo'])
        ->name('tecnico-estadisticas.storeMasivo');

    Route::resource('equipo-estadisticas', EquipoEstadisticaManualController::class);

    Route::get(
        'equipos/{equipo}/equipo-estadisticas',
        [EquipoEstadisticaManualController::class, 'indexPorEquipo']
    )->name('equipo-estadisticas.indexPorEquipo');
    Route::get('equipo-estadisticas/createPorEquipo/{equipoId}', [EquipoEstadisticaManualController::class, 'createPorEquipo'])
        ->name('equipo-estadisticas.createPorEquipo');

    Route::get('/test-scraper', [ScraperController::class, 'test']);
    Route::get('/scraper/tecnico', [ScraperController::class, 'autocompletar']);

    Route::get('/scraper/equipo', [ScraperController::class, 'equipo']);

    Route::post('/scraper/csv-tecnico', [ScraperController::class, 'csvTecnico']);

    Route::get('/scraper/tecnico-footballdb', [ScraperController::class, 'tecnicoFootballDatabase']);
    Route::get('/scraper/jugador-footballdb', [App\Http\Controllers\ScraperController::class, 'jugadorFootballDatabase']);
    Route::get('/scraper/jugador-transfermarkt', [App\Http\Controllers\ScraperController::class, 'jugadorTransfermarkt']);
    Route::get('/scraper/tecnico-transfermarkt', [App\Http\Controllers\ScraperController::class, 'tecnicoTransfermarkt']);
    Route::get('/scraper/tecnico-wikipedia', [ScraperController::class, 'tecnicoWikipedia']);
    Route::get('/scraper/nuevos-torneos', [ScraperController::class, 'nuevosTorneos'])->name('scraper.nuevos-torneos');

    Route::group(['prefix' => 'competencias-excluidas'], function () {
        Route::get('/',             [CompetenciaExcluidaController::class, 'index'])->name('competencias_excluidas.index');
        Route::get('/listar',       [CompetenciaExcluidaController::class, 'listar'])->name('competencias_excluidas.listar');
        Route::post('/',            [CompetenciaExcluidaController::class, 'store'])->name('competencias_excluidas.store');
        Route::put('/{id}',         [CompetenciaExcluidaController::class, 'update'])->name('competencias_excluidas.update');
        Route::post('/{id}/toggle', [CompetenciaExcluidaController::class, 'toggle'])->name('competencias_excluidas.toggle');
        Route::delete('/{id}',      [CompetenciaExcluidaController::class, 'destroy'])->name('competencias_excluidas.destroy');
        Route::post('/probar',      [CompetenciaExcluidaController::class, 'probar'])->name('competencias_excluidas.probar');
        Route::post('/excluir-rapido', [CompetenciaExcluidaController::class, 'excluirRapido'])->name('competencias_excluidas.excluirRapido');
    });

    // Tira la caché del sitio público. Se vacía sola con cada cambio hecho desde
    // la app; esto es para los cambios hechos por fuera (phpMyAdmin, SQL a mano).
    Route::post('/cache-paginas/vaciar', function () {
        \App\Services\CachePaginas::vaciarTodo();
        return back()->with('success', 'Caché del sitio público vaciada.');
    })->name('cache_paginas.vaciar');
    Route::get('/scraper/jugador-transfermarkt-goles',
        [App\Http\Controllers\ScraperController::class, 'jugadorTransfermarktGoles'])
        ->name('scraper.jugador-transfermarkt-goles');

    Route::post('/equipos-excluidos/excluir-rapido', [EquipoExcluidoController::class, 'excluirRapido']);

    // Lista de equipos en JSON (id, nombre) para refrescar los desplegables del
    // scraper sin recargar la página tras dar de alta un club en el ABM.
    Route::get('/equipos-json', [EquipoController::class, 'json'])->name('equipos.json');

    Route::get('/scraper/equipo-transfermarkt', [ScraperController::class, 'equipoTransfermarkt']);
    Route::post('equipo-estadisticas/store-masivo', [EquipoEstadisticaManualController::class, 'storeMasivo'])
        ->name('equipo-estadisticas.storeMasivo');

    Route::get('/import-partidos', [ImportPartidosController::class, 'index'])->name('import_partidos.index');
    Route::get('/import-partidos/sondear', [ImportPartidosController::class, 'sondear'])->name('import_partidos.sondear');
    Route::get('/import-partidos/aplicar', [ImportPartidosController::class, 'aplicar'])->name('import_partidos.aplicar');
    Route::get('/import-partidos/partido', [ImportPartidosController::class, 'partido'])->name('import_partidos.partido');
    Route::get('/import-partidos/crear-equipo', [ImportPartidosController::class, 'crearEquipo'])->name('import_partidos.crear_equipo');
    Route::get('/import-partidos/fixture', [ImportPartidosController::class, 'fixture'])->name('import_partidos.fixture');
    Route::get('/import-partidos/fixture-aplicar', [ImportPartidosController::class, 'fixtureAplicar'])->name('import_partidos.fixture_aplicar');
    Route::get('/import-partidos/fixture-aplicar-todas', [ImportPartidosController::class, 'fixtureAplicarTodas'])->name('import_partidos.fixture_aplicar_todas');
    Route::get('/import-partidos/fechas', [ImportPartidosController::class, 'fechas'])->name('import_partidos.fechas');

    // Segunda etapa: el detalle de cada partido (alineaciones, goles, tarjetas,
    // cambios, árbitros). Ver ImportDetallesController.
    Route::get('/import-detalles', [ImportDetallesController::class, 'index'])->name('import_detalles.index');
    Route::get('/import-detalles/ver', [ImportDetallesController::class, 'ver'])->name('import_detalles.ver');
    Route::get('/import-detalles/bajar', [ImportDetallesController::class, 'bajar'])->name('import_detalles.bajar');
    // Solo el marcador de UN partido, sin tocar el resto: es el único camino
    // para los definidos por penales (el fixture publica 90'+tanda junto).
    Route::get('/import-detalles/marcador', [ImportDetallesController::class, 'marcador'])->name('import_detalles.marcador');
    Route::get('/import-detalles/fecha', [ImportDetallesController::class, 'fecha'])->name('import_detalles.fecha');
    Route::get('/import-detalles/gameid', [ImportDetallesController::class, 'gameIdMover'])->name('import_detalles.gameid');
    // Un partido con más de un gameId (la ida y la vuelta atadas al mismo) y el
    // botón que le saca a una fila del staging el partido al que apuntaba.
    Route::get('/import-detalles/gameids', [ImportDetallesController::class, 'gameIds'])->name('import_detalles.gameids');
    Route::get('/import-detalles/desatar', [ImportDetallesController::class, 'desatar'])->name('import_detalles.desatar');
    Route::get('/import-detalles/tanda', [ImportDetallesController::class, 'tanda'])->name('import_detalles.tanda');
    Route::get('/import-detalles/penales', [ImportDetallesController::class, 'penales'])->name('import_detalles.penales');
    Route::get('/import-detalles/tipos-gol', [ImportDetallesController::class, 'tiposGol'])->name('import_detalles.tipos_gol');
    Route::get('/import-detalles/sembrar', [ImportDetallesController::class, 'sembrar'])->name('import_detalles.sembrar');
    Route::get('/import-detalles/revisar', [ImportDetallesController::class, 'revisar'])->name('import_detalles.revisar');
    Route::get('/import-detalles/mapeos', [ImportDetallesController::class, 'mapeos'])->name('import_detalles.mapeos');
    // Mapeos atados sólo por el nombre de pila (caso Jesse González → José Gayà, oct-2026).
    Route::get('/import-detalles/mapeos-dudosos', [ImportDetallesController::class, 'mapeosDudosos'])->name('import_detalles.mapeos_dudosos');
    Route::post('/import-detalles/mapeos-dudosos/desatar', [ImportDetallesController::class, 'mapeosDudososDesatar'])->name('import_detalles.mapeos_dudosos_desatar');
    Route::post('/import-detalles/mapeos-dudosos/confirmar', [ImportDetallesController::class, 'mapeosDudososConfirmar'])->name('import_detalles.mapeos_dudosos_confirmar');
    Route::get('/import-detalles/mapeos-dudosos/clubes', [ImportDetallesController::class, 'mapeosDudososClubes'])->name('import_detalles.mapeos_dudosos_clubes');
    // Fichas con más de un id de TM (mellizos Quina, oct-2026): clasificar, separar y rehacer en lote.
    Route::get('/import-detalles/fichas-mezcladas', [ImportDetallesController::class, 'fichasMezcladas'])->name('import_detalles.fichas_mezcladas');
    Route::post('/import-detalles/fichas-mezcladas/confirmar', [ImportDetallesController::class, 'fichasMezcladasConfirmar'])->name('import_detalles.fichas_mezcladas_confirmar');
    Route::post('/import-detalles/fichas-mezcladas/separar', [ImportDetallesController::class, 'fichasMezcladasSeparar'])->name('import_detalles.fichas_mezcladas_separar');
    Route::get('/import-detalles/fichas-mezcladas/rehacer', [ImportDetallesController::class, 'fichasMezcladasRehacer'])->name('import_detalles.fichas_mezcladas_rehacer');
    Route::get('/import-detalles/plantillas', [ImportDetallesController::class, 'plantillas'])->name('import_detalles.plantillas');
    Route::get('/import-detalles/resultados', [ImportDetallesController::class, 'resultados'])->name('import_detalles.resultados');
    Route::get('/import-detalles/arbitro', [ImportDetallesController::class, 'arbitro'])->name('import_detalles.arbitro');
    // Mide si Transfermarkt manda los asistentes o no los tiene. Ver TernaSondeoController.
    Route::get('/import-detalles/terna-sondeo', [TernaSondeoController::class, 'index'])->name('import_detalles.terna_sondeo');
    Route::get('/import-detalles/terna-barrido', [TernaSondeoController::class, 'barrido'])->name('import_detalles.terna_barrido');
    Route::get('/import-detalles/competencia', [ImportDetallesController::class, 'competencia'])->name('import_detalles.competencia');
    Route::get('/import-detalles/club-html', [ImportDetallesController::class, 'clubHtml'])->name('import_detalles.club_html');
    Route::get('/import-detalles/competencia-html', [ImportDetallesController::class, 'competenciaHtml'])->name('import_detalles.competencia_html');
    Route::get('/import-detalles/nombres-alfabeto', [ImportDetallesController::class, 'nombresAlfabeto'])->name('import_detalles.nombres_alfabeto');
    Route::get('/import-detalles/clubes-tm', [ImportDetallesController::class, 'clubesTm'])->name('import_detalles.clubes_tm');
    // Partidos con un equipo vacío (`equipol_id`/`equipov_id` en 0, en null o
    // apuntando a un equipo borrado). Va acá porque lo destapa la tanda de
    // detalles: «Los clubes no coinciden. Base: #0 vs #429». El POST es el que
    // escribe; el GET sólo muestra.
    Route::get('/import-detalles/equipos-vacios', [ImportDetallesController::class, 'equiposVacios'])
        ->name('import_detalles.equipos_vacios');
    Route::post('/import-detalles/equipos-vacios', [ImportDetallesController::class, 'equiposVacios']);

    // Unificar dos equipos que son el mismo club. Va acá y no en `equipos`
    // porque el caso lo destapa siempre el importador: TM renombra el verein
    // cuando el club se muda y nos quedan dos equipos para un solo club.
    Route::get('/import-detalles/fusionar-equipos', [ImportDetallesController::class, 'fusionarEquipos'])
        ->name('import_detalles.fusionar_equipos');

    // Los pares de equipos que parecen el mismo club (la lista que le falta a
    // «Unificar equipos»: primero hay que saber cuál está partido en dos).
    // Sólo GET: no escribe nada, ofrece el link a unificar o a borrar la ficha
    // vacía, que es el otro final posible.
    Route::get('/import-detalles/equipos-repetidos', [ImportDetallesController::class, 'equiposRepetidos'])
        ->name('import_detalles.equipos_repetidos');
    // Lo único que escribe esa pantalla: borrar una ficha de equipo que no
    // tiene nada colgando (el otro final posible del par, cuando unificar no
    // movería ninguna fila). Re-cuenta adentro de la transacción antes de
    // borrar: no confía en lo que vio el navegador.
    Route::post('/import-detalles/equipos-repetidos/borrar', [ImportDetallesController::class, 'borrarEquipoVacio'])
        ->name('import_detalles.equipos_repetidos_borrar');

    // «No son el mismo club» y su vuelta atrás. Lo único que se guarda de la
    // decisión humana: los candidatos se recalculan solos, esto no.
    Route::post('/import-detalles/equipos-repetidos/marcar', [ImportDetallesController::class, 'marcarEquiposDistintos'])
        ->name('import_detalles.equipos_repetidos_marcar');

    // Clubes que TM marca como desaparecidos con "(- 2019)" / "(1981-2019)" al
    // final del nombre: nombre limpio + año a equipos.desaparicion. El GET sólo
    // muestra; el POST escribe los tildados.
    Route::get('/import-detalles/clubes-desaparecidos', [ImportDetallesController::class, 'clubesDesaparecidos'])
        ->name('import_detalles.clubes_desaparecidos');
    Route::post('/import-detalles/clubes-desaparecidos', [ImportDetallesController::class, 'clubesDesaparecidos']);
});


// ─────────────────────────────────────────────────────────────────────────
//  Sitio público, en cada idioma de idiomas_sitio() (app/helpers.php)
//
//  Las mismas rutas se registran una vez por idioma: primero las de español,
//  sin prefijo (así las URLs de siempre no cambian), y después las de los otros
//  idiomas con su prefijo (/en/verTorneo…). Todas llevan el MISMO nombre:
//  route('torneos.ver') apunta a la de español y App\Routing\UrlIdioma le
//  agrega el /en cuando la página está en inglés.
//
//  OJO con el orden: desde Laravel 11 un nombre repetido lo conserva la
//  PRIMERA ruta registrada (RouteCollection::addLookups usa !inNameLookup);
//  hasta Laravel 10 ganaba la última. Por eso español va primero. Al revés,
//  route() da /en/... también en español, y /en/en/... en inglés.
//  Así ninguna vista tuvo que cambiar sus route() ni sus routeIs().
// ─────────────────────────────────────────────────────────────────────────
$rutasPublicas = function () {
    // La raiz sirve el fixture directamente. Antes renderizaba portada.blade.php,
    // que era solo un <script>window.location = '/fixture'</script>: la URL mas
    // importante del sitio devolvia una pagina vacia (mala para buscadores, y un
    // parpadeo en blanco para el visitante).
    Route::get('/', [FechaController::class, 'fixture'])->name('home');

    // Se conserva la URL vieja para los favoritos, pero ahora redirige de verdad,
    // del lado del servidor, en vez de por JavaScript.
    //
    // El destino va por route('home') y NO como path literal '/': la app vive bajo
    // /~torneospinia/public, y Route::redirect con '/' resuelve contra la raiz del
    // dominio, que es la pagina por defecto de cPanel. Misma trampa del prefijo de
    // siempre: el destino lo pone route(), nunca una ruta escrita a mano.
    Route::get('portada', function () {
        return redirect()->route('home');
    })->name('portada');

    Route::get('posiciones', [GrupoController::class, 'posiciones'])->name('grupos.posiciones');
    Route::get('tablaGoles', [GrupoController::class, 'goleadores'])->name('grupos.goleadores');
    Route::get('tablaJugadores', [GrupoController::class, 'jugadores'])->name('grupos.jugadores');
    Route::get('tablaTarjetas', [GrupoController::class, 'tarjetas'])->name('grupos.tarjetas');
    Route::get('jueces', [PartidoController::class, 'arbitros'])->name('partidos.arbitros');
    Route::get('promedios', [TorneoController::class, 'promedios'])->name('torneos.promedios');
    Route::get('tecnicos', [GrupoController::class, 'tecnicos'])->name('grupos.tecnicos');
    Route::get('verTorneo', [TorneoController::class, 'ver'])->name('torneos.ver');
    Route::get('tabla', [GrupoController::class, 'posicionesPublic'])->name('grupos.posicionesPublic');
    Route::get('goleadores', [GrupoController::class, 'goleadoresPublic'])->name('grupos.goleadoresPublic');
    Route::get('tarjetero', [GrupoController::class, 'tarjetasPublic'])->name('grupos.tarjetasPublic');
    Route::get('verFechas', [FechaController::class, 'ver'])->name('fechas.ver');
    Route::get('fixture', [FechaController::class, 'fixture'])->name('fechas.fixture');
    Route::get('buscar', [BuscadorController::class, 'index'])->name('buscar');

    // Menú «Torneos» por país / región: el desplegable baja el JSON la primera vez
    // que se abre; /competiciones es lo mismo como página común (sin JS, buscadores).
    Route::get('torneos-menu', [MenuTorneosController::class, 'json'])->name('torneos.menuJson');
    Route::get('competiciones', [MenuTorneosController::class, 'explorar'])->name('torneos.explorar');
    Route::get('verFecha', [FechaController::class, 'showPublic'])->name('fechas.showPublic');
    Route::get('detalleFecha', [FechaController::class, 'detalle'])->name('fechas.detalle');
    Route::get('verJugador', [JugadorController::class, 'ver'])->name('jugadores.ver');
    Route::get('jugadorJugados', [JugadorController::class, 'jugados'])->name('jugadores.jugados');
    Route::get('jugadorGoles', [JugadorController::class, 'goles'])->name('jugadores.goles');
    Route::get('jugadorTarjetas', [JugadorController::class, 'tarjetas'])->name('jugadores.tarjetas');
    Route::get('jugadorPenals', [JugadorController::class, 'penals'])->name('jugadores.penals');
    Route::get('jugadorTitulos', [JugadorController::class, 'titulos'])->name('jugadores.titulos');
    Route::get('verEquipo', [EquipoController::class, 'ver'])->name('equipos.ver');
    Route::get('equipoJugados', [EquipoController::class, 'jugados'])->name('equipos.jugados');
    Route::get('verTecnico', [TecnicoController::class, 'ver'])->name('tecnicos.ver');
    Route::get('tecnicoJugados', [TecnicoController::class, 'jugados'])->name('tecnicos.jugados');
    Route::get('verArbitro', [ArbitroController::class, 'ver'])->name('arbitros.ver');
    Route::get('descensos', [TorneoController::class, 'promediosPublic'])->name('torneos.promediosPublic');
    Route::get('acumulado', [TorneoController::class, 'acumulado'])->name('torneos.acumulado');
    Route::get('arqueros', [GrupoController::class, 'arqueros'])->name('grupos.arqueros');
    Route::get('plantillas', [TorneoController::class, 'plantillas'])->name('torneos.plantillas');

    Route::get('historiales', [TorneoController::class, 'historiales'])->name('torneos.historiales');
    Route::get('goleadoresHistorico', [TorneoController::class, 'goleadores'])->name('torneos.goleadores');
    Route::get('jugadoresHistorico', [TorneoController::class, 'jugadores'])->name('torneos.jugadores');
    Route::get('tarjetasHistorico', [TorneoController::class, 'tarjetas'])->name('torneos.tarjetas');
    Route::get('posicionesHistorico', [TorneoController::class, 'posiciones'])->name('torneos.posiciones');
    Route::get('otrasEstadisticas', [TorneoController::class, 'estadisticasOtras'])->name('torneos.estadisticasOtras');
    Route::get('estadisticasTorneo', [TorneoController::class, 'estadisticasTorneo'])->name('torneos.estadisticasTorneo');
    Route::get('tecnicosHistorico', [TorneoController::class, 'tecnicos'])->name('torneos.tecnicos');
    Route::get('arquerosHistorico', [TorneoController::class, 'arqueros'])->name('torneos.arqueros');
    Route::get('titulosHistorico', [TorneoController::class, 'titulos'])->name('torneos.titulos');
};

foreach (array_keys(idiomas_sitio()) as $idioma) {
    $esLaDeLaCasa = $idioma === array_keys(idiomas_sitio())[0];
    Route::group(
        // 'pagina.cache' va después de 'idioma': la clave de la caché lleva el idioma.
        $esLaDeLaCasa
            ? ['middleware' => ['idioma:' . $idioma, 'pagina.cache']]
            : ['prefix' => $idioma, 'middleware' => ['idioma:' . $idioma, 'pagina.cache']],
        $rutasPublicas
    );
}


Route::get('logout', [LoginController::class, 'logout']);


