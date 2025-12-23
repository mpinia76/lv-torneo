<?php

use Illuminate\Support\Facades\Route;

use Illuminate\Support\Facades\Soap;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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



Auth::routes();




Route::group(['prefix' => 'admin'], function()
{
    Route::get('/', function () {
        return view('/home');
    });
    Route::get('/home', 'TorneoController@index')->name('index');
    Route::resource('torneos', 'TorneoController');
    Route::get(
        '/jugadores/name-completo-no-verificado',
        'JugadorController@nameCompletoNoVerificado'
    )->name('jugadores.nameCompletoNoVerificado');

    Route::post(
        'jugadores/verificar-nombre-apellido-simple',
        'JugadorController@verificarNombreApellidoSimple'
    )->name('jugadores.verificarNombreApellidoSimple');

    Route::post(
        '/jugadores/confirmar-nombre-largo/{persona}',
        'JugadorController@confirmarNombreLargo'
    )->name('jugadores.confirmarNombreLargo');

    Route::post(
        'jugadores/confirmar-nombres-largos',
        'JugadorController@confirmarNombresLargos'
    )->name('jugadores.confirmarNombresLargos');


    Route::resource('jugadores', 'JugadorController');
    Route::resource('equipos', 'EquipoController');
    Route::resource('fechas', 'FechaController');
    Route::resource('partidos', 'PartidoController');
    Route::resource('plantillas', 'PlantillaController');
    Route::resource('goles', 'GolController');
    Route::resource('arbitros', 'ArbitroController');
    Route::resource('tecnicos', 'TecnicoController');
    Route::resource('tarjetas', 'TarjetaController');
    Route::resource('partidoarbitros', 'PartidoArbitroController');
    Route::resource('alineaciones', 'AlineacionController');
    Route::resource('cambios', 'CambioController');
    Route::resource('incidencias', 'IncidenciaController');
    Route::resource('cruces', 'CruceController');
    Route::resource('penales', 'PenalController');

    Route::get('importarJugador', 'JugadorController@importar')->name('jugadores.importar');
    Route::post('importarJugadorProcess', 'JugadorController@importarProcess');
    Route::get('verificarPersonas', 'JugadorController@verificarPersonas')->name('jugadores.verificarPersonas');

    Route::post('verificar-similitud', 'JugadorController@verificarSimilitud')->name('jugadores.verificarSimilitud');



    Route::get('importarPartido', 'FechaController@importarPartido')->name('fechas.importarPartido');
    Route::post('importarPartidoProcess', 'FechaController@importarPartidoProcess');
    Route::get('importfechas', 'FechaController@import')->name('fechas.import');
    Route::post('importprocess', 'FechaController@importprocess');
    Route::get('torneos/{torneo}/plantillas-buscar', 'PlantillaController@buscarPorTorneo')->name('plantillas.buscarPorTorneo');

    Route::get('importplantillas', 'PlantillaController@import')->name('plantillas.import');
    Route::post('importplantillaprocess', 'PlantillaController@importprocess');
    Route::get('importarplantilla', 'PlantillaController@importar')->name('plantilla.importar');
    Route::post('importarplantillaprocess', 'PlantillaController@importarProcess');
    Route::get('controlarplantillas', 'PlantillaController@controlar')->name('plantillas.controlar');
    Route::delete('/eliminar-jugador/{id}', 'PlantillaController@eliminarJugador')->name('plantilla.destroy');
    Route::delete('/eliminar-jugadores-seleccionados', 'PlantillaController@eliminarJugadoresSeleccionados')->name('plantilla.eliminarSeleccionados');

    Route::get('/reasignar/{id}', 'JugadorController@reasignar')->name('jugadores.reasignar');
    Route::put('guardarReasignar', 'JugadorController@guardarReasignar');

    Route::get('controlarAlineaciones', 'PartidoController@controlarAlineaciones')->name('partidos.controlarAlineaciones');
    Route::get('controlarTarjetas', 'PartidoController@controlarTarjetas')->name('partidos.controlarTarjetas');
    Route::get('controlarGoles', 'PartidoController@controlarGoles')->name('partidos.controlarGoles');
    Route::get('controlarCambios', 'PartidoController@controlarCambios')->name('partidos.controlarCambios');
    Route::get('controlarArbitros', 'PartidoController@controlarArbitros')->name('partidos.controlarArbitros');
    Route::get('controlarTecnicos', 'PartidoController@controlarTecnicos')->name('partidos.controlarTecnicos');


    Route::get('/reassign/{id}', 'TecnicoController@reasignar')->name('tecnicos.reasignar');
    Route::put('saveReassign', 'TecnicoController@guardarReasignar');

    Route::get('importincidencias', 'FechaController@importincidencias')->name('fechas.importincidencias');
    Route::post('importincidenciasprocess', 'FechaController@importincidenciasprocess');

    Route::get('importincidenciasfecha', 'FechaController@importincidenciasfecha')->name('fechas.importincidenciasfecha');
    Route::get('importgolesfecha', 'FechaController@importgolesfecha')->name('fechas.importgolesfecha');
    Route::get('importpenalesfecha', 'FechaController@importpenalesfecha')->name('fechas.importpenalesfecha');
    Route::get('controlarbitrosfecha', 'FechaController@controlarbitrosfecha')->name('fechas.controlarbitrosfecha');

    Route::get('controlarPenales', 'TorneoController@controlarPenales')->name('torneos.controlarPenales');

    Route::get('finalizar', 'TorneoController@finalizar')->name('torneos.finalizar');
    Route::put('guardarFinalizar', 'TorneoController@guardarFinalizar');

    Route::get('dorsal', 'TorneoController@dorsal')->name('torneos.dorsal');
    Route::put('guardarDorsal', 'TorneoController@guardarDorsal');

    Route::get('importarArbitro', 'ArbitroController@importar')->name('arbitros.importar');
    Route::post('importarArbitroProcess', 'ArbitroController@importarProcess');

    Route::get('importarTecnico', 'TecnicoController@importar')->name('tecnicos.importar');
    Route::post('importarTecnicoProcess', 'TecnicoController@importarProcess');

    Route::get('importpoll', 'PollController@importPoll')->name('polls.importPoll');
    Route::post('importpollprocess', 'PollController@importpollprocess');
    Route::get('plantillasearch', 'PlantillaController@search')
        ->name('plantilla.search');

    Route::get('torneos/{torneo}/clasificados', 'TorneoController@clasificados')->name('torneos.clasificados');
    Route::post('torneos/{torneo}/clasificados', 'TorneoController@updateClasificados')->name('torneos.updateClasificados');

    Route::get('importargoles', 'TorneoController@importargoles')->name('torneos.importargoles');

    Route::resource('titulos', 'TituloController');


});


