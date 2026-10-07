/*M!999999\- enable the sandbox mode */ 

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `acumulado_torneos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `acumulado_torneos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `torneo_id` bigint(20) unsigned NOT NULL,
  `torneoAnterior_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `acumulado_torneos_torneo_id_foreign` (`torneo_id`),
  KEY `acumulado_torneos_torneoanterior_id_foreign` (`torneoAnterior_id`),
  CONSTRAINT `acumulado_torneos_torneo_id_foreign` FOREIGN KEY (`torneo_id`) REFERENCES `torneos` (`id`),
  CONSTRAINT `acumulado_torneos_torneoanterior_id_foreign` FOREIGN KEY (`torneoAnterior_id`) REFERENCES `torneos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `alineacions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `alineacions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `partido_id` bigint(20) unsigned NOT NULL,
  `equipo_id` bigint(20) unsigned NOT NULL,
  `jugador_id` bigint(20) unsigned NOT NULL,
  `dorsal` int(11) DEFAULT NULL,
  `tipo` enum('Titular','Suplente') NOT NULL,
  `orden` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `partido_id_jugador_id` (`partido_id`,`jugador_id`),
  UNIQUE KEY `partido_id_equipo_id_dorsal` (`partido_id`,`equipo_id`,`dorsal`),
  KEY `alineacions_equipo_id_foreign` (`equipo_id`),
  KEY `alineacions_jugador_tipo` (`jugador_id`,`tipo`),
  CONSTRAINT `alineacions_equipo_id_foreign` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`),
  CONSTRAINT `alineacions_jugador_id_foreign` FOREIGN KEY (`jugador_id`) REFERENCES `jugadors` (`id`),
  CONSTRAINT `alineacions_partido_id_foreign` FOREIGN KEY (`partido_id`) REFERENCES `partidos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `arbitro_tm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `arbitro_tm` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tm_referee_id` varchar(40) NOT NULL,
  `arbitro_id` bigint(20) unsigned NOT NULL,
  `nombre_tm` varchar(191) DEFAULT NULL,
  `origen` varchar(20) NOT NULL DEFAULT 'auto',
  `revisar` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `arbitro_tm_tm_referee_id_unique` (`tm_referee_id`),
  KEY `arbitro_tm_arbitro_id_index` (`arbitro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `arbitros`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `arbitros` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `persona_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `persona_id` (`persona_id`),
  CONSTRAINT `FK1_personas_id_foreign` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cambios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cambios` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `partido_id` bigint(20) unsigned NOT NULL,
  `jugador_id` bigint(20) unsigned NOT NULL,
  `minuto` int(11) DEFAULT NULL,
  `adicionado` tinyint(3) unsigned DEFAULT NULL,
  `tipo` enum('Sale','Entra') NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cambios_partido_id_foreign` (`partido_id`),
  KEY `cambios_jugador_tipo` (`jugador_id`,`tipo`),
  CONSTRAINT `cambios_jugador_id_foreign` FOREIGN KEY (`jugador_id`) REFERENCES `jugadors` (`id`),
  CONSTRAINT `cambios_partido_id_foreign` FOREIGN KEY (`partido_id`) REFERENCES `partidos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `competencias_excluidas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `competencias_excluidas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `patron` varchar(255) NOT NULL,
  `tipo_match` enum('exacto','contiene','regex') NOT NULL DEFAULT 'contiene',
  `motivo` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `competencias_excluidas_patron_index` (`patron`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cruces`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cruces` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `torneo_id` bigint(20) unsigned NOT NULL,
  `dia` timestamp NULL DEFAULT NULL,
  `fase` varchar(191) NOT NULL,
  `orden` int(10) unsigned NOT NULL,
  `clasificado_1` varchar(191) NOT NULL,
  `clasificado_2` varchar(191) NOT NULL,
  `ganador_id` bigint(20) unsigned DEFAULT NULL,
  `partido_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `neutral` tinyint(1) DEFAULT 0,
  `siguiente_fase` varchar(255) DEFAULT NULL,
  `orden_siguiente` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `cruces_torneo_id_foreign` (`torneo_id`) USING BTREE,
  KEY `FK_cruces_equipos` (`ganador_id`) USING BTREE,
  KEY `FK_cruces_partidos` (`partido_id`) USING BTREE,
  CONSTRAINT `FK_cruces_equipos` FOREIGN KEY (`ganador_id`) REFERENCES `equipos` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `FK_cruces_partidos` FOREIGN KEY (`partido_id`) REFERENCES `partidos` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `cruces_torneo_id_foreign` FOREIGN KEY (`torneo_id`) REFERENCES `torneos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `equipo_clasificados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `equipo_clasificados` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `torneo_id` bigint(20) unsigned NOT NULL,
  `equipo_id` bigint(20) unsigned NOT NULL,
  `torneo_clasificacion_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `equipo_clasificados_torneo_id_foreign` (`torneo_id`),
  KEY `equipo_clasificados_equipo_id_foreign` (`equipo_id`),
  KEY `equipo_clasificados_torneo_clasificacion_id_foreign` (`torneo_clasificacion_id`),
  CONSTRAINT `equipo_clasificados_equipo_id_foreign` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`),
  CONSTRAINT `equipo_clasificados_torneo_clasificacion_id_foreign` FOREIGN KEY (`torneo_clasificacion_id`) REFERENCES `torneo_clasificacions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `equipo_clasificados_torneo_id_foreign` FOREIGN KEY (`torneo_id`) REFERENCES `torneos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `equipo_descartados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `equipo_descartados` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `equipo_id` bigint(20) unsigned NOT NULL,
  `equipo2_id` bigint(20) unsigned NOT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `equipo_descartados_par_unique` (`equipo_id`,`equipo2_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `equipo_estadistica_manuals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `equipo_estadistica_manuals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `equipo_id` bigint(20) unsigned NOT NULL,
  `torneo_nombre` varchar(191) NOT NULL,
  `torneo_logo` varchar(191) DEFAULT NULL,
  `tipo` enum('Liga','Copa') NOT NULL,
  `ambito` enum('Nacional','Internacional') NOT NULL DEFAULT 'Nacional',
  `posicion` int(11) DEFAULT 0,
  `partidos` int(11) DEFAULT 0,
  `ganados` int(11) DEFAULT 0,
  `empatados` int(11) DEFAULT 0,
  `perdidos` int(11) DEFAULT 0,
  `goles_favor` int(11) DEFAULT 0,
  `goles_en_contra` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `equipo_estadistica_manuals_equipo_id_foreign` (`equipo_id`),
  CONSTRAINT `equipo_estadistica_manuals_equipo_id_foreign` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `equipo_tm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `equipo_tm` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `equipo_id` bigint(20) unsigned NOT NULL,
  `tm_club_id` varchar(40) NOT NULL,
  `nombre_tm` varchar(191) DEFAULT NULL,
  `origen` varchar(20) NOT NULL DEFAULT 'manual',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `equipo_tm_tm_club_id_unique` (`tm_club_id`),
  KEY `equipo_tm_equipo_id_index` (`equipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `equipos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `equipos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(191) DEFAULT NULL,
  `siglas` varchar(191) DEFAULT NULL,
  `socios` int(11) DEFAULT NULL,
  `fundacion` datetime DEFAULT NULL,
  `desaparicion` date DEFAULT NULL,
  `estadio` varchar(191) DEFAULT NULL,
  `escudo` varchar(191) DEFAULT NULL,
  `historia` text DEFAULT NULL,
  `historia_en` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `pais` varchar(191) NOT NULL DEFAULT 'Argentina',
  `url_nombre` varchar(191) DEFAULT NULL,
  `url_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `equipos_excluidos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `equipos_excluidos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `equipos_excluidos_nombre_index` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fechas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fechas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `numero` varchar(191) NOT NULL,
  `grupo_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `url_nombre` varchar(255) DEFAULT NULL,
  `sofa_slug` varchar(50) DEFAULT NULL,
  `orden` int(11) DEFAULT NULL,
  `penales_importados` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero_grupo_id` (`numero`,`grupo_id`),
  KEY `fechas_grupo_id_foreign` (`grupo_id`),
  CONSTRAINT `fechas_grupo_id_foreign` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gols`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gols` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `partido_id` bigint(20) unsigned NOT NULL,
  `jugador_id` bigint(20) unsigned NOT NULL,
  `minuto` int(11) DEFAULT NULL,
  `adicionado` tinyint(3) unsigned DEFAULT NULL,
  `tipo` enum('Cabeza','En Contra','Jugada','Penal','Tiro Libre','Olímpico') NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `gols_partido_id_foreign` (`partido_id`),
  KEY `gols_jugador_id_foreign` (`jugador_id`),
  CONSTRAINT `gols_jugador_id_foreign` FOREIGN KEY (`jugador_id`) REFERENCES `jugadors` (`id`),
  CONSTRAINT `gols_partido_id_foreign` FOREIGN KEY (`partido_id`) REFERENCES `partidos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `grupos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `grupos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(191) NOT NULL,
  `torneo_id` bigint(20) unsigned NOT NULL,
  `equipos` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `posiciones` tinyint(1) NOT NULL DEFAULT 1,
  `promedios` tinyint(1) NOT NULL DEFAULT 1,
  `agrupacion` int(11) NOT NULL DEFAULT 1,
  `penales` tinyint(1) NOT NULL DEFAULT 0,
  `acumulado` int(11) NOT NULL DEFAULT 1,
  `clasificados` int(11) DEFAULT NULL,
  `goles_importados` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `grupos_torneo_id_foreign` (`torneo_id`),
  CONSTRAINT `grupos_torneo_id_foreign` FOREIGN KEY (`torneo_id`) REFERENCES `torneos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `import_partidos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `import_partidos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `fuente` varchar(30) NOT NULL DEFAULT 'transfermarkt',
  `external_id` varchar(40) DEFAULT NULL,
  `tecnico_id` bigint(20) unsigned DEFAULT NULL,
  `coach_external_id` varchar(40) DEFAULT NULL,
  `competencia_external_id` varchar(40) DEFAULT NULL,
  `competencia_nombre` varchar(191) DEFAULT NULL,
  `temporada` varchar(20) DEFAULT NULL,
  `ronda` varchar(100) DEFAULT NULL,
  `club_external_id` varchar(40) DEFAULT NULL,
  `club_nombre` varchar(191) DEFAULT NULL,
  `rival_external_id` varchar(40) DEFAULT NULL,
  `rival_nombre` varchar(191) DEFAULT NULL,
  `local` tinyint(1) DEFAULT NULL,
  `dia` datetime DEFAULT NULL,
  `goles_favor` int(11) DEFAULT NULL,
  `goles_contra` int(11) DEFAULT NULL,
  `equipo_id` bigint(20) unsigned DEFAULT NULL,
  `rival_id` bigint(20) unsigned DEFAULT NULL,
  `partido_id` bigint(20) unsigned DEFAULT NULL,
  `clubes_mal_at` timestamp NULL DEFAULT NULL,
  `sin_detalle_at` timestamp NULL DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'nuevo',
  `penales_revisado_at` timestamp NULL DEFAULT NULL,
  `motivo` text DEFAULT NULL,
  `payload` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `tipos_gol_revisado_at` datetime DEFAULT NULL,
  `minutos_revisado_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_partido_gameid` (`partido_id`,`external_id`),
  KEY `import_partidos_tecnico_id_estado_index` (`tecnico_id`,`estado`),
  KEY `import_partidos_fuente_external_id_index` (`fuente`,`external_id`),
  KEY `import_partidos_dia_index` (`dia`),
  KEY `import_partidos_penales_revisado_idx` (`penales_revisado_at`),
  KEY `import_partidos_partido_id_index` (`partido_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `incidencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `incidencias` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `torneo_id` bigint(20) unsigned DEFAULT NULL,
  `equipo_id` bigint(20) unsigned DEFAULT NULL,
  `partido_id` bigint(20) unsigned DEFAULT NULL,
  `puntos` int(11) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `observaciones_en` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `incidencias_torneo_id_foreign` (`torneo_id`),
  KEY `incidencias_equipo_id_foreign` (`equipo_id`),
  KEY `incidencias_partido_id_foreign` (`partido_id`),
  CONSTRAINT `incidencias_equipo_id_foreign` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`),
  CONSTRAINT `incidencias_partido_id_foreign` FOREIGN KEY (`partido_id`) REFERENCES `partidos` (`id`),
  CONSTRAINT `incidencias_torneo_id_foreign` FOREIGN KEY (`torneo_id`) REFERENCES `torneos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jugador_estadistica_manuals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jugador_estadistica_manuals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `jugador_id` bigint(20) unsigned NOT NULL,
  `equipo_id` bigint(20) unsigned DEFAULT NULL,
  `torneo_nombre` varchar(191) DEFAULT NULL,
  `torneo_logo` varchar(191) DEFAULT NULL,
  `tipo` enum('Liga','Copa') DEFAULT NULL,
  `ambito` enum('Nacional','Internacional') DEFAULT NULL,
  `partidos` int(11) DEFAULT 0,
  `posicion` int(11) DEFAULT 0,
  `goles_cabeza` int(11) DEFAULT 0,
  `goles_penal` int(11) DEFAULT 0,
  `goles_tiro_libre` int(11) DEFAULT 0,
  `goles_jugada` int(11) DEFAULT 0,
  `goles_olimpico` int(11) NOT NULL DEFAULT 0,
  `goles_en_contra` int(11) DEFAULT 0,
  `amarillas` int(11) DEFAULT 0,
  `rojas` int(11) DEFAULT 0,
  `penales_errados` int(11) DEFAULT 0,
  `penales_atajados` int(11) DEFAULT 0,
  `goles_recibidos` int(11) DEFAULT 0,
  `vallas_invictas` int(11) DEFAULT 0,
  `penales_atajo` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `jugador_equipo_torneo_unique` (`jugador_id`,`equipo_id`,`torneo_nombre`),
  KEY `jugador_estadistica_manuals_equipo_id_foreign` (`equipo_id`),
  CONSTRAINT `jugador_estadistica_manuals_equipo_id_foreign` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jugador_estadistica_manuals_jugador_id_foreign` FOREIGN KEY (`jugador_id`) REFERENCES `jugadors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jugador_tm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jugador_tm` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tm_player_id` varchar(40) NOT NULL,
  `jugador_id` bigint(20) unsigned NOT NULL,
  `nombre_tm` varchar(191) DEFAULT NULL,
  `origen` varchar(20) NOT NULL DEFAULT 'auto',
  `revisar` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `jugador_tm_tm_player_id_unique` (`tm_player_id`),
  KEY `jugador_tm_jugador_id_index` (`jugador_id`),
  KEY `jugador_tm_revisar_origen_index` (`revisar`,`origen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jugadors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jugadors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tipoJugador` enum('Arquero','Defensor','Medio','Delantero') NOT NULL,
  `pie` enum('Derecha','Izquierda','Ambas') DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `persona_id` bigint(20) unsigned NOT NULL,
  `url_nombre` varchar(255) DEFAULT NULL,
  `transfermarkt_url` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `persona_id` (`persona_id`),
  CONSTRAINT `FK1_persona` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(191) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `partido_arbitros`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `partido_arbitros` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `partido_id` bigint(20) unsigned NOT NULL,
  `arbitro_id` bigint(20) unsigned NOT NULL,
  `tipo` enum('Principal','Linea 1','Linea 2','Cuarto','VAR') NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `partido_id_arbitro_id` (`partido_id`,`arbitro_id`),
  KEY `partido_arbitros_arbitro_id_foreign` (`arbitro_id`),
  CONSTRAINT `partido_arbitros_arbitro_id_foreign` FOREIGN KEY (`arbitro_id`) REFERENCES `arbitros` (`id`),
  CONSTRAINT `partido_arbitros_partido_id_foreign` FOREIGN KEY (`partido_id`) REFERENCES `partidos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `partido_tecnicos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `partido_tecnicos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `partido_id` bigint(20) unsigned NOT NULL,
  `equipo_id` bigint(20) unsigned NOT NULL,
  `tecnico_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `partido_tecnicos_partido_id_foreign` (`partido_id`),
  KEY `partido_tecnicos_equipo_id_foreign` (`equipo_id`),
  KEY `partido_tecnicos_tecnico_id_foreign` (`tecnico_id`),
  CONSTRAINT `partido_tecnicos_equipo_id_foreign` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`),
  CONSTRAINT `partido_tecnicos_partido_id_foreign` FOREIGN KEY (`partido_id`) REFERENCES `partidos` (`id`),
  CONSTRAINT `partido_tecnicos_tecnico_id_foreign` FOREIGN KEY (`tecnico_id`) REFERENCES `tecnicos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `partidos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `partidos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `dia` timestamp NULL DEFAULT NULL,
  `fecha_id` bigint(20) unsigned NOT NULL,
  `orden` int(11) unsigned DEFAULT 0,
  `equipol_id` bigint(20) unsigned DEFAULT NULL,
  `equipov_id` bigint(20) unsigned DEFAULT NULL,
  `golesl` int(11) DEFAULT NULL,
  `golesv` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `penalesl` int(11) DEFAULT NULL,
  `penalesv` int(11) DEFAULT NULL,
  `neutral` tinyint(1) DEFAULT 0,
  `bloquear` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fecha_id_equipol_id` (`fecha_id`,`equipol_id`),
  UNIQUE KEY `fecha_id_equipov_id` (`fecha_id`,`equipov_id`),
  KEY `partidos_equipol_id_foreign` (`equipol_id`),
  KEY `partidos_equipov_id_foreign` (`equipov_id`),
  KEY `partidos_dia` (`dia`),
  KEY `partidos_equipol_dia` (`equipol_id`,`dia`),
  KEY `partidos_equipov_dia` (`equipov_id`,`dia`),
  CONSTRAINT `partidos_equipol_id_foreign` FOREIGN KEY (`equipol_id`) REFERENCES `equipos` (`id`),
  CONSTRAINT `partidos_equipov_id_foreign` FOREIGN KEY (`equipov_id`) REFERENCES `equipos` (`id`),
  CONSTRAINT `partidos_fecha_id_foreign` FOREIGN KEY (`fecha_id`) REFERENCES `fechas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `email` varchar(191) NOT NULL,
  `token` varchar(191) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `penals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `penals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `partido_id` bigint(20) unsigned NOT NULL,
  `jugador_id` bigint(20) unsigned NOT NULL,
  `minuto` int(11) DEFAULT NULL,
  `adicionado` tinyint(3) unsigned DEFAULT NULL,
  `tipo` enum('Errado','Atajado','Atajó','Convirtieron') NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `penals_partido_id_foreign` (`partido_id`),
  KEY `penals_jugador_id_foreign` (`jugador_id`),
  KEY `penals_jugador_tipo` (`jugador_id`,`tipo`),
  CONSTRAINT `penals_jugador_id_foreign` FOREIGN KEY (`jugador_id`) REFERENCES `jugadors` (`id`),
  CONSTRAINT `penals_partido_id_foreign` FOREIGN KEY (`partido_id`) REFERENCES `partidos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `persona_duplicados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `persona_duplicados` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `persona_id` bigint(20) unsigned NOT NULL,
  `simil_id` bigint(20) unsigned NOT NULL,
  `puntaje` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `motivo` varchar(150) DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'pendiente',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `persona_duplicados_par_uk` (`persona_id`,`simil_id`),
  KEY `persona_duplicados_estado_idx` (`estado`,`puntaje`),
  KEY `persona_duplicados_simil_idx` (`simil_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `persona_fecha_tm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `persona_fecha_tm` (
  `persona_id` bigint(20) unsigned NOT NULL,
  `tm_id` varchar(40) DEFAULT NULL,
  `fuente` varchar(20) NOT NULL DEFAULT 'api',
  `estado` varchar(20) NOT NULL DEFAULT 'sin_fecha',
  `intentos` int(10) unsigned NOT NULL DEFAULT 1,
  `consultado_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`persona_id`),
  KEY `persona_fecha_tm_estado_index` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `persona_fusiones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `persona_fusiones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `persona_id` bigint(20) unsigned NOT NULL,
  `absorbida_id` bigint(20) unsigned NOT NULL,
  `absorbida_nombre` varchar(191) DEFAULT NULL,
  `detalle` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `persona_fusiones_persona_idx` (`persona_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `persona_sin_fecha`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `persona_sin_fecha` (
  `persona_id` bigint(20) unsigned NOT NULL,
  `motivo` varchar(200) DEFAULT NULL COMMENT 'donde se busco y no estaba',
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`persona_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `persona_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `persona_tokens` (
  `persona_id` bigint(20) unsigned NOT NULL,
  `token` varchar(64) NOT NULL,
  `campo` char(1) NOT NULL DEFAULT 'a',
  PRIMARY KEY (`persona_id`,`token`,`campo`),
  KEY `persona_tokens_token_idx` (`token`,`campo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `personas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(191) DEFAULT NULL,
  `apellido` varchar(191) DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `telefono` varchar(191) DEFAULT NULL,
  `ciudad` varchar(191) DEFAULT NULL,
  `altura` double(8,2) DEFAULT NULL,
  `peso` double(8,2) DEFAULT NULL,
  `foto` text DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `observaciones_en` text DEFAULT NULL,
  `tipoDocumento` enum('DNI','PAS','CI') DEFAULT NULL,
  `documento` varchar(191) DEFAULT NULL,
  `nacimiento` datetime DEFAULT NULL,
  `fallecimiento` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `nacionalidad` varchar(191) DEFAULT 'Argentina',
  `verificado` tinyint(4) DEFAULT 0,
  `name` varchar(255) DEFAULT NULL,
  `clave_norm` varchar(191) DEFAULT NULL,
  `clave_orden` varchar(191) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre_apellido_nacimiento` (`nombre`,`apellido`,`nacimiento`),
  KEY `personas_clave_norm_idx` (`clave_norm`),
  KEY `personas_clave_orden_idx` (`clave_orden`),
  KEY `personas_nacionalidad_idx` (`nacionalidad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personas_verificadas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `personas_verificadas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `persona_id` bigint(20) unsigned NOT NULL,
  `simil_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `personas_verificadas_persona_id_foreign` (`persona_id`) USING BTREE,
  KEY `personas_verificadas_simil_id_foreign` (`simil_id`) USING BTREE,
  CONSTRAINT `personas_verificadas_persona_id_foreign` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `personas_verificadas_simil_id_foreign` FOREIGN KEY (`simil_id`) REFERENCES `personas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `plantilla_jugadors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `plantilla_jugadors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `plantilla_id` bigint(20) unsigned NOT NULL,
  `jugador_id` bigint(20) unsigned NOT NULL,
  `dorsal` int(11) DEFAULT NULL,
  `foto` text DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `plantilla_id_jugador_id` (`plantilla_id`,`jugador_id`),
  UNIQUE KEY `plantilla_id_dorsal` (`plantilla_id`,`dorsal`),
  KEY `plantilla_jugadors_jugador_id_foreign` (`jugador_id`),
  CONSTRAINT `plantilla_jugadors_jugador_id_foreign` FOREIGN KEY (`jugador_id`) REFERENCES `jugadors` (`id`),
  CONSTRAINT `plantilla_jugadors_plantilla_id_foreign` FOREIGN KEY (`plantilla_id`) REFERENCES `plantillas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `plantillas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `plantillas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `equipo_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `grupo_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `plantillas_equipo_id_foreign` (`equipo_id`),
  KEY `plantillas_grupo_id_foreign` (`grupo_id`),
  CONSTRAINT `plantillas_equipo_id_foreign` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`),
  CONSTRAINT `plantillas_grupo_id_foreign` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `posicion_torneos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `posicion_torneos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `torneo_id` bigint(20) unsigned NOT NULL,
  `equipo_id` bigint(20) unsigned NOT NULL,
  `posicion` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `torneo_id_equipo_id` (`torneo_id`,`equipo_id`),
  KEY `posicion_torneos_equipo_id_foreign` (`equipo_id`),
  CONSTRAINT `posicion_torneos_equipo_id_foreign` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`),
  CONSTRAINT `posicion_torneos_torneo_id_foreign` FOREIGN KEY (`torneo_id`) REFERENCES `torneos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `promedio_torneos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `promedio_torneos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `torneo_id` bigint(20) unsigned NOT NULL,
  `torneoAnterior_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `torneo_id_torneoAnterior_id` (`torneo_id`,`torneoAnterior_id`),
  KEY `promedio_torneos_torneoanterior_id_foreign` (`torneoAnterior_id`),
  CONSTRAINT `promedio_torneos_torneo_id_foreign` FOREIGN KEY (`torneo_id`) REFERENCES `torneos` (`id`),
  CONSTRAINT `promedio_torneos_torneoanterior_id_foreign` FOREIGN KEY (`torneoAnterior_id`) REFERENCES `torneos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tarjetas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tarjetas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `partido_id` bigint(20) unsigned NOT NULL,
  `jugador_id` bigint(20) unsigned NOT NULL,
  `minuto` int(11) DEFAULT NULL,
  `adicionado` tinyint(3) unsigned DEFAULT NULL,
  `tipo` enum('Amarilla','Doble Amarilla','Roja') NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tarjetas_partido_id_foreign` (`partido_id`),
  KEY `tarjetas_jugador_id_foreign` (`jugador_id`),
  KEY `tarjetas_jugador_tipo` (`jugador_id`,`tipo`),
  CONSTRAINT `tarjetas_jugador_id_foreign` FOREIGN KEY (`jugador_id`) REFERENCES `jugadors` (`id`),
  CONSTRAINT `tarjetas_partido_id_foreign` FOREIGN KEY (`partido_id`) REFERENCES `partidos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tecnico_estadistica_manuals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tecnico_estadistica_manuals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tecnico_id` bigint(20) unsigned NOT NULL,
  `equipo_id` bigint(20) unsigned NOT NULL,
  `torneo_nombre` varchar(191) DEFAULT NULL,
  `torneo_logo` varchar(191) DEFAULT NULL,
  `tipo` enum('Liga','Copa') DEFAULT NULL,
  `ambito` enum('Nacional','Internacional') DEFAULT 'Nacional',
  `partidos` int(11) DEFAULT 0,
  `posicion` int(11) DEFAULT 0,
  `ganados` int(11) DEFAULT 0,
  `empatados` int(11) DEFAULT 0,
  `perdidos` int(11) DEFAULT 0,
  `goles_favor` int(11) DEFAULT 0,
  `goles_en_contra` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tecnico_estadistica_manuals_equipo_id_foreign` (`equipo_id`),
  KEY `tecnico_estadistica_manuals_jugador_id_foreign` (`tecnico_id`) USING BTREE,
  CONSTRAINT `tecnico_estadistica_manuals_equipo_id_foreign` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tecnico_estadistica_manuals_tecnico_id_foreign` FOREIGN KEY (`tecnico_id`) REFERENCES `tecnicos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tecnico_sondeos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tecnico_sondeos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tecnico_id` bigint(20) unsigned NOT NULL,
  `sondeado_at` datetime DEFAULT NULL,
  `partidos` int(11) NOT NULL DEFAULT 0,
  `fuera_1ra` int(11) NOT NULL DEFAULT 0,
  `fuera_alcance` int(11) NOT NULL DEFAULT 0,
  `duplicados` int(11) NOT NULL DEFAULT 0,
  `nuevos` int(11) NOT NULL DEFAULT 0,
  `conflictos` int(11) NOT NULL DEFAULT 0,
  `guardadas` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `fuera_detalle` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tecnico_sondeos_tecnico_id_unique` (`tecnico_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tecnicos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tecnicos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `persona_id` bigint(20) unsigned NOT NULL,
  `transfermarkt_url` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `persona_id` (`persona_id`),
  CONSTRAINT `FK1_personas` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `titulo_torneos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `titulo_torneos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `titulo_id` bigint(20) unsigned NOT NULL,
  `torneo_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `titulo_torneos_titulo_id_foreign` (`titulo_id`),
  KEY `titulo_torneos_torneo_id_foreign` (`torneo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `titulos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `titulos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(191) NOT NULL,
  `equipo_id` bigint(20) unsigned NOT NULL,
  `year` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `tipo` enum('Liga','Copa') NOT NULL,
  `ambito` enum('Nacional','Internacional') NOT NULL DEFAULT 'Nacional',
  PRIMARY KEY (`id`),
  KEY `titulos_equipo_id_foreign` (`equipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `torneo_clasificacions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `torneo_clasificacions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `torneo_id` bigint(20) unsigned NOT NULL,
  `nombre` varchar(191) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `torneo_clasificacions_torneo_id_foreign` (`torneo_id`),
  CONSTRAINT `torneo_clasificacions_torneo_id_foreign` FOREIGN KEY (`torneo_id`) REFERENCES `torneos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `torneos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `torneos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(191) NOT NULL,
  `year` varchar(191) NOT NULL,
  `equipos` int(11) NOT NULL,
  `grupos` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `tipo` enum('Liga','Copa') NOT NULL,
  `ambito` enum('Nacional','Internacional') NOT NULL DEFAULT 'Nacional',
  `url_nombre` varchar(255) DEFAULT NULL,
  `escudo` varchar(255) DEFAULT NULL,
  `neutral` tinyint(1) DEFAULT 0,
  `descenso` int(11) DEFAULT NULL,
  `descenso_promedio` int(11) DEFAULT NULL,
  `region` varchar(50) DEFAULT NULL,
  `sofa_tournament_id` bigint(20) DEFAULT NULL,
  `sofa_season_id` bigint(20) DEFAULT NULL,
  `sofa_slug` varchar(191) DEFAULT NULL,
  `sofa_category_id` bigint(20) DEFAULT NULL,
  `sofa_category_slug` varchar(191) DEFAULT NULL,
  `tm_competition_id` varchar(20) DEFAULT NULL,
  `tm_season_id` varchar(10) DEFAULT NULL,
  `goles_importados` tinyint(1) DEFAULT 0,
  `parcial` tinyint(1) NOT NULL DEFAULT 0,
  `inconcluso` tinyint(1) NOT NULL DEFAULT 0,
  `pais` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `email` varchar(191) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(191) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