Route::get('/', function () {
    return view('/portada');
});
Route::get('portada', 'PortadaController@index')->name('portada');

Route::get('posiciones', 'GrupoController@posiciones')->name('grupos.posiciones');
Route::get('tablaGoles', 'GrupoController@goleadores')->name('grupos.goleadores');
Route::get('tablaJugadores', 'GrupoController@jugadores')->name('grupos.jugadores');
Route::get('tablaTarjetas', 'GrupoController@tarjetas')->name('grupos.tarjetas');
Route::get('jueces', 'PartidoController@arbitros')->name('partidos.arbitros');
Route::get('promedios', 'TorneoController@promedios')->name('torneos.promedios');
Route::get('tecnicos', 'GrupoController@tecnicos')->name('grupos.tecnicos');
Route::get('verTorneo', 'TorneoController@ver')->name('torneos.ver');
Route::get('tabla', 'GrupoController@posicionesPublic')->name('grupos.posicionesPublic');
Route::get('goleadores', 'GrupoController@goleadoresPublic')->name('grupos.goleadoresPublic');
Route::get('tarjetero', 'GrupoController@tarjetasPublic')->name('grupos.tarjetasPublic');
Route::get('verFechas', 'FechaController@ver')->name('fechas.ver');
Route::get('fixture', 'FechaController@fixture')->name('fechas.fixture');
Route::get('verFecha', 'FechaController@showPublic')->name('fechas.showPublic');
Route::get('detalleFecha', 'FechaController@detalle')->name('fechas.detalle');
Route::get('verJugador', 'JugadorController@ver')->name('jugadores.ver');
Route::get('jugadorJugados', 'JugadorController@jugados')->name('jugadores.jugados');
Route::get('jugadorGoles', 'JugadorController@goles')->name('jugadores.goles');
Route::get('jugadorTarjetas', 'JugadorController@tarjetas')->name('jugadores.tarjetas');
Route::get('jugadorPenals', 'JugadorController@penals')->name('jugadores.penals');
Route::get('jugadorTitulos', 'JugadorController@titulos')->name('jugadores.titulos');
Route::get('verEquipo', 'EquipoController@ver')->name('equipos.ver');
Route::get('equipoJugados', 'EquipoController@jugados')->name('equipos.jugados');
Route::get('verTecnico', 'TecnicoController@ver')->name('tecnicos.ver');
Route::get('tecnicoJugados', 'TecnicoController@jugados')->name('tecnicos.jugados');
Route::get('verArbitro', 'ArbitroController@ver')->name('arbitros.ver');
Route::get('descensos', 'TorneoController@promediosPublic')->name('torneos.promediosPublic');
Route::get('acumulado', 'TorneoController@acumulado')->name('torneos.acumulado');
Route::get('arqueros', 'GrupoController@arqueros')->name('grupos.arqueros');
Route::get('metodo', 'GrupoController@metodo')->name('grupos.metodo');
Route::get('plantillas', 'TorneoController@plantillas')->name('torneos.plantillas');

Route::get('historiales', 'TorneoController@historiales')->name('torneos.historiales');
Route::get('goleadoresHistorico', 'TorneoController@goleadores')->name('torneos.goleadores');
Route::get('jugadoresHistorico', 'TorneoController@jugadores')->name('torneos.jugadores');
Route::get('tarjetasHistorico', 'TorneoController@tarjetas')->name('torneos.tarjetas');
Route::get('posicionesHistorico', 'TorneoController@posiciones')->name('torneos.posiciones');
Route::get('otrasEstadisticas', 'TorneoController@estadisticasOtras')->name('torneos.estadisticasOtras');
Route::get('estadisticasTorneo', 'TorneoController@estadisticasTorneo')->name('torneos.estadisticasTorneo');
Route::get('tecnicosHistorico', 'TorneoController@tecnicos')->name('torneos.tecnicos');
Route::get('arquerosHistorico', 'TorneoController@arqueros')->name('torneos.arqueros');
Route::get('titulosHistorico', 'TorneoController@titulos')->name('torneos.titulos');


Route::get('logout', 'Auth\LoginController@logout');


Route::get('/ejecutar-actualizar-nombres', function (Request $request) {
    // Token de seguridad
    $token = $request->query('token');

    if ($token !== 'Zp4rV9kN2qM7LbXy') {
        abort(403, 'Acceso no autorizado');
    }

    Artisan::call('personas:actualizar-nombres');

    return '✅ Comando ejecutado correctamente.';
});
