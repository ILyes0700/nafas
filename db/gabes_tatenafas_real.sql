-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : jeu. 11 juin 2026 à 12:27
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `gabes_tatenafas`
--

-- --------------------------------------------------------

--
-- Structure de la table `alerts`
--

CREATE TABLE `alerts` (
  `id` int(11) NOT NULL,
  `zone_id` int(11) DEFAULT NULL,
  `title` varchar(160) NOT NULL,
  `message` text DEFAULT NULL,
  `severity` enum('info','warning','danger','critical') DEFAULT 'info',
  `type` varchar(60) DEFAULT 'pollution',
  `priority_groups` varchar(120) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `resolved` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `alerts`
--

INSERT INTO `alerts` (`id`, `zone_id`, `title`, `message`, `severity`, `type`, `priority_groups`, `created_at`, `resolved`) VALUES
(1, 3, 'Pic de SO₂ détecté', 'Niveau de dioxyde de soufre élevé à Ghannouch. Évitez les efforts en plein air.', 'critical', 'pollution', NULL, '2026-05-13 18:00:15', 0),
(2, 2, 'Vigilance respiratoire', 'Augmentation des particules fines à Chott Essalem.', 'danger', 'pollution', NULL, '2026-05-13 15:00:15', 0),
(3, 1, 'Qualité de l\'air dégradée', 'Centre-ville: indice modéré, surveillance recommandée.', 'warning', 'pollution', NULL, '2026-05-13 11:00:15', 0),
(4, 4, 'Odeur signalée', 'Plusieurs signalements d\'odeur de soufre à Mtorrech.', 'warning', 'odor', NULL, '2026-05-12 20:00:15', 0),
(5, 3, 'Mode école activé', 'Recommandation: suspension activités plein air écoles Ghannouch.', 'danger', 'school', NULL, '2026-05-13 17:00:15', 0),
(6, 7, 'Fumée signalée', 'Fumée visible signalée par citoyens à Bou Chemma.', 'warning', 'smoke', NULL, '2026-05-13 14:00:15', 0),
(7, 2, '[AUTO:school] School closed — École Chott Essalem', 'École Chott Essalem (Chatt Salem) is suspending activities due to environmental risk. Parents and staff: shelter-in-place guidance.', 'danger', 'school', NULL, '2026-06-01 21:45:31', 0),
(8, 2, '[AUTO:school] Reopening — École Chott Essalem', 'École Chott Essalem (Chatt Salem) is resuming normal activities. The zone is no longer at critical threshold.', 'info', 'school', NULL, '2026-06-01 21:45:35', 0),
(9, 3, '[AUTO:school] Watch — École primaire Ghannouch 1', 'École primaire Ghannouch 1 (Ghannouche) under heightened watch. Limit intense activities.', 'warning', 'school', NULL, '2026-06-01 21:45:40', 0),
(10, 2, '[AUTO:school] Watch — École Chott Essalem', 'École Chott Essalem (Chatt Salem) under heightened watch. Limit intense activities.', 'warning', 'school', NULL, '2026-06-01 21:45:42', 0);

-- --------------------------------------------------------

--
-- Structure de la table `api_config`
--

CREATE TABLE `api_config` (
  `id` int(11) NOT NULL,
  `api_name` varchar(50) DEFAULT NULL,
  `api_key` varchar(200) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `daily_calls_used` int(11) DEFAULT 0,
  `daily_limit` int(11) DEFAULT NULL,
  `last_reset` date DEFAULT NULL,
  `last_success` datetime DEFAULT NULL,
  `avg_response_time` float DEFAULT NULL,
  `success_rate` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `api_config`
--

INSERT INTO `api_config` (`id`, `api_name`, `api_key`, `is_active`, `daily_calls_used`, `daily_limit`, `last_reset`, `last_success`, `avg_response_time`, `success_rate`) VALUES
(1, 'accuweather', 'SET_IN_backend/config/accuweather.php', 1, 0, 50, '2026-06-09', NULL, 0, 100),
(2, 'iqair', 'SET_IN_backend/config/iqair.php', 1, 0, 10000, '2026-06-09', NULL, 0, 100),
(3, 'waqi', 'SET_IN_backend/config/waqi.php', 1, 0, 1000, '2026-06-09', NULL, 0, 100);

-- --------------------------------------------------------

--
-- Structure de la table `api_readings`
--

CREATE TABLE `api_readings` (
  `id` int(11) NOT NULL,
  `city_id` varchar(50) NOT NULL,
  `city_name` varchar(100) DEFAULT NULL,
  `timestamp` datetime NOT NULL,
  `final_aqi` float DEFAULT NULL,
  `final_category` varchar(50) DEFAULT NULL,
  `final_pm25` float DEFAULT NULL,
  `final_pm10` float DEFAULT NULL,
  `final_no2` float DEFAULT NULL,
  `final_so2` float DEFAULT NULL,
  `final_o3` float DEFAULT NULL,
  `final_co` float DEFAULT NULL,
  `final_temperature` float DEFAULT NULL,
  `final_humidity` float DEFAULT NULL,
  `final_wind_speed` float DEFAULT NULL,
  `final_wind_direction` float DEFAULT NULL,
  `final_pressure` float DEFAULT NULL,
  `accuw_aqi` float DEFAULT NULL,
  `accuw_category` varchar(50) DEFAULT NULL,
  `accuw_pm25` float DEFAULT NULL,
  `accuw_pm10` float DEFAULT NULL,
  `accuw_no2` float DEFAULT NULL,
  `accuw_so2` float DEFAULT NULL,
  `accuw_o3` float DEFAULT NULL,
  `accuw_co` float DEFAULT NULL,
  `accuw_temp` float DEFAULT NULL,
  `accuw_feels_like` float DEFAULT NULL,
  `accuw_humidity` float DEFAULT NULL,
  `accuw_wind_speed` float DEFAULT NULL,
  `accuw_wind_dir` float DEFAULT NULL,
  `accuw_pressure` float DEFAULT NULL,
  `accuw_visibility` float DEFAULT NULL,
  `accuw_uv_index` float DEFAULT NULL,
  `accuw_cloud_cover` float DEFAULT NULL,
  `accuw_dew_point` float DEFAULT NULL,
  `accuw_weather_text` varchar(100) DEFAULT NULL,
  `accuw_available` tinyint(1) DEFAULT 0,
  `accuw_forecast_1h` float DEFAULT NULL,
  `accuw_forecast_3h` float DEFAULT NULL,
  `accuw_forecast_6h` float DEFAULT NULL,
  `accuw_forecast_12h` float DEFAULT NULL,
  `accuw_forecast_temp_max` float DEFAULT NULL,
  `accuw_forecast_wind_max` float DEFAULT NULL,
  `iqair_aqi_us` float DEFAULT NULL,
  `iqair_aqi_cn` float DEFAULT NULL,
  `iqair_main_pollutant` varchar(20) DEFAULT NULL,
  `iqair_pm25` float DEFAULT NULL,
  `iqair_pm10` float DEFAULT NULL,
  `iqair_temp` float DEFAULT NULL,
  `iqair_humidity` float DEFAULT NULL,
  `iqair_wind_speed` float DEFAULT NULL,
  `iqair_wind_dir` float DEFAULT NULL,
  `iqair_pressure` float DEFAULT NULL,
  `iqair_available` tinyint(1) DEFAULT 0,
  `waqi_aqi` float DEFAULT NULL,
  `waqi_pm25` float DEFAULT NULL,
  `waqi_pm10` float DEFAULT NULL,
  `waqi_no2` float DEFAULT NULL,
  `waqi_so2` float DEFAULT NULL,
  `waqi_o3` float DEFAULT NULL,
  `waqi_co` float DEFAULT NULL,
  `waqi_temp` float DEFAULT NULL,
  `waqi_humidity` float DEFAULT NULL,
  `waqi_wind` float DEFAULT NULL,
  `waqi_available` tinyint(1) DEFAULT 0,
  `data_quality_score` float DEFAULT NULL,
  `fusion_method` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `api_readings`
--

INSERT INTO `api_readings` (`id`, `city_id`, `city_name`, `timestamp`, `final_aqi`, `final_category`, `final_pm25`, `final_pm10`, `final_no2`, `final_so2`, `final_o3`, `final_co`, `final_temperature`, `final_humidity`, `final_wind_speed`, `final_wind_direction`, `final_pressure`, `accuw_aqi`, `accuw_category`, `accuw_pm25`, `accuw_pm10`, `accuw_no2`, `accuw_so2`, `accuw_o3`, `accuw_co`, `accuw_temp`, `accuw_feels_like`, `accuw_humidity`, `accuw_wind_speed`, `accuw_wind_dir`, `accuw_pressure`, `accuw_visibility`, `accuw_uv_index`, `accuw_cloud_cover`, `accuw_dew_point`, `accuw_weather_text`, `accuw_available`, `accuw_forecast_1h`, `accuw_forecast_3h`, `accuw_forecast_6h`, `accuw_forecast_12h`, `accuw_forecast_temp_max`, `accuw_forecast_wind_max`, `iqair_aqi_us`, `iqair_aqi_cn`, `iqair_main_pollutant`, `iqair_pm25`, `iqair_pm10`, `iqair_temp`, `iqair_humidity`, `iqair_wind_speed`, `iqair_wind_dir`, `iqair_pressure`, `iqair_available`, `waqi_aqi`, `waqi_pm25`, `waqi_pm10`, `waqi_no2`, `waqi_so2`, `waqi_o3`, `waqi_co`, `waqi_temp`, `waqi_humidity`, `waqi_wind`, `waqi_available`, `data_quality_score`, `fusion_method`) VALUES
(1, '1', 'Centre Ville', '2026-06-09 15:29:39', 95, 'Modéré', 26.43, 42.3, 25.27, 22.65, 58.42, 1.27, 27.55, 53.8, 15.9, 246, 1008.7, 80, 'Modéré', 26, 41.6, 25.1, 22.5, 58.1, 1.26, 27.5, 26.5, 53, 17.2, 266, 1008, 14.8, 6, 6, 18.1, 'Ensoleillé', 1, 80, 73, 73, 60, 30.5, 23.2, 56, 17, 'p2', NULL, NULL, 28, 61, 4.17, 66, 1015, 1, 86, 28.6, 45.8, 26.1, 23.4, 60, 1.33, 27.5, 53, 17.2, 1, 1, 'weighted_3api'),
(2, '2', 'Chatt Salem', '2026-06-09 15:29:43', 127, 'Mauvais SGS', 25.6, 40.98, 33.92, 57.72, 57.77, 1.26, 27.55, 53.8, 22.11, 61.5, 1008.7, 80, 'Modéré', 25.8, 41.3, 34, 57.9, 57.9, 1.26, 27.5, 26.5, 53, 24.1, 61, 1008, 14.8, 6, 4, 18.1, 'Ensoleillé', 1, 80, 73, 73, 60, 30.5, 30.1, 56, 17, 'p2', NULL, NULL, 28, 61, 4.17, 66, 1015, 1, 77, 24.6, 39.4, 33.5, 56.8, 57.1, 1.23, 27.5, 53, 24.1, 1, 1, 'weighted_3api'),
(3, '3', 'Ghannouche', '2026-06-09 15:29:46', 156, 'Mauvais', 24.45, 39.12, 33.38, 56.65, 56.95, 1.22, 27.55, 53.8, 16.44, 190.2, 1008.7, 77, 'Modéré', 24.5, 39.2, 33.4, 56.7, 57, 1.22, 27.5, 26.5, 53, 17.8, 204, 1008, 14.8, 6, 34, 18.1, 'Ensoleillé', 1, 77, 70, 70, 57, 30.5, 23.8, 56, 17, 'p2', NULL, NULL, 28, 61, 4.17, 66, 1015, 1, 76, 24.2, 38.7, 33.3, 56.4, 56.7, 1.22, 27.5, 53, 17.8, 1, 1, 'weighted_3api'),
(4, '4', 'Chenini', '2026-06-09 15:29:49', 52, 'Modéré', 26.13, 41.78, 25.1, 22.48, 58.18, 1.27, 27.55, 53.8, 23.01, 216.3, 1008.7, 80, 'Modéré', 25.9, 41.4, 25, 22.4, 58, 1.26, 27.5, 26.5, 53, 25.1, 233, 1008, 14.8, 6, 14, 18.1, 'Ensoleillé', 1, 80, 73, 73, 60, 30.5, 31.1, 56, 17, 'p2', NULL, NULL, 28, 61, 4.17, 66, 1015, 1, 83, 27.3, 43.7, 25.6, 22.9, 59.1, 1.3, 27.5, 53, 25.1, 1, 1, 'weighted_3api'),
(5, '5', 'El Bled', '2026-06-09 15:29:52', 82, 'Modéré', 24.05, 38.48, 24.22, 21.72, 56.63, 1.21, 27.55, 53.8, 8.07, 18.3, 1008.7, 76, 'Modéré', 24, 38.4, 24.2, 21.7, 56.6, 1.21, 27.5, 26.5, 53, 8.5, 13, 1008, 14.8, 6, 13, 18.1, 'Ensoleillé', 1, 76, 69, 69, 57, 30.5, 14.5, 56, 17, 'p2', NULL, NULL, 28, 61, 4.17, 66, 1015, 1, 77, 24.3, 38.9, 24.3, 21.8, 56.8, 1.22, 27.5, 53, 8.5, 1, 1, 'weighted_3api'),
(6, '6', 'Bouchamma', '2026-06-09 15:29:55', 69, 'Modéré', 25.38, 40.57, 24.78, 22.19, 57.58, 1.25, 27.5, 53, 11.7, 257, 1008, 79, 'Modéré', 25.4, 40.6, 24.8, 22.2, 57.6, 1.25, 27.5, 26.5, 53, 11.7, 257, 1008, 14.8, 6, 46, 18.1, 'Partiellement nuageux', 1, 79, 72, 72, 59, 30.5, 17.7, 75, 69, 'p2', 23.5, 37.6, 27.5, 53, 11.7, 257, 1008, 1, 81, 26.5, 42.4, 25.2, 22.6, 58.4, 1.27, 27.5, 53, 11.7, 1, 1, 'weighted_3api'),
(7, '4', 'Chenini', '2026-06-09 15:34:50', 52, 'Modéré', 26.13, 41.78, 25.1, 22.48, 58.18, 1.27, 27.55, 53.8, 23.01, 216.3, 1008.7, 80, 'Modéré', 25.9, 41.4, 25, 22.4, 58, 1.26, 27.5, 26.5, 53, 25.1, 233, 1008, 14.8, 6, 14, 18.1, 'Ensoleillé', 1, 80, 73, 73, 60, 30.5, 31.1, 56, 17, 'p2', NULL, NULL, 28, 61, 4.17, 66, 1015, 1, 83, 27.3, 43.7, 25.6, 22.9, 59.1, 1.3, 27.5, 53, 25.1, 1, 1, 'weighted_3api'),
(8, '6', 'Bouchamma', '2026-06-09 15:35:10', 68, 'Modéré', 25.58, 40.9, 24.87, 22.27, 57.73, 1.25, 27.55, 53.8, 10.95, 237.9, 1008.7, 79, 'Modéré', 25.4, 40.6, 24.8, 22.2, 57.6, 1.25, 27.5, 26.5, 53, 11.7, 257, 1008, 14.8, 6, 46, 18.1, 'Partiellement nuageux', 1, 79, 72, 72, 59, 30.5, 17.7, 56, 17, 'p2', NULL, NULL, 28, 61, 4.17, 66, 1015, 1, 81, 26.5, 42.4, 25.2, 22.6, 58.4, 1.27, 27.5, 53, 11.7, 1, 1, 'weighted_3api');

-- --------------------------------------------------------

--
-- Structure de la table `api_verification_log`
--

CREATE TABLE `api_verification_log` (
  `id` int(11) NOT NULL,
  `source` varchar(20) NOT NULL,
  `zone_id` int(11) NOT NULL,
  `raw_value` int(11) DEFAULT NULL,
  `normalized_value` int(11) DEFAULT NULL,
  `trust_score` decimal(3,2) NOT NULL DEFAULT 1.00,
  `flags` varchar(120) DEFAULT NULL,
  `verified_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `api_verification_log`
--

INSERT INTO `api_verification_log` (`id`, `source`, `zone_id`, `raw_value`, `normalized_value`, `trust_score`, `flags`, `verified_at`) VALUES
(1, 'iqair', 6, 66, 26, 1.00, 'range_ok', '2026-05-13 22:27:12'),
(2, 'fused', 6, NULL, 26, 1.00, 'fused_ok', '2026-05-13 22:27:19'),
(3, 'fused', 7, NULL, NULL, 0.00, 'no_source', '2026-05-13 22:27:36'),
(4, 'iqair', 8, 66, 26, 1.00, 'range_ok', '2026-05-13 22:27:42'),
(5, 'fused', 8, NULL, 26, 1.00, 'fused_ok', '2026-05-13 22:27:43'),
(6, 'iqair', 1, 59, 24, 1.00, 'range_ok', '2026-05-13 23:07:48'),
(7, 'fused', 1, NULL, 24, 1.00, 'fused_ok', '2026-05-13 23:07:51'),
(8, 'iqair', 2, 59, 24, 1.00, 'range_ok', '2026-05-13 23:07:54'),
(9, 'fused', 2, NULL, 24, 1.00, 'fused_ok', '2026-05-13 23:07:55'),
(10, 'iqair', 3, 59, 24, 1.00, 'range_ok', '2026-05-13 23:08:03'),
(11, 'fused', 3, NULL, 24, 1.00, 'fused_ok', '2026-05-13 23:08:05'),
(12, 'fused', 4, NULL, NULL, 0.00, 'no_source', '2026-05-13 23:08:10'),
(13, 'fused', 5, NULL, NULL, 0.00, 'no_source', '2026-05-13 23:08:15'),
(14, 'fused', 6, NULL, NULL, 0.00, 'no_source', '2026-05-13 23:08:21'),
(15, 'fused', 7, NULL, NULL, 0.00, 'no_source', '2026-05-13 23:08:25'),
(16, 'fused', 8, NULL, NULL, 0.00, 'no_source', '2026-05-13 23:08:31'),
(17, 'iqair', 1, 74, 30, 1.00, 'range_ok', '2026-05-14 12:40:37'),
(18, 'fused', 1, NULL, 30, 1.00, 'fused_ok', '2026-05-14 12:40:41'),
(19, 'iqair', 2, 74, 30, 1.00, 'range_ok', '2026-05-14 12:40:48'),
(20, 'fused', 2, NULL, 30, 1.00, 'fused_ok', '2026-05-14 12:40:56'),
(21, 'iqair', 3, 74, 30, 1.00, 'range_ok', '2026-05-14 12:41:08'),
(22, 'fused', 3, NULL, 30, 1.00, 'fused_ok', '2026-05-14 12:41:13'),
(23, 'fused', 3, NULL, NULL, 0.00, 'no_source', '2026-05-14 12:41:37'),
(24, 'iqair', 4, 74, 30, 1.00, 'range_ok', '2026-05-14 12:41:54'),
(25, 'fused', 4, NULL, 30, 1.00, 'fused_ok', '2026-05-14 12:42:00'),
(26, 'iqair', 1, 74, 30, 1.00, 'range_ok', '2026-05-14 12:42:12'),
(27, 'fused', 1, NULL, 30, 1.00, 'fused_ok', '2026-05-14 12:42:19'),
(28, 'fused', 2, NULL, NULL, 0.00, 'no_source', '2026-05-14 12:42:30'),
(29, 'fused', 3, NULL, NULL, 0.00, 'no_source', '2026-05-14 12:42:41'),
(30, 'iqair', 4, 74, 30, 1.00, 'range_ok', '2026-05-14 12:42:49'),
(31, 'fused', 4, NULL, 30, 1.00, 'fused_ok', '2026-05-14 12:42:52'),
(32, 'fused', 3, NULL, NULL, 0.00, 'no_source', '2026-05-14 12:44:10'),
(33, 'fused', 5, NULL, NULL, 0.00, 'no_source', '2026-05-14 12:44:35'),
(34, 'iqair', 6, 74, 30, 1.00, 'range_ok', '2026-05-14 12:44:44'),
(35, 'fused', 3, NULL, NULL, 0.00, 'no_source', '2026-05-14 13:10:52'),
(36, 'iqair', 6, 74, 30, 1.00, 'range_ok', '2026-05-14 13:11:09'),
(37, 'fused', 6, NULL, 30, 1.00, 'fused_ok', '2026-05-14 13:11:13'),
(38, 'iqair', 1, 74, 30, 1.00, 'range_ok', '2026-05-14 13:11:55'),
(39, 'fused', 1, NULL, 30, 1.00, 'fused_ok', '2026-05-14 13:11:57'),
(40, 'iqair', 2, 74, 30, 1.00, 'range_ok', '2026-05-14 13:12:03'),
(41, 'fused', 2, NULL, 30, 1.00, 'fused_ok', '2026-05-14 13:12:05'),
(42, 'iqair', 3, 74, 30, 1.00, 'range_ok', '2026-05-14 13:12:09'),
(43, 'fused', 3, NULL, 30, 1.00, 'fused_ok', '2026-05-14 13:12:14'),
(44, 'iqair', 4, 74, 30, 1.00, 'range_ok', '2026-05-14 13:12:26'),
(45, 'fused', 4, NULL, 30, 1.00, 'fused_ok', '2026-05-14 13:12:30'),
(46, 'fused', 5, NULL, NULL, 0.00, 'no_source', '2026-05-14 13:12:44'),
(47, 'fused', 6, NULL, NULL, 0.00, 'no_source', '2026-05-14 13:13:07'),
(48, 'fused', 7, NULL, NULL, 0.00, 'no_source', '2026-05-14 13:13:31'),
(49, 'iqair', 8, 74, 30, 1.00, 'range_ok', '2026-05-14 13:13:43'),
(50, 'fused', 8, NULL, 30, 1.00, 'fused_ok', '2026-05-14 13:13:51'),
(51, 'iqair', 3, 55, 22, 1.00, 'range_ok', '2026-05-25 10:45:52'),
(52, 'fused', 3, NULL, 22, 1.00, 'fused_ok', '2026-05-25 10:45:54');

-- --------------------------------------------------------

--
-- Structure de la table `chatbot_logs`
--

CREATE TABLE `chatbot_logs` (
  `id` int(11) NOT NULL,
  `user_label` varchar(120) DEFAULT 'citizen',
  `message` text NOT NULL,
  `response` text DEFAULT NULL,
  `intent` varchar(60) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `chatbot_logs`
--

INSERT INTO `chatbot_logs` (`id`, `user_label`, `message`, `response`, `intent`, `created_at`) VALUES
(1, 'citizen', 'J\'ai mal à la tête et je tousse', 'Vos symptômes peuvent être liés à la pollution actuelle (niveau élevé). Restez à l\'intérieur, hydratez-vous et consultez si ça persiste plus de 24h.', 'symptom_check', '2026-05-13 20:00:15'),
(2, 'citizen', 'Quelle est la qualité de l\'air à Ghannouch ?', 'Ghannouch est actuellement en niveau CRITIQUE. Évitez les activités en plein air.', 'air_query', '2026-05-13 20:00:15'),
(3, 'citizen', 'My eyes are stinging, is it serious?', 'AI service temporarily unavailable. Rephrase your question (health, pollution, school) or check the Help page. For a medical emergency: 190 (SAMU).', 'general|fb:http=0', '2026-05-13 21:51:30'),
(4, 'admin', 'My eyes are stinging, is it serious?', 'Stinging eyes can be uncomfortable. Given the current air quality in Gabès, it\'s possible that pollution is irritating your eyes. I recommend rinsing your eyes with clean water and avoiding rubbing them. If the discomfort persists or is severe, consult an eye doctor or a general practitioner for proper evaluation.', 'groq:llama-3.3-70b-versatile', '2026-05-13 23:12:32'),
(5, 'admin', 'salem', 'As-salamu alaykum. I\'m Nafas, the health and environment assistant for Gabès. How can I help you today?', 'groq:llama-3.3-70b-versatile', '2026-05-13 23:12:47'),
(6, 'admin', 'i am tired today', 'I understand you\'re feeling tired today. Given the current air quality in Gabès, it\'s possible that the pollution might be contributing to your fatigue. I recommend staying hydrated by drinking plenty of water and taking regular breaks to rest. If your tiredness persists or is accompanied by other concerning symptoms like difficulty breathing or chest pain, consider consulting a doctor for proper evaluation.', 'groq:llama-3.3-70b-versatile', '2026-05-13 23:13:03'),
(7, 'admin', 'My eyes are stinging, is it serious?', 'AI service temporarily unavailable. Rephrase your question (health, pollution, school) or check the Help page. For a medical emergency: 190 (SAMU).', 'general|fb:curl=28:Failed to co', '2026-05-14 13:16:30'),
(8, 'citizen', 'My eyes are stinging, is it serious?', 'Eye stinging can be uncomfortable. Given the current air quality in Gabès is relatively safe, it might be due to minor irritation. However, if the stinging persists or is severe, it\'s best to consult an eye doctor to rule out any underlying conditions. If you experience vision changes, increased sensitivity to light, or severe pain, seek medical attention promptly.', 'groq:llama-3.3-70b-versatile', '2026-05-25 10:48:54'),
(9, 'admin', 'ok', 'I\'m نفاس, the health & environment assistant for Gabès. How can I help you today with health, symptoms, or air quality concerns?', 'groq:llama-3.3-70b-versatile', '2026-06-01 21:42:37'),
(10, 'admin', 'ok what the last higt level of poultiont in gabes', 'I am نفاس, the health & environment assistant for Gabès. The current average risk score is 21.7/100, which is relatively safe. However, I don\'t have real-time data on the \"last high level of pollution\" in Gabès. For accurate and up-to-date information, I recommend checking the official air quality indexes or consulting local environmental authorities. If you\'re concerned about pollution-related symptoms, I can offer general guidance on protection and health precautions.', 'groq:llama-3.3-70b-versatile', '2026-06-01 21:43:18'),
(11, 'admin', 'oo', 'I am نفاس, the health & environment assistant for Gabès. I can only answer medical, environmental, or local-alert questions. How can I assist you today?', 'groq:llama-3.3-70b-versatile', '2026-06-01 21:45:03');

-- --------------------------------------------------------

--
-- Structure de la table `daily_tips`
--

CREATE TABLE `daily_tips` (
  `id` int(11) NOT NULL,
  `tip_date` date NOT NULL,
  `language` varchar(10) NOT NULL DEFAULT 'fr',
  `status_at_gen` varchar(20) DEFAULT NULL,
  `tip_text` mediumtext NOT NULL,
  `generated_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `daily_tips`
--

INSERT INTO `daily_tips` (`id`, `tip_date`, `language`, `status_at_gen`, `tip_text`, `generated_at`) VALUES
(1, '2026-05-13', 'en', 'critical', 'Residents in high-risk zones are advised to avoid consuming fish from the Gulf of Gabès for the next 48 hours due to potential industrial pollution.', '2026-05-13 20:24:32'),
(2, '2026-05-14', 'en', 'warning', 'Citizen, given the high urgency level and extreme pollution in Ghannouche, today\'s tip is to consider using a high-efficiency air purifier in your home to minimize exposure to industrial pollutants.', '2026-05-14 12:39:40'),
(3, '2026-05-25', 'en', 'safe', 'Today\'s tip: If you live or work in Ghannouche, avoid using tap water for drinking or cooking until further notice. Use bottled or filtered water instead to minimize exposure to potential pollutants.', '2026-05-25 10:34:08'),
(4, '2026-05-30', 'en', 'safe', 'As your health and environment assistant in Gabès, today\'s tip is: \"Residents in Ghannouche are advised to minimize exposure to outdoor pollution by staying in well-ventilated areas and using air purifiers if available.', '2026-05-30 12:48:55'),
(5, '2026-06-01', 'en', 'safe', 'Be cautious when spending time outdoors in areas around industrial facilities like Ghannouche. Limit exposure to potential pollutants and wear protective gear such as a mask when necessary.', '2026-06-01 21:27:24'),
(6, '2026-06-09', 'en', 'safe', 'Given the high urgency level and extreme pollution, today\'s tip is: When outdoors, always wear a mask rated N95 to minimize your inhalation of polluted air, especially when in areas like Ghannouche with high air quality risks.', '2026-06-09 15:29:37');

-- --------------------------------------------------------

--
-- Structure de la table `forecast_metrics`
--

CREATE TABLE `forecast_metrics` (
  `id` int(11) NOT NULL,
  `model_name` varchar(60) NOT NULL,
  `zone_id` int(11) DEFAULT NULL,
  `mae` decimal(6,3) DEFAULT NULL,
  `rmse` decimal(6,3) DEFAULT NULL,
  `mape` decimal(6,3) DEFAULT NULL,
  `r2` decimal(5,3) DEFAULT NULL,
  `smape` decimal(6,3) DEFAULT NULL,
  `sample_size` int(11) DEFAULT NULL,
  `trained_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `forecast_metrics`
--

INSERT INTO `forecast_metrics` (`id`, `model_name`, `zone_id`, `mae`, `rmse`, `mape`, `r2`, `smape`, `sample_size`, `trained_at`) VALUES
(1, 'ar7', 1, 26.312, 30.118, 186.269, -3.641, 182.893, 11, '2026-05-13 22:18:10'),
(2, 'mewma', 1, 12.922, 15.906, 137.381, -0.294, 71.292, 11, '2026-05-13 22:18:10'),
(3, 'ensemble', 1, 12.922, 15.906, 137.381, -0.294, 71.292, 11, '2026-05-13 22:18:11'),
(4, 'ar7', 2, 17.731, 21.955, 252.509, -0.212, 84.674, 11, '2026-05-13 22:18:11'),
(5, 'mewma', 2, 14.396, 22.002, 149.095, -0.217, 75.175, 11, '2026-05-13 22:18:11'),
(6, 'ensemble', 2, 15.685, 21.200, 199.196, -0.130, 78.258, 11, '2026-05-13 22:18:11'),
(7, 'ar7', 3, 19.167, 28.135, 50.030, -0.763, 73.467, 11, '2026-05-13 22:18:12'),
(8, 'mewma', 3, 13.996, 22.325, 38.921, -0.110, 45.396, 11, '2026-05-13 22:18:12'),
(9, 'ensemble', 3, 13.996, 22.325, 38.921, -0.110, 45.396, 11, '2026-05-13 22:18:12'),
(10, 'ar7', 4, 7.851, 10.032, 180.781, 0.346, 52.500, 11, '2026-05-13 22:18:12'),
(11, 'mewma', 4, 12.161, 13.913, 265.823, -0.257, 72.342, 11, '2026-05-13 22:18:12'),
(12, 'ensemble', 4, 7.851, 10.032, 180.781, 0.346, 52.500, 11, '2026-05-13 22:18:12'),
(13, 'ar7', 5, 9.876, 13.504, 59.233, -0.067, 53.014, 11, '2026-05-13 22:18:13'),
(14, 'mewma', 5, 12.265, 14.848, 96.006, -0.290, 65.097, 11, '2026-05-13 22:18:13'),
(15, 'ensemble', 5, 9.816, 13.469, 59.742, -0.061, 51.688, 11, '2026-05-13 22:18:13'),
(16, 'ar7', 6, 12.212, 14.211, 110.223, -0.476, 67.621, 11, '2026-05-13 22:18:13'),
(17, 'mewma', 6, 11.052, 12.397, 77.034, -0.124, 62.192, 11, '2026-05-13 22:18:13'),
(18, 'ensemble', 6, 11.100, 12.196, 81.993, -0.087, 62.272, 11, '2026-05-13 22:18:13'),
(19, 'ar7', 7, 14.718, 16.255, 215.218, -0.320, 69.599, 11, '2026-05-13 22:18:14'),
(20, 'mewma', 7, 12.419, 14.980, 160.648, -0.121, 65.005, 11, '2026-05-13 22:18:14'),
(21, 'ensemble', 7, 12.446, 14.828, 169.504, -0.098, 64.230, 11, '2026-05-13 22:18:14'),
(22, 'ar7', 8, 16.234, 17.860, 187.320, -0.563, 117.795, 11, '2026-05-13 22:18:14'),
(23, 'mewma', 8, 13.518, 15.645, 237.916, -0.199, 74.240, 11, '2026-05-13 22:18:14'),
(24, 'ensemble', 8, 12.521, 14.718, 209.514, -0.062, 71.579, 11, '2026-05-13 22:18:14'),
(25, 'ar7', 1, 26.312, 30.118, 186.269, -3.641, 182.893, 11, '2026-05-13 22:20:33'),
(26, 'mewma', 1, 12.922, 15.906, 137.381, -0.294, 71.292, 11, '2026-05-13 22:20:33'),
(27, 'ensemble', 1, 12.922, 15.906, 137.381, -0.294, 71.292, 11, '2026-05-13 22:20:33'),
(28, 'ar7', 2, 17.731, 21.955, 252.509, -0.212, 84.674, 11, '2026-05-13 22:20:33'),
(29, 'mewma', 2, 14.396, 22.002, 149.095, -0.217, 75.175, 11, '2026-05-13 22:20:33'),
(30, 'ensemble', 2, 15.685, 21.200, 199.196, -0.130, 78.258, 11, '2026-05-13 22:20:33'),
(31, 'ar7', 3, 19.167, 28.135, 50.030, -0.763, 73.467, 11, '2026-05-13 22:20:34'),
(32, 'mewma', 3, 13.996, 22.325, 38.921, -0.110, 45.396, 11, '2026-05-13 22:20:34'),
(33, 'ensemble', 3, 13.996, 22.325, 38.921, -0.110, 45.396, 11, '2026-05-13 22:20:34'),
(34, 'ar7', 4, 7.851, 10.032, 180.781, 0.346, 52.500, 11, '2026-05-13 22:20:34'),
(35, 'mewma', 4, 12.161, 13.913, 265.823, -0.257, 72.342, 11, '2026-05-13 22:20:34'),
(36, 'ensemble', 4, 7.851, 10.032, 180.781, 0.346, 52.500, 11, '2026-05-13 22:20:34'),
(37, 'ar7', 5, 9.876, 13.504, 59.233, -0.067, 53.014, 11, '2026-05-13 22:20:35'),
(38, 'mewma', 5, 12.265, 14.848, 96.006, -0.290, 65.097, 11, '2026-05-13 22:20:35'),
(39, 'ensemble', 5, 9.816, 13.469, 59.742, -0.061, 51.688, 11, '2026-05-13 22:20:35'),
(40, 'ar7', 6, 12.212, 14.211, 110.223, -0.476, 67.621, 11, '2026-05-13 22:20:35'),
(41, 'mewma', 6, 11.052, 12.397, 77.034, -0.124, 62.192, 11, '2026-05-13 22:20:35'),
(42, 'ensemble', 6, 11.100, 12.196, 81.993, -0.087, 62.272, 11, '2026-05-13 22:20:35'),
(43, 'ar7', 7, 14.718, 16.255, 215.218, -0.320, 69.599, 11, '2026-05-13 22:20:36'),
(44, 'mewma', 7, 12.419, 14.980, 160.648, -0.121, 65.005, 11, '2026-05-13 22:20:36'),
(45, 'ensemble', 7, 12.446, 14.828, 169.504, -0.098, 64.230, 11, '2026-05-13 22:20:36'),
(46, 'ar7', 8, 16.234, 17.860, 187.320, -0.563, 117.795, 11, '2026-05-13 22:20:37'),
(47, 'mewma', 8, 13.518, 15.645, 237.916, -0.199, 74.240, 11, '2026-05-13 22:20:37'),
(48, 'ensemble', 8, 12.521, 14.718, 209.514, -0.062, 71.579, 11, '2026-05-13 22:20:37'),
(49, 'ar7', 1, 22.093, 26.346, 148.870, -2.541, 188.303, 11, '2026-05-14 12:40:02'),
(50, 'mewma', 1, 12.810, 15.870, 138.544, -0.285, 71.578, 11, '2026-05-14 12:40:02'),
(51, 'ensemble', 1, 12.810, 15.870, 138.544, -0.285, 71.578, 11, '2026-05-14 12:40:02'),
(52, 'ar7', 2, 15.275, 20.391, 224.346, -0.049, 76.700, 11, '2026-05-14 12:40:03'),
(53, 'mewma', 2, 13.987, 21.916, 145.955, -0.212, 72.760, 11, '2026-05-14 12:40:03'),
(54, 'ensemble', 2, 14.828, 20.312, 207.872, -0.041, 74.483, 11, '2026-05-14 12:40:03'),
(55, 'ar7', 3, 20.193, 28.418, 53.023, -0.865, 77.348, 11, '2026-05-14 12:40:03'),
(56, 'mewma', 3, 13.981, 22.322, 37.916, -0.151, 44.017, 11, '2026-05-14 12:40:03'),
(57, 'ensemble', 3, 12.905, 22.306, 33.821, -0.149, 40.456, 11, '2026-05-14 12:40:03'),
(58, 'ar7', 4, 7.851, 10.032, 180.781, 0.346, 52.500, 11, '2026-05-14 12:40:04'),
(59, 'mewma', 4, 12.161, 13.913, 265.823, -0.257, 72.342, 11, '2026-05-14 12:40:04'),
(60, 'ensemble', 4, 7.851, 10.032, 180.781, 0.346, 52.500, 11, '2026-05-14 12:40:04'),
(61, 'ar7', 5, 9.876, 13.504, 59.233, -0.067, 53.014, 11, '2026-05-14 12:40:05'),
(62, 'mewma', 5, 12.265, 14.848, 96.006, -0.290, 65.097, 11, '2026-05-14 12:40:05'),
(63, 'ensemble', 5, 9.816, 13.469, 59.742, -0.061, 51.688, 11, '2026-05-14 12:40:05'),
(64, 'ar7', 6, 11.966, 14.132, 88.845, -0.743, 62.791, 11, '2026-05-14 12:40:05'),
(65, 'mewma', 6, 11.452, 12.753, 65.956, -0.419, 58.750, 11, '2026-05-14 12:40:05'),
(66, 'ensemble', 6, 11.294, 12.369, 69.981, -0.335, 58.062, 11, '2026-05-14 12:40:05'),
(67, 'ar7', 7, 13.680, 15.637, 200.007, -0.216, 66.120, 11, '2026-05-14 12:40:06'),
(68, 'mewma', 7, 12.786, 15.231, 163.219, -0.154, 66.495, 11, '2026-05-14 12:40:06'),
(69, 'ensemble', 7, 12.670, 14.824, 175.680, -0.093, 64.936, 11, '2026-05-14 12:40:06'),
(70, 'ar7', 8, 15.008, 16.708, 198.516, -0.729, 106.425, 11, '2026-05-14 12:40:06'),
(71, 'mewma', 8, 11.480, 13.639, 234.736, -0.152, 68.517, 11, '2026-05-14 12:40:06'),
(72, 'ensemble', 8, 10.794, 12.907, 212.849, -0.032, 67.114, 11, '2026-05-14 12:40:06'),
(73, 'ar7', 3, 8.971, 11.360, 136.134, -0.482, 58.969, 31, '2026-05-25 10:45:12'),
(74, 'mewma', 3, 8.160, 10.003, 144.856, -0.149, 52.098, 31, '2026-05-25 10:45:12'),
(75, 'ensemble', 3, 8.157, 9.937, 141.768, -0.134, 52.553, 31, '2026-05-25 10:45:12'),
(76, 'ar7', 1, 9.554, 11.805, 54.373, -2.171, 78.209, 32, '2026-05-25 10:45:14'),
(77, 'mewma', 1, 5.926, 7.256, 50.110, -0.198, 37.805, 32, '2026-05-25 10:45:14'),
(78, 'ensemble', 1, 5.926, 7.256, 50.110, -0.198, 37.805, 32, '2026-05-25 10:45:14'),
(79, 'ar7', 1, 9.554, 11.805, 54.373, -2.171, 78.209, 32, '2026-05-25 10:45:18'),
(80, 'mewma', 1, 5.926, 7.256, 50.110, -0.198, 37.805, 32, '2026-05-25 10:45:18'),
(81, 'ensemble', 1, 5.926, 7.256, 50.110, -0.198, 37.805, 32, '2026-05-25 10:45:18'),
(82, 'ar7', 2, 6.326, 7.669, 46.014, -0.232, 42.769, 32, '2026-05-25 10:45:20'),
(83, 'mewma', 2, 5.499, 7.161, 47.921, -0.074, 35.963, 32, '2026-05-25 10:45:20'),
(84, 'ensemble', 2, 5.460, 6.956, 45.327, -0.014, 36.062, 32, '2026-05-25 10:45:20'),
(85, 'ar7', 2, 6.326, 7.669, 46.014, -0.232, 42.769, 32, '2026-05-25 10:45:25'),
(86, 'mewma', 2, 5.499, 7.161, 47.921, -0.074, 35.963, 32, '2026-05-25 10:45:25'),
(87, 'ensemble', 2, 5.460, 6.956, 45.327, -0.014, 36.062, 32, '2026-05-25 10:45:25'),
(88, 'ar7', 3, 8.971, 11.360, 136.134, -0.482, 58.969, 31, '2026-05-25 10:45:26'),
(89, 'mewma', 3, 8.160, 10.003, 144.856, -0.149, 52.098, 31, '2026-05-25 10:45:26'),
(90, 'ensemble', 3, 8.157, 9.937, 141.768, -0.134, 52.553, 31, '2026-05-25 10:45:26'),
(91, 'ar7', 4, 5.788, 7.041, 59.283, -0.905, 46.634, 31, '2026-05-25 10:45:30'),
(92, 'mewma', 4, 4.304, 5.568, 54.027, -0.191, 33.987, 31, '2026-05-25 10:45:30'),
(93, 'ensemble', 4, 4.213, 5.514, 52.298, -0.168, 33.392, 31, '2026-05-25 10:45:30'),
(94, 'ar7', 4, 5.788, 7.041, 59.283, -0.905, 46.634, 31, '2026-05-25 10:45:32'),
(95, 'mewma', 4, 4.304, 5.568, 54.027, -0.191, 33.987, 31, '2026-05-25 10:45:32'),
(96, 'ensemble', 4, 4.213, 5.514, 52.298, -0.168, 33.392, 31, '2026-05-25 10:45:32'),
(97, 'ar7', 5, 8.145, 10.652, 49.529, -1.927, 73.842, 31, '2026-05-25 10:45:35'),
(98, 'mewma', 5, 5.701, 7.014, 50.312, -0.269, 39.195, 31, '2026-05-25 10:45:35'),
(99, 'ensemble', 5, 5.581, 6.922, 46.525, -0.236, 38.870, 31, '2026-05-25 10:45:35'),
(100, 'ar7', 5, 8.145, 10.652, 49.529, -1.927, 73.842, 31, '2026-05-25 10:45:37'),
(101, 'mewma', 5, 5.701, 7.014, 50.312, -0.269, 39.195, 31, '2026-05-25 10:45:37'),
(102, 'ensemble', 5, 5.581, 6.922, 46.525, -0.236, 38.870, 31, '2026-05-25 10:45:37'),
(103, 'ar7', 6, 5.899, 7.135, 66.507, -0.319, 44.887, 32, '2026-05-25 10:45:40'),
(104, 'mewma', 6, 5.883, 7.076, 65.985, -0.297, 45.536, 32, '2026-05-25 10:45:40'),
(105, 'ensemble', 6, 5.584, 6.842, 63.992, -0.212, 43.186, 32, '2026-05-25 10:45:40'),
(106, 'ar7', 6, 5.899, 7.135, 66.507, -0.319, 44.887, 32, '2026-05-25 10:45:42'),
(107, 'mewma', 6, 5.883, 7.076, 65.985, -0.297, 45.536, 32, '2026-05-25 10:45:42'),
(108, 'ensemble', 6, 5.584, 6.842, 63.992, -0.212, 43.186, 32, '2026-05-25 10:45:42'),
(109, 'ar7', 7, 5.668, 7.494, 47.029, -0.707, 39.829, 31, '2026-05-25 10:45:45'),
(110, 'mewma', 7, 5.133, 6.333, 47.120, -0.219, 35.869, 31, '2026-05-25 10:45:45'),
(111, 'ensemble', 7, 5.074, 6.329, 46.302, -0.218, 35.499, 31, '2026-05-25 10:45:45'),
(112, 'ar7', 7, 5.668, 7.494, 47.029, -0.707, 39.829, 31, '2026-05-25 10:45:46'),
(113, 'mewma', 7, 5.133, 6.333, 47.120, -0.219, 35.869, 31, '2026-05-25 10:45:46'),
(114, 'ensemble', 7, 5.074, 6.329, 46.302, -0.218, 35.499, 31, '2026-05-25 10:45:46'),
(115, 'ar7', 8, 6.457, 9.105, 65.425, -0.262, 53.605, 31, '2026-05-25 10:45:53'),
(116, 'mewma', 8, 7.012, 9.041, 99.892, -0.244, 54.769, 31, '2026-05-25 10:45:53'),
(117, 'ensemble', 8, 5.987, 8.462, 76.113, -0.090, 48.435, 31, '2026-05-25 10:45:53'),
(118, 'ar7', 1, 9.554, 11.805, 54.373, -2.171, 78.209, 32, '2026-05-25 13:56:43'),
(119, 'mewma', 1, 5.926, 7.256, 50.110, -0.198, 37.805, 32, '2026-05-25 13:56:43'),
(120, 'ensemble', 1, 5.926, 7.256, 50.110, -0.198, 37.805, 32, '2026-05-25 13:56:43'),
(121, 'ar7', 2, 6.326, 7.669, 46.014, -0.232, 42.769, 32, '2026-05-25 13:56:48'),
(122, 'mewma', 2, 5.499, 7.161, 47.921, -0.074, 35.963, 32, '2026-05-25 13:56:48'),
(123, 'ensemble', 2, 5.460, 6.956, 45.327, -0.014, 36.062, 32, '2026-05-25 13:56:48'),
(124, 'ar7', 3, 8.139, 10.478, 139.386, -0.393, 54.315, 31, '2026-05-25 13:56:54'),
(125, 'mewma', 3, 8.216, 10.068, 147.503, -0.286, 53.173, 31, '2026-05-25 13:56:54'),
(126, 'ensemble', 3, 7.937, 9.772, 142.243, -0.211, 52.488, 31, '2026-05-25 13:56:54'),
(127, 'ar7', 4, 5.788, 7.041, 59.283, -0.905, 46.634, 31, '2026-05-25 13:56:58'),
(128, 'mewma', 4, 4.304, 5.568, 54.027, -0.191, 33.987, 31, '2026-05-25 13:56:58'),
(129, 'ensemble', 4, 4.213, 5.514, 52.298, -0.168, 33.392, 31, '2026-05-25 13:56:58'),
(130, 'ar7', 5, 8.145, 10.652, 49.529, -1.927, 73.842, 31, '2026-05-25 13:57:02'),
(131, 'mewma', 5, 5.701, 7.014, 50.312, -0.269, 39.195, 31, '2026-05-25 13:57:02'),
(132, 'ensemble', 5, 5.581, 6.922, 46.525, -0.236, 38.870, 31, '2026-05-25 13:57:02'),
(133, 'ar7', 6, 5.899, 7.135, 66.507, -0.319, 44.887, 32, '2026-05-25 13:57:05'),
(134, 'mewma', 6, 5.883, 7.076, 65.985, -0.297, 45.536, 32, '2026-05-25 13:57:05'),
(135, 'ensemble', 6, 5.584, 6.842, 63.992, -0.212, 43.186, 32, '2026-05-25 13:57:05'),
(136, 'ar7', 7, 5.668, 7.494, 47.029, -0.707, 39.829, 31, '2026-05-25 13:57:09'),
(137, 'mewma', 7, 5.133, 6.333, 47.120, -0.219, 35.869, 31, '2026-05-25 13:57:09'),
(138, 'ensemble', 7, 5.074, 6.329, 46.302, -0.218, 35.499, 31, '2026-05-25 13:57:09'),
(139, 'ar7', 8, 6.457, 9.105, 65.425, -0.262, 53.605, 31, '2026-05-25 13:57:12'),
(140, 'mewma', 8, 7.012, 9.041, 99.892, -0.244, 54.769, 31, '2026-05-25 13:57:12'),
(141, 'ensemble', 8, 5.987, 8.462, 76.113, -0.090, 48.435, 31, '2026-05-25 13:57:12'),
(142, 'ar7', 1, 9.554, 11.805, 54.373, -2.171, 78.209, 32, '2026-05-30 12:49:01'),
(143, 'mewma', 1, 5.926, 7.256, 50.110, -0.198, 37.805, 32, '2026-05-30 12:49:01'),
(144, 'ensemble', 1, 5.926, 7.256, 50.110, -0.198, 37.805, 32, '2026-05-30 12:49:01'),
(145, 'ar7', 2, 6.326, 7.669, 46.014, -0.232, 42.769, 32, '2026-05-30 12:49:03'),
(146, 'mewma', 2, 5.499, 7.161, 47.921, -0.074, 35.963, 32, '2026-05-30 12:49:03'),
(147, 'ensemble', 2, 5.460, 6.956, 45.327, -0.014, 36.062, 32, '2026-05-30 12:49:03'),
(148, 'ar7', 3, 8.139, 10.478, 139.386, -0.393, 54.315, 31, '2026-05-30 12:49:04'),
(149, 'mewma', 3, 8.216, 10.068, 147.503, -0.286, 53.173, 31, '2026-05-30 12:49:04'),
(150, 'ensemble', 3, 7.937, 9.772, 142.243, -0.211, 52.488, 31, '2026-05-30 12:49:04'),
(151, 'ar7', 3, 8.139, 10.478, 139.386, -0.393, 54.315, 31, '2026-05-30 12:49:04'),
(152, 'mewma', 3, 8.216, 10.068, 147.503, -0.286, 53.173, 31, '2026-05-30 12:49:04'),
(153, 'ensemble', 3, 7.937, 9.772, 142.243, -0.211, 52.488, 31, '2026-05-30 12:49:04'),
(154, 'ar7', 4, 5.788, 7.041, 59.283, -0.905, 46.634, 31, '2026-05-30 12:49:05'),
(155, 'mewma', 4, 4.304, 5.568, 54.027, -0.191, 33.987, 31, '2026-05-30 12:49:05'),
(156, 'ensemble', 4, 4.213, 5.514, 52.298, -0.168, 33.392, 31, '2026-05-30 12:49:05'),
(157, 'ar7', 4, 5.788, 7.041, 59.283, -0.905, 46.634, 31, '2026-05-30 12:49:06'),
(158, 'mewma', 4, 4.304, 5.568, 54.027, -0.191, 33.987, 31, '2026-05-30 12:49:06'),
(159, 'ensemble', 4, 4.213, 5.514, 52.298, -0.168, 33.392, 31, '2026-05-30 12:49:06'),
(160, 'ar7', 5, 8.145, 10.652, 49.529, -1.927, 73.842, 31, '2026-05-30 12:49:07'),
(161, 'mewma', 5, 5.701, 7.014, 50.312, -0.269, 39.195, 31, '2026-05-30 12:49:07'),
(162, 'ensemble', 5, 5.581, 6.922, 46.525, -0.236, 38.870, 31, '2026-05-30 12:49:07'),
(163, 'ar7', 5, 8.145, 10.652, 49.529, -1.927, 73.842, 31, '2026-05-30 12:49:07'),
(164, 'mewma', 5, 5.701, 7.014, 50.312, -0.269, 39.195, 31, '2026-05-30 12:49:07'),
(165, 'ensemble', 5, 5.581, 6.922, 46.525, -0.236, 38.870, 31, '2026-05-30 12:49:07'),
(166, 'ar7', 6, 5.899, 7.135, 66.507, -0.319, 44.887, 32, '2026-05-30 12:49:08'),
(167, 'mewma', 6, 5.883, 7.076, 65.985, -0.297, 45.536, 32, '2026-05-30 12:49:08'),
(168, 'ensemble', 6, 5.584, 6.842, 63.992, -0.212, 43.186, 32, '2026-05-30 12:49:08'),
(169, 'ar7', 6, 5.899, 7.135, 66.507, -0.319, 44.887, 32, '2026-05-30 12:49:08'),
(170, 'mewma', 6, 5.883, 7.076, 65.985, -0.297, 45.536, 32, '2026-05-30 12:49:08'),
(171, 'ensemble', 6, 5.584, 6.842, 63.992, -0.212, 43.186, 32, '2026-05-30 12:49:08'),
(172, 'ar7', 7, 5.668, 7.494, 47.029, -0.707, 39.829, 31, '2026-05-30 12:49:10'),
(173, 'mewma', 7, 5.133, 6.333, 47.120, -0.219, 35.869, 31, '2026-05-30 12:49:10'),
(174, 'ensemble', 7, 5.074, 6.329, 46.302, -0.218, 35.499, 31, '2026-05-30 12:49:10'),
(175, 'ar7', 7, 5.668, 7.494, 47.029, -0.707, 39.829, 31, '2026-05-30 12:49:10'),
(176, 'mewma', 7, 5.133, 6.333, 47.120, -0.219, 35.869, 31, '2026-05-30 12:49:10'),
(177, 'ensemble', 7, 5.074, 6.329, 46.302, -0.218, 35.499, 31, '2026-05-30 12:49:10'),
(178, 'ar7', 8, 6.457, 9.105, 65.425, -0.262, 53.605, 31, '2026-05-30 12:49:12'),
(179, 'mewma', 8, 7.012, 9.041, 99.892, -0.244, 54.769, 31, '2026-05-30 12:49:12'),
(180, 'ensemble', 8, 5.987, 8.462, 76.113, -0.090, 48.435, 31, '2026-05-30 12:49:12'),
(181, 'ar7', 8, 6.457, 9.105, 65.425, -0.262, 53.605, 31, '2026-05-30 12:49:13'),
(182, 'mewma', 8, 7.012, 9.041, 99.892, -0.244, 54.769, 31, '2026-05-30 12:49:13'),
(183, 'ensemble', 8, 5.987, 8.462, 76.113, -0.090, 48.435, 31, '2026-05-30 12:49:13'),
(184, 'ar7', 1, 9.554, 11.805, 54.373, -2.171, 78.209, 32, '2026-06-01 21:28:06'),
(185, 'mewma', 1, 5.926, 7.256, 50.110, -0.198, 37.805, 32, '2026-06-01 21:28:06'),
(186, 'ensemble', 1, 5.926, 7.256, 50.110, -0.198, 37.805, 32, '2026-06-01 21:28:06'),
(187, 'ar7', 2, 6.326, 7.669, 46.014, -0.232, 42.769, 32, '2026-06-01 21:28:09'),
(188, 'mewma', 2, 5.499, 7.161, 47.921, -0.074, 35.963, 32, '2026-06-01 21:28:09'),
(189, 'ensemble', 2, 5.460, 6.956, 45.327, -0.014, 36.062, 32, '2026-06-01 21:28:09'),
(190, 'ar7', 3, 8.139, 10.478, 139.386, -0.393, 54.315, 31, '2026-06-01 21:28:11'),
(191, 'mewma', 3, 8.216, 10.068, 147.503, -0.286, 53.173, 31, '2026-06-01 21:28:11'),
(192, 'ensemble', 3, 7.937, 9.772, 142.243, -0.211, 52.488, 31, '2026-06-01 21:28:11'),
(193, 'ar7', 4, 5.788, 7.041, 59.283, -0.905, 46.634, 31, '2026-06-01 21:28:14'),
(194, 'mewma', 4, 4.304, 5.568, 54.027, -0.191, 33.987, 31, '2026-06-01 21:28:14'),
(195, 'ensemble', 4, 4.213, 5.514, 52.298, -0.168, 33.392, 31, '2026-06-01 21:28:14'),
(196, 'ar7', 5, 8.145, 10.652, 49.529, -1.927, 73.842, 31, '2026-06-01 21:28:17'),
(197, 'mewma', 5, 5.701, 7.014, 50.312, -0.269, 39.195, 31, '2026-06-01 21:28:17'),
(198, 'ensemble', 5, 5.581, 6.922, 46.525, -0.236, 38.870, 31, '2026-06-01 21:28:17'),
(199, 'ar7', 6, 5.899, 7.135, 66.507, -0.319, 44.887, 32, '2026-06-01 21:28:20'),
(200, 'mewma', 6, 5.883, 7.076, 65.985, -0.297, 45.536, 32, '2026-06-01 21:28:20'),
(201, 'ensemble', 6, 5.584, 6.842, 63.992, -0.212, 43.186, 32, '2026-06-01 21:28:20'),
(202, 'ar7', 7, 5.668, 7.494, 47.029, -0.707, 39.829, 31, '2026-06-01 21:28:23'),
(203, 'mewma', 7, 5.133, 6.333, 47.120, -0.219, 35.869, 31, '2026-06-01 21:28:23'),
(204, 'ensemble', 7, 5.074, 6.329, 46.302, -0.218, 35.499, 31, '2026-06-01 21:28:23'),
(205, 'ar7', 8, 6.457, 9.105, 65.425, -0.262, 53.605, 31, '2026-06-01 21:28:26'),
(206, 'mewma', 8, 7.012, 9.041, 99.892, -0.244, 54.769, 31, '2026-06-01 21:28:26'),
(207, 'ensemble', 8, 5.987, 8.462, 76.113, -0.090, 48.435, 31, '2026-06-01 21:28:26'),
(208, 'ar7', 1, 9.554, 11.805, 54.373, -2.171, 78.209, 32, '2026-06-09 15:30:01'),
(209, 'mewma', 1, 5.926, 7.256, 50.110, -0.198, 37.805, 32, '2026-06-09 15:30:01'),
(210, 'ensemble', 1, 5.926, 7.256, 50.110, -0.198, 37.805, 32, '2026-06-09 15:30:01'),
(211, 'ar7', 2, 6.326, 7.669, 46.014, -0.232, 42.769, 32, '2026-06-09 15:30:03'),
(212, 'mewma', 2, 5.499, 7.161, 47.921, -0.074, 35.963, 32, '2026-06-09 15:30:03'),
(213, 'ensemble', 2, 5.460, 6.956, 45.327, -0.014, 36.062, 32, '2026-06-09 15:30:03'),
(214, 'ar7', 3, 8.139, 10.478, 139.386, -0.393, 54.315, 31, '2026-06-09 15:30:05'),
(215, 'mewma', 3, 8.216, 10.068, 147.503, -0.286, 53.173, 31, '2026-06-09 15:30:05'),
(216, 'ensemble', 3, 7.937, 9.772, 142.243, -0.211, 52.488, 31, '2026-06-09 15:30:05'),
(217, 'ar7', 4, 5.788, 7.041, 59.283, -0.905, 46.634, 31, '2026-06-09 15:30:06'),
(218, 'mewma', 4, 4.304, 5.568, 54.027, -0.191, 33.987, 31, '2026-06-09 15:30:06'),
(219, 'ensemble', 4, 4.213, 5.514, 52.298, -0.168, 33.392, 31, '2026-06-09 15:30:06'),
(220, 'ar7', 5, 8.145, 10.652, 49.529, -1.927, 73.842, 31, '2026-06-09 15:30:08'),
(221, 'mewma', 5, 5.701, 7.014, 50.312, -0.269, 39.195, 31, '2026-06-09 15:30:08'),
(222, 'ensemble', 5, 5.581, 6.922, 46.525, -0.236, 38.870, 31, '2026-06-09 15:30:08'),
(223, 'ar7', 6, 5.899, 7.135, 66.507, -0.319, 44.887, 32, '2026-06-09 15:30:10'),
(224, 'mewma', 6, 5.883, 7.076, 65.985, -0.297, 45.536, 32, '2026-06-09 15:30:10'),
(225, 'ensemble', 6, 5.584, 6.842, 63.992, -0.212, 43.186, 32, '2026-06-09 15:30:10'),
(226, 'ar7', 7, 5.668, 7.494, 47.029, -0.707, 39.829, 31, '2026-06-09 15:30:12'),
(227, 'mewma', 7, 5.133, 6.333, 47.120, -0.219, 35.869, 31, '2026-06-09 15:30:12'),
(228, 'ensemble', 7, 5.074, 6.329, 46.302, -0.218, 35.499, 31, '2026-06-09 15:30:12'),
(229, 'ar7', 8, 6.457, 9.105, 65.425, -0.262, 53.605, 31, '2026-06-09 15:30:13'),
(230, 'mewma', 8, 7.012, 9.041, 99.892, -0.244, 54.769, 31, '2026-06-09 15:30:13'),
(231, 'ensemble', 8, 5.987, 8.462, 76.113, -0.090, 48.435, 31, '2026-06-09 15:30:13');

-- --------------------------------------------------------

--
-- Structure de la table `forecast_predictions`
--

CREATE TABLE `forecast_predictions` (
  `id` int(11) NOT NULL,
  `zone_id` int(11) NOT NULL,
  `horizon_hours` int(11) NOT NULL,
  `predicted_score` int(11) NOT NULL,
  `predicted_level` enum('safe','warning','critical') NOT NULL DEFAULT 'safe',
  `method` varchar(50) NOT NULL,
  `confidence` decimal(3,2) DEFAULT NULL,
  `computed_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `forecast_predictions`
--

INSERT INTO `forecast_predictions` (`id`, `zone_id`, `horizon_hours`, `predicted_score`, `predicted_level`, `method`, `confidence`, `computed_at`) VALUES
(1, 1, 6, 17, 'safe', 'ensemble_ar7_mewma', 0.84, '2026-05-13 22:18:10'),
(2, 1, 12, 17, 'safe', 'ensemble_ar7_mewma', 0.84, '2026-05-13 22:18:10'),
(3, 1, 24, 17, 'safe', 'ensemble_ar7_mewma', 0.84, '2026-05-13 22:18:10'),
(4, 2, 6, 23, 'safe', 'ensemble_ar7_mewma', 0.79, '2026-05-13 22:18:11'),
(5, 2, 12, 23, 'safe', 'ensemble_ar7_mewma', 0.79, '2026-05-13 22:18:11'),
(6, 2, 24, 23, 'safe', 'ensemble_ar7_mewma', 0.79, '2026-05-13 22:18:11'),
(7, 3, 6, 36, 'safe', 'ensemble_ar7_mewma', 0.78, '2026-05-13 22:18:12'),
(8, 3, 12, 36, 'safe', 'ensemble_ar7_mewma', 0.78, '2026-05-13 22:18:12'),
(9, 3, 24, 36, 'safe', 'ensemble_ar7_mewma', 0.78, '2026-05-13 22:18:12'),
(10, 4, 6, 9, 'safe', 'ensemble_ar7_mewma', 0.90, '2026-05-13 22:18:12'),
(11, 4, 12, 9, 'safe', 'ensemble_ar7_mewma', 0.90, '2026-05-13 22:18:12'),
(12, 4, 24, 9, 'safe', 'ensemble_ar7_mewma', 0.90, '2026-05-13 22:18:12'),
(13, 5, 6, 4, 'safe', 'ensemble_ar7_mewma', 0.87, '2026-05-13 22:18:13'),
(14, 5, 12, 4, 'safe', 'ensemble_ar7_mewma', 0.87, '2026-05-13 22:18:13'),
(15, 5, 24, 4, 'safe', 'ensemble_ar7_mewma', 0.87, '2026-05-13 22:18:13'),
(16, 6, 6, 24, 'safe', 'ensemble_ar7_mewma', 0.88, '2026-05-13 22:18:13'),
(17, 6, 12, 24, 'safe', 'ensemble_ar7_mewma', 0.88, '2026-05-13 22:18:13'),
(18, 6, 24, 24, 'safe', 'ensemble_ar7_mewma', 0.88, '2026-05-13 22:18:13'),
(19, 7, 6, 24, 'safe', 'ensemble_ar7_mewma', 0.85, '2026-05-13 22:18:14'),
(20, 7, 12, 24, 'safe', 'ensemble_ar7_mewma', 0.85, '2026-05-13 22:18:14'),
(21, 7, 24, 24, 'safe', 'ensemble_ar7_mewma', 0.85, '2026-05-13 22:18:14'),
(22, 8, 6, 13, 'safe', 'ensemble_ar7_mewma', 0.85, '2026-05-13 22:18:14'),
(23, 8, 12, 13, 'safe', 'ensemble_ar7_mewma', 0.85, '2026-05-13 22:18:14'),
(24, 8, 24, 13, 'safe', 'ensemble_ar7_mewma', 0.85, '2026-05-13 22:18:14'),
(25, 1, 6, 17, 'safe', 'ensemble_ar7_mewma', 0.84, '2026-05-13 22:20:33'),
(26, 1, 12, 17, 'safe', 'ensemble_ar7_mewma', 0.84, '2026-05-13 22:20:33'),
(27, 1, 24, 17, 'safe', 'ensemble_ar7_mewma', 0.84, '2026-05-13 22:20:33'),
(28, 2, 6, 23, 'safe', 'ensemble_ar7_mewma', 0.79, '2026-05-13 22:20:33'),
(29, 2, 12, 23, 'safe', 'ensemble_ar7_mewma', 0.79, '2026-05-13 22:20:33'),
(30, 2, 24, 23, 'safe', 'ensemble_ar7_mewma', 0.79, '2026-05-13 22:20:33'),
(31, 3, 6, 36, 'safe', 'ensemble_ar7_mewma', 0.78, '2026-05-13 22:20:34'),
(32, 3, 12, 36, 'safe', 'ensemble_ar7_mewma', 0.78, '2026-05-13 22:20:34'),
(33, 3, 24, 36, 'safe', 'ensemble_ar7_mewma', 0.78, '2026-05-13 22:20:34'),
(34, 4, 6, 9, 'safe', 'ensemble_ar7_mewma', 0.90, '2026-05-13 22:20:34'),
(35, 4, 12, 9, 'safe', 'ensemble_ar7_mewma', 0.90, '2026-05-13 22:20:34'),
(36, 4, 24, 9, 'safe', 'ensemble_ar7_mewma', 0.90, '2026-05-13 22:20:34'),
(37, 5, 6, 4, 'safe', 'ensemble_ar7_mewma', 0.87, '2026-05-13 22:20:35'),
(38, 5, 12, 4, 'safe', 'ensemble_ar7_mewma', 0.87, '2026-05-13 22:20:35'),
(39, 5, 24, 4, 'safe', 'ensemble_ar7_mewma', 0.87, '2026-05-13 22:20:35'),
(40, 6, 6, 24, 'safe', 'ensemble_ar7_mewma', 0.88, '2026-05-13 22:20:35'),
(41, 6, 12, 24, 'safe', 'ensemble_ar7_mewma', 0.88, '2026-05-13 22:20:35'),
(42, 6, 24, 24, 'safe', 'ensemble_ar7_mewma', 0.88, '2026-05-13 22:20:35'),
(43, 7, 6, 24, 'safe', 'ensemble_ar7_mewma', 0.85, '2026-05-13 22:20:36'),
(44, 7, 12, 24, 'safe', 'ensemble_ar7_mewma', 0.85, '2026-05-13 22:20:36'),
(45, 7, 24, 24, 'safe', 'ensemble_ar7_mewma', 0.85, '2026-05-13 22:20:36'),
(46, 8, 6, 13, 'safe', 'ensemble_ar7_mewma', 0.85, '2026-05-13 22:20:37'),
(47, 8, 12, 13, 'safe', 'ensemble_ar7_mewma', 0.85, '2026-05-13 22:20:37'),
(48, 8, 24, 13, 'safe', 'ensemble_ar7_mewma', 0.85, '2026-05-13 22:20:37'),
(49, 1, 6, 16, 'safe', 'ensemble_ar7_mewma', 0.84, '2026-05-14 12:40:02'),
(50, 1, 12, 16, 'safe', 'ensemble_ar7_mewma', 0.84, '2026-05-14 12:40:02'),
(51, 1, 24, 16, 'safe', 'ensemble_ar7_mewma', 0.84, '2026-05-14 12:40:02'),
(52, 2, 6, 20, 'safe', 'ensemble_ar7_mewma', 0.80, '2026-05-14 12:40:03'),
(53, 2, 12, 20, 'safe', 'ensemble_ar7_mewma', 0.80, '2026-05-14 12:40:03'),
(54, 2, 24, 10, 'safe', 'ensemble_ar7_mewma', 0.80, '2026-05-14 12:40:03'),
(55, 3, 6, 31, 'safe', 'ensemble_ar7_mewma', 0.78, '2026-05-14 12:40:03'),
(56, 3, 12, 31, 'safe', 'ensemble_ar7_mewma', 0.78, '2026-05-14 12:40:03'),
(57, 3, 24, 29, 'safe', 'ensemble_ar7_mewma', 0.78, '2026-05-14 12:40:03'),
(58, 4, 6, 9, 'safe', 'ensemble_ar7_mewma', 0.90, '2026-05-14 12:40:04'),
(59, 4, 12, 9, 'safe', 'ensemble_ar7_mewma', 0.90, '2026-05-14 12:40:04'),
(60, 4, 24, 0, 'safe', 'ensemble_ar7_mewma', 0.90, '2026-05-14 12:40:04'),
(61, 5, 6, 4, 'safe', 'ensemble_ar7_mewma', 0.87, '2026-05-14 12:40:05'),
(62, 5, 12, 4, 'safe', 'ensemble_ar7_mewma', 0.87, '2026-05-14 12:40:05'),
(63, 5, 24, 0, 'safe', 'ensemble_ar7_mewma', 0.87, '2026-05-14 12:40:05'),
(64, 6, 6, 18, 'safe', 'ensemble_ar7_mewma', 0.88, '2026-05-14 12:40:05'),
(65, 6, 12, 18, 'safe', 'ensemble_ar7_mewma', 0.88, '2026-05-14 12:40:05'),
(66, 6, 24, 16, 'safe', 'ensemble_ar7_mewma', 0.88, '2026-05-14 12:40:05'),
(67, 7, 6, 20, 'safe', 'ensemble_ar7_mewma', 0.85, '2026-05-14 12:40:06'),
(68, 7, 12, 20, 'safe', 'ensemble_ar7_mewma', 0.85, '2026-05-14 12:40:06'),
(69, 7, 24, 14, 'safe', 'ensemble_ar7_mewma', 0.85, '2026-05-14 12:40:06'),
(70, 8, 6, 15, 'safe', 'ensemble_ar7_mewma', 0.87, '2026-05-14 12:40:06'),
(71, 8, 12, 15, 'safe', 'ensemble_ar7_mewma', 0.87, '2026-05-14 12:40:06'),
(72, 8, 24, 7, 'safe', 'ensemble_ar7_mewma', 0.87, '2026-05-14 12:40:06'),
(73, 3, 6, 21, 'safe', 'ensemble_ar7_mewma', 0.90, '2026-05-25 10:45:12'),
(74, 3, 12, 21, 'safe', 'ensemble_ar7_mewma', 0.90, '2026-05-25 10:45:12'),
(75, 3, 24, 22, 'safe', 'ensemble_ar7_mewma', 0.90, '2026-05-25 10:45:12'),
(76, 1, 6, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-25 10:45:14'),
(77, 1, 12, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-25 10:45:14'),
(78, 1, 24, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-25 10:45:14'),
(79, 1, 6, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-25 10:45:18'),
(80, 1, 12, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-25 10:45:18'),
(81, 1, 24, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-25 10:45:18'),
(82, 2, 6, 16, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-25 10:45:20'),
(83, 2, 12, 16, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-25 10:45:20'),
(84, 2, 24, 18, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-25 10:45:20'),
(85, 2, 6, 16, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-25 10:45:25'),
(86, 2, 12, 16, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-25 10:45:25'),
(87, 2, 24, 18, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-25 10:45:25'),
(88, 3, 6, 21, 'safe', 'ensemble_ar7_mewma', 0.90, '2026-05-25 10:45:26'),
(89, 3, 12, 21, 'safe', 'ensemble_ar7_mewma', 0.90, '2026-05-25 10:45:26'),
(90, 3, 24, 22, 'safe', 'ensemble_ar7_mewma', 0.90, '2026-05-25 10:45:26'),
(91, 4, 6, 14, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-05-25 10:45:30'),
(92, 4, 12, 14, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-05-25 10:45:30'),
(93, 4, 24, 16, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-05-25 10:45:30'),
(94, 4, 6, 14, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-05-25 10:45:31'),
(95, 4, 12, 14, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-05-25 10:45:31'),
(96, 4, 24, 16, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-05-25 10:45:31'),
(97, 5, 6, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-25 10:45:35'),
(98, 5, 12, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-25 10:45:35'),
(99, 5, 24, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-25 10:45:35'),
(100, 5, 6, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-25 10:45:37'),
(101, 5, 12, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-25 10:45:37'),
(102, 5, 24, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-25 10:45:37'),
(103, 6, 6, 10, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-25 10:45:40'),
(104, 6, 12, 10, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-25 10:45:40'),
(105, 6, 24, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-25 10:45:40'),
(106, 6, 6, 10, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-25 10:45:41'),
(107, 6, 12, 10, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-25 10:45:41'),
(108, 6, 24, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-25 10:45:41'),
(109, 7, 6, 14, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-05-25 10:45:45'),
(110, 7, 12, 14, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-05-25 10:45:45'),
(111, 7, 24, 14, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-05-25 10:45:45'),
(112, 7, 6, 14, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-05-25 10:45:46'),
(113, 7, 12, 14, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-05-25 10:45:46'),
(114, 7, 24, 14, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-05-25 10:45:46'),
(115, 8, 6, 11, 'safe', 'ensemble_ar7_mewma', 0.92, '2026-05-25 10:45:53'),
(116, 8, 12, 11, 'safe', 'ensemble_ar7_mewma', 0.92, '2026-05-25 10:45:53'),
(117, 8, 24, 15, 'safe', 'ensemble_ar7_mewma', 0.92, '2026-05-25 10:45:53'),
(118, 1, 6, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-25 13:56:43'),
(119, 1, 12, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-25 13:56:43'),
(120, 1, 24, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-25 13:56:43'),
(121, 2, 6, 16, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-25 13:56:48'),
(122, 2, 12, 18, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-25 13:56:48'),
(123, 2, 24, 18, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-25 13:56:48'),
(124, 3, 6, 17, 'safe', 'ensemble_ar7_mewma', 0.90, '2026-05-25 13:56:53'),
(125, 3, 12, 18, 'safe', 'ensemble_ar7_mewma', 0.90, '2026-05-25 13:56:53'),
(126, 3, 24, 18, 'safe', 'ensemble_ar7_mewma', 0.90, '2026-05-25 13:56:53'),
(127, 4, 6, 14, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-05-25 13:56:58'),
(128, 4, 12, 16, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-05-25 13:56:58'),
(129, 4, 24, 16, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-05-25 13:56:58'),
(130, 5, 6, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-25 13:57:02'),
(131, 5, 12, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-25 13:57:02'),
(132, 5, 24, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-25 13:57:02'),
(133, 6, 6, 10, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-25 13:57:05'),
(134, 6, 12, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-25 13:57:05'),
(135, 6, 24, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-25 13:57:05'),
(136, 7, 6, 14, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-05-25 13:57:09'),
(137, 7, 12, 14, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-05-25 13:57:09'),
(138, 7, 24, 14, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-05-25 13:57:09'),
(139, 8, 6, 11, 'safe', 'ensemble_ar7_mewma', 0.92, '2026-05-25 13:57:12'),
(140, 8, 12, 15, 'safe', 'ensemble_ar7_mewma', 0.92, '2026-05-25 13:57:12'),
(141, 8, 24, 15, 'safe', 'ensemble_ar7_mewma', 0.92, '2026-05-25 13:57:12'),
(142, 1, 6, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-30 12:49:01'),
(143, 1, 12, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-30 12:49:01'),
(144, 1, 24, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-30 12:49:01'),
(145, 2, 6, 13, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-30 12:49:03'),
(146, 2, 12, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-30 12:49:03'),
(147, 2, 24, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-30 12:49:03'),
(148, 3, 6, 12, 'safe', 'ensemble_ar7_mewma', 0.90, '2026-05-30 12:49:04'),
(149, 3, 12, 14, 'safe', 'ensemble_ar7_mewma', 0.90, '2026-05-30 12:49:04'),
(150, 3, 24, 14, 'safe', 'ensemble_ar7_mewma', 0.90, '2026-05-30 12:49:04'),
(151, 3, 6, 12, 'safe', 'ensemble_ar7_mewma', 0.90, '2026-05-30 12:49:04'),
(152, 3, 12, 14, 'safe', 'ensemble_ar7_mewma', 0.90, '2026-05-30 12:49:04'),
(153, 3, 24, 14, 'safe', 'ensemble_ar7_mewma', 0.90, '2026-05-30 12:49:04'),
(154, 4, 6, 11, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-05-30 12:49:05'),
(155, 4, 12, 12, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-05-30 12:49:05'),
(156, 4, 24, 12, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-05-30 12:49:05'),
(157, 4, 6, 11, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-05-30 12:49:06'),
(158, 4, 12, 12, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-05-30 12:49:06'),
(159, 4, 24, 12, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-05-30 12:49:06'),
(160, 5, 6, 12, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-30 12:49:07'),
(161, 5, 12, 12, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-30 12:49:07'),
(162, 5, 24, 12, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-30 12:49:07'),
(163, 5, 6, 12, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-30 12:49:07'),
(164, 5, 12, 12, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-30 12:49:07'),
(165, 5, 24, 12, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-30 12:49:07'),
(166, 6, 6, 6, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-30 12:49:08'),
(167, 6, 12, 6, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-30 12:49:08'),
(168, 6, 24, 6, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-30 12:49:08'),
(169, 6, 6, 6, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-30 12:49:08'),
(170, 6, 12, 6, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-30 12:49:08'),
(171, 6, 24, 6, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-05-30 12:49:08'),
(172, 7, 6, 12, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-05-30 12:49:10'),
(173, 7, 12, 13, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-05-30 12:49:10'),
(174, 7, 24, 13, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-05-30 12:49:10'),
(175, 7, 6, 12, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-05-30 12:49:10'),
(176, 7, 12, 13, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-05-30 12:49:10'),
(177, 7, 24, 13, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-05-30 12:49:10'),
(178, 8, 6, 3, 'safe', 'ensemble_ar7_mewma', 0.92, '2026-05-30 12:49:12'),
(179, 8, 12, 6, 'safe', 'ensemble_ar7_mewma', 0.92, '2026-05-30 12:49:12'),
(180, 8, 24, 6, 'safe', 'ensemble_ar7_mewma', 0.92, '2026-05-30 12:49:12'),
(181, 8, 6, 3, 'safe', 'ensemble_ar7_mewma', 0.92, '2026-05-30 12:49:13'),
(182, 8, 12, 6, 'safe', 'ensemble_ar7_mewma', 0.92, '2026-05-30 12:49:13'),
(183, 8, 24, 6, 'safe', 'ensemble_ar7_mewma', 0.92, '2026-05-30 12:49:13'),
(184, 1, 6, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-06-01 21:28:06'),
(185, 1, 12, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-06-01 21:28:06'),
(186, 1, 24, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-06-01 21:28:06'),
(187, 2, 6, 18, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-06-01 21:28:09'),
(188, 2, 12, 18, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-06-01 21:28:09'),
(189, 2, 24, 18, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-06-01 21:28:09'),
(190, 3, 6, 18, 'safe', 'ensemble_ar7_mewma', 0.90, '2026-06-01 21:28:11'),
(191, 3, 12, 18, 'safe', 'ensemble_ar7_mewma', 0.90, '2026-06-01 21:28:11'),
(192, 3, 24, 18, 'safe', 'ensemble_ar7_mewma', 0.90, '2026-06-01 21:28:11'),
(193, 4, 6, 16, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-06-01 21:28:14'),
(194, 4, 12, 16, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-06-01 21:28:14'),
(195, 4, 24, 16, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-06-01 21:28:14'),
(196, 5, 6, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-06-01 21:28:17'),
(197, 5, 12, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-06-01 21:28:17'),
(198, 5, 24, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-06-01 21:28:17'),
(199, 6, 6, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-06-01 21:28:20'),
(200, 6, 12, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-06-01 21:28:20'),
(201, 6, 24, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-06-01 21:28:20'),
(202, 7, 6, 14, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-06-01 21:28:23'),
(203, 7, 12, 14, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-06-01 21:28:23'),
(204, 7, 24, 14, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-06-01 21:28:23'),
(205, 8, 6, 15, 'safe', 'ensemble_ar7_mewma', 0.92, '2026-06-01 21:28:26'),
(206, 8, 12, 15, 'safe', 'ensemble_ar7_mewma', 0.92, '2026-06-01 21:28:26'),
(207, 8, 24, 15, 'safe', 'ensemble_ar7_mewma', 0.92, '2026-06-01 21:28:26'),
(208, 1, 6, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-06-09 15:30:01'),
(209, 1, 12, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-06-09 15:30:01'),
(210, 1, 24, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-06-09 15:30:01'),
(211, 2, 6, 18, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-06-09 15:30:03'),
(212, 2, 12, 18, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-06-09 15:30:03'),
(213, 2, 24, 18, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-06-09 15:30:03'),
(214, 3, 6, 18, 'safe', 'ensemble_ar7_mewma', 0.90, '2026-06-09 15:30:05'),
(215, 3, 12, 18, 'safe', 'ensemble_ar7_mewma', 0.90, '2026-06-09 15:30:05'),
(216, 3, 24, 18, 'safe', 'ensemble_ar7_mewma', 0.90, '2026-06-09 15:30:05'),
(217, 4, 6, 16, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-06-09 15:30:06'),
(218, 4, 12, 16, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-06-09 15:30:06'),
(219, 4, 24, 16, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-06-09 15:30:06'),
(220, 5, 6, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-06-09 15:30:08'),
(221, 5, 12, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-06-09 15:30:08'),
(222, 5, 24, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-06-09 15:30:08'),
(223, 6, 6, 14, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-06-09 15:30:10'),
(224, 6, 12, 15, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-06-09 15:30:10'),
(225, 6, 24, 15, 'safe', 'ensemble_ar7_mewma', 0.93, '2026-06-09 15:30:10'),
(226, 7, 6, 14, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-06-09 15:30:12'),
(227, 7, 12, 14, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-06-09 15:30:12'),
(228, 7, 24, 14, 'safe', 'ensemble_ar7_mewma', 0.94, '2026-06-09 15:30:12'),
(229, 8, 6, 15, 'safe', 'ensemble_ar7_mewma', 0.92, '2026-06-09 15:30:13'),
(230, 8, 12, 15, 'safe', 'ensemble_ar7_mewma', 0.92, '2026-06-09 15:30:13'),
(231, 8, 24, 15, 'safe', 'ensemble_ar7_mewma', 0.92, '2026-06-09 15:30:13');

-- --------------------------------------------------------

--
-- Structure de la table `fragile_profiles`
--

CREATE TABLE `fragile_profiles` (
  `user_id` int(11) NOT NULL,
  `has_asthma` tinyint(1) NOT NULL DEFAULT 0,
  `has_heart_disease` tinyint(1) NOT NULL DEFAULT 0,
  `has_allergy` tinyint(1) NOT NULL DEFAULT 0,
  `is_pregnant` tinyint(1) NOT NULL DEFAULT 0,
  `is_child` tinyint(1) NOT NULL DEFAULT 0,
  `is_elderly` tinyint(1) NOT NULL DEFAULT 0,
  `notes` varchar(255) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `fragile_profiles`
--

INSERT INTO `fragile_profiles` (`user_id`, `has_asthma`, `has_heart_disease`, `has_allergy`, `is_pregnant`, `is_child`, `is_elderly`, `notes`, `updated_at`) VALUES
(4, 1, 0, 1, 0, 0, 1, NULL, '2026-05-13 21:52:04');

-- --------------------------------------------------------

--
-- Structure de la table `fuzzy_assessments`
--

CREATE TABLE `fuzzy_assessments` (
  `id` int(11) NOT NULL,
  `reading_id` int(11) DEFAULT NULL,
  `city_id` varchar(50) DEFAULT NULL,
  `timestamp` datetime DEFAULT NULL,
  `pollution_input` float DEFAULT NULL,
  `vulnerability_input` float DEFAULT NULL,
  `symptom_severity_input` float DEFAULT NULL,
  `alerts_24h_input` float DEFAULT NULL,
  `fuzzy_score_type2` float DEFAULT NULL,
  `uncertainty_lower` float DEFAULT NULL,
  `uncertainty_upper` float DEFAULT NULL,
  `risk_level` enum('low','moderate','high','critical') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `fuzzy_reco_logs`
--

CREATE TABLE `fuzzy_reco_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `zone_id` int(11) DEFAULT NULL,
  `pollution` int(11) NOT NULL,
  `vulnerability` int(11) NOT NULL,
  `symptom_sev` int(11) NOT NULL,
  `alerts_24h` int(11) NOT NULL,
  `age` int(11) DEFAULT NULL,
  `risk_fuzzy` decimal(5,2) NOT NULL,
  `urgency_level` enum('low','moderate','high','critical') NOT NULL,
  `fired_rules` text DEFAULT NULL,
  `computed_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `fuzzy_reco_logs`
--

INSERT INTO `fuzzy_reco_logs` (`id`, `user_id`, `zone_id`, `pollution`, `vulnerability`, `symptom_sev`, `alerts_24h`, `age`, `risk_fuzzy`, `urgency_level`, `fired_rules`, `computed_at`) VALUES
(1, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:05:40'),
(2, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:05:40'),
(3, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:06:09'),
(4, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:06:28'),
(5, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:06:40'),
(6, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:06:49'),
(7, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:07:09'),
(8, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:07:10'),
(9, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:07:29'),
(10, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:07:40'),
(11, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:07:49'),
(12, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:08:09'),
(13, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:08:10'),
(14, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:08:29'),
(15, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:08:40'),
(16, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:09:27'),
(17, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:09:27'),
(18, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:09:32'),
(19, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:10:32'),
(20, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:10:32'),
(21, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:11:32'),
(22, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:11:32'),
(23, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:18:03'),
(24, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:18:03'),
(25, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:18:22'),
(26, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:18:33'),
(27, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:19:04'),
(28, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:19:34'),
(29, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:20:04'),
(30, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:20:37'),
(31, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:21:03'),
(32, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:21:34'),
(33, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:22:04'),
(34, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:23:20'),
(35, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:24:21'),
(36, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:24:43'),
(37, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:25:03'),
(38, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:25:34'),
(39, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:26:04'),
(40, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:26:56'),
(41, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:27:03'),
(42, 1, 7, 52, 0, 0, 1, 30, 38.20, 'moderate', '[{\"id\":5,\"activation\":0.65,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":9,\"activation\":0.1,\"consequent\":{\"risk\":\"MODERATE\"}}]', '2026-05-13 22:27:43'),
(43, 1, 1, 13, 0, 0, 1, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 22:28:04'),
(44, 1, 1, 13, 0, 0, 1, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 22:29:20'),
(45, 1, 1, 13, 0, 0, 1, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 22:30:21'),
(46, 1, 1, 13, 0, 0, 1, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 22:31:22'),
(47, 1, 1, 13, 0, 0, 1, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 22:32:23'),
(48, 1, 1, 13, 0, 0, 1, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 22:33:24'),
(49, 1, 1, 13, 0, 0, 1, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 22:33:58'),
(50, 1, 1, 13, 0, 0, 1, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 22:34:04'),
(51, 1, 1, 13, 0, 0, 1, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 22:34:34'),
(52, 1, 1, 13, 0, 0, 1, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 22:35:32'),
(53, 1, 5, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:10:26'),
(54, 1, 5, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:10:26'),
(55, 1, 5, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:10:56'),
(56, 1, 5, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:11:26'),
(57, 1, 5, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:11:33'),
(58, 1, 5, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:11:50'),
(59, 1, 5, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:11:53'),
(60, 1, 5, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:11:56'),
(61, 1, 5, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:12:24'),
(62, 1, 5, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:12:26'),
(63, 1, NULL, 0, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:12:31'),
(64, 1, NULL, 0, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:12:47'),
(65, 1, 5, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:12:56'),
(66, 1, NULL, 0, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:13:02'),
(67, 1, 5, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:13:15'),
(68, 1, 5, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:13:18'),
(69, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:13:32'),
(70, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:13:32'),
(71, 4, 3, 88, 5, 0, 2, 72, 84.03, 'critical', '[{\"id\":15,\"activation\":0.5,\"consequent\":{\"risk\":\"CRITICAL\"}},{\"id\":14,\"activation\":0.25,\"consequent\":{\"risk\":\"HIGH\"}}]', '2026-05-13 23:13:32'),
(72, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:14:02'),
(73, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:14:23'),
(74, 4, 3, 88, 5, 0, 2, 72, 84.03, 'critical', '[{\"id\":15,\"activation\":0.5,\"consequent\":{\"risk\":\"CRITICAL\"}},{\"id\":14,\"activation\":0.25,\"consequent\":{\"risk\":\"HIGH\"}}]', '2026-05-13 23:14:23'),
(75, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:14:32'),
(76, 4, NULL, 0, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:14:49'),
(77, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:15:02'),
(78, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:15:09'),
(79, 4, 3, 88, 5, 0, 2, 72, 84.03, 'critical', '[{\"id\":15,\"activation\":0.5,\"consequent\":{\"risk\":\"CRITICAL\"}},{\"id\":14,\"activation\":0.25,\"consequent\":{\"risk\":\"HIGH\"}}]', '2026-05-13 23:15:09'),
(80, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:15:29'),
(81, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:15:32'),
(82, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:16:02'),
(83, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:16:32'),
(84, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:17:02'),
(85, 4, NULL, 88, 5, 4, 0, 72, 84.03, 'critical', '[{\"id\":15,\"activation\":0.5,\"consequent\":{\"risk\":\"CRITICAL\"}},{\"id\":14,\"activation\":0.25,\"consequent\":{\"risk\":\"HIGH\"}}]', '2026-05-13 23:17:32'),
(86, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:17:33'),
(87, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:17:50'),
(88, 4, 3, 88, 5, 0, 2, 72, 84.03, 'critical', '[{\"id\":15,\"activation\":0.5,\"consequent\":{\"risk\":\"CRITICAL\"}},{\"id\":14,\"activation\":0.25,\"consequent\":{\"risk\":\"HIGH\"}}]', '2026-05-13 23:17:50'),
(89, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:18:02'),
(90, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:18:10'),
(91, 4, 3, 88, 5, 0, 2, 72, 84.03, 'critical', '[{\"id\":15,\"activation\":0.5,\"consequent\":{\"risk\":\"CRITICAL\"}},{\"id\":14,\"activation\":0.25,\"consequent\":{\"risk\":\"HIGH\"}}]', '2026-05-13 23:18:10'),
(92, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:18:32'),
(93, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:39:01'),
(94, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:39:01'),
(95, 4, 3, 88, 5, 0, 2, 72, 84.03, 'critical', '[{\"id\":15,\"activation\":0.5,\"consequent\":{\"risk\":\"CRITICAL\"}},{\"id\":14,\"activation\":0.25,\"consequent\":{\"risk\":\"HIGH\"}}]', '2026-05-13 23:39:01'),
(96, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:39:21'),
(97, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:39:31'),
(98, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:39:41'),
(99, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:40:01'),
(100, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:40:01'),
(101, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:40:31'),
(102, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:40:34'),
(103, 4, 3, 88, 5, 0, 2, 72, 84.03, 'critical', '[{\"id\":15,\"activation\":0.5,\"consequent\":{\"risk\":\"CRITICAL\"}},{\"id\":14,\"activation\":0.25,\"consequent\":{\"risk\":\"HIGH\"}}]', '2026-05-13 23:40:34'),
(104, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:40:40'),
(105, 4, 3, 88, 5, 0, 2, 72, 84.03, 'critical', '[{\"id\":15,\"activation\":0.5,\"consequent\":{\"risk\":\"CRITICAL\"}},{\"id\":14,\"activation\":0.25,\"consequent\":{\"risk\":\"HIGH\"}}]', '2026-05-13 23:40:40'),
(106, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:40:54'),
(107, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:40:54'),
(108, 4, 3, 88, 5, 0, 2, 72, 84.03, 'critical', '[{\"id\":15,\"activation\":0.5,\"consequent\":{\"risk\":\"CRITICAL\"}},{\"id\":14,\"activation\":0.25,\"consequent\":{\"risk\":\"HIGH\"}}]', '2026-05-13 23:40:54'),
(109, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:40:58'),
(110, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:40:58'),
(111, 4, 3, 88, 5, 0, 2, 72, 84.03, 'critical', '[{\"id\":15,\"activation\":0.5,\"consequent\":{\"risk\":\"CRITICAL\"}},{\"id\":14,\"activation\":0.25,\"consequent\":{\"risk\":\"HIGH\"}}]', '2026-05-13 23:40:58'),
(112, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:41:02'),
(113, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:41:02'),
(114, 4, 3, 88, 5, 0, 2, 72, 84.03, 'critical', '[{\"id\":15,\"activation\":0.5,\"consequent\":{\"risk\":\"CRITICAL\"}},{\"id\":14,\"activation\":0.25,\"consequent\":{\"risk\":\"HIGH\"}}]', '2026-05-13 23:41:02'),
(115, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:41:20'),
(116, 4, 3, 88, 5, 0, 2, 72, 84.03, 'critical', '[{\"id\":15,\"activation\":0.5,\"consequent\":{\"risk\":\"CRITICAL\"}},{\"id\":14,\"activation\":0.25,\"consequent\":{\"risk\":\"HIGH\"}}]', '2026-05-13 23:41:20'),
(117, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:41:32'),
(118, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:41:40'),
(119, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:42:00'),
(120, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:42:02'),
(121, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:42:20'),
(122, 4, 5, 13, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:42:32'),
(123, 2, 5, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:43:02'),
(124, 2, 5, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:43:02'),
(125, 2, 5, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:43:09'),
(126, 2, 5, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:43:32'),
(127, 2, 5, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:43:53'),
(128, 2, 5, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:43:57'),
(129, 2, 5, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:44:02'),
(130, 2, 5, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:44:12'),
(131, 2, 5, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:44:14'),
(132, 2, 5, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:44:33'),
(133, 2, 5, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-13 23:45:02'),
(134, 1, 5, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:39:35'),
(135, 1, 5, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:39:35'),
(136, 1, NULL, 88, 0, 0, 0, 30, 73.31, 'high', '[{\"id\":13,\"activation\":0.8667,\"consequent\":{\"risk\":\"HIGH\"}}]', '2026-05-14 12:39:36'),
(137, 1, 5, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:40:05'),
(138, 1, 5, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:41:21'),
(139, 1, 5, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:41:21'),
(140, 1, 5, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:42:09'),
(141, 1, 5, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:42:57'),
(142, 1, 5, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:43:05'),
(143, 1, 5, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:43:52'),
(144, 1, 5, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:43:52'),
(145, 1, 5, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:44:46'),
(146, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:44:52'),
(147, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:45:22'),
(148, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:45:52'),
(149, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:46:22'),
(150, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:46:52'),
(151, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:47:41'),
(152, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:48:42'),
(153, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:49:41'),
(154, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:50:41'),
(155, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:51:41'),
(156, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:52:16'),
(157, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:52:22'),
(158, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:52:52'),
(159, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:53:22'),
(160, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:53:29'),
(161, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:53:50'),
(162, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:53:52'),
(163, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:54:09'),
(164, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:54:22'),
(165, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:54:29'),
(166, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:54:49'),
(167, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:55:04'),
(168, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:55:09'),
(169, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:55:22'),
(170, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:55:52'),
(171, 1, NULL, 0, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:55:56'),
(172, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:56:22'),
(173, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:56:52'),
(174, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:57:41'),
(175, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:57:52'),
(176, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:58:22'),
(177, 1, NULL, 0, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:58:31'),
(178, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:58:52'),
(179, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:59:22'),
(180, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 12:59:52'),
(181, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 13:00:22'),
(182, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 13:00:52'),
(183, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 13:01:22'),
(184, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 13:02:38'),
(185, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 13:03:39'),
(186, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 13:04:19'),
(187, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 13:04:22'),
(188, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 13:04:52'),
(189, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 13:05:22'),
(190, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 13:10:25'),
(191, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 13:10:26'),
(192, 1, 6, 13, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 13:11:25'),
(193, 1, 1, 15, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 13:11:25'),
(194, 1, 1, 15, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 13:15:42'),
(195, 1, 1, 15, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 13:15:42'),
(196, 1, 1, 15, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 13:15:59'),
(197, 1, 1, 15, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 13:16:12'),
(198, 1, NULL, 0, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 13:16:25'),
(199, 1, 1, 15, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 13:16:36'),
(200, 1, 1, 15, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 13:16:42'),
(201, 1, 1, 15, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-14 13:16:44'),
(202, 1, 3, 35, 0, 0, 0, 30, 27.66, 'low', '[{\"id\":5,\"activation\":0.6,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":1,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 10:44:52'),
(203, 1, 3, 35, 0, 0, 0, 30, 27.66, 'low', '[{\"id\":5,\"activation\":0.6,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":1,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 10:44:52'),
(204, 1, 3, 35, 0, 0, 0, 30, 27.66, 'low', '[{\"id\":5,\"activation\":0.6,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":1,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 10:45:50'),
(205, 1, 3, 35, 0, 0, 0, 30, 27.66, 'low', '[{\"id\":5,\"activation\":0.6,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":1,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 10:45:57'),
(206, 1, 3, 35, 0, 0, 0, 30, 27.66, 'low', '[{\"id\":5,\"activation\":0.6,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":1,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 10:45:57'),
(207, 1, 1, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 10:46:22'),
(208, 1, 1, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 10:46:34'),
(209, 1, 1, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 10:46:52'),
(210, 1, 1, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 10:47:22'),
(211, 1, 1, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 10:47:52'),
(212, 4, 1, 11, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 10:48:28'),
(213, 4, 1, 11, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 10:48:28'),
(214, 4, 3, 88, 5, 0, 0, 72, 84.03, 'critical', '[{\"id\":15,\"activation\":0.5,\"consequent\":{\"risk\":\"CRITICAL\"}},{\"id\":14,\"activation\":0.25,\"consequent\":{\"risk\":\"HIGH\"}}]', '2026-05-25 10:48:28'),
(215, 4, NULL, 0, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 10:48:46'),
(216, 4, NULL, 0, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 10:48:53'),
(217, 4, 1, 11, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 10:48:58'),
(218, 4, 1, 11, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 10:49:28'),
(219, 4, 1, 11, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 10:49:58'),
(220, 4, 1, 11, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 10:50:28'),
(221, 4, 1, 11, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 10:50:58'),
(222, 4, 1, 11, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 10:51:28'),
(223, 4, 1, 11, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 10:52:52'),
(224, 4, 1, 11, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 10:53:52'),
(225, 4, 1, 11, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 10:54:52'),
(226, 4, 1, 11, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 10:55:52'),
(227, 4, 1, 11, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 10:56:52'),
(228, 4, 1, 11, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 10:56:58'),
(229, 4, 1, 11, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 10:57:01'),
(230, 4, 3, 88, 5, 0, 0, 72, 84.03, 'critical', '[{\"id\":15,\"activation\":0.5,\"consequent\":{\"risk\":\"CRITICAL\"}},{\"id\":14,\"activation\":0.25,\"consequent\":{\"risk\":\"HIGH\"}}]', '2026-05-25 10:57:01'),
(231, 4, 1, 11, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 10:57:28'),
(232, 4, 1, 11, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 10:57:58'),
(233, 2, 1, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 13:55:27'),
(234, 2, 1, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 13:55:27'),
(235, 2, 1, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 13:55:39'),
(236, 2, 1, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 13:55:57'),
(237, 2, 1, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 13:56:27'),
(238, 2, 1, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 13:57:12'),
(239, 2, 1, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 13:57:27'),
(240, 2, 1, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 13:57:57'),
(241, 2, 1, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 13:58:03'),
(242, 2, 1, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 13:58:07'),
(243, 2, 1, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 13:58:27'),
(244, 2, 1, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 13:58:57'),
(245, 2, 1, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 13:59:42'),
(246, 2, 1, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 14:00:42'),
(247, 2, 1, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 14:01:42'),
(248, 2, 1, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 14:02:42'),
(249, 2, 1, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-25 14:03:42'),
(250, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:48:54'),
(251, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:48:54'),
(252, 1, NULL, 88, 0, 0, 0, 30, 73.31, 'high', '[{\"id\":13,\"activation\":0.8667,\"consequent\":{\"risk\":\"HIGH\"}}]', '2026-05-30 12:48:54'),
(253, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:49:24'),
(254, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:49:24'),
(255, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:49:24'),
(256, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:49:44'),
(257, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:49:54'),
(258, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:49:54'),
(259, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:50:04'),
(260, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:50:24'),
(261, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:50:24'),
(262, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:50:24'),
(263, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:50:44'),
(264, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:50:54'),
(265, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:50:54'),
(266, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:51:04'),
(267, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:51:24'),
(268, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:51:24'),
(269, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:51:24'),
(270, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:51:49'),
(271, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:51:54'),
(272, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:51:54'),
(273, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:52:24'),
(274, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:52:41'),
(275, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:52:49'),
(276, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:53:42'),
(277, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:53:49'),
(278, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:53:49'),
(279, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:54:43'),
(280, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:54:49'),
(281, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:54:49'),
(282, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:55:08'),
(283, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:55:08'),
(284, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:55:24'),
(285, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:55:24'),
(286, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:55:28'),
(287, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:55:44'),
(288, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:55:54'),
(289, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:56:11'),
(290, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:56:19'),
(291, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:56:24'),
(292, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:56:45'),
(293, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:56:54'),
(294, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:57:24'),
(295, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:57:46'),
(296, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:57:54'),
(297, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:57:58'),
(298, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:58:24'),
(299, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:58:47'),
(300, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:58:54'),
(301, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:59:24'),
(302, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:59:48'),
(303, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 12:59:54'),
(304, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:00:24'),
(305, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:00:49'),
(306, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:00:54'),
(307, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:01:24'),
(308, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:01:49'),
(309, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:02:40');
INSERT INTO `fuzzy_reco_logs` (`id`, `user_id`, `zone_id`, `pollution`, `vulnerability`, `symptom_sev`, `alerts_24h`, `age`, `risk_fuzzy`, `urgency_level`, `fired_rules`, `computed_at`) VALUES
(310, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:02:49'),
(311, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:03:41'),
(312, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:03:49'),
(313, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:04:42'),
(314, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:04:49'),
(315, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:05:43'),
(316, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:05:49'),
(317, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:06:44'),
(318, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:06:49'),
(319, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:06:54'),
(320, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:07:24'),
(321, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:07:49'),
(322, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:08:40'),
(323, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:08:49'),
(324, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:09:41'),
(325, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:09:49'),
(326, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:10:42'),
(327, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:10:49'),
(328, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:11:43'),
(329, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:11:49'),
(330, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:12:44'),
(331, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:12:49'),
(332, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:13:45'),
(333, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:13:49'),
(334, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:14:46'),
(335, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:14:49'),
(336, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:15:47'),
(337, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:15:49'),
(338, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:16:48'),
(339, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:16:49'),
(340, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:17:49'),
(341, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:17:49'),
(342, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:18:49'),
(343, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:18:49'),
(344, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:19:49'),
(345, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:19:49'),
(346, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:20:49'),
(347, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:20:49'),
(348, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:21:49'),
(349, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:21:49'),
(350, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:22:49'),
(351, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:22:49'),
(352, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:23:49'),
(353, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:23:49'),
(354, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:24:49'),
(355, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:24:49'),
(356, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:25:49'),
(357, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:25:49'),
(358, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:26:49'),
(359, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:26:49'),
(360, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:27:49'),
(361, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:27:49'),
(362, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:28:49'),
(363, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:28:49'),
(364, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:29:49'),
(365, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:29:49'),
(366, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:30:49'),
(367, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:30:49'),
(368, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:31:49'),
(369, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:31:49'),
(370, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:32:49'),
(371, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:32:49'),
(372, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:33:49'),
(373, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:33:49'),
(374, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:34:49'),
(375, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:34:49'),
(376, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:35:49'),
(377, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:35:49'),
(378, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:36:49'),
(379, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:36:49'),
(380, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:37:49'),
(381, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:37:49'),
(382, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:38:49'),
(383, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:38:49'),
(384, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:39:49'),
(385, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:39:49'),
(386, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:40:49'),
(387, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:40:49'),
(388, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:41:49'),
(389, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:41:49'),
(390, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:42:49'),
(391, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:42:49'),
(392, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:43:49'),
(393, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 13:43:49'),
(394, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:02:31'),
(395, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:02:31'),
(396, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:03:49'),
(397, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:03:49'),
(398, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:04:49'),
(399, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:04:49'),
(400, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:05:49'),
(401, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:05:49'),
(402, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:06:49'),
(403, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:06:49'),
(404, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:07:49'),
(405, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:07:49'),
(406, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:08:49'),
(407, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:08:49'),
(408, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:09:49'),
(409, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:09:49'),
(410, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:10:49'),
(411, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:10:49'),
(412, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:11:49'),
(413, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:11:49'),
(414, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:12:49'),
(415, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:12:49'),
(416, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:13:49'),
(417, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:13:49'),
(418, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:14:49'),
(419, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:14:49'),
(420, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:15:49'),
(421, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:15:49'),
(422, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:16:49'),
(423, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:16:49'),
(424, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:17:49'),
(425, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:17:49'),
(426, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:18:49'),
(427, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:18:49'),
(428, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:19:49'),
(429, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:19:49'),
(430, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:20:49'),
(431, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:20:49'),
(432, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:21:49'),
(433, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:21:49'),
(434, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:22:49'),
(435, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:22:49'),
(436, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:23:49'),
(437, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:23:49'),
(438, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:24:49'),
(439, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:24:49'),
(440, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:25:49'),
(441, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:25:49'),
(442, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:26:49'),
(443, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:26:49'),
(444, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:27:49'),
(445, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:27:49'),
(446, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:28:49'),
(447, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:28:49'),
(448, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:29:49'),
(449, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:29:49'),
(450, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:30:49'),
(451, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:30:49'),
(452, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:31:49'),
(453, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:31:49'),
(454, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:32:49'),
(455, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:32:49'),
(456, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:33:49'),
(457, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:33:49'),
(458, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:34:49'),
(459, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:34:49'),
(460, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:35:49'),
(461, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:35:49'),
(462, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:36:49'),
(463, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:36:49'),
(464, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:37:49'),
(465, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:37:49'),
(466, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:38:49'),
(467, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:38:49'),
(468, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:39:50'),
(469, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:39:50'),
(470, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:40:49'),
(471, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:40:49'),
(472, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:41:49'),
(473, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:41:49'),
(474, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:42:49'),
(475, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:42:49'),
(476, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:43:49'),
(477, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:43:49'),
(478, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:44:49'),
(479, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:44:49'),
(480, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:45:49'),
(481, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:45:49'),
(482, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:46:49'),
(483, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:46:49'),
(484, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:47:49'),
(485, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:47:49'),
(486, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:48:49'),
(487, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:48:49'),
(488, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:49:49'),
(489, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:49:49'),
(490, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:50:49'),
(491, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:50:49'),
(492, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:51:49'),
(493, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:51:49'),
(494, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:52:49'),
(495, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:52:49'),
(496, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:53:49'),
(497, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:53:49'),
(498, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:54:49'),
(499, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:54:49'),
(500, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:55:49'),
(501, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:55:49'),
(502, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:56:49'),
(503, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:56:49'),
(504, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:57:49'),
(505, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:57:49'),
(506, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:58:49'),
(507, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:58:49'),
(508, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:59:49'),
(509, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 14:59:49'),
(510, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:00:49'),
(511, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:00:49'),
(512, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:01:49'),
(513, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:01:49'),
(514, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:02:49'),
(515, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:02:49'),
(516, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:03:49'),
(517, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:03:49'),
(518, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:04:49'),
(519, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:04:49'),
(520, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:05:49'),
(521, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:05:49'),
(522, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:06:49'),
(523, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:06:49'),
(524, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:07:49'),
(525, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:07:49'),
(526, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:08:49'),
(527, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:08:49'),
(528, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:09:49'),
(529, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:09:49'),
(530, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:10:49'),
(531, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:10:49'),
(532, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:11:49'),
(533, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:11:49'),
(534, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:12:49'),
(535, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:12:49'),
(536, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:13:49'),
(537, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:13:49'),
(538, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:14:49'),
(539, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:14:49'),
(540, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:15:49'),
(541, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:15:49'),
(542, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:16:49'),
(543, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:16:49'),
(544, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:17:49'),
(545, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:17:49'),
(546, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:18:49'),
(547, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:18:49'),
(548, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:19:49'),
(549, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:19:49'),
(550, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:20:49'),
(551, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:20:49'),
(552, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:21:49'),
(553, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:21:49'),
(554, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:22:49'),
(555, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:22:49'),
(556, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:23:49'),
(557, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:23:49'),
(558, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:24:49'),
(559, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:24:49'),
(560, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:25:49'),
(561, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:25:49'),
(562, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:26:49'),
(563, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:26:49'),
(564, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:27:49'),
(565, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:27:49'),
(566, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:28:49'),
(567, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:28:49'),
(568, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:29:49'),
(569, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:29:49'),
(570, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:30:49'),
(571, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:30:49'),
(572, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:31:49'),
(573, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:31:49'),
(574, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:32:49'),
(575, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:32:49'),
(576, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:33:49'),
(577, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:33:49'),
(578, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:34:49'),
(579, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:34:49'),
(580, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:35:49'),
(581, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:35:49'),
(582, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:36:49'),
(583, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:36:49'),
(584, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:37:49'),
(585, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:37:49'),
(586, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:38:49'),
(587, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:38:49'),
(588, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:39:49'),
(589, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:39:49'),
(590, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:40:49'),
(591, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:40:49'),
(592, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:41:49'),
(593, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:41:49'),
(594, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:42:49'),
(595, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-05-30 15:42:49'),
(596, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 10:25:27'),
(597, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 10:25:27'),
(598, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 10:28:43'),
(599, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 10:29:43'),
(600, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 10:30:43'),
(601, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 10:31:26'),
(602, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:27:23'),
(603, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:27:23'),
(604, 1, NULL, 88, 0, 0, 0, 30, 73.31, 'high', '[{\"id\":13,\"activation\":0.8667,\"consequent\":{\"risk\":\"HIGH\"}}]', '2026-06-01 21:27:23'),
(605, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:27:43'),
(606, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:27:53'),
(607, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:28:23'),
(608, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:28:53'),
(609, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:29:23'),
(610, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:29:27'),
(611, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:29:48'),
(612, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:29:53'),
(613, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:30:08'),
(614, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:30:23'),
(615, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:30:53'),
(616, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:31:35'),
(617, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:31:35'),
(618, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:31:43'),
(619, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:32:05'),
(620, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:32:35'),
(621, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:33:05'),
(622, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:33:35'),
(623, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:34:05'),
(624, 1, NULL, 0, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:34:25'),
(625, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:34:35'),
(626, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:35:05'),
(627, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:35:35'),
(628, 1, NULL, 0, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:35:52'),
(629, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:36:05'),
(630, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:36:35'),
(631, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:37:05'),
(632, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:37:35'),
(633, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:38:05'),
(634, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:38:35'),
(635, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:39:05'),
(636, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:39:35'),
(637, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:40:05'),
(638, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:40:35'),
(639, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:41:05'),
(640, 1, NULL, 0, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:41:15'),
(641, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:41:35'),
(642, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:42:05'),
(643, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:42:35'),
(644, 1, NULL, 0, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:42:37'),
(645, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:43:05'),
(646, 1, NULL, 0, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:43:17'),
(647, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:43:35'),
(648, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:43:48'),
(649, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:43:53'),
(650, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:44:03'),
(651, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:44:05'),
(652, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:44:12'),
(653, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:44:35'),
(654, 1, NULL, 0, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:45:02'),
(655, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:45:05'),
(656, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:45:05'),
(657, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:45:25'),
(658, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:45:31'),
(659, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:45:35'),
(660, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:45:35'),
(661, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:45:35'),
(662, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:45:40'),
(663, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:45:42'),
(664, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:45:45'),
(665, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:45:55'),
(666, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:46:05'),
(667, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:46:35'),
(668, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:47:05'),
(669, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:47:35'),
(670, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:48:05'),
(671, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:49:31'),
(672, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:50:31'),
(673, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:51:31'),
(674, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-01 21:52:31'),
(675, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-02 10:15:20'),
(676, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-02 10:16:20'),
(677, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-09 15:29:35'),
(678, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-09 15:29:35'),
(679, 1, NULL, 88, 0, 0, 0, 30, 73.31, 'high', '[{\"id\":13,\"activation\":0.8667,\"consequent\":{\"risk\":\"HIGH\"}}]', '2026-06-09 15:29:36'),
(680, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-09 15:30:05'),
(681, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-09 15:30:35'),
(682, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-09 15:31:05'),
(683, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-09 15:31:11');
INSERT INTO `fuzzy_reco_logs` (`id`, `user_id`, `zone_id`, `pollution`, `vulnerability`, `symptom_sev`, `alerts_24h`, `age`, `risk_fuzzy`, `urgency_level`, `fired_rules`, `computed_at`) VALUES
(684, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-09 15:31:35'),
(685, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-09 15:31:45'),
(686, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-09 15:32:05'),
(687, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-09 15:32:06'),
(688, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-09 15:32:26'),
(689, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-09 15:32:36'),
(690, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-09 15:32:46'),
(691, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-09 15:33:05'),
(692, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-09 15:33:35'),
(693, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-09 15:34:05'),
(694, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-09 15:34:35'),
(695, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-09 15:35:05'),
(696, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-09 15:35:35'),
(697, 1, 8, 11, 0, 0, 0, 30, 11.58, 'low', '[{\"id\":1,\"activation\":1,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-09 15:35:56'),
(698, 4, 8, 11, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-09 15:36:14'),
(699, 4, 8, 11, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-09 15:36:14'),
(700, 4, 3, 88, 5, 0, 0, 72, 84.03, 'critical', '[{\"id\":15,\"activation\":0.5,\"consequent\":{\"risk\":\"CRITICAL\"}},{\"id\":14,\"activation\":0.25,\"consequent\":{\"risk\":\"HIGH\"}}]', '2026-06-09 15:36:15'),
(701, 4, 8, 11, 5, 0, 0, 72, 27.07, 'low', '[{\"id\":3,\"activation\":0.5,\"consequent\":{\"risk\":\"LOW\"}},{\"id\":2,\"activation\":0.25,\"consequent\":{\"risk\":\"SAFE\"}}]', '2026-06-09 15:36:45');

-- --------------------------------------------------------

--
-- Structure de la table `learn_resources`
--

CREATE TABLE `learn_resources` (
  `id` int(11) NOT NULL,
  `slug` varchar(140) NOT NULL,
  `kind` enum('article','video','infographic','quiz') NOT NULL DEFAULT 'article',
  `category` varchar(60) NOT NULL,
  `language` varchar(8) NOT NULL DEFAULT 'en',
  `title` varchar(220) NOT NULL,
  `summary` text DEFAULT NULL,
  `body` mediumtext DEFAULT NULL,
  `media_url` varchar(500) DEFAULT NULL,
  `thumbnail` varchar(500) DEFAULT NULL,
  `duration_min` int(11) DEFAULT NULL,
  `reading_min` int(11) DEFAULT NULL,
  `level` enum('beginner','intermediate','advanced') NOT NULL DEFAULT 'beginner',
  `is_published` tinyint(1) NOT NULL DEFAULT 1,
  `views` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `learn_resources`
--

INSERT INTO `learn_resources` (`id`, `slug`, `kind`, `category`, `language`, `title`, `summary`, `body`, `media_url`, `thumbnail`, `duration_min`, `reading_min`, `level`, `is_published`, `views`, `created_at`, `updated_at`) VALUES
(1, 'what-is-pm25', 'article', 'pollution', 'en', 'What is PM2.5 and why does it matter?', 'Fine particles smaller than 2.5 microns penetrate deep into the lungs and bloodstream. Learn how they affect your health and what you can do.', '## What is PM2.5?\n\nPM2.5 refers to airborne particles with a diameter of 2.5 micrometers or less — about 1/30th the width of a human hair. They come from combustion (vehicles, industry, biomass burning) and chemical reactions in the atmosphere.\n\n## Why are they dangerous?\n- They bypass the nose\'s filtering system\n- They reach deep into the alveoli of the lungs\n- They can enter the bloodstream and affect the heart and brain\n\n## In Gabès\nThe Groupe Chimique Tunisien (GCT) and surrounding industry are major contributors. Wind from the south-east often carries plumes toward residential zones.\n\n## What you can do\n1. Check the daily air quality before going out.\n2. Keep windows closed during pollution peaks.\n3. Wear an FFP2 mask if you must go outside on critical days.\n4. Use an air purifier with a HEPA filter at home if possible.', NULL, NULL, NULL, 4, 'beginner', 1, 2, '2026-05-03 13:38:33', '2026-05-03 14:59:27'),
(2, 'asthma-prevention-gabes', 'article', 'asthma', 'en', 'Asthma in Gabès: practical prevention tips', 'Asthma rates are higher in industrial regions. Concrete steps to reduce attacks and protect your family.', '## Why Gabès residents are at higher risk\nLong-term exposure to SO2, NO2, and fine particles inflames the airways and increases asthma severity.\n\n## Daily routine\n- Track air quality before leaving home\n- Carry your reliever inhaler at all times\n- Avoid outdoor exercise on critical days\n- Keep a 7-day diary of symptoms and inhaler use\n\n## Warning signs requiring a doctor\n- Wheezing that doesn\'t respond to inhaler\n- Difficulty speaking in full sentences\n- Bluish lips or fingertips\n- Resting heart rate above 120 bpm\n\n## Use the Nafass platform\nReport symptoms in the app. The system correlates spikes with pollution data and can request a telemedicine consultation in one click.', NULL, NULL, NULL, 5, 'beginner', 1, 3, '2026-05-03 13:38:33', '2026-06-01 21:46:32'),
(3, 'how-to-wear-ffp2', 'video', 'mask', 'en', 'How to wear an FFP2 mask correctly', 'A 2-minute demo: fit, seal, and reuse rules for FFP2 respirators.', NULL, 'https://www.youtube.com/embed/lrvFrH_npQI', NULL, 2, NULL, 'beginner', 1, 5, '2026-05-03 13:38:33', '2026-06-01 21:46:36'),
(4, 'children-pollution-protection', 'article', 'children', 'en', 'Protecting children from air pollution', 'Children breathe faster than adults and absorb more pollutants per kilo of body weight. Specific guidance for parents and schools.', '## Why kids are more vulnerable\n- Higher breathing rate per kg\n- Lungs still developing until ~18\n- Spend more time outdoors\n\n## At school\n- Indoor recess on critical days\n- Air quality monitor in the classroom\n- Teach children the visual air quality cues\n\n## At home\n- No smoking indoors (passive exposure)\n- Cooking with hood ventilation\n- Avoid scented candles / aerosols\n\n## When to keep them home\nSchool Mode (built-in to Nafass) automatically suspends activities at critical thresholds. Trust the system and follow the alerts.', NULL, NULL, NULL, 6, 'beginner', 1, 4, '2026-05-03 13:38:33', '2026-06-01 21:46:26'),
(5, 'first-aid-respiratory-distress', 'article', 'first-aid', 'en', 'First aid for respiratory distress', 'What to do when someone struggles to breathe — before the ambulance arrives.', '## Recognise the emergency\n- Gasping, blue lips, confusion → call 190 (Tunisian SAMU) immediately\n- Mild shortness of breath → seat the person upright, loosen clothing\n\n## Steps\n1. Sit them upright (do not lay them down)\n2. Open windows for fresh air, or move to a less-polluted indoor room with closed windows\n3. If they have a prescribed inhaler — help them use it\n4. Stay calm and reassuring; panic worsens breathing\n5. If unconscious → check breathing, start CPR if needed\n\n## Do not\n- Force them to drink\n- Give unprescribed medication\n- Leave them alone', NULL, NULL, NULL, 4, 'beginner', 1, 4, '2026-05-03 13:38:33', '2026-05-03 17:40:26'),
(6, 'pollution-quiz-1', 'quiz', 'pollution', 'en', 'Test your knowledge: air pollution basics', 'A 5-question quiz to check what you know about pollution in Gabès.', '[{\"q\":\"Which pollutant is most associated with the Gabès phosphate industry?\",\"options\":[\"CO2\",\"SO2\",\"CFC\",\"Methane\"],\"answer\":1},{\"q\":\"PM2.5 stands for…\",\"options\":[\"Particulate matter < 2.5 mm\",\"Particulate matter < 2.5 micrometers\",\"Pollution mass 2.5 kg\",\"Phosphate mineral 2.5\"],\"answer\":1},{\"q\":\"Which mask is most effective for fine particles?\",\"options\":[\"Cloth mask\",\"Surgical mask\",\"FFP2 / N95\",\"Bandana\"],\"answer\":2},{\"q\":\"On a critical day, you should…\",\"options\":[\"Go for a long run outside\",\"Open all windows\",\"Stay indoors with windows closed\",\"Drive with windows down\"],\"answer\":2},{\"q\":\"Children are more vulnerable to pollution because…\",\"options\":[\"They eat more sugar\",\"They breathe faster relative to weight\",\"They sleep more\",\"They drink less water\"],\"answer\":1}]', NULL, NULL, NULL, NULL, 'beginner', 1, 2, '2026-05-03 13:38:33', '2026-05-03 13:45:49'),
(7, 'what-is-pm25-ar', 'article', 'pollution', 'ar', 'ما هو PM2.5 ولماذا يهمّ؟', 'الجسيمات الدقيقة الأصغر من 2.5 ميكرون تخترق عميقاً في الرئتين ومجرى الدم. تعرّف على آثارها الصحية وكيفية الوقاية.', '## ما هو PM2.5؟\n\nيشير PM2.5 إلى الجسيمات المحمولة جواً التي يقلّ قطرها عن 2.5 ميكرومتر — أي حوالي 1/30 من سمك شعرة الإنسان. وتأتي من الاحتراق (السيارات، الصناعة، حرق الكتلة الحيوية).\n\n## لماذا هي خطيرة؟\n- تتجاوز نظام الترشيح في الأنف\n- تصل إلى عمق الحويصلات الرئوية\n- يمكنها دخول مجرى الدم وتؤثر على القلب والدماغ\n\n## في قابس\nالمجمع الكيميائي التونسي والصناعات المحيطة من أكبر المساهمين. غالباً ما تحمل رياح الجنوب الشرقي السحب نحو المناطق السكنية.\n\n## ما يمكنك فعله\n1. تحقّق من جودة الهواء يومياً قبل الخروج.\n2. أبقِ النوافذ مغلقة خلال ذروة التلوّث.\n3. ارتدِ كمامة FFP2 إذا اضطررت للخروج في الأيام الحرجة.\n4. استخدم منقّي هواء بفلتر HEPA في المنزل عند الإمكان.', NULL, NULL, NULL, 4, 'beginner', 1, 2, '2026-05-03 13:38:33', '2026-05-03 14:29:59');

-- --------------------------------------------------------

--
-- Structure de la table `model_performance`
--

CREATE TABLE `model_performance` (
  `id` int(11) NOT NULL,
  `model_name` varchar(100) DEFAULT NULL,
  `city_id` varchar(50) DEFAULT NULL,
  `evaluated_at` datetime DEFAULT NULL,
  `accuracy` float DEFAULT NULL,
  `precision_macro` float DEFAULT NULL,
  `recall_macro` float DEFAULT NULL,
  `f1_macro` float DEFAULT NULL,
  `mae` float DEFAULT NULL,
  `rmse` float DEFAULT NULL,
  `mape` float DEFAULT NULL,
  `smape` float DEFAULT NULL,
  `r_squared` float DEFAULT NULL,
  `auc_roc` float DEFAULT NULL,
  `avg_latency_ms` float DEFAULT NULL,
  `cv_results` text DEFAULT NULL,
  `improvement_vs_baseline` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `target_role` varchar(40) DEFAULT 'all',
  `target_user_id` int(11) DEFAULT NULL,
  `title` varchar(160) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `level` enum('info','warning','danger') DEFAULT 'info',
  `priority` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `notifications`
--

INSERT INTO `notifications` (`id`, `target_role`, `target_user_id`, `title`, `message`, `level`, `priority`, `created_at`, `is_read`) VALUES
(1, 'citizen', NULL, 'Alerte locale', 'Risque élevé dans votre zone, limitez les sorties.', 'danger', 0, '2026-05-13 20:00:15', 0),
(2, 'school', NULL, 'Mode école recommandé', 'Risque critique à Ghannouch — suspension activités plein air conseillée.', 'danger', 0, '2026-05-13 20:00:15', 0),
(3, 'health', NULL, 'Pic SO₂', 'Surveillance continue des zones critiques.', 'warning', 0, '2026-05-13 20:00:15', 0),
(4, 'all', NULL, 'Système opérationnel', 'Application Gabes Tatenafas connectée à la base locale.', 'info', 0, '2026-05-13 20:00:15', 0),
(5, 'all', NULL, '[AUTO:school] School closed — École Chott Essalem', 'École Chott Essalem (Chatt Salem) is suspending activities due to environmental risk. Parents and staff: shelter-in-place guidance.', 'danger', 0, '2026-06-01 21:45:31', 0),
(6, 'all', NULL, '[AUTO:school] Reopening — École Chott Essalem', 'École Chott Essalem (Chatt Salem) is resuming normal activities. The zone is no longer at critical threshold.', 'info', 0, '2026-06-01 21:45:35', 0),
(7, 'all', NULL, '[AUTO:school] Watch — École primaire Ghannouch 1', 'École primaire Ghannouch 1 (Ghannouche) under heightened watch. Limit intense activities.', 'warning', 0, '2026-06-01 21:45:40', 0),
(8, 'all', NULL, '[AUTO:school] Watch — École Chott Essalem', 'École Chott Essalem (Chatt Salem) under heightened watch. Limit intense activities.', 'warning', 0, '2026-06-01 21:45:42', 0);

-- --------------------------------------------------------

--
-- Structure de la table `personal_diary`
--

CREATE TABLE `personal_diary` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `diary_date` date NOT NULL,
  `mood` tinyint(4) NOT NULL DEFAULT 3,
  `cough` tinyint(4) NOT NULL DEFAULT 0,
  `breath_diff` tinyint(4) NOT NULL DEFAULT 0,
  `eye_irritation` tinyint(4) NOT NULL DEFAULT 0,
  `headache` tinyint(4) NOT NULL DEFAULT 0,
  `fatigue` tinyint(4) NOT NULL DEFAULT 0,
  `notes` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `personal_diary`
--

INSERT INTO `personal_diary` (`id`, `user_id`, `diary_date`, `mood`, `cough`, `breath_diff`, `eye_irritation`, `headache`, `fatigue`, `notes`, `created_at`) VALUES
(1, 4, '2026-05-13', 3, 2, 4, 1, 2, 2, 'test', '2026-05-13 23:14:46'),
(2, 1, '2026-05-14', 3, 3, 1, 2, 1, 4, NULL, '2026-05-14 12:55:54'),
(3, 1, '2026-05-25', 3, 4, 2, 2, 4, 3, NULL, '2026-05-25 10:36:22'),
(4, 4, '2026-05-25', 3, 2, 3, 1, 2, 4, NULL, '2026-05-25 10:37:24'),
(5, 1, '2026-06-01', 1, 5, 5, 5, 5, 5, 'bach nmot', '2026-06-01 21:33:51');

-- --------------------------------------------------------

--
-- Structure de la table `pollution_forecast`
--

CREATE TABLE `pollution_forecast` (
  `id` int(11) NOT NULL,
  `zone_id` int(11) NOT NULL,
  `horizon_hours` tinyint(4) NOT NULL,
  `predicted_score` tinyint(4) NOT NULL,
  `predicted_level` enum('safe','warning','critical') NOT NULL DEFAULT 'safe',
  `computed_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `rate_limits`
--

CREATE TABLE `rate_limits` (
  `id` int(11) NOT NULL,
  `scope_key` varchar(80) NOT NULL,
  `action_type` varchar(40) NOT NULL,
  `occurred_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `rate_limits`
--

INSERT INTO `rate_limits` (`id`, `scope_key`, `action_type`, `occurred_at`) VALUES
(1, 'uid:4|reports', 'reports', '2026-05-13 23:17:09'),
(2, 'uid:4|triage', 'triage', '2026-05-13 23:17:32'),
(3, 'uid:4|symptoms', 'symptoms', '2026-05-13 23:42:45');

-- --------------------------------------------------------

--
-- Structure de la table `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `zone_id` int(11) DEFAULT NULL,
  `citizen_name` varchar(120) DEFAULT 'Anonyme',
  `category` enum('odor','smoke','breathing','dust','noise','other') DEFAULT 'other',
  `description` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `ai_analysis` text DEFAULT NULL,
  `ai_category` varchar(40) DEFAULT NULL,
  `ai_severity` tinyint(4) DEFAULT NULL,
  `ai_intensity` varchar(20) DEFAULT NULL,
  `ai_fake_score` tinyint(4) DEFAULT NULL,
  `image_hash` char(64) DEFAULT NULL,
  `ai_analysis_at` datetime DEFAULT NULL,
  `reported_at` datetime DEFAULT current_timestamp(),
  `status` enum('pending','validated','rejected') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `reports`
--

INSERT INTO `reports` (`id`, `zone_id`, `citizen_name`, `category`, `description`, `image_path`, `ai_analysis`, `ai_category`, `ai_severity`, `ai_intensity`, `ai_fake_score`, `image_hash`, `ai_analysis_at`, `reported_at`, `status`) VALUES
(1, 3, 'Ahmed B.', 'smoke', 'Fumée épaisse au-dessus de l\'usine ce matin.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-13 16:00:15', 'validated'),
(2, 2, 'Salma K.', 'odor', 'Forte odeur de soufre près du port.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-13 13:00:15', 'pending'),
(3, 1, 'Anonyme', 'breathing', 'Difficulté à respirer en marchant.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-12 20:00:15', 'validated'),
(4, 3, 'Imen T.', 'dust', 'Beaucoup de poussière dans l\'air.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-13 18:00:15', 'pending'),
(5, 4, 'Anonyme', 'odor', 'Odeur chimique inhabituelle.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-13 12:00:15', 'pending'),
(6, 7, 'Mohamed S.', 'smoke', 'Fumée noire visible depuis ma fenêtre.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-13 19:30:15', 'pending'),
(7, 2, 'rejeb ilyes', 'smoke', 'i see this now ', 'uploads/reports/report-20260513-221709-39e09d7d.jpg', 'The image shows a significant plume of smoke emitted from industrial chimneys, likely from a power plant or industrial facility. The smoke appears thick and white, indicating a substantial release of pollutants into the atmosphere.', 'smoke', 8, 'high', 0, '015cf5da5f093bea002d46ba43d9edba39ea0490ef3f3e7fe0e4998992f8cf1d', '2026-05-13 23:17:11', '2026-05-13 23:17:09', 'pending');

-- --------------------------------------------------------

--
-- Structure de la table `reports_pdf`
--

CREATE TABLE `reports_pdf` (
  `id` int(11) NOT NULL,
  `title` varchar(160) DEFAULT NULL,
  `period` varchar(40) DEFAULT NULL,
  `filename` varchar(200) DEFAULT NULL,
  `generated_at` datetime DEFAULT current_timestamp(),
  `generated_by` varchar(80) DEFAULT 'health_authority'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `reports_pdf`
--

INSERT INTO `reports_pdf` (`id`, `title`, `period`, `filename`, `generated_at`, `generated_by`) VALUES
(1, 'Rapport quotidien — Qualité air', 'daily', 'rapport-quotidien.pdf', '2026-05-13 20:00:15', 'health_authority'),
(2, 'Rapport hebdomadaire', 'weekly', 'rapport-hebdo.pdf', '2026-05-13 20:00:15', 'health_authority'),
(3, 'Synthèse mensuelle', 'monthly', 'rapport-mensuel.pdf', '2026-05-13 20:00:15', 'health_authority'),
(4, 'Weekly Report', 'weekly', 'weekly-20260513-2244.html', '2026-05-13 23:44:16', 'health_authority'),
(5, 'Weekly Report', 'weekly', 'weekly-20260513-2244.html', '2026-05-13 23:44:19', 'health_authority'),
(6, 'Weekly Report', 'weekly', 'weekly-20260513-2244.html', '2026-05-13 23:44:23', 'health_authority'),
(7, 'Daily Report', 'daily', 'daily-20260513-2244.html', '2026-05-13 23:44:47', 'health_authority'),
(8, 'Daily Report', 'daily', 'daily-20260601-2131.html', '2026-06-01 21:31:48', 'health_authority');

-- --------------------------------------------------------

--
-- Structure de la table `risk_scores`
--

CREATE TABLE `risk_scores` (
  `id` int(11) NOT NULL,
  `zone_id` int(11) DEFAULT NULL,
  `score` int(11) DEFAULT 0,
  `level` enum('safe','warning','critical') DEFAULT 'safe',
  `computed_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `risk_scores`
--

INSERT INTO `risk_scores` (`id`, `zone_id`, `score`, `level`, `computed_at`) VALUES
(1, 1, 48, 'warning', '2026-05-13 20:00:15'),
(2, 2, 78, 'critical', '2026-05-13 20:00:15'),
(3, 3, 88, 'critical', '2026-05-13 20:00:15'),
(4, 4, 41, 'warning', '2026-05-13 20:00:15'),
(5, 5, 18, 'safe', '2026-05-13 20:00:15'),
(6, 6, 22, 'safe', '2026-05-13 20:00:15'),
(7, 7, 52, 'warning', '2026-05-13 20:00:15'),
(8, 8, 16, 'safe', '2026-05-13 20:00:15'),
(9, 1, 13, 'safe', '2026-05-13 21:29:23'),
(10, 2, 22, 'safe', '2026-05-13 21:29:25'),
(11, 3, 33, 'safe', '2026-05-13 21:29:26'),
(12, 4, 18, 'safe', '2026-05-13 21:29:27'),
(13, 5, 13, 'safe', '2026-05-13 21:29:28'),
(14, 6, 13, 'safe', '2026-05-13 22:27:19'),
(15, 7, 16, 'safe', '2026-05-13 22:27:38'),
(16, 8, 13, 'safe', '2026-05-13 22:27:43'),
(17, 1, 12, 'safe', '2026-05-13 23:07:52'),
(18, 2, 21, 'safe', '2026-05-13 23:07:57'),
(19, 3, 32, 'safe', '2026-05-13 23:08:07'),
(20, 1, 15, 'safe', '2026-05-14 12:40:45'),
(21, 2, 27, 'safe', '2026-05-14 12:41:01'),
(22, 4, 15, 'safe', '2026-05-14 12:42:05'),
(23, 1, 15, 'safe', '2026-05-14 12:42:22'),
(24, 5, 17, 'safe', '2026-05-14 12:44:41'),
(25, 6, 15, 'safe', '2026-05-14 13:11:23'),
(26, 1, 15, 'safe', '2026-05-14 13:11:59'),
(27, 2, 24, 'safe', '2026-05-14 13:12:08'),
(28, 3, 35, 'safe', '2026-05-14 13:12:16'),
(29, 4, 15, 'safe', '2026-05-14 13:12:34'),
(30, 6, 15, 'safe', '2026-05-14 13:13:13'),
(31, 8, 15, 'safe', '2026-05-14 13:14:01'),
(32, 1, 11, 'safe', '2026-05-25 10:34:25'),
(33, 2, 11, 'safe', '2026-05-25 10:34:35'),
(34, 6, 11, 'safe', '2026-05-25 10:35:08'),
(35, 7, 11, 'safe', '2026-05-25 10:35:11'),
(36, 1, 11, 'safe', '2026-05-25 10:35:17'),
(37, 4, 11, 'safe', '2026-05-25 10:35:26'),
(38, 5, 11, 'safe', '2026-05-25 10:35:28'),
(39, 6, 11, 'safe', '2026-05-25 10:35:31'),
(40, 7, 11, 'safe', '2026-05-25 10:35:33'),
(41, 8, 11, 'safe', '2026-05-25 10:35:35'),
(42, 3, 11, 'safe', '2026-05-25 10:45:56');

-- --------------------------------------------------------

--
-- Structure de la table `risk_scores_augmented`
--

CREATE TABLE `risk_scores_augmented` (
  `id` int(11) NOT NULL,
  `zone_id` int(11) NOT NULL,
  `synthetic_at` datetime NOT NULL,
  `score` int(11) NOT NULL,
  `generation_method` enum('jitter','magnitude_warp','time_warp','bootstrap','timegan','tsdiff','csdi','gan_php') NOT NULL,
  `generator_version` varchar(20) DEFAULT NULL,
  `fidelity_score` decimal(3,2) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `risk_scores_augmented`
--

INSERT INTO `risk_scores_augmented` (`id`, `zone_id`, `synthetic_at`, `score`, `generation_method`, `generator_version`, `fidelity_score`, `created_at`) VALUES
(1, 1, '2026-05-11 19:15:50', 25, 'gan_php', 'php-v1', 0.75, '2026-05-13 22:15:50'),
(2, 1, '2026-05-11 20:15:50', 21, 'gan_php', 'php-v1', 0.71, '2026-05-13 22:15:50'),
(3, 1, '2026-05-11 21:15:50', 22, 'gan_php', 'php-v1', 0.72, '2026-05-13 22:15:50'),
(4, 1, '2026-05-11 22:15:50', 35, 'gan_php', 'php-v1', 0.85, '2026-05-13 22:15:50'),
(5, 1, '2026-05-11 23:15:50', 9, 'gan_php', 'php-v1', 0.59, '2026-05-13 22:15:50'),
(6, 1, '2026-05-12 00:15:50', 4, 'gan_php', 'php-v1', 0.54, '2026-05-13 22:15:50'),
(7, 1, '2026-05-12 01:15:50', 8, 'gan_php', 'php-v1', 0.58, '2026-05-13 22:15:50'),
(8, 1, '2026-05-12 02:15:50', 36, 'gan_php', 'php-v1', 0.86, '2026-05-13 22:15:50'),
(9, 1, '2026-05-12 03:15:50', 23, 'gan_php', 'php-v1', 0.73, '2026-05-13 22:15:50'),
(10, 1, '2026-05-12 04:15:50', 11, 'gan_php', 'php-v1', 0.61, '2026-05-13 22:15:50'),
(11, 1, '2026-05-12 05:15:50', 39, 'gan_php', 'php-v1', 0.89, '2026-05-13 22:15:50'),
(12, 1, '2026-05-12 06:15:50', 27, 'gan_php', 'php-v1', 0.77, '2026-05-13 22:15:50'),
(13, 1, '2026-05-12 07:15:50', 12, 'gan_php', 'php-v1', 0.62, '2026-05-13 22:15:50'),
(14, 1, '2026-05-12 08:15:50', 13, 'gan_php', 'php-v1', 0.63, '2026-05-13 22:15:50'),
(15, 1, '2026-05-12 09:15:50', 0, 'gan_php', 'php-v1', 0.50, '2026-05-13 22:15:50'),
(16, 1, '2026-05-12 10:15:50', 3, 'gan_php', 'php-v1', 0.53, '2026-05-13 22:15:50'),
(17, 1, '2026-05-12 11:15:50', 38, 'gan_php', 'php-v1', 0.88, '2026-05-13 22:15:50'),
(18, 1, '2026-05-12 12:15:50', 37, 'gan_php', 'php-v1', 0.87, '2026-05-13 22:15:50'),
(19, 1, '2026-05-12 13:15:50', 10, 'gan_php', 'php-v1', 0.60, '2026-05-13 22:15:50'),
(20, 1, '2026-05-12 14:15:50', 12, 'gan_php', 'php-v1', 0.62, '2026-05-13 22:15:50'),
(21, 1, '2026-05-12 15:15:50', 20, 'gan_php', 'php-v1', 0.70, '2026-05-13 22:15:50'),
(22, 1, '2026-05-12 16:15:50', 5, 'gan_php', 'php-v1', 0.55, '2026-05-13 22:15:50'),
(23, 1, '2026-05-12 17:15:50', 7, 'gan_php', 'php-v1', 0.57, '2026-05-13 22:15:50'),
(24, 1, '2026-05-12 18:15:50', 51, 'gan_php', 'php-v1', 0.99, '2026-05-13 22:15:50'),
(25, 1, '2026-05-12 19:15:50', 13, 'gan_php', 'php-v1', 0.63, '2026-05-13 22:15:50'),
(26, 1, '2026-05-12 20:15:50', 27, 'gan_php', 'php-v1', 0.77, '2026-05-13 22:15:50'),
(27, 1, '2026-05-12 21:15:50', 4, 'gan_php', 'php-v1', 0.54, '2026-05-13 22:15:50'),
(28, 1, '2026-05-12 22:15:50', 0, 'gan_php', 'php-v1', 0.50, '2026-05-13 22:15:50'),
(29, 1, '2026-05-12 23:15:50', 2, 'gan_php', 'php-v1', 0.52, '2026-05-13 22:15:50'),
(30, 1, '2026-05-13 00:15:50', 32, 'gan_php', 'php-v1', 0.82, '2026-05-13 22:15:50'),
(31, 1, '2026-05-13 01:15:50', 3, 'gan_php', 'php-v1', 0.53, '2026-05-13 22:15:50'),
(32, 1, '2026-05-13 02:15:50', 1, 'gan_php', 'php-v1', 0.51, '2026-05-13 22:15:50'),
(33, 1, '2026-05-13 03:15:50', 6, 'gan_php', 'php-v1', 0.56, '2026-05-13 22:15:50'),
(34, 1, '2026-05-13 04:15:50', 3, 'gan_php', 'php-v1', 0.53, '2026-05-13 22:15:50'),
(35, 1, '2026-05-13 05:15:50', 12, 'gan_php', 'php-v1', 0.62, '2026-05-13 22:15:50'),
(36, 1, '2026-05-13 06:15:50', 21, 'gan_php', 'php-v1', 0.71, '2026-05-13 22:15:50'),
(37, 1, '2026-05-13 07:15:50', 3, 'gan_php', 'php-v1', 0.53, '2026-05-13 22:15:50'),
(38, 1, '2026-05-13 08:15:50', 4, 'gan_php', 'php-v1', 0.54, '2026-05-13 22:15:50'),
(39, 1, '2026-05-13 09:15:50', 7, 'gan_php', 'php-v1', 0.57, '2026-05-13 22:15:50'),
(40, 1, '2026-05-13 10:15:50', 33, 'gan_php', 'php-v1', 0.83, '2026-05-13 22:15:50'),
(41, 1, '2026-05-13 11:15:50', 26, 'gan_php', 'php-v1', 0.76, '2026-05-13 22:15:50'),
(42, 1, '2026-05-13 12:15:50', 23, 'gan_php', 'php-v1', 0.73, '2026-05-13 22:15:50'),
(43, 1, '2026-05-13 13:15:50', 13, 'gan_php', 'php-v1', 0.63, '2026-05-13 22:15:50'),
(44, 1, '2026-05-13 14:15:50', 23, 'gan_php', 'php-v1', 0.73, '2026-05-13 22:15:50'),
(45, 1, '2026-05-13 15:15:50', 42, 'gan_php', 'php-v1', 0.92, '2026-05-13 22:15:50'),
(46, 1, '2026-05-13 16:15:50', 8, 'gan_php', 'php-v1', 0.58, '2026-05-13 22:15:50'),
(47, 1, '2026-05-13 17:15:50', 11, 'gan_php', 'php-v1', 0.61, '2026-05-13 22:15:50'),
(48, 1, '2026-05-13 18:15:50', 11, 'gan_php', 'php-v1', 0.61, '2026-05-13 22:15:50'),
(49, 1, '2026-05-13 19:15:50', 2, 'gan_php', 'php-v1', 0.52, '2026-05-13 22:15:50'),
(50, 1, '2026-05-13 20:15:50', 7, 'gan_php', 'php-v1', 0.57, '2026-05-13 22:15:50'),
(51, 2, '2026-05-11 19:15:50', 7, 'gan_php', 'php-v1', 0.57, '2026-05-13 22:15:50'),
(52, 2, '2026-05-11 20:15:50', 3, 'gan_php', 'php-v1', 0.53, '2026-05-13 22:15:50'),
(53, 2, '2026-05-11 21:15:50', 26, 'gan_php', 'php-v1', 0.76, '2026-05-13 22:15:50'),
(54, 2, '2026-05-11 22:15:50', 7, 'gan_php', 'php-v1', 0.57, '2026-05-13 22:15:50'),
(55, 2, '2026-05-11 23:15:50', 30, 'gan_php', 'php-v1', 0.80, '2026-05-13 22:15:50'),
(56, 2, '2026-05-12 00:15:50', 27, 'gan_php', 'php-v1', 0.77, '2026-05-13 22:15:50'),
(57, 2, '2026-05-12 01:15:50', 7, 'gan_php', 'php-v1', 0.57, '2026-05-13 22:15:50'),
(58, 2, '2026-05-12 02:15:50', 14, 'gan_php', 'php-v1', 0.64, '2026-05-13 22:15:50'),
(59, 2, '2026-05-12 03:15:50', 31, 'gan_php', 'php-v1', 0.81, '2026-05-13 22:15:50'),
(60, 2, '2026-05-12 04:15:50', 29, 'gan_php', 'php-v1', 0.79, '2026-05-13 22:15:50'),
(61, 2, '2026-05-12 05:15:50', 3, 'gan_php', 'php-v1', 0.53, '2026-05-13 22:15:50'),
(62, 2, '2026-05-12 06:15:50', 17, 'gan_php', 'php-v1', 0.67, '2026-05-13 22:15:50'),
(63, 2, '2026-05-12 07:15:50', 43, 'gan_php', 'php-v1', 0.93, '2026-05-13 22:15:50'),
(64, 2, '2026-05-12 08:15:50', 12, 'gan_php', 'php-v1', 0.62, '2026-05-13 22:15:50'),
(65, 2, '2026-05-12 09:15:50', 13, 'gan_php', 'php-v1', 0.63, '2026-05-13 22:15:50'),
(66, 2, '2026-05-12 10:15:50', 25, 'gan_php', 'php-v1', 0.75, '2026-05-13 22:15:50'),
(67, 2, '2026-05-12 11:15:50', 5, 'gan_php', 'php-v1', 0.55, '2026-05-13 22:15:50'),
(68, 2, '2026-05-12 12:15:50', 28, 'gan_php', 'php-v1', 0.78, '2026-05-13 22:15:50'),
(69, 2, '2026-05-12 13:15:50', 11, 'gan_php', 'php-v1', 0.61, '2026-05-13 22:15:50'),
(70, 2, '2026-05-12 14:15:50', 33, 'gan_php', 'php-v1', 0.83, '2026-05-13 22:15:50'),
(71, 2, '2026-05-12 15:15:50', 11, 'gan_php', 'php-v1', 0.61, '2026-05-13 22:15:50'),
(72, 2, '2026-05-12 16:15:50', 5, 'gan_php', 'php-v1', 0.55, '2026-05-13 22:15:50'),
(73, 2, '2026-05-12 17:15:50', 3, 'gan_php', 'php-v1', 0.53, '2026-05-13 22:15:50'),
(74, 2, '2026-05-12 18:15:50', 17, 'gan_php', 'php-v1', 0.67, '2026-05-13 22:15:50'),
(75, 2, '2026-05-12 19:15:50', 3, 'gan_php', 'php-v1', 0.53, '2026-05-13 22:15:50'),
(76, 2, '2026-05-12 20:15:50', 18, 'gan_php', 'php-v1', 0.68, '2026-05-13 22:15:50'),
(77, 2, '2026-05-12 21:15:50', 10, 'gan_php', 'php-v1', 0.60, '2026-05-13 22:15:50'),
(78, 2, '2026-05-12 22:15:50', 40, 'gan_php', 'php-v1', 0.90, '2026-05-13 22:15:50'),
(79, 2, '2026-05-12 23:15:50', 9, 'gan_php', 'php-v1', 0.59, '2026-05-13 22:15:50'),
(80, 2, '2026-05-13 00:15:50', 0, 'gan_php', 'php-v1', 0.50, '2026-05-13 22:15:50'),
(81, 2, '2026-05-13 01:15:50', 26, 'gan_php', 'php-v1', 0.76, '2026-05-13 22:15:50'),
(82, 2, '2026-05-13 02:15:50', 20, 'gan_php', 'php-v1', 0.70, '2026-05-13 22:15:50'),
(83, 2, '2026-05-13 03:15:50', 10, 'gan_php', 'php-v1', 0.60, '2026-05-13 22:15:50'),
(84, 2, '2026-05-13 04:15:50', 43, 'gan_php', 'php-v1', 0.93, '2026-05-13 22:15:50'),
(85, 2, '2026-05-13 05:15:50', 60, 'gan_php', 'php-v1', 0.90, '2026-05-13 22:15:50'),
(86, 2, '2026-05-13 06:15:50', 16, 'gan_php', 'php-v1', 0.66, '2026-05-13 22:15:50'),
(87, 2, '2026-05-13 07:15:50', 13, 'gan_php', 'php-v1', 0.63, '2026-05-13 22:15:50'),
(88, 2, '2026-05-13 08:15:50', 23, 'gan_php', 'php-v1', 0.73, '2026-05-13 22:15:50'),
(89, 2, '2026-05-13 09:15:50', 6, 'gan_php', 'php-v1', 0.56, '2026-05-13 22:15:50'),
(90, 2, '2026-05-13 10:15:50', 13, 'gan_php', 'php-v1', 0.63, '2026-05-13 22:15:50'),
(91, 2, '2026-05-13 11:15:50', 33, 'gan_php', 'php-v1', 0.83, '2026-05-13 22:15:50'),
(92, 2, '2026-05-13 12:15:50', 15, 'gan_php', 'php-v1', 0.65, '2026-05-13 22:15:50'),
(93, 2, '2026-05-13 13:15:50', 10, 'gan_php', 'php-v1', 0.60, '2026-05-13 22:15:50'),
(94, 2, '2026-05-13 14:15:50', 6, 'gan_php', 'php-v1', 0.56, '2026-05-13 22:15:50'),
(95, 2, '2026-05-13 15:15:50', 2, 'gan_php', 'php-v1', 0.52, '2026-05-13 22:15:50'),
(96, 2, '2026-05-13 16:15:50', 20, 'gan_php', 'php-v1', 0.70, '2026-05-13 22:15:50'),
(97, 2, '2026-05-13 17:15:50', 25, 'gan_php', 'php-v1', 0.75, '2026-05-13 22:15:50'),
(98, 2, '2026-05-13 18:15:50', 17, 'gan_php', 'php-v1', 0.67, '2026-05-13 22:15:50'),
(99, 2, '2026-05-13 19:15:50', 4, 'gan_php', 'php-v1', 0.54, '2026-05-13 22:15:50'),
(100, 2, '2026-05-13 20:15:50', 11, 'gan_php', 'php-v1', 0.61, '2026-05-13 22:15:50'),
(101, 3, '2026-05-11 19:15:50', 7, 'gan_php', 'php-v1', 0.57, '2026-05-13 22:15:50'),
(102, 3, '2026-05-11 20:15:50', 6, 'gan_php', 'php-v1', 0.56, '2026-05-13 22:15:50'),
(103, 3, '2026-05-11 21:15:50', 23, 'gan_php', 'php-v1', 0.73, '2026-05-13 22:15:50'),
(104, 3, '2026-05-11 22:15:50', 16, 'gan_php', 'php-v1', 0.66, '2026-05-13 22:15:50'),
(105, 3, '2026-05-11 23:15:50', 19, 'gan_php', 'php-v1', 0.69, '2026-05-13 22:15:50'),
(106, 3, '2026-05-12 00:15:50', 12, 'gan_php', 'php-v1', 0.62, '2026-05-13 22:15:50'),
(107, 3, '2026-05-12 01:15:50', 6, 'gan_php', 'php-v1', 0.56, '2026-05-13 22:15:50'),
(108, 3, '2026-05-12 02:15:50', 32, 'gan_php', 'php-v1', 0.82, '2026-05-13 22:15:50'),
(109, 3, '2026-05-12 03:15:50', 39, 'gan_php', 'php-v1', 0.89, '2026-05-13 22:15:50'),
(110, 3, '2026-05-12 04:15:50', 17, 'gan_php', 'php-v1', 0.67, '2026-05-13 22:15:50'),
(111, 3, '2026-05-12 05:15:50', 12, 'gan_php', 'php-v1', 0.62, '2026-05-13 22:15:50'),
(112, 3, '2026-05-12 06:15:50', 38, 'gan_php', 'php-v1', 0.88, '2026-05-13 22:15:50'),
(113, 3, '2026-05-12 07:15:50', 38, 'gan_php', 'php-v1', 0.88, '2026-05-13 22:15:50'),
(114, 3, '2026-05-12 08:15:50', 10, 'gan_php', 'php-v1', 0.60, '2026-05-13 22:15:50'),
(115, 3, '2026-05-12 09:15:50', 49, 'gan_php', 'php-v1', 0.99, '2026-05-13 22:15:50'),
(116, 3, '2026-05-12 10:15:51', 5, 'gan_php', 'php-v1', 0.55, '2026-05-13 22:15:51'),
(117, 3, '2026-05-12 11:15:51', 7, 'gan_php', 'php-v1', 0.57, '2026-05-13 22:15:51'),
(118, 3, '2026-05-12 12:15:51', 34, 'gan_php', 'php-v1', 0.84, '2026-05-13 22:15:51'),
(119, 3, '2026-05-12 13:15:51', 14, 'gan_php', 'php-v1', 0.64, '2026-05-13 22:15:51'),
(120, 3, '2026-05-12 14:15:51', 24, 'gan_php', 'php-v1', 0.74, '2026-05-13 22:15:51'),
(121, 3, '2026-05-12 15:15:51', 34, 'gan_php', 'php-v1', 0.84, '2026-05-13 22:15:51'),
(122, 3, '2026-05-12 16:15:51', 2, 'gan_php', 'php-v1', 0.52, '2026-05-13 22:15:51'),
(123, 3, '2026-05-12 17:15:51', 22, 'gan_php', 'php-v1', 0.72, '2026-05-13 22:15:51'),
(124, 3, '2026-05-12 18:15:51', 15, 'gan_php', 'php-v1', 0.65, '2026-05-13 22:15:51'),
(125, 3, '2026-05-12 19:15:51', 13, 'gan_php', 'php-v1', 0.63, '2026-05-13 22:15:51'),
(126, 3, '2026-05-12 20:15:51', 3, 'gan_php', 'php-v1', 0.53, '2026-05-13 22:15:51'),
(127, 3, '2026-05-12 21:15:51', 10, 'gan_php', 'php-v1', 0.60, '2026-05-13 22:15:51'),
(128, 3, '2026-05-12 22:15:51', 11, 'gan_php', 'php-v1', 0.61, '2026-05-13 22:15:51'),
(129, 3, '2026-05-12 23:15:51', 40, 'gan_php', 'php-v1', 0.90, '2026-05-13 22:15:51'),
(130, 3, '2026-05-13 00:15:51', 18, 'gan_php', 'php-v1', 0.68, '2026-05-13 22:15:51'),
(131, 3, '2026-05-13 01:15:51', 8, 'gan_php', 'php-v1', 0.58, '2026-05-13 22:15:51'),
(132, 3, '2026-05-13 02:15:51', 22, 'gan_php', 'php-v1', 0.72, '2026-05-13 22:15:51'),
(133, 3, '2026-05-13 03:15:51', 17, 'gan_php', 'php-v1', 0.67, '2026-05-13 22:15:51'),
(134, 3, '2026-05-13 04:15:51', 4, 'gan_php', 'php-v1', 0.54, '2026-05-13 22:15:51'),
(135, 3, '2026-05-13 05:15:51', 35, 'gan_php', 'php-v1', 0.85, '2026-05-13 22:15:51'),
(136, 3, '2026-05-13 06:15:51', 23, 'gan_php', 'php-v1', 0.73, '2026-05-13 22:15:51'),
(137, 3, '2026-05-13 07:15:51', 1, 'gan_php', 'php-v1', 0.51, '2026-05-13 22:15:51'),
(138, 3, '2026-05-13 08:15:51', 41, 'gan_php', 'php-v1', 0.91, '2026-05-13 22:15:51'),
(139, 3, '2026-05-13 09:15:51', 17, 'gan_php', 'php-v1', 0.67, '2026-05-13 22:15:51'),
(140, 3, '2026-05-13 10:15:51', 6, 'gan_php', 'php-v1', 0.56, '2026-05-13 22:15:51'),
(141, 3, '2026-05-13 11:15:51', 3, 'gan_php', 'php-v1', 0.53, '2026-05-13 22:15:51'),
(142, 3, '2026-05-13 12:15:51', 18, 'gan_php', 'php-v1', 0.68, '2026-05-13 22:15:51'),
(143, 3, '2026-05-13 13:15:51', 10, 'gan_php', 'php-v1', 0.60, '2026-05-13 22:15:51'),
(144, 3, '2026-05-13 14:15:51', 20, 'gan_php', 'php-v1', 0.70, '2026-05-13 22:15:51'),
(145, 3, '2026-05-13 15:15:51', 19, 'gan_php', 'php-v1', 0.69, '2026-05-13 22:15:51'),
(146, 3, '2026-05-13 16:15:51', 11, 'gan_php', 'php-v1', 0.61, '2026-05-13 22:15:51'),
(147, 3, '2026-05-13 17:15:51', 41, 'gan_php', 'php-v1', 0.91, '2026-05-13 22:15:51'),
(148, 3, '2026-05-13 18:15:51', 43, 'gan_php', 'php-v1', 0.93, '2026-05-13 22:15:51'),
(149, 3, '2026-05-13 19:15:51', 19, 'gan_php', 'php-v1', 0.69, '2026-05-13 22:15:51'),
(150, 3, '2026-05-13 20:15:51', 36, 'gan_php', 'php-v1', 0.86, '2026-05-13 22:15:51'),
(151, 4, '2026-05-11 19:15:51', 12, 'gan_php', 'php-v1', 0.62, '2026-05-13 22:15:51'),
(152, 4, '2026-05-11 20:15:51', 15, 'gan_php', 'php-v1', 0.65, '2026-05-13 22:15:51'),
(153, 4, '2026-05-11 21:15:51', 5, 'gan_php', 'php-v1', 0.55, '2026-05-13 22:15:51'),
(154, 4, '2026-05-11 22:15:51', 9, 'gan_php', 'php-v1', 0.59, '2026-05-13 22:15:51'),
(155, 4, '2026-05-11 23:15:51', 5, 'gan_php', 'php-v1', 0.55, '2026-05-13 22:15:51'),
(156, 4, '2026-05-12 00:15:51', 13, 'gan_php', 'php-v1', 0.63, '2026-05-13 22:15:51'),
(157, 4, '2026-05-12 01:15:51', 22, 'gan_php', 'php-v1', 0.72, '2026-05-13 22:15:51'),
(158, 4, '2026-05-12 02:15:51', 5, 'gan_php', 'php-v1', 0.55, '2026-05-13 22:15:51'),
(159, 4, '2026-05-12 03:15:51', 9, 'gan_php', 'php-v1', 0.59, '2026-05-13 22:15:51'),
(160, 4, '2026-05-12 04:15:51', 15, 'gan_php', 'php-v1', 0.65, '2026-05-13 22:15:51'),
(161, 4, '2026-05-12 05:15:51', 14, 'gan_php', 'php-v1', 0.64, '2026-05-13 22:15:51'),
(162, 4, '2026-05-12 06:15:51', 14, 'gan_php', 'php-v1', 0.64, '2026-05-13 22:15:51'),
(163, 4, '2026-05-12 07:15:51', 26, 'gan_php', 'php-v1', 0.76, '2026-05-13 22:15:51'),
(164, 4, '2026-05-12 08:15:51', 31, 'gan_php', 'php-v1', 0.81, '2026-05-13 22:15:51'),
(165, 4, '2026-05-12 09:15:51', 4, 'gan_php', 'php-v1', 0.54, '2026-05-13 22:15:51'),
(166, 4, '2026-05-12 10:15:51', 30, 'gan_php', 'php-v1', 0.80, '2026-05-13 22:15:51'),
(167, 4, '2026-05-12 11:15:51', 40, 'gan_php', 'php-v1', 0.90, '2026-05-13 22:15:51'),
(168, 4, '2026-05-12 12:15:51', 7, 'gan_php', 'php-v1', 0.57, '2026-05-13 22:15:51'),
(169, 4, '2026-05-12 13:15:51', 5, 'gan_php', 'php-v1', 0.55, '2026-05-13 22:15:51'),
(170, 4, '2026-05-12 14:15:51', 11, 'gan_php', 'php-v1', 0.61, '2026-05-13 22:15:51'),
(171, 4, '2026-05-12 15:15:51', 24, 'gan_php', 'php-v1', 0.74, '2026-05-13 22:15:51'),
(172, 4, '2026-05-12 16:15:51', 30, 'gan_php', 'php-v1', 0.80, '2026-05-13 22:15:51'),
(173, 4, '2026-05-12 17:15:51', 2, 'gan_php', 'php-v1', 0.52, '2026-05-13 22:15:51'),
(174, 4, '2026-05-12 18:15:51', 14, 'gan_php', 'php-v1', 0.64, '2026-05-13 22:15:51'),
(175, 4, '2026-05-12 19:15:51', 12, 'gan_php', 'php-v1', 0.62, '2026-05-13 22:15:51'),
(176, 4, '2026-05-12 20:15:51', 47, 'gan_php', 'php-v1', 0.97, '2026-05-13 22:15:51'),
(177, 4, '2026-05-12 21:15:51', 17, 'gan_php', 'php-v1', 0.67, '2026-05-13 22:15:51'),
(178, 4, '2026-05-12 22:15:51', 31, 'gan_php', 'php-v1', 0.81, '2026-05-13 22:15:51'),
(179, 4, '2026-05-12 23:15:51', 27, 'gan_php', 'php-v1', 0.77, '2026-05-13 22:15:51'),
(180, 4, '2026-05-13 00:15:51', 34, 'gan_php', 'php-v1', 0.84, '2026-05-13 22:15:51'),
(181, 4, '2026-05-13 01:15:51', 4, 'gan_php', 'php-v1', 0.54, '2026-05-13 22:15:51'),
(182, 4, '2026-05-13 02:15:51', 37, 'gan_php', 'php-v1', 0.87, '2026-05-13 22:15:51'),
(183, 4, '2026-05-13 03:15:51', 3, 'gan_php', 'php-v1', 0.53, '2026-05-13 22:15:51'),
(184, 4, '2026-05-13 04:15:51', 61, 'gan_php', 'php-v1', 0.89, '2026-05-13 22:15:51'),
(185, 4, '2026-05-13 05:15:51', 5, 'gan_php', 'php-v1', 0.55, '2026-05-13 22:15:51'),
(186, 4, '2026-05-13 06:15:51', 37, 'gan_php', 'php-v1', 0.87, '2026-05-13 22:15:51'),
(187, 4, '2026-05-13 07:15:51', 25, 'gan_php', 'php-v1', 0.75, '2026-05-13 22:15:51'),
(188, 4, '2026-05-13 08:15:51', 15, 'gan_php', 'php-v1', 0.65, '2026-05-13 22:15:51'),
(189, 4, '2026-05-13 09:15:51', 18, 'gan_php', 'php-v1', 0.68, '2026-05-13 22:15:51'),
(190, 4, '2026-05-13 10:15:51', 19, 'gan_php', 'php-v1', 0.69, '2026-05-13 22:15:51'),
(191, 4, '2026-05-13 11:15:51', 9, 'gan_php', 'php-v1', 0.59, '2026-05-13 22:15:51'),
(192, 4, '2026-05-13 12:15:51', 31, 'gan_php', 'php-v1', 0.81, '2026-05-13 22:15:51'),
(193, 4, '2026-05-13 13:15:51', 1, 'gan_php', 'php-v1', 0.51, '2026-05-13 22:15:51'),
(194, 4, '2026-05-13 14:15:51', 32, 'gan_php', 'php-v1', 0.82, '2026-05-13 22:15:51'),
(195, 4, '2026-05-13 15:15:51', 12, 'gan_php', 'php-v1', 0.62, '2026-05-13 22:15:51'),
(196, 4, '2026-05-13 16:15:51', 27, 'gan_php', 'php-v1', 0.77, '2026-05-13 22:15:51'),
(197, 4, '2026-05-13 17:15:51', 13, 'gan_php', 'php-v1', 0.63, '2026-05-13 22:15:51'),
(198, 4, '2026-05-13 18:15:51', 5, 'gan_php', 'php-v1', 0.55, '2026-05-13 22:15:51'),
(199, 4, '2026-05-13 19:15:51', 8, 'gan_php', 'php-v1', 0.58, '2026-05-13 22:15:51'),
(200, 4, '2026-05-13 20:15:51', 8, 'gan_php', 'php-v1', 0.58, '2026-05-13 22:15:51'),
(201, 5, '2026-05-11 19:15:51', 4, 'gan_php', 'php-v1', 0.54, '2026-05-13 22:15:51'),
(202, 5, '2026-05-11 20:15:51', 9, 'gan_php', 'php-v1', 0.59, '2026-05-13 22:15:51'),
(203, 5, '2026-05-11 21:15:51', 4, 'gan_php', 'php-v1', 0.54, '2026-05-13 22:15:51'),
(204, 5, '2026-05-11 22:15:51', 1, 'gan_php', 'php-v1', 0.51, '2026-05-13 22:15:51'),
(205, 5, '2026-05-11 23:15:51', 24, 'gan_php', 'php-v1', 0.74, '2026-05-13 22:15:51'),
(206, 5, '2026-05-12 00:15:51', 17, 'gan_php', 'php-v1', 0.67, '2026-05-13 22:15:51'),
(207, 5, '2026-05-12 01:15:51', 9, 'gan_php', 'php-v1', 0.59, '2026-05-13 22:15:51'),
(208, 5, '2026-05-12 02:15:51', 27, 'gan_php', 'php-v1', 0.77, '2026-05-13 22:15:51'),
(209, 5, '2026-05-12 03:15:51', 12, 'gan_php', 'php-v1', 0.62, '2026-05-13 22:15:51'),
(210, 5, '2026-05-12 04:15:51', 26, 'gan_php', 'php-v1', 0.76, '2026-05-13 22:15:51'),
(211, 5, '2026-05-12 05:15:51', 25, 'gan_php', 'php-v1', 0.75, '2026-05-13 22:15:51'),
(212, 5, '2026-05-12 06:15:51', 20, 'gan_php', 'php-v1', 0.70, '2026-05-13 22:15:51'),
(213, 5, '2026-05-12 07:15:51', 3, 'gan_php', 'php-v1', 0.53, '2026-05-13 22:15:51'),
(214, 5, '2026-05-12 08:15:51', 5, 'gan_php', 'php-v1', 0.55, '2026-05-13 22:15:51'),
(215, 5, '2026-05-12 09:15:51', 10, 'gan_php', 'php-v1', 0.60, '2026-05-13 22:15:51'),
(216, 5, '2026-05-12 10:15:51', 4, 'gan_php', 'php-v1', 0.54, '2026-05-13 22:15:51'),
(217, 5, '2026-05-12 11:15:51', 8, 'gan_php', 'php-v1', 0.58, '2026-05-13 22:15:51'),
(218, 5, '2026-05-12 12:15:51', 21, 'gan_php', 'php-v1', 0.71, '2026-05-13 22:15:51'),
(219, 5, '2026-05-12 13:15:51', 31, 'gan_php', 'php-v1', 0.81, '2026-05-13 22:15:51'),
(220, 5, '2026-05-12 14:15:51', 52, 'gan_php', 'php-v1', 0.98, '2026-05-13 22:15:51'),
(221, 5, '2026-05-12 15:15:51', 0, 'gan_php', 'php-v1', 0.50, '2026-05-13 22:15:51'),
(222, 5, '2026-05-12 16:15:51', 2, 'gan_php', 'php-v1', 0.52, '2026-05-13 22:15:51'),
(223, 5, '2026-05-12 17:15:51', 18, 'gan_php', 'php-v1', 0.68, '2026-05-13 22:15:51'),
(224, 5, '2026-05-12 18:15:51', 6, 'gan_php', 'php-v1', 0.56, '2026-05-13 22:15:51'),
(225, 5, '2026-05-12 19:15:51', 20, 'gan_php', 'php-v1', 0.70, '2026-05-13 22:15:51'),
(226, 5, '2026-05-12 20:15:51', 43, 'gan_php', 'php-v1', 0.93, '2026-05-13 22:15:51'),
(227, 5, '2026-05-12 21:15:51', 17, 'gan_php', 'php-v1', 0.67, '2026-05-13 22:15:51'),
(228, 5, '2026-05-12 22:15:51', 15, 'gan_php', 'php-v1', 0.65, '2026-05-13 22:15:51'),
(229, 5, '2026-05-12 23:15:51', 5, 'gan_php', 'php-v1', 0.55, '2026-05-13 22:15:51'),
(230, 5, '2026-05-13 00:15:51', 37, 'gan_php', 'php-v1', 0.87, '2026-05-13 22:15:51'),
(231, 5, '2026-05-13 01:15:51', 33, 'gan_php', 'php-v1', 0.83, '2026-05-13 22:15:51'),
(232, 5, '2026-05-13 02:15:51', 5, 'gan_php', 'php-v1', 0.55, '2026-05-13 22:15:51'),
(233, 5, '2026-05-13 03:15:51', 7, 'gan_php', 'php-v1', 0.57, '2026-05-13 22:15:51'),
(234, 5, '2026-05-13 04:15:51', 13, 'gan_php', 'php-v1', 0.63, '2026-05-13 22:15:51'),
(235, 5, '2026-05-13 05:15:51', 33, 'gan_php', 'php-v1', 0.83, '2026-05-13 22:15:51'),
(236, 5, '2026-05-13 06:15:51', 6, 'gan_php', 'php-v1', 0.56, '2026-05-13 22:15:51'),
(237, 5, '2026-05-13 07:15:51', 6, 'gan_php', 'php-v1', 0.56, '2026-05-13 22:15:51'),
(238, 5, '2026-05-13 08:15:51', 26, 'gan_php', 'php-v1', 0.76, '2026-05-13 22:15:51'),
(239, 5, '2026-05-13 09:15:51', 3, 'gan_php', 'php-v1', 0.53, '2026-05-13 22:15:51'),
(240, 5, '2026-05-13 10:15:51', 29, 'gan_php', 'php-v1', 0.79, '2026-05-13 22:15:51'),
(241, 5, '2026-05-13 11:15:51', 25, 'gan_php', 'php-v1', 0.75, '2026-05-13 22:15:51'),
(242, 5, '2026-05-13 12:15:51', 21, 'gan_php', 'php-v1', 0.71, '2026-05-13 22:15:51'),
(243, 5, '2026-05-13 13:15:51', 10, 'gan_php', 'php-v1', 0.60, '2026-05-13 22:15:51'),
(244, 5, '2026-05-13 14:15:51', 46, 'gan_php', 'php-v1', 0.96, '2026-05-13 22:15:51'),
(245, 5, '2026-05-13 15:15:51', 9, 'gan_php', 'php-v1', 0.59, '2026-05-13 22:15:51'),
(246, 5, '2026-05-13 16:15:51', 8, 'gan_php', 'php-v1', 0.58, '2026-05-13 22:15:51'),
(247, 5, '2026-05-13 17:15:51', 23, 'gan_php', 'php-v1', 0.73, '2026-05-13 22:15:51'),
(248, 5, '2026-05-13 18:15:51', 7, 'gan_php', 'php-v1', 0.57, '2026-05-13 22:15:51'),
(249, 5, '2026-05-13 19:15:51', 41, 'gan_php', 'php-v1', 0.91, '2026-05-13 22:15:51'),
(250, 5, '2026-05-13 20:15:51', 6, 'gan_php', 'php-v1', 0.56, '2026-05-13 22:15:51'),
(251, 6, '2026-05-11 19:15:51', 16, 'gan_php', 'php-v1', 0.66, '2026-05-13 22:15:51'),
(252, 6, '2026-05-11 20:15:51', 18, 'gan_php', 'php-v1', 0.68, '2026-05-13 22:15:51'),
(253, 6, '2026-05-11 21:15:51', 22, 'gan_php', 'php-v1', 0.72, '2026-05-13 22:15:51'),
(254, 6, '2026-05-11 22:15:51', 30, 'gan_php', 'php-v1', 0.80, '2026-05-13 22:15:51'),
(255, 6, '2026-05-11 23:15:51', 20, 'gan_php', 'php-v1', 0.70, '2026-05-13 22:15:51'),
(256, 6, '2026-05-12 00:15:51', 32, 'gan_php', 'php-v1', 0.82, '2026-05-13 22:15:51'),
(257, 6, '2026-05-12 01:15:51', 18, 'gan_php', 'php-v1', 0.68, '2026-05-13 22:15:51'),
(258, 6, '2026-05-12 02:15:51', 9, 'gan_php', 'php-v1', 0.59, '2026-05-13 22:15:51'),
(259, 6, '2026-05-12 03:15:51', 10, 'gan_php', 'php-v1', 0.60, '2026-05-13 22:15:51'),
(260, 6, '2026-05-12 04:15:51', 5, 'gan_php', 'php-v1', 0.55, '2026-05-13 22:15:51'),
(261, 6, '2026-05-12 05:15:51', 27, 'gan_php', 'php-v1', 0.77, '2026-05-13 22:15:51'),
(262, 6, '2026-05-12 06:15:51', 10, 'gan_php', 'php-v1', 0.60, '2026-05-13 22:15:51'),
(263, 6, '2026-05-12 07:15:51', 5, 'gan_php', 'php-v1', 0.55, '2026-05-13 22:15:51'),
(264, 6, '2026-05-12 08:15:51', 29, 'gan_php', 'php-v1', 0.79, '2026-05-13 22:15:51'),
(265, 6, '2026-05-12 09:15:51', 2, 'gan_php', 'php-v1', 0.52, '2026-05-13 22:15:51'),
(266, 6, '2026-05-12 10:15:51', 12, 'gan_php', 'php-v1', 0.62, '2026-05-13 22:15:51'),
(267, 6, '2026-05-12 11:15:51', 23, 'gan_php', 'php-v1', 0.73, '2026-05-13 22:15:51'),
(268, 6, '2026-05-12 12:15:51', 46, 'gan_php', 'php-v1', 0.96, '2026-05-13 22:15:51'),
(269, 6, '2026-05-12 13:15:51', 7, 'gan_php', 'php-v1', 0.57, '2026-05-13 22:15:51'),
(270, 6, '2026-05-12 14:15:51', 8, 'gan_php', 'php-v1', 0.58, '2026-05-13 22:15:51'),
(271, 6, '2026-05-12 15:15:51', 26, 'gan_php', 'php-v1', 0.76, '2026-05-13 22:15:51'),
(272, 6, '2026-05-12 16:15:51', 9, 'gan_php', 'php-v1', 0.59, '2026-05-13 22:15:51'),
(273, 6, '2026-05-12 17:15:51', 9, 'gan_php', 'php-v1', 0.59, '2026-05-13 22:15:51'),
(274, 6, '2026-05-12 18:15:51', 11, 'gan_php', 'php-v1', 0.61, '2026-05-13 22:15:51'),
(275, 6, '2026-05-12 19:15:51', 13, 'gan_php', 'php-v1', 0.63, '2026-05-13 22:15:51'),
(276, 6, '2026-05-12 20:15:51', 4, 'gan_php', 'php-v1', 0.54, '2026-05-13 22:15:51'),
(277, 6, '2026-05-12 21:15:51', 6, 'gan_php', 'php-v1', 0.56, '2026-05-13 22:15:51'),
(278, 6, '2026-05-12 22:15:51', 28, 'gan_php', 'php-v1', 0.78, '2026-05-13 22:15:51'),
(279, 6, '2026-05-12 23:15:51', 6, 'gan_php', 'php-v1', 0.56, '2026-05-13 22:15:51'),
(280, 6, '2026-05-13 00:15:51', 16, 'gan_php', 'php-v1', 0.66, '2026-05-13 22:15:51'),
(281, 6, '2026-05-13 01:15:51', 9, 'gan_php', 'php-v1', 0.59, '2026-05-13 22:15:51'),
(282, 6, '2026-05-13 02:15:52', 21, 'gan_php', 'php-v1', 0.71, '2026-05-13 22:15:52'),
(283, 6, '2026-05-13 03:15:52', 23, 'gan_php', 'php-v1', 0.73, '2026-05-13 22:15:52'),
(284, 6, '2026-05-13 04:15:52', 22, 'gan_php', 'php-v1', 0.72, '2026-05-13 22:15:52'),
(285, 6, '2026-05-13 05:15:52', 37, 'gan_php', 'php-v1', 0.87, '2026-05-13 22:15:52'),
(286, 6, '2026-05-13 06:15:52', 11, 'gan_php', 'php-v1', 0.61, '2026-05-13 22:15:52'),
(287, 6, '2026-05-13 07:15:52', 18, 'gan_php', 'php-v1', 0.68, '2026-05-13 22:15:52'),
(288, 6, '2026-05-13 08:15:52', 5, 'gan_php', 'php-v1', 0.55, '2026-05-13 22:15:52'),
(289, 6, '2026-05-13 09:15:52', 7, 'gan_php', 'php-v1', 0.57, '2026-05-13 22:15:52'),
(290, 6, '2026-05-13 10:15:52', 12, 'gan_php', 'php-v1', 0.62, '2026-05-13 22:15:52'),
(291, 6, '2026-05-13 11:15:52', 4, 'gan_php', 'php-v1', 0.54, '2026-05-13 22:15:52'),
(292, 6, '2026-05-13 12:15:52', 4, 'gan_php', 'php-v1', 0.54, '2026-05-13 22:15:52'),
(293, 6, '2026-05-13 13:15:52', 23, 'gan_php', 'php-v1', 0.73, '2026-05-13 22:15:52'),
(294, 6, '2026-05-13 14:15:52', 29, 'gan_php', 'php-v1', 0.79, '2026-05-13 22:15:52'),
(295, 6, '2026-05-13 15:15:52', 35, 'gan_php', 'php-v1', 0.85, '2026-05-13 22:15:52'),
(296, 6, '2026-05-13 16:15:52', 24, 'gan_php', 'php-v1', 0.74, '2026-05-13 22:15:52'),
(297, 6, '2026-05-13 17:15:52', 13, 'gan_php', 'php-v1', 0.63, '2026-05-13 22:15:52'),
(298, 6, '2026-05-13 18:15:52', 34, 'gan_php', 'php-v1', 0.84, '2026-05-13 22:15:52'),
(299, 6, '2026-05-13 19:15:52', 11, 'gan_php', 'php-v1', 0.61, '2026-05-13 22:15:52'),
(300, 6, '2026-05-13 20:15:52', 39, 'gan_php', 'php-v1', 0.89, '2026-05-13 22:15:52'),
(301, 7, '2026-05-11 19:15:52', 37, 'gan_php', 'php-v1', 0.87, '2026-05-13 22:15:52'),
(302, 7, '2026-05-11 20:15:52', 4, 'gan_php', 'php-v1', 0.54, '2026-05-13 22:15:52'),
(303, 7, '2026-05-11 21:15:52', 31, 'gan_php', 'php-v1', 0.81, '2026-05-13 22:15:52'),
(304, 7, '2026-05-11 22:15:52', 13, 'gan_php', 'php-v1', 0.63, '2026-05-13 22:15:52'),
(305, 7, '2026-05-11 23:15:52', 9, 'gan_php', 'php-v1', 0.59, '2026-05-13 22:15:52'),
(306, 7, '2026-05-12 00:15:52', 33, 'gan_php', 'php-v1', 0.83, '2026-05-13 22:15:52'),
(307, 7, '2026-05-12 01:15:52', 58, 'gan_php', 'php-v1', 0.92, '2026-05-13 22:15:52'),
(308, 7, '2026-05-12 02:15:52', 7, 'gan_php', 'php-v1', 0.57, '2026-05-13 22:15:52'),
(309, 7, '2026-05-12 03:15:52', 48, 'gan_php', 'php-v1', 0.98, '2026-05-13 22:15:52'),
(310, 7, '2026-05-12 04:15:52', 42, 'gan_php', 'php-v1', 0.92, '2026-05-13 22:15:52'),
(311, 7, '2026-05-12 05:15:52', 4, 'gan_php', 'php-v1', 0.54, '2026-05-13 22:15:52'),
(312, 7, '2026-05-12 06:15:52', 21, 'gan_php', 'php-v1', 0.71, '2026-05-13 22:15:52'),
(313, 7, '2026-05-12 07:15:52', 39, 'gan_php', 'php-v1', 0.89, '2026-05-13 22:15:52'),
(314, 7, '2026-05-12 08:15:52', 11, 'gan_php', 'php-v1', 0.61, '2026-05-13 22:15:52'),
(315, 7, '2026-05-12 09:15:52', 21, 'gan_php', 'php-v1', 0.71, '2026-05-13 22:15:52'),
(316, 7, '2026-05-12 10:15:52', 25, 'gan_php', 'php-v1', 0.75, '2026-05-13 22:15:52'),
(317, 7, '2026-05-12 11:15:52', 11, 'gan_php', 'php-v1', 0.61, '2026-05-13 22:15:52'),
(318, 7, '2026-05-12 12:15:52', 14, 'gan_php', 'php-v1', 0.64, '2026-05-13 22:15:52'),
(319, 7, '2026-05-12 13:15:52', 1, 'gan_php', 'php-v1', 0.51, '2026-05-13 22:15:52'),
(320, 7, '2026-05-12 14:15:52', 26, 'gan_php', 'php-v1', 0.76, '2026-05-13 22:15:52'),
(321, 7, '2026-05-12 15:15:52', 36, 'gan_php', 'php-v1', 0.86, '2026-05-13 22:15:52'),
(322, 7, '2026-05-12 16:15:52', 32, 'gan_php', 'php-v1', 0.82, '2026-05-13 22:15:52'),
(323, 7, '2026-05-12 17:15:52', 36, 'gan_php', 'php-v1', 0.86, '2026-05-13 22:15:52'),
(324, 7, '2026-05-12 18:15:52', 1, 'gan_php', 'php-v1', 0.51, '2026-05-13 22:15:52'),
(325, 7, '2026-05-12 19:15:52', 23, 'gan_php', 'php-v1', 0.73, '2026-05-13 22:15:52'),
(326, 7, '2026-05-12 20:15:52', 38, 'gan_php', 'php-v1', 0.88, '2026-05-13 22:15:52'),
(327, 7, '2026-05-12 21:15:52', 48, 'gan_php', 'php-v1', 0.98, '2026-05-13 22:15:52'),
(328, 7, '2026-05-12 22:15:52', 39, 'gan_php', 'php-v1', 0.89, '2026-05-13 22:15:52'),
(329, 7, '2026-05-12 23:15:52', 10, 'gan_php', 'php-v1', 0.60, '2026-05-13 22:15:52'),
(330, 7, '2026-05-13 00:15:52', 12, 'gan_php', 'php-v1', 0.62, '2026-05-13 22:15:52'),
(331, 7, '2026-05-13 01:15:52', 52, 'gan_php', 'php-v1', 0.98, '2026-05-13 22:15:52'),
(332, 7, '2026-05-13 02:15:52', 14, 'gan_php', 'php-v1', 0.64, '2026-05-13 22:15:52'),
(333, 7, '2026-05-13 03:15:52', 31, 'gan_php', 'php-v1', 0.81, '2026-05-13 22:15:52'),
(334, 7, '2026-05-13 04:15:52', 11, 'gan_php', 'php-v1', 0.61, '2026-05-13 22:15:52'),
(335, 7, '2026-05-13 05:15:52', 31, 'gan_php', 'php-v1', 0.81, '2026-05-13 22:15:52'),
(336, 7, '2026-05-13 06:15:52', 37, 'gan_php', 'php-v1', 0.87, '2026-05-13 22:15:52'),
(337, 7, '2026-05-13 07:15:52', 11, 'gan_php', 'php-v1', 0.61, '2026-05-13 22:15:52'),
(338, 7, '2026-05-13 08:15:52', 22, 'gan_php', 'php-v1', 0.72, '2026-05-13 22:15:52'),
(339, 7, '2026-05-13 09:15:52', 31, 'gan_php', 'php-v1', 0.81, '2026-05-13 22:15:52'),
(340, 7, '2026-05-13 10:15:52', 25, 'gan_php', 'php-v1', 0.75, '2026-05-13 22:15:52'),
(341, 7, '2026-05-13 11:15:52', 17, 'gan_php', 'php-v1', 0.67, '2026-05-13 22:15:52'),
(342, 7, '2026-05-13 12:15:52', 10, 'gan_php', 'php-v1', 0.60, '2026-05-13 22:15:52'),
(343, 7, '2026-05-13 13:15:52', 4, 'gan_php', 'php-v1', 0.54, '2026-05-13 22:15:52'),
(344, 7, '2026-05-13 14:15:52', 21, 'gan_php', 'php-v1', 0.71, '2026-05-13 22:15:52'),
(345, 7, '2026-05-13 15:15:52', 14, 'gan_php', 'php-v1', 0.64, '2026-05-13 22:15:52'),
(346, 7, '2026-05-13 16:15:52', 32, 'gan_php', 'php-v1', 0.82, '2026-05-13 22:15:52'),
(347, 7, '2026-05-13 17:15:52', 2, 'gan_php', 'php-v1', 0.52, '2026-05-13 22:15:52'),
(348, 7, '2026-05-13 18:15:52', 30, 'gan_php', 'php-v1', 0.80, '2026-05-13 22:15:52'),
(349, 7, '2026-05-13 19:15:52', 18, 'gan_php', 'php-v1', 0.68, '2026-05-13 22:15:52'),
(350, 7, '2026-05-13 20:15:52', 35, 'gan_php', 'php-v1', 0.85, '2026-05-13 22:15:52'),
(351, 8, '2026-05-11 19:15:52', 7, 'gan_php', 'php-v1', 0.57, '2026-05-13 22:15:52'),
(352, 8, '2026-05-11 20:15:52', 6, 'gan_php', 'php-v1', 0.56, '2026-05-13 22:15:52'),
(353, 8, '2026-05-11 21:15:52', 35, 'gan_php', 'php-v1', 0.85, '2026-05-13 22:15:52'),
(354, 8, '2026-05-11 22:15:52', 1, 'gan_php', 'php-v1', 0.51, '2026-05-13 22:15:52'),
(355, 8, '2026-05-11 23:15:52', 6, 'gan_php', 'php-v1', 0.56, '2026-05-13 22:15:52'),
(356, 8, '2026-05-12 00:15:52', 13, 'gan_php', 'php-v1', 0.63, '2026-05-13 22:15:52'),
(357, 8, '2026-05-12 01:15:52', 23, 'gan_php', 'php-v1', 0.73, '2026-05-13 22:15:52'),
(358, 8, '2026-05-12 02:15:52', 31, 'gan_php', 'php-v1', 0.81, '2026-05-13 22:15:52'),
(359, 8, '2026-05-12 03:15:52', 34, 'gan_php', 'php-v1', 0.84, '2026-05-13 22:15:52'),
(360, 8, '2026-05-12 04:15:52', 3, 'gan_php', 'php-v1', 0.53, '2026-05-13 22:15:52'),
(361, 8, '2026-05-12 05:15:52', 9, 'gan_php', 'php-v1', 0.59, '2026-05-13 22:15:52'),
(362, 8, '2026-05-12 06:15:52', 32, 'gan_php', 'php-v1', 0.82, '2026-05-13 22:15:52'),
(363, 8, '2026-05-12 07:15:52', 17, 'gan_php', 'php-v1', 0.67, '2026-05-13 22:15:52'),
(364, 8, '2026-05-12 08:15:52', 1, 'gan_php', 'php-v1', 0.51, '2026-05-13 22:15:52'),
(365, 8, '2026-05-12 09:15:52', 41, 'gan_php', 'php-v1', 0.91, '2026-05-13 22:15:52'),
(366, 8, '2026-05-12 10:15:52', 4, 'gan_php', 'php-v1', 0.54, '2026-05-13 22:15:52'),
(367, 8, '2026-05-12 11:15:52', 49, 'gan_php', 'php-v1', 0.99, '2026-05-13 22:15:52'),
(368, 8, '2026-05-12 12:15:52', 5, 'gan_php', 'php-v1', 0.55, '2026-05-13 22:15:52'),
(369, 8, '2026-05-12 13:15:52', 12, 'gan_php', 'php-v1', 0.62, '2026-05-13 22:15:52'),
(370, 8, '2026-05-12 14:15:52', 3, 'gan_php', 'php-v1', 0.53, '2026-05-13 22:15:52'),
(371, 8, '2026-05-12 15:15:52', 34, 'gan_php', 'php-v1', 0.84, '2026-05-13 22:15:52'),
(372, 8, '2026-05-12 16:15:52', 6, 'gan_php', 'php-v1', 0.56, '2026-05-13 22:15:52'),
(373, 8, '2026-05-12 17:15:52', 5, 'gan_php', 'php-v1', 0.55, '2026-05-13 22:15:52'),
(374, 8, '2026-05-12 18:15:52', 16, 'gan_php', 'php-v1', 0.66, '2026-05-13 22:15:52'),
(375, 8, '2026-05-12 19:15:52', 23, 'gan_php', 'php-v1', 0.73, '2026-05-13 22:15:52'),
(376, 8, '2026-05-12 20:15:52', 11, 'gan_php', 'php-v1', 0.61, '2026-05-13 22:15:52'),
(377, 8, '2026-05-12 21:15:52', 2, 'gan_php', 'php-v1', 0.52, '2026-05-13 22:15:52'),
(378, 8, '2026-05-12 22:15:52', 16, 'gan_php', 'php-v1', 0.66, '2026-05-13 22:15:52'),
(379, 8, '2026-05-12 23:15:52', 51, 'gan_php', 'php-v1', 0.99, '2026-05-13 22:15:52'),
(380, 8, '2026-05-13 00:15:52', 18, 'gan_php', 'php-v1', 0.68, '2026-05-13 22:15:52'),
(381, 8, '2026-05-13 01:15:52', 29, 'gan_php', 'php-v1', 0.79, '2026-05-13 22:15:52'),
(382, 8, '2026-05-13 02:15:52', 8, 'gan_php', 'php-v1', 0.58, '2026-05-13 22:15:52'),
(383, 8, '2026-05-13 03:15:52', 10, 'gan_php', 'php-v1', 0.60, '2026-05-13 22:15:52'),
(384, 8, '2026-05-13 04:15:52', 8, 'gan_php', 'php-v1', 0.58, '2026-05-13 22:15:52'),
(385, 8, '2026-05-13 05:15:52', 19, 'gan_php', 'php-v1', 0.69, '2026-05-13 22:15:52'),
(386, 8, '2026-05-13 06:15:52', 48, 'gan_php', 'php-v1', 0.98, '2026-05-13 22:15:52'),
(387, 8, '2026-05-13 07:15:52', 8, 'gan_php', 'php-v1', 0.58, '2026-05-13 22:15:52'),
(388, 8, '2026-05-13 08:15:52', 7, 'gan_php', 'php-v1', 0.57, '2026-05-13 22:15:52'),
(389, 8, '2026-05-13 09:15:52', 11, 'gan_php', 'php-v1', 0.61, '2026-05-13 22:15:52'),
(390, 8, '2026-05-13 10:15:52', 23, 'gan_php', 'php-v1', 0.73, '2026-05-13 22:15:52'),
(391, 8, '2026-05-13 11:15:52', 43, 'gan_php', 'php-v1', 0.93, '2026-05-13 22:15:52'),
(392, 8, '2026-05-13 12:15:52', 32, 'gan_php', 'php-v1', 0.82, '2026-05-13 22:15:52'),
(393, 8, '2026-05-13 13:15:52', 18, 'gan_php', 'php-v1', 0.68, '2026-05-13 22:15:52'),
(394, 8, '2026-05-13 14:15:52', 34, 'gan_php', 'php-v1', 0.84, '2026-05-13 22:15:52'),
(395, 8, '2026-05-13 15:15:52', 19, 'gan_php', 'php-v1', 0.69, '2026-05-13 22:15:52'),
(396, 8, '2026-05-13 16:15:52', 26, 'gan_php', 'php-v1', 0.76, '2026-05-13 22:15:52'),
(397, 8, '2026-05-13 17:15:52', 4, 'gan_php', 'php-v1', 0.54, '2026-05-13 22:15:52'),
(398, 8, '2026-05-13 18:15:52', 2, 'gan_php', 'php-v1', 0.52, '2026-05-13 22:15:52'),
(399, 8, '2026-05-13 19:15:52', 41, 'gan_php', 'php-v1', 0.91, '2026-05-13 22:15:52'),
(400, 8, '2026-05-13 20:15:52', 2, 'gan_php', 'php-v1', 0.52, '2026-05-13 22:15:52'),
(401, 1, '2026-05-12 10:07:11', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:07:11'),
(402, 1, '2026-05-12 11:07:11', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:07:11'),
(403, 1, '2026-05-12 12:07:11', 8, 'gan_php', 'php-v1', 0.58, '2026-05-14 13:07:11'),
(404, 1, '2026-05-12 13:07:11', 5, 'gan_php', 'php-v1', 0.55, '2026-05-14 13:07:11'),
(405, 1, '2026-05-12 14:07:11', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:07:11'),
(406, 1, '2026-05-12 15:07:11', 1, 'gan_php', 'php-v1', 0.51, '2026-05-14 13:07:11'),
(407, 1, '2026-05-12 16:07:11', 4, 'gan_php', 'php-v1', 0.54, '2026-05-14 13:07:11'),
(408, 1, '2026-05-12 17:07:11', 4, 'gan_php', 'php-v1', 0.54, '2026-05-14 13:07:11'),
(409, 1, '2026-05-12 18:07:11', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:07:11'),
(410, 1, '2026-05-12 19:07:11', 8, 'gan_php', 'php-v1', 0.58, '2026-05-14 13:07:11'),
(411, 1, '2026-05-12 20:07:11', 4, 'gan_php', 'php-v1', 0.54, '2026-05-14 13:07:11'),
(412, 1, '2026-05-12 21:07:11', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:07:11'),
(413, 1, '2026-05-12 22:07:11', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:07:11'),
(414, 1, '2026-05-12 23:07:11', 17, 'gan_php', 'php-v1', 0.67, '2026-05-14 13:07:11'),
(415, 1, '2026-05-13 00:07:11', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:07:11'),
(416, 1, '2026-05-13 01:07:11', 16, 'gan_php', 'php-v1', 0.66, '2026-05-14 13:07:11'),
(417, 1, '2026-05-13 02:07:11', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:07:11'),
(418, 1, '2026-05-13 03:07:11', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:07:11'),
(419, 1, '2026-05-13 04:07:11', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:07:11'),
(420, 1, '2026-05-13 05:07:11', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:07:11'),
(421, 1, '2026-05-13 06:07:11', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:07:11'),
(422, 1, '2026-05-13 07:07:11', 16, 'gan_php', 'php-v1', 0.66, '2026-05-14 13:07:11'),
(423, 1, '2026-05-13 08:07:11', 7, 'gan_php', 'php-v1', 0.57, '2026-05-14 13:07:11'),
(424, 1, '2026-05-13 09:07:11', 15, 'gan_php', 'php-v1', 0.65, '2026-05-14 13:07:11'),
(425, 1, '2026-05-13 10:07:11', 7, 'gan_php', 'php-v1', 0.57, '2026-05-14 13:07:11'),
(426, 1, '2026-05-13 11:07:11', 4, 'gan_php', 'php-v1', 0.54, '2026-05-14 13:07:11'),
(427, 1, '2026-05-13 12:07:11', 15, 'gan_php', 'php-v1', 0.65, '2026-05-14 13:07:11'),
(428, 1, '2026-05-13 13:07:11', 8, 'gan_php', 'php-v1', 0.58, '2026-05-14 13:07:11'),
(429, 1, '2026-05-13 14:07:11', 15, 'gan_php', 'php-v1', 0.65, '2026-05-14 13:07:11'),
(430, 1, '2026-05-13 15:07:11', 19, 'gan_php', 'php-v1', 0.69, '2026-05-14 13:07:11'),
(431, 1, '2026-05-13 16:07:11', 2, 'gan_php', 'php-v1', 0.52, '2026-05-14 13:07:11'),
(432, 1, '2026-05-13 17:07:11', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:07:11'),
(433, 1, '2026-05-13 18:07:11', 5, 'gan_php', 'php-v1', 0.55, '2026-05-14 13:07:11'),
(434, 1, '2026-05-13 19:07:11', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:07:11'),
(435, 1, '2026-05-13 20:07:11', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:07:11'),
(436, 1, '2026-05-13 21:07:11', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:07:11'),
(437, 1, '2026-05-13 22:07:11', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:07:11'),
(438, 1, '2026-05-13 23:07:11', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:07:11'),
(439, 1, '2026-05-14 00:07:11', 4, 'gan_php', 'php-v1', 0.54, '2026-05-14 13:07:11'),
(440, 1, '2026-05-14 01:07:11', 6, 'gan_php', 'php-v1', 0.56, '2026-05-14 13:07:11'),
(441, 1, '2026-05-14 02:07:11', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:07:11'),
(442, 1, '2026-05-14 03:07:11', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:07:11'),
(443, 1, '2026-05-14 04:07:11', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:07:11'),
(444, 1, '2026-05-14 05:07:11', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:07:11'),
(445, 1, '2026-05-14 06:07:11', 15, 'gan_php', 'php-v1', 0.65, '2026-05-14 13:07:11'),
(446, 1, '2026-05-14 07:07:11', 5, 'gan_php', 'php-v1', 0.55, '2026-05-14 13:07:11'),
(447, 1, '2026-05-14 08:07:11', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:07:11'),
(448, 1, '2026-05-14 09:07:11', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:07:11'),
(449, 1, '2026-05-14 10:07:11', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:07:11'),
(450, 1, '2026-05-14 11:07:11', 23, 'gan_php', 'php-v1', 0.73, '2026-05-14 13:07:11'),
(451, 2, '2026-05-12 10:07:11', 2, 'gan_php', 'php-v1', 0.52, '2026-05-14 13:07:11'),
(452, 2, '2026-05-12 11:07:11', 16, 'gan_php', 'php-v1', 0.66, '2026-05-14 13:07:11'),
(453, 2, '2026-05-12 12:07:11', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:07:11'),
(454, 2, '2026-05-12 13:07:11', 31, 'gan_php', 'php-v1', 0.81, '2026-05-14 13:07:11'),
(455, 2, '2026-05-12 14:07:11', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:07:11'),
(456, 2, '2026-05-12 15:07:11', 22, 'gan_php', 'php-v1', 0.72, '2026-05-14 13:07:11'),
(457, 2, '2026-05-12 16:07:11', 19, 'gan_php', 'php-v1', 0.69, '2026-05-14 13:07:11'),
(458, 2, '2026-05-12 17:07:11', 6, 'gan_php', 'php-v1', 0.56, '2026-05-14 13:07:11'),
(459, 2, '2026-05-12 18:07:11', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:07:11'),
(460, 2, '2026-05-12 19:07:11', 6, 'gan_php', 'php-v1', 0.56, '2026-05-14 13:07:11'),
(461, 2, '2026-05-12 20:07:11', 6, 'gan_php', 'php-v1', 0.56, '2026-05-14 13:07:11'),
(462, 2, '2026-05-12 21:07:11', 16, 'gan_php', 'php-v1', 0.66, '2026-05-14 13:07:11'),
(463, 2, '2026-05-12 22:07:11', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:07:11'),
(464, 2, '2026-05-12 23:07:11', 3, 'gan_php', 'php-v1', 0.53, '2026-05-14 13:07:11'),
(465, 2, '2026-05-13 00:07:11', 5, 'gan_php', 'php-v1', 0.55, '2026-05-14 13:07:11'),
(466, 2, '2026-05-13 01:07:11', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:07:11'),
(467, 2, '2026-05-13 02:07:11', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:07:11'),
(468, 2, '2026-05-13 03:07:11', 6, 'gan_php', 'php-v1', 0.56, '2026-05-14 13:07:11'),
(469, 2, '2026-05-13 04:07:11', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:07:11'),
(470, 2, '2026-05-13 05:07:11', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:07:11'),
(471, 2, '2026-05-13 06:07:11', 15, 'gan_php', 'php-v1', 0.65, '2026-05-14 13:07:11'),
(472, 2, '2026-05-13 07:07:11', 5, 'gan_php', 'php-v1', 0.55, '2026-05-14 13:07:11'),
(473, 2, '2026-05-13 08:07:11', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:07:11'),
(474, 2, '2026-05-13 09:07:11', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:07:11'),
(475, 2, '2026-05-13 10:07:11', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:07:11'),
(476, 2, '2026-05-13 11:07:11', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:07:11'),
(477, 2, '2026-05-13 12:07:11', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:07:11'),
(478, 2, '2026-05-13 13:07:11', 4, 'gan_php', 'php-v1', 0.54, '2026-05-14 13:07:11'),
(479, 2, '2026-05-13 14:07:11', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:07:11'),
(480, 2, '2026-05-13 15:07:11', 6, 'gan_php', 'php-v1', 0.56, '2026-05-14 13:07:11'),
(481, 2, '2026-05-13 16:07:11', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:07:11'),
(482, 2, '2026-05-13 17:07:11', 5, 'gan_php', 'php-v1', 0.55, '2026-05-14 13:07:11'),
(483, 2, '2026-05-13 18:07:11', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:07:11'),
(484, 2, '2026-05-13 19:07:11', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:07:11'),
(485, 2, '2026-05-13 20:07:11', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:07:11'),
(486, 2, '2026-05-13 21:07:11', 4, 'gan_php', 'php-v1', 0.54, '2026-05-14 13:07:11'),
(487, 2, '2026-05-13 22:07:11', 20, 'gan_php', 'php-v1', 0.70, '2026-05-14 13:07:11'),
(488, 2, '2026-05-13 23:07:11', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:07:11'),
(489, 2, '2026-05-14 00:07:11', 15, 'gan_php', 'php-v1', 0.65, '2026-05-14 13:07:11'),
(490, 2, '2026-05-14 01:07:11', 6, 'gan_php', 'php-v1', 0.56, '2026-05-14 13:07:11'),
(491, 2, '2026-05-14 02:07:11', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:07:11'),
(492, 2, '2026-05-14 03:07:11', 4, 'gan_php', 'php-v1', 0.54, '2026-05-14 13:07:11'),
(493, 2, '2026-05-14 04:07:11', 7, 'gan_php', 'php-v1', 0.57, '2026-05-14 13:07:11'),
(494, 2, '2026-05-14 05:07:11', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:07:11'),
(495, 2, '2026-05-14 06:07:11', 32, 'gan_php', 'php-v1', 0.82, '2026-05-14 13:07:11'),
(496, 2, '2026-05-14 07:07:11', 7, 'gan_php', 'php-v1', 0.57, '2026-05-14 13:07:11'),
(497, 2, '2026-05-14 08:07:11', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:07:11'),
(498, 2, '2026-05-14 09:07:11', 17, 'gan_php', 'php-v1', 0.67, '2026-05-14 13:07:11'),
(499, 2, '2026-05-14 10:07:11', 17, 'gan_php', 'php-v1', 0.67, '2026-05-14 13:07:11'),
(500, 2, '2026-05-14 11:07:11', 24, 'gan_php', 'php-v1', 0.74, '2026-05-14 13:07:11'),
(501, 3, '2026-05-12 10:07:11', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:07:11'),
(502, 3, '2026-05-12 11:07:11', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:07:11'),
(503, 3, '2026-05-12 12:07:11', 19, 'gan_php', 'php-v1', 0.69, '2026-05-14 13:07:11'),
(504, 3, '2026-05-12 13:07:12', 6, 'gan_php', 'php-v1', 0.56, '2026-05-14 13:07:12'),
(505, 3, '2026-05-12 14:07:12', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:07:12'),
(506, 3, '2026-05-12 15:07:12', 8, 'gan_php', 'php-v1', 0.58, '2026-05-14 13:07:12'),
(507, 3, '2026-05-12 16:07:12', 27, 'gan_php', 'php-v1', 0.77, '2026-05-14 13:07:12'),
(508, 3, '2026-05-12 17:07:12', 21, 'gan_php', 'php-v1', 0.71, '2026-05-14 13:07:12'),
(509, 3, '2026-05-12 18:07:12', 4, 'gan_php', 'php-v1', 0.54, '2026-05-14 13:07:12'),
(510, 3, '2026-05-12 19:07:12', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:07:12'),
(511, 3, '2026-05-12 20:07:12', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:07:12'),
(512, 3, '2026-05-12 21:07:12', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:07:12'),
(513, 3, '2026-05-12 22:07:12', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:07:12'),
(514, 3, '2026-05-12 23:07:12', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:07:12'),
(515, 3, '2026-05-13 00:07:12', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:07:12'),
(516, 3, '2026-05-13 01:07:12', 19, 'gan_php', 'php-v1', 0.69, '2026-05-14 13:07:12'),
(517, 3, '2026-05-13 02:07:12', 8, 'gan_php', 'php-v1', 0.58, '2026-05-14 13:07:12'),
(518, 3, '2026-05-13 03:07:12', 26, 'gan_php', 'php-v1', 0.76, '2026-05-14 13:07:12'),
(519, 3, '2026-05-13 04:07:12', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:07:12'),
(520, 3, '2026-05-13 05:07:12', 4, 'gan_php', 'php-v1', 0.54, '2026-05-14 13:07:12'),
(521, 3, '2026-05-13 06:07:12', 3, 'gan_php', 'php-v1', 0.53, '2026-05-14 13:07:12'),
(522, 3, '2026-05-13 07:07:12', 5, 'gan_php', 'php-v1', 0.55, '2026-05-14 13:07:12'),
(523, 3, '2026-05-13 08:07:12', 6, 'gan_php', 'php-v1', 0.56, '2026-05-14 13:07:12'),
(524, 3, '2026-05-13 09:07:12', 2, 'gan_php', 'php-v1', 0.52, '2026-05-14 13:07:12'),
(525, 3, '2026-05-13 10:07:12', 17, 'gan_php', 'php-v1', 0.67, '2026-05-14 13:07:12'),
(526, 3, '2026-05-13 11:07:12', 6, 'gan_php', 'php-v1', 0.56, '2026-05-14 13:07:12'),
(527, 3, '2026-05-13 12:07:12', 6, 'gan_php', 'php-v1', 0.56, '2026-05-14 13:07:12'),
(528, 3, '2026-05-13 13:07:12', 3, 'gan_php', 'php-v1', 0.53, '2026-05-14 13:07:12'),
(529, 3, '2026-05-13 14:07:12', 27, 'gan_php', 'php-v1', 0.77, '2026-05-14 13:07:12'),
(530, 3, '2026-05-13 15:07:12', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:07:12'),
(531, 3, '2026-05-13 16:07:12', 5, 'gan_php', 'php-v1', 0.55, '2026-05-14 13:07:12'),
(532, 3, '2026-05-13 17:07:12', 23, 'gan_php', 'php-v1', 0.73, '2026-05-14 13:07:12'),
(533, 3, '2026-05-13 18:07:12', 6, 'gan_php', 'php-v1', 0.56, '2026-05-14 13:07:12'),
(534, 3, '2026-05-13 19:07:12', 2, 'gan_php', 'php-v1', 0.52, '2026-05-14 13:07:12'),
(535, 3, '2026-05-13 20:07:12', 17, 'gan_php', 'php-v1', 0.67, '2026-05-14 13:07:12'),
(536, 3, '2026-05-13 21:07:12', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:07:12'),
(537, 3, '2026-05-13 22:07:12', 1, 'gan_php', 'php-v1', 0.51, '2026-05-14 13:07:12'),
(538, 3, '2026-05-13 23:07:12', 4, 'gan_php', 'php-v1', 0.54, '2026-05-14 13:07:12'),
(539, 3, '2026-05-14 00:07:12', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:07:12'),
(540, 3, '2026-05-14 01:07:12', 16, 'gan_php', 'php-v1', 0.66, '2026-05-14 13:07:12'),
(541, 3, '2026-05-14 02:07:12', 4, 'gan_php', 'php-v1', 0.54, '2026-05-14 13:07:12'),
(542, 3, '2026-05-14 03:07:12', 8, 'gan_php', 'php-v1', 0.58, '2026-05-14 13:07:12'),
(543, 3, '2026-05-14 04:07:12', 21, 'gan_php', 'php-v1', 0.71, '2026-05-14 13:07:12'),
(544, 3, '2026-05-14 05:07:12', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:07:12'),
(545, 3, '2026-05-14 06:07:12', 15, 'gan_php', 'php-v1', 0.65, '2026-05-14 13:07:12'),
(546, 3, '2026-05-14 07:07:12', 21, 'gan_php', 'php-v1', 0.71, '2026-05-14 13:07:12'),
(547, 3, '2026-05-14 08:07:12', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:07:12'),
(548, 3, '2026-05-14 09:07:12', 5, 'gan_php', 'php-v1', 0.55, '2026-05-14 13:07:12'),
(549, 3, '2026-05-14 10:07:12', 19, 'gan_php', 'php-v1', 0.69, '2026-05-14 13:07:12'),
(550, 3, '2026-05-14 11:07:12', 8, 'gan_php', 'php-v1', 0.58, '2026-05-14 13:07:12'),
(551, 4, '2026-05-12 10:07:12', 8, 'gan_php', 'php-v1', 0.58, '2026-05-14 13:07:12'),
(552, 4, '2026-05-12 11:07:12', 7, 'gan_php', 'php-v1', 0.57, '2026-05-14 13:07:12'),
(553, 4, '2026-05-12 12:07:12', 4, 'gan_php', 'php-v1', 0.54, '2026-05-14 13:07:12'),
(554, 4, '2026-05-12 13:07:12', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:07:12'),
(555, 4, '2026-05-12 14:07:12', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:07:12'),
(556, 4, '2026-05-12 15:07:12', 7, 'gan_php', 'php-v1', 0.57, '2026-05-14 13:07:12'),
(557, 4, '2026-05-12 16:07:12', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:07:12'),
(558, 4, '2026-05-12 17:07:12', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:07:12'),
(559, 4, '2026-05-12 18:07:12', 16, 'gan_php', 'php-v1', 0.66, '2026-05-14 13:07:12'),
(560, 4, '2026-05-12 19:07:12', 7, 'gan_php', 'php-v1', 0.57, '2026-05-14 13:07:12'),
(561, 4, '2026-05-12 20:07:12', 6, 'gan_php', 'php-v1', 0.56, '2026-05-14 13:07:12'),
(562, 4, '2026-05-12 21:07:12', 8, 'gan_php', 'php-v1', 0.58, '2026-05-14 13:07:12'),
(563, 4, '2026-05-12 22:07:12', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:07:12'),
(564, 4, '2026-05-12 23:07:12', 32, 'gan_php', 'php-v1', 0.82, '2026-05-14 13:07:12'),
(565, 4, '2026-05-13 00:07:12', 8, 'gan_php', 'php-v1', 0.58, '2026-05-14 13:07:12'),
(566, 4, '2026-05-13 01:07:12', 8, 'gan_php', 'php-v1', 0.58, '2026-05-14 13:07:12'),
(567, 4, '2026-05-13 02:07:12', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:07:12'),
(568, 4, '2026-05-13 03:07:12', 7, 'gan_php', 'php-v1', 0.57, '2026-05-14 13:07:12'),
(569, 4, '2026-05-13 04:07:12', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:07:12'),
(570, 4, '2026-05-13 05:07:12', 22, 'gan_php', 'php-v1', 0.72, '2026-05-14 13:07:12'),
(571, 4, '2026-05-13 06:07:12', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:07:12'),
(572, 4, '2026-05-13 07:07:12', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:07:12'),
(573, 4, '2026-05-13 08:07:12', 8, 'gan_php', 'php-v1', 0.58, '2026-05-14 13:07:12'),
(574, 4, '2026-05-13 09:07:12', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:07:12'),
(575, 4, '2026-05-13 10:07:12', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:07:12'),
(576, 4, '2026-05-13 11:07:12', 20, 'gan_php', 'php-v1', 0.70, '2026-05-14 13:07:12'),
(577, 4, '2026-05-13 12:07:12', 3, 'gan_php', 'php-v1', 0.53, '2026-05-14 13:07:12'),
(578, 4, '2026-05-13 13:07:12', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:07:12'),
(579, 4, '2026-05-13 14:07:12', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:07:12'),
(580, 4, '2026-05-13 15:07:12', 22, 'gan_php', 'php-v1', 0.72, '2026-05-14 13:07:12'),
(581, 4, '2026-05-13 16:07:12', 7, 'gan_php', 'php-v1', 0.57, '2026-05-14 13:07:12'),
(582, 4, '2026-05-13 17:07:12', 28, 'gan_php', 'php-v1', 0.78, '2026-05-14 13:07:12'),
(583, 4, '2026-05-13 18:07:12', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:07:12'),
(584, 4, '2026-05-13 19:07:12', 26, 'gan_php', 'php-v1', 0.76, '2026-05-14 13:07:12'),
(585, 4, '2026-05-13 20:07:12', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:07:12'),
(586, 4, '2026-05-13 21:07:12', 2, 'gan_php', 'php-v1', 0.52, '2026-05-14 13:07:12'),
(587, 4, '2026-05-13 22:07:12', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:07:12'),
(588, 4, '2026-05-13 23:07:12', 15, 'gan_php', 'php-v1', 0.65, '2026-05-14 13:07:12'),
(589, 4, '2026-05-14 00:07:12', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:07:12'),
(590, 4, '2026-05-14 01:07:12', 17, 'gan_php', 'php-v1', 0.67, '2026-05-14 13:07:12');
INSERT INTO `risk_scores_augmented` (`id`, `zone_id`, `synthetic_at`, `score`, `generation_method`, `generator_version`, `fidelity_score`, `created_at`) VALUES
(591, 4, '2026-05-14 02:07:12', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:07:12'),
(592, 4, '2026-05-14 03:07:12', 3, 'gan_php', 'php-v1', 0.53, '2026-05-14 13:07:12'),
(593, 4, '2026-05-14 04:07:12', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:07:12'),
(594, 4, '2026-05-14 05:07:12', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:07:12'),
(595, 4, '2026-05-14 06:07:12', 23, 'gan_php', 'php-v1', 0.73, '2026-05-14 13:07:12'),
(596, 4, '2026-05-14 07:07:12', 6, 'gan_php', 'php-v1', 0.56, '2026-05-14 13:07:12'),
(597, 4, '2026-05-14 08:07:12', 19, 'gan_php', 'php-v1', 0.69, '2026-05-14 13:07:12'),
(598, 4, '2026-05-14 09:07:12', 3, 'gan_php', 'php-v1', 0.53, '2026-05-14 13:07:12'),
(599, 4, '2026-05-14 10:07:12', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:07:12'),
(600, 4, '2026-05-14 11:07:12', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:07:12'),
(601, 5, '2026-05-12 10:07:12', 35, 'gan_php', 'php-v1', 0.85, '2026-05-14 13:07:12'),
(602, 5, '2026-05-12 11:07:12', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:07:12'),
(603, 5, '2026-05-12 12:07:12', 8, 'gan_php', 'php-v1', 0.58, '2026-05-14 13:07:12'),
(604, 5, '2026-05-12 13:07:12', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:07:12'),
(605, 5, '2026-05-12 14:07:12', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:07:12'),
(606, 5, '2026-05-12 15:07:12', 24, 'gan_php', 'php-v1', 0.74, '2026-05-14 13:07:12'),
(607, 5, '2026-05-12 16:07:12', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:07:12'),
(608, 5, '2026-05-12 17:07:12', 5, 'gan_php', 'php-v1', 0.55, '2026-05-14 13:07:12'),
(609, 5, '2026-05-12 18:07:12', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:07:12'),
(610, 5, '2026-05-12 19:07:12', 5, 'gan_php', 'php-v1', 0.55, '2026-05-14 13:07:12'),
(611, 5, '2026-05-12 20:07:12', 2, 'gan_php', 'php-v1', 0.52, '2026-05-14 13:07:12'),
(612, 5, '2026-05-12 21:07:12', 8, 'gan_php', 'php-v1', 0.58, '2026-05-14 13:07:12'),
(613, 5, '2026-05-12 22:07:12', 20, 'gan_php', 'php-v1', 0.70, '2026-05-14 13:07:12'),
(614, 5, '2026-05-12 23:07:12', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:07:12'),
(615, 5, '2026-05-13 00:07:12', 6, 'gan_php', 'php-v1', 0.56, '2026-05-14 13:07:12'),
(616, 5, '2026-05-13 01:07:12', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:07:12'),
(617, 5, '2026-05-13 02:07:12', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:07:12'),
(618, 5, '2026-05-13 03:07:12', 19, 'gan_php', 'php-v1', 0.69, '2026-05-14 13:07:12'),
(619, 5, '2026-05-13 04:07:12', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:07:12'),
(620, 5, '2026-05-13 05:07:12', 7, 'gan_php', 'php-v1', 0.57, '2026-05-14 13:07:12'),
(621, 5, '2026-05-13 06:07:12', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:07:12'),
(622, 5, '2026-05-13 07:07:12', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:07:12'),
(623, 5, '2026-05-13 08:07:12', 16, 'gan_php', 'php-v1', 0.66, '2026-05-14 13:07:12'),
(624, 5, '2026-05-13 09:07:12', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:07:12'),
(625, 5, '2026-05-13 10:07:12', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:07:12'),
(626, 5, '2026-05-13 11:07:12', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:07:12'),
(627, 5, '2026-05-13 12:07:12', 3, 'gan_php', 'php-v1', 0.53, '2026-05-14 13:07:12'),
(628, 5, '2026-05-13 13:07:12', 4, 'gan_php', 'php-v1', 0.54, '2026-05-14 13:07:12'),
(629, 5, '2026-05-13 14:07:12', 15, 'gan_php', 'php-v1', 0.65, '2026-05-14 13:07:12'),
(630, 5, '2026-05-13 15:07:12', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:07:12'),
(631, 5, '2026-05-13 16:07:12', 3, 'gan_php', 'php-v1', 0.53, '2026-05-14 13:07:12'),
(632, 5, '2026-05-13 17:07:12', 2, 'gan_php', 'php-v1', 0.52, '2026-05-14 13:07:12'),
(633, 5, '2026-05-13 18:07:12', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:07:12'),
(634, 5, '2026-05-13 19:07:12', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:07:12'),
(635, 5, '2026-05-13 20:07:12', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:07:12'),
(636, 5, '2026-05-13 21:07:12', 8, 'gan_php', 'php-v1', 0.58, '2026-05-14 13:07:12'),
(637, 5, '2026-05-13 22:07:12', 15, 'gan_php', 'php-v1', 0.65, '2026-05-14 13:07:12'),
(638, 5, '2026-05-13 23:07:12', 15, 'gan_php', 'php-v1', 0.65, '2026-05-14 13:07:12'),
(639, 5, '2026-05-14 00:07:12', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:07:12'),
(640, 5, '2026-05-14 01:07:12', 8, 'gan_php', 'php-v1', 0.58, '2026-05-14 13:07:12'),
(641, 5, '2026-05-14 02:07:12', 7, 'gan_php', 'php-v1', 0.57, '2026-05-14 13:07:12'),
(642, 5, '2026-05-14 03:07:12', 8, 'gan_php', 'php-v1', 0.58, '2026-05-14 13:07:12'),
(643, 5, '2026-05-14 04:07:12', 21, 'gan_php', 'php-v1', 0.71, '2026-05-14 13:07:12'),
(644, 5, '2026-05-14 05:07:12', 6, 'gan_php', 'php-v1', 0.56, '2026-05-14 13:07:12'),
(645, 5, '2026-05-14 06:07:12', 19, 'gan_php', 'php-v1', 0.69, '2026-05-14 13:07:12'),
(646, 5, '2026-05-14 07:07:12', 5, 'gan_php', 'php-v1', 0.55, '2026-05-14 13:07:12'),
(647, 5, '2026-05-14 08:07:12', 15, 'gan_php', 'php-v1', 0.65, '2026-05-14 13:07:12'),
(648, 5, '2026-05-14 09:07:12', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:07:12'),
(649, 5, '2026-05-14 10:07:12', 20, 'gan_php', 'php-v1', 0.70, '2026-05-14 13:07:12'),
(650, 5, '2026-05-14 11:07:12', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:07:12'),
(651, 6, '2026-05-12 10:07:12', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:07:12'),
(652, 6, '2026-05-12 11:07:12', 7, 'gan_php', 'php-v1', 0.57, '2026-05-14 13:07:12'),
(653, 6, '2026-05-12 12:07:12', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:07:12'),
(654, 6, '2026-05-12 13:07:12', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:07:12'),
(655, 6, '2026-05-12 14:07:12', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:07:12'),
(656, 6, '2026-05-12 15:07:12', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:07:12'),
(657, 6, '2026-05-12 16:07:12', 24, 'gan_php', 'php-v1', 0.74, '2026-05-14 13:07:12'),
(658, 6, '2026-05-12 17:07:12', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:07:12'),
(659, 6, '2026-05-12 18:07:12', 3, 'gan_php', 'php-v1', 0.53, '2026-05-14 13:07:12'),
(660, 6, '2026-05-12 19:07:12', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:07:12'),
(661, 6, '2026-05-12 20:07:12', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:07:12'),
(662, 6, '2026-05-12 21:07:12', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:07:12'),
(663, 6, '2026-05-12 22:07:12', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:07:12'),
(664, 6, '2026-05-12 23:07:12', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:07:12'),
(665, 6, '2026-05-13 00:07:12', 16, 'gan_php', 'php-v1', 0.66, '2026-05-14 13:07:12'),
(666, 6, '2026-05-13 01:07:12', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:07:12'),
(667, 6, '2026-05-13 02:07:12', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:07:12'),
(668, 6, '2026-05-13 03:07:12', 4, 'gan_php', 'php-v1', 0.54, '2026-05-14 13:07:12'),
(669, 6, '2026-05-13 04:07:12', 7, 'gan_php', 'php-v1', 0.57, '2026-05-14 13:07:12'),
(670, 6, '2026-05-13 05:07:12', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:07:12'),
(671, 6, '2026-05-13 06:07:12', 24, 'gan_php', 'php-v1', 0.74, '2026-05-14 13:07:12'),
(672, 6, '2026-05-13 07:07:12', 20, 'gan_php', 'php-v1', 0.70, '2026-05-14 13:07:12'),
(673, 6, '2026-05-13 08:07:12', 21, 'gan_php', 'php-v1', 0.71, '2026-05-14 13:07:12'),
(674, 6, '2026-05-13 09:07:12', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:07:12'),
(675, 6, '2026-05-13 10:07:12', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:07:12'),
(676, 6, '2026-05-13 11:07:13', 15, 'gan_php', 'php-v1', 0.65, '2026-05-14 13:07:13'),
(677, 6, '2026-05-13 12:07:13', 7, 'gan_php', 'php-v1', 0.57, '2026-05-14 13:07:13'),
(678, 6, '2026-05-13 13:07:13', 24, 'gan_php', 'php-v1', 0.74, '2026-05-14 13:07:13'),
(679, 6, '2026-05-13 14:07:13', 20, 'gan_php', 'php-v1', 0.70, '2026-05-14 13:07:13'),
(680, 6, '2026-05-13 15:07:13', 6, 'gan_php', 'php-v1', 0.56, '2026-05-14 13:07:13'),
(681, 6, '2026-05-13 16:07:13', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:07:13'),
(682, 6, '2026-05-13 17:07:13', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:07:13'),
(683, 6, '2026-05-13 18:07:13', 26, 'gan_php', 'php-v1', 0.76, '2026-05-14 13:07:13'),
(684, 6, '2026-05-13 19:07:13', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:07:13'),
(685, 6, '2026-05-13 20:07:13', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:07:13'),
(686, 6, '2026-05-13 21:07:13', 7, 'gan_php', 'php-v1', 0.57, '2026-05-14 13:07:13'),
(687, 6, '2026-05-13 22:07:13', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:07:13'),
(688, 6, '2026-05-13 23:07:13', 4, 'gan_php', 'php-v1', 0.54, '2026-05-14 13:07:13'),
(689, 6, '2026-05-14 00:07:13', 23, 'gan_php', 'php-v1', 0.73, '2026-05-14 13:07:13'),
(690, 6, '2026-05-14 01:07:13', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:07:13'),
(691, 6, '2026-05-14 02:07:13', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:07:13'),
(692, 6, '2026-05-14 03:07:13', 28, 'gan_php', 'php-v1', 0.78, '2026-05-14 13:07:13'),
(693, 6, '2026-05-14 04:07:13', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:07:13'),
(694, 6, '2026-05-14 05:07:13', 3, 'gan_php', 'php-v1', 0.53, '2026-05-14 13:07:13'),
(695, 6, '2026-05-14 06:07:13', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:07:13'),
(696, 6, '2026-05-14 07:07:13', 6, 'gan_php', 'php-v1', 0.56, '2026-05-14 13:07:13'),
(697, 6, '2026-05-14 08:07:13', 5, 'gan_php', 'php-v1', 0.55, '2026-05-14 13:07:13'),
(698, 6, '2026-05-14 09:07:13', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:07:13'),
(699, 6, '2026-05-14 10:07:13', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:07:13'),
(700, 6, '2026-05-14 11:07:13', 6, 'gan_php', 'php-v1', 0.56, '2026-05-14 13:07:13'),
(701, 7, '2026-05-12 10:07:13', 4, 'gan_php', 'php-v1', 0.54, '2026-05-14 13:07:13'),
(702, 7, '2026-05-12 11:07:13', 6, 'gan_php', 'php-v1', 0.56, '2026-05-14 13:07:13'),
(703, 7, '2026-05-12 12:07:13', 5, 'gan_php', 'php-v1', 0.55, '2026-05-14 13:07:13'),
(704, 7, '2026-05-12 13:07:13', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:07:13'),
(705, 7, '2026-05-12 14:07:13', 29, 'gan_php', 'php-v1', 0.79, '2026-05-14 13:07:13'),
(706, 7, '2026-05-12 15:07:13', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:07:13'),
(707, 7, '2026-05-12 16:07:13', 20, 'gan_php', 'php-v1', 0.70, '2026-05-14 13:07:13'),
(708, 7, '2026-05-12 17:07:13', 5, 'gan_php', 'php-v1', 0.55, '2026-05-14 13:07:13'),
(709, 7, '2026-05-12 18:07:13', 15, 'gan_php', 'php-v1', 0.65, '2026-05-14 13:07:13'),
(710, 7, '2026-05-12 19:07:13', 4, 'gan_php', 'php-v1', 0.54, '2026-05-14 13:07:13'),
(711, 7, '2026-05-12 20:07:13', 16, 'gan_php', 'php-v1', 0.66, '2026-05-14 13:07:13'),
(712, 7, '2026-05-12 21:07:13', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:07:13'),
(713, 7, '2026-05-12 22:07:13', 6, 'gan_php', 'php-v1', 0.56, '2026-05-14 13:07:13'),
(714, 7, '2026-05-12 23:07:13', 23, 'gan_php', 'php-v1', 0.73, '2026-05-14 13:07:13'),
(715, 7, '2026-05-13 00:07:13', 19, 'gan_php', 'php-v1', 0.69, '2026-05-14 13:07:13'),
(716, 7, '2026-05-13 01:07:13', 5, 'gan_php', 'php-v1', 0.55, '2026-05-14 13:07:13'),
(717, 7, '2026-05-13 02:07:13', 5, 'gan_php', 'php-v1', 0.55, '2026-05-14 13:07:13'),
(718, 7, '2026-05-13 03:07:13', 4, 'gan_php', 'php-v1', 0.54, '2026-05-14 13:07:13'),
(719, 7, '2026-05-13 04:07:13', 20, 'gan_php', 'php-v1', 0.70, '2026-05-14 13:07:13'),
(720, 7, '2026-05-13 05:07:13', 8, 'gan_php', 'php-v1', 0.58, '2026-05-14 13:07:13'),
(721, 7, '2026-05-13 06:07:13', 17, 'gan_php', 'php-v1', 0.67, '2026-05-14 13:07:13'),
(722, 7, '2026-05-13 07:07:13', 16, 'gan_php', 'php-v1', 0.66, '2026-05-14 13:07:13'),
(723, 7, '2026-05-13 08:07:13', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:07:13'),
(724, 7, '2026-05-13 09:07:13', 2, 'gan_php', 'php-v1', 0.52, '2026-05-14 13:07:13'),
(725, 7, '2026-05-13 10:07:13', 20, 'gan_php', 'php-v1', 0.70, '2026-05-14 13:07:13'),
(726, 7, '2026-05-13 11:07:13', 4, 'gan_php', 'php-v1', 0.54, '2026-05-14 13:07:13'),
(727, 7, '2026-05-13 12:07:13', 3, 'gan_php', 'php-v1', 0.53, '2026-05-14 13:07:13'),
(728, 7, '2026-05-13 13:07:13', 5, 'gan_php', 'php-v1', 0.55, '2026-05-14 13:07:13'),
(729, 7, '2026-05-13 14:07:13', 6, 'gan_php', 'php-v1', 0.56, '2026-05-14 13:07:13'),
(730, 7, '2026-05-13 15:07:13', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:07:13'),
(731, 7, '2026-05-13 16:07:13', 17, 'gan_php', 'php-v1', 0.67, '2026-05-14 13:07:13'),
(732, 7, '2026-05-13 17:07:13', 19, 'gan_php', 'php-v1', 0.69, '2026-05-14 13:07:13'),
(733, 7, '2026-05-13 18:07:13', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:07:13'),
(734, 7, '2026-05-13 19:07:13', 20, 'gan_php', 'php-v1', 0.70, '2026-05-14 13:07:13'),
(735, 7, '2026-05-13 20:07:13', 6, 'gan_php', 'php-v1', 0.56, '2026-05-14 13:07:13'),
(736, 7, '2026-05-13 21:07:13', 5, 'gan_php', 'php-v1', 0.55, '2026-05-14 13:07:13'),
(737, 7, '2026-05-13 22:07:13', 5, 'gan_php', 'php-v1', 0.55, '2026-05-14 13:07:13'),
(738, 7, '2026-05-13 23:07:13', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:07:13'),
(739, 7, '2026-05-14 00:07:13', 17, 'gan_php', 'php-v1', 0.67, '2026-05-14 13:07:13'),
(740, 7, '2026-05-14 01:07:13', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:07:13'),
(741, 7, '2026-05-14 02:07:13', 20, 'gan_php', 'php-v1', 0.70, '2026-05-14 13:07:13'),
(742, 7, '2026-05-14 03:07:13', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:07:13'),
(743, 7, '2026-05-14 04:07:13', 8, 'gan_php', 'php-v1', 0.58, '2026-05-14 13:07:13'),
(744, 7, '2026-05-14 05:07:13', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:07:13'),
(745, 7, '2026-05-14 06:07:13', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:07:13'),
(746, 7, '2026-05-14 07:07:13', 16, 'gan_php', 'php-v1', 0.66, '2026-05-14 13:07:13'),
(747, 7, '2026-05-14 08:07:13', 6, 'gan_php', 'php-v1', 0.56, '2026-05-14 13:07:13'),
(748, 7, '2026-05-14 09:07:13', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:07:13'),
(749, 7, '2026-05-14 10:07:13', 6, 'gan_php', 'php-v1', 0.56, '2026-05-14 13:07:13'),
(750, 7, '2026-05-14 11:07:13', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:07:13'),
(751, 8, '2026-05-12 10:07:13', 3, 'gan_php', 'php-v1', 0.53, '2026-05-14 13:07:13'),
(752, 8, '2026-05-12 11:07:13', 5, 'gan_php', 'php-v1', 0.55, '2026-05-14 13:07:13'),
(753, 8, '2026-05-12 12:07:13', 7, 'gan_php', 'php-v1', 0.57, '2026-05-14 13:07:13'),
(754, 8, '2026-05-12 13:07:13', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:07:13'),
(755, 8, '2026-05-12 14:07:13', 6, 'gan_php', 'php-v1', 0.56, '2026-05-14 13:07:13'),
(756, 8, '2026-05-12 15:07:13', 16, 'gan_php', 'php-v1', 0.66, '2026-05-14 13:07:13'),
(757, 8, '2026-05-12 16:07:13', 21, 'gan_php', 'php-v1', 0.71, '2026-05-14 13:07:13'),
(758, 8, '2026-05-12 17:07:13', 25, 'gan_php', 'php-v1', 0.75, '2026-05-14 13:07:13'),
(759, 8, '2026-05-12 18:07:13', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:07:13'),
(760, 8, '2026-05-12 19:07:13', 7, 'gan_php', 'php-v1', 0.57, '2026-05-14 13:07:13'),
(761, 8, '2026-05-12 20:07:13', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:07:13'),
(762, 8, '2026-05-12 21:07:13', 15, 'gan_php', 'php-v1', 0.65, '2026-05-14 13:07:13'),
(763, 8, '2026-05-12 22:07:13', 16, 'gan_php', 'php-v1', 0.66, '2026-05-14 13:07:13'),
(764, 8, '2026-05-12 23:07:13', 4, 'gan_php', 'php-v1', 0.54, '2026-05-14 13:07:13'),
(765, 8, '2026-05-13 00:07:13', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:07:13'),
(766, 8, '2026-05-13 01:07:13', 5, 'gan_php', 'php-v1', 0.55, '2026-05-14 13:07:13'),
(767, 8, '2026-05-13 02:07:13', 7, 'gan_php', 'php-v1', 0.57, '2026-05-14 13:07:13'),
(768, 8, '2026-05-13 03:07:13', 8, 'gan_php', 'php-v1', 0.58, '2026-05-14 13:07:13'),
(769, 8, '2026-05-13 04:07:13', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:07:13'),
(770, 8, '2026-05-13 05:07:13', 19, 'gan_php', 'php-v1', 0.69, '2026-05-14 13:07:13'),
(771, 8, '2026-05-13 06:07:13', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:07:13'),
(772, 8, '2026-05-13 07:07:13', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:07:13'),
(773, 8, '2026-05-13 08:07:13', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:07:13'),
(774, 8, '2026-05-13 09:07:13', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:07:13'),
(775, 8, '2026-05-13 10:07:13', 8, 'gan_php', 'php-v1', 0.58, '2026-05-14 13:07:13'),
(776, 8, '2026-05-13 11:07:13', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:07:13'),
(777, 8, '2026-05-13 12:07:13', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:07:13'),
(778, 8, '2026-05-13 13:07:13', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:07:13'),
(779, 8, '2026-05-13 14:07:13', 6, 'gan_php', 'php-v1', 0.56, '2026-05-14 13:07:13'),
(780, 8, '2026-05-13 15:07:13', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:07:13'),
(781, 8, '2026-05-13 16:07:13', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:07:13'),
(782, 8, '2026-05-13 17:07:13', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:07:13'),
(783, 8, '2026-05-13 18:07:13', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:07:13'),
(784, 8, '2026-05-13 19:07:13', 8, 'gan_php', 'php-v1', 0.58, '2026-05-14 13:07:13'),
(785, 8, '2026-05-13 20:07:13', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:07:13'),
(786, 8, '2026-05-13 21:07:13', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:07:13'),
(787, 8, '2026-05-13 22:07:13', 2, 'gan_php', 'php-v1', 0.52, '2026-05-14 13:07:13'),
(788, 8, '2026-05-13 23:07:13', 15, 'gan_php', 'php-v1', 0.65, '2026-05-14 13:07:13'),
(789, 8, '2026-05-14 00:07:13', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:07:13'),
(790, 8, '2026-05-14 01:07:13', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:07:13'),
(791, 8, '2026-05-14 02:07:13', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:07:13'),
(792, 8, '2026-05-14 03:07:13', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:07:13'),
(793, 8, '2026-05-14 04:07:13', 5, 'gan_php', 'php-v1', 0.55, '2026-05-14 13:07:13'),
(794, 8, '2026-05-14 05:07:13', 2, 'gan_php', 'php-v1', 0.52, '2026-05-14 13:07:13'),
(795, 8, '2026-05-14 06:07:13', 4, 'gan_php', 'php-v1', 0.54, '2026-05-14 13:07:13'),
(796, 8, '2026-05-14 07:07:13', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:07:13'),
(797, 8, '2026-05-14 08:07:13', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:07:13'),
(798, 8, '2026-05-14 09:07:13', 7, 'gan_php', 'php-v1', 0.57, '2026-05-14 13:07:13'),
(799, 8, '2026-05-14 10:07:13', 4, 'gan_php', 'php-v1', 0.54, '2026-05-14 13:07:13'),
(800, 8, '2026-05-14 11:07:13', 6, 'gan_php', 'php-v1', 0.56, '2026-05-14 13:07:13'),
(801, 1, '2026-05-12 10:15:26', 15, 'gan_php', 'php-v1', 0.65, '2026-05-14 13:15:26'),
(802, 1, '2026-05-12 11:15:26', 17, 'gan_php', 'php-v1', 0.67, '2026-05-14 13:15:26'),
(803, 1, '2026-05-12 12:15:26', 23, 'gan_php', 'php-v1', 0.73, '2026-05-14 13:15:26'),
(804, 1, '2026-05-12 13:15:26', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:15:26'),
(805, 1, '2026-05-12 14:15:26', 29, 'gan_php', 'php-v1', 0.79, '2026-05-14 13:15:26'),
(806, 1, '2026-05-12 15:15:26', 25, 'gan_php', 'php-v1', 0.75, '2026-05-14 13:15:26'),
(807, 1, '2026-05-12 16:15:26', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:15:26'),
(808, 1, '2026-05-12 17:15:26', 24, 'gan_php', 'php-v1', 0.74, '2026-05-14 13:15:26'),
(809, 1, '2026-05-12 18:15:26', 28, 'gan_php', 'php-v1', 0.78, '2026-05-14 13:15:26'),
(810, 1, '2026-05-12 19:15:26', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:15:26'),
(811, 1, '2026-05-12 20:15:26', 23, 'gan_php', 'php-v1', 0.73, '2026-05-14 13:15:26'),
(812, 1, '2026-05-12 21:15:26', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:15:26'),
(813, 1, '2026-05-12 22:15:26', 25, 'gan_php', 'php-v1', 0.75, '2026-05-14 13:15:26'),
(814, 1, '2026-05-12 23:15:26', 21, 'gan_php', 'php-v1', 0.71, '2026-05-14 13:15:26'),
(815, 1, '2026-05-13 00:15:26', 31, 'gan_php', 'php-v1', 0.81, '2026-05-14 13:15:26'),
(816, 1, '2026-05-13 01:15:26', 8, 'gan_php', 'php-v1', 0.58, '2026-05-14 13:15:26'),
(817, 1, '2026-05-13 02:15:26', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:15:26'),
(818, 1, '2026-05-13 03:15:26', 15, 'gan_php', 'php-v1', 0.65, '2026-05-14 13:15:26'),
(819, 1, '2026-05-13 04:15:26', 17, 'gan_php', 'php-v1', 0.67, '2026-05-14 13:15:26'),
(820, 1, '2026-05-13 05:15:26', 5, 'gan_php', 'php-v1', 0.55, '2026-05-14 13:15:26'),
(821, 1, '2026-05-13 06:15:26', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:15:26'),
(822, 1, '2026-05-13 07:15:26', 15, 'gan_php', 'php-v1', 0.65, '2026-05-14 13:15:26'),
(823, 1, '2026-05-13 08:15:26', 28, 'gan_php', 'php-v1', 0.78, '2026-05-14 13:15:26'),
(824, 1, '2026-05-13 09:15:26', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:15:26'),
(825, 1, '2026-05-13 10:15:26', 8, 'gan_php', 'php-v1', 0.58, '2026-05-14 13:15:26'),
(826, 1, '2026-05-13 11:15:26', 6, 'gan_php', 'php-v1', 0.56, '2026-05-14 13:15:26'),
(827, 1, '2026-05-13 12:15:26', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:15:26'),
(828, 1, '2026-05-13 13:15:26', 16, 'gan_php', 'php-v1', 0.66, '2026-05-14 13:15:26'),
(829, 1, '2026-05-13 14:15:26', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:15:26'),
(830, 1, '2026-05-13 15:15:26', 35, 'gan_php', 'php-v1', 0.85, '2026-05-14 13:15:26'),
(831, 1, '2026-05-13 16:15:26', 24, 'gan_php', 'php-v1', 0.74, '2026-05-14 13:15:26'),
(832, 1, '2026-05-13 17:15:26', 30, 'gan_php', 'php-v1', 0.80, '2026-05-14 13:15:26'),
(833, 1, '2026-05-13 18:15:26', 16, 'gan_php', 'php-v1', 0.66, '2026-05-14 13:15:26'),
(834, 1, '2026-05-13 19:15:26', 27, 'gan_php', 'php-v1', 0.77, '2026-05-14 13:15:26'),
(835, 1, '2026-05-13 20:15:26', 22, 'gan_php', 'php-v1', 0.72, '2026-05-14 13:15:26'),
(836, 1, '2026-05-13 21:15:26', 17, 'gan_php', 'php-v1', 0.67, '2026-05-14 13:15:26'),
(837, 1, '2026-05-13 22:15:26', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:15:26'),
(838, 1, '2026-05-13 23:15:26', 20, 'gan_php', 'php-v1', 0.70, '2026-05-14 13:15:26'),
(839, 1, '2026-05-14 00:15:26', 25, 'gan_php', 'php-v1', 0.75, '2026-05-14 13:15:26'),
(840, 1, '2026-05-14 01:15:26', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:15:26'),
(841, 1, '2026-05-14 02:15:26', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:15:26'),
(842, 1, '2026-05-14 03:15:26', 19, 'gan_php', 'php-v1', 0.69, '2026-05-14 13:15:26'),
(843, 1, '2026-05-14 04:15:26', 27, 'gan_php', 'php-v1', 0.77, '2026-05-14 13:15:26'),
(844, 1, '2026-05-14 05:15:26', 29, 'gan_php', 'php-v1', 0.79, '2026-05-14 13:15:26'),
(845, 1, '2026-05-14 06:15:26', 29, 'gan_php', 'php-v1', 0.79, '2026-05-14 13:15:26'),
(846, 1, '2026-05-14 07:15:26', 25, 'gan_php', 'php-v1', 0.75, '2026-05-14 13:15:26'),
(847, 1, '2026-05-14 08:15:26', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:15:26'),
(848, 1, '2026-05-14 09:15:26', 21, 'gan_php', 'php-v1', 0.71, '2026-05-14 13:15:26'),
(849, 1, '2026-05-14 10:15:26', 17, 'gan_php', 'php-v1', 0.67, '2026-05-14 13:15:26'),
(850, 1, '2026-05-14 11:15:26', 25, 'gan_php', 'php-v1', 0.75, '2026-05-14 13:15:26'),
(851, 2, '2026-05-12 10:15:26', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:15:26'),
(852, 2, '2026-05-12 11:15:26', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:15:26'),
(853, 2, '2026-05-12 12:15:26', 21, 'gan_php', 'php-v1', 0.71, '2026-05-14 13:15:26'),
(854, 2, '2026-05-12 13:15:26', 22, 'gan_php', 'php-v1', 0.72, '2026-05-14 13:15:26'),
(855, 2, '2026-05-12 14:15:26', 15, 'gan_php', 'php-v1', 0.65, '2026-05-14 13:15:26'),
(856, 2, '2026-05-12 15:15:26', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:15:26'),
(857, 2, '2026-05-12 16:15:26', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:15:26'),
(858, 2, '2026-05-12 17:15:26', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:15:26'),
(859, 2, '2026-05-12 18:15:26', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:15:26'),
(860, 2, '2026-05-12 19:15:26', 20, 'gan_php', 'php-v1', 0.70, '2026-05-14 13:15:26'),
(861, 2, '2026-05-12 20:15:26', 17, 'gan_php', 'php-v1', 0.67, '2026-05-14 13:15:26'),
(862, 2, '2026-05-12 21:15:26', 25, 'gan_php', 'php-v1', 0.75, '2026-05-14 13:15:26'),
(863, 2, '2026-05-12 22:15:26', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:15:26'),
(864, 2, '2026-05-12 23:15:26', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:15:26'),
(865, 2, '2026-05-13 00:15:26', 23, 'gan_php', 'php-v1', 0.73, '2026-05-14 13:15:26'),
(866, 2, '2026-05-13 01:15:26', 21, 'gan_php', 'php-v1', 0.71, '2026-05-14 13:15:26'),
(867, 2, '2026-05-13 02:15:26', 22, 'gan_php', 'php-v1', 0.72, '2026-05-14 13:15:26'),
(868, 2, '2026-05-13 03:15:26', 27, 'gan_php', 'php-v1', 0.77, '2026-05-14 13:15:26'),
(869, 2, '2026-05-13 04:15:26', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:15:26'),
(870, 2, '2026-05-13 05:15:26', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:15:26'),
(871, 2, '2026-05-13 06:15:26', 23, 'gan_php', 'php-v1', 0.73, '2026-05-14 13:15:26'),
(872, 2, '2026-05-13 07:15:26', 22, 'gan_php', 'php-v1', 0.72, '2026-05-14 13:15:26'),
(873, 2, '2026-05-13 08:15:26', 32, 'gan_php', 'php-v1', 0.82, '2026-05-14 13:15:26'),
(874, 2, '2026-05-13 09:15:26', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:15:26'),
(875, 2, '2026-05-13 10:15:26', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:15:26'),
(876, 2, '2026-05-13 11:15:26', 32, 'gan_php', 'php-v1', 0.82, '2026-05-14 13:15:26'),
(877, 2, '2026-05-13 12:15:26', 19, 'gan_php', 'php-v1', 0.69, '2026-05-14 13:15:26'),
(878, 2, '2026-05-13 13:15:26', 20, 'gan_php', 'php-v1', 0.70, '2026-05-14 13:15:26'),
(879, 2, '2026-05-13 14:15:26', 27, 'gan_php', 'php-v1', 0.77, '2026-05-14 13:15:26'),
(880, 2, '2026-05-13 15:15:26', 19, 'gan_php', 'php-v1', 0.69, '2026-05-14 13:15:26'),
(881, 2, '2026-05-13 16:15:26', 29, 'gan_php', 'php-v1', 0.79, '2026-05-14 13:15:26'),
(882, 2, '2026-05-13 17:15:26', 28, 'gan_php', 'php-v1', 0.78, '2026-05-14 13:15:26'),
(883, 2, '2026-05-13 18:15:26', 25, 'gan_php', 'php-v1', 0.75, '2026-05-14 13:15:26'),
(884, 2, '2026-05-13 19:15:26', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:15:26'),
(885, 2, '2026-05-13 20:15:26', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:15:26'),
(886, 2, '2026-05-13 21:15:26', 20, 'gan_php', 'php-v1', 0.70, '2026-05-14 13:15:26'),
(887, 2, '2026-05-13 22:15:26', 24, 'gan_php', 'php-v1', 0.74, '2026-05-14 13:15:26'),
(888, 2, '2026-05-13 23:15:26', 8, 'gan_php', 'php-v1', 0.58, '2026-05-14 13:15:26'),
(889, 2, '2026-05-14 00:15:26', 16, 'gan_php', 'php-v1', 0.66, '2026-05-14 13:15:26'),
(890, 2, '2026-05-14 01:15:26', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:15:26'),
(891, 2, '2026-05-14 02:15:26', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:15:26'),
(892, 2, '2026-05-14 03:15:26', 21, 'gan_php', 'php-v1', 0.71, '2026-05-14 13:15:26'),
(893, 2, '2026-05-14 04:15:26', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:15:26'),
(894, 2, '2026-05-14 05:15:26', 7, 'gan_php', 'php-v1', 0.57, '2026-05-14 13:15:26'),
(895, 2, '2026-05-14 06:15:26', 27, 'gan_php', 'php-v1', 0.77, '2026-05-14 13:15:26'),
(896, 2, '2026-05-14 07:15:26', 16, 'gan_php', 'php-v1', 0.66, '2026-05-14 13:15:26'),
(897, 2, '2026-05-14 08:15:26', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:15:26'),
(898, 2, '2026-05-14 09:15:26', 21, 'gan_php', 'php-v1', 0.71, '2026-05-14 13:15:26'),
(899, 2, '2026-05-14 10:15:26', 25, 'gan_php', 'php-v1', 0.75, '2026-05-14 13:15:26'),
(900, 2, '2026-05-14 11:15:26', 17, 'gan_php', 'php-v1', 0.67, '2026-05-14 13:15:26'),
(901, 3, '2026-05-12 10:15:26', 15, 'gan_php', 'php-v1', 0.65, '2026-05-14 13:15:26'),
(902, 3, '2026-05-12 11:15:26', 15, 'gan_php', 'php-v1', 0.65, '2026-05-14 13:15:26'),
(903, 3, '2026-05-12 12:15:26', 25, 'gan_php', 'php-v1', 0.75, '2026-05-14 13:15:26'),
(904, 3, '2026-05-12 13:15:26', 27, 'gan_php', 'php-v1', 0.77, '2026-05-14 13:15:26'),
(905, 3, '2026-05-12 14:15:26', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:15:26'),
(906, 3, '2026-05-12 15:15:26', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:15:26'),
(907, 3, '2026-05-12 16:15:26', 8, 'gan_php', 'php-v1', 0.58, '2026-05-14 13:15:26'),
(908, 3, '2026-05-12 17:15:26', 22, 'gan_php', 'php-v1', 0.72, '2026-05-14 13:15:26'),
(909, 3, '2026-05-12 18:15:26', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:15:26'),
(910, 3, '2026-05-12 19:15:26', 19, 'gan_php', 'php-v1', 0.69, '2026-05-14 13:15:26'),
(911, 3, '2026-05-12 20:15:26', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:15:26'),
(912, 3, '2026-05-12 21:15:27', 31, 'gan_php', 'php-v1', 0.81, '2026-05-14 13:15:27'),
(913, 3, '2026-05-12 22:15:27', 26, 'gan_php', 'php-v1', 0.76, '2026-05-14 13:15:27'),
(914, 3, '2026-05-12 23:15:27', 26, 'gan_php', 'php-v1', 0.76, '2026-05-14 13:15:27'),
(915, 3, '2026-05-13 00:15:27', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:15:27'),
(916, 3, '2026-05-13 01:15:27', 16, 'gan_php', 'php-v1', 0.66, '2026-05-14 13:15:27'),
(917, 3, '2026-05-13 02:15:27', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:15:27'),
(918, 3, '2026-05-13 03:15:27', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:15:27'),
(919, 3, '2026-05-13 04:15:27', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:15:27'),
(920, 3, '2026-05-13 05:15:27', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:15:27'),
(921, 3, '2026-05-13 06:15:27', 22, 'gan_php', 'php-v1', 0.72, '2026-05-14 13:15:27'),
(922, 3, '2026-05-13 07:15:27', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:15:27'),
(923, 3, '2026-05-13 08:15:27', 32, 'gan_php', 'php-v1', 0.82, '2026-05-14 13:15:27'),
(924, 3, '2026-05-13 09:15:27', 24, 'gan_php', 'php-v1', 0.74, '2026-05-14 13:15:27'),
(925, 3, '2026-05-13 10:15:27', 21, 'gan_php', 'php-v1', 0.71, '2026-05-14 13:15:27'),
(926, 3, '2026-05-13 11:15:27', 21, 'gan_php', 'php-v1', 0.71, '2026-05-14 13:15:27'),
(927, 3, '2026-05-13 12:15:27', 22, 'gan_php', 'php-v1', 0.72, '2026-05-14 13:15:27'),
(928, 3, '2026-05-13 13:15:27', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:15:27'),
(929, 3, '2026-05-13 14:15:27', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:15:27'),
(930, 3, '2026-05-13 15:15:27', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:15:27'),
(931, 3, '2026-05-13 16:15:27', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:15:27'),
(932, 3, '2026-05-13 17:15:27', 26, 'gan_php', 'php-v1', 0.76, '2026-05-14 13:15:27'),
(933, 3, '2026-05-13 18:15:27', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:15:27'),
(934, 3, '2026-05-13 19:15:27', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:15:27'),
(935, 3, '2026-05-13 20:15:27', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:15:27'),
(936, 3, '2026-05-13 21:15:27', 15, 'gan_php', 'php-v1', 0.65, '2026-05-14 13:15:27'),
(937, 3, '2026-05-13 22:15:27', 8, 'gan_php', 'php-v1', 0.58, '2026-05-14 13:15:27'),
(938, 3, '2026-05-13 23:15:27', 34, 'gan_php', 'php-v1', 0.84, '2026-05-14 13:15:27'),
(939, 3, '2026-05-14 00:15:27', 21, 'gan_php', 'php-v1', 0.71, '2026-05-14 13:15:27'),
(940, 3, '2026-05-14 01:15:27', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:15:27'),
(941, 3, '2026-05-14 02:15:27', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:15:27'),
(942, 3, '2026-05-14 03:15:27', 16, 'gan_php', 'php-v1', 0.66, '2026-05-14 13:15:27'),
(943, 3, '2026-05-14 04:15:27', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:15:27'),
(944, 3, '2026-05-14 05:15:27', 8, 'gan_php', 'php-v1', 0.58, '2026-05-14 13:15:27'),
(945, 3, '2026-05-14 06:15:27', 16, 'gan_php', 'php-v1', 0.66, '2026-05-14 13:15:27'),
(946, 3, '2026-05-14 07:15:27', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:15:27'),
(947, 3, '2026-05-14 08:15:27', 22, 'gan_php', 'php-v1', 0.72, '2026-05-14 13:15:27'),
(948, 3, '2026-05-14 09:15:27', 22, 'gan_php', 'php-v1', 0.72, '2026-05-14 13:15:27'),
(949, 3, '2026-05-14 10:15:27', 19, 'gan_php', 'php-v1', 0.69, '2026-05-14 13:15:27'),
(950, 3, '2026-05-14 11:15:27', 33, 'gan_php', 'php-v1', 0.83, '2026-05-14 13:15:27'),
(951, 4, '2026-05-12 10:15:27', 17, 'gan_php', 'php-v1', 0.67, '2026-05-14 13:15:27'),
(952, 4, '2026-05-12 11:15:27', 17, 'gan_php', 'php-v1', 0.67, '2026-05-14 13:15:27'),
(953, 4, '2026-05-12 12:15:27', 26, 'gan_php', 'php-v1', 0.76, '2026-05-14 13:15:27'),
(954, 4, '2026-05-12 13:15:27', 27, 'gan_php', 'php-v1', 0.77, '2026-05-14 13:15:27'),
(955, 4, '2026-05-12 14:15:27', 29, 'gan_php', 'php-v1', 0.79, '2026-05-14 13:15:27'),
(956, 4, '2026-05-12 15:15:27', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:15:27'),
(957, 4, '2026-05-12 16:15:27', 25, 'gan_php', 'php-v1', 0.75, '2026-05-14 13:15:27'),
(958, 4, '2026-05-12 17:15:27', 17, 'gan_php', 'php-v1', 0.67, '2026-05-14 13:15:27'),
(959, 4, '2026-05-12 18:15:27', 16, 'gan_php', 'php-v1', 0.66, '2026-05-14 13:15:27'),
(960, 4, '2026-05-12 19:15:27', 28, 'gan_php', 'php-v1', 0.78, '2026-05-14 13:15:27'),
(961, 4, '2026-05-12 20:15:27', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:15:27'),
(962, 4, '2026-05-12 21:15:27', 15, 'gan_php', 'php-v1', 0.65, '2026-05-14 13:15:27'),
(963, 4, '2026-05-12 22:15:27', 19, 'gan_php', 'php-v1', 0.69, '2026-05-14 13:15:27'),
(964, 4, '2026-05-12 23:15:27', 22, 'gan_php', 'php-v1', 0.72, '2026-05-14 13:15:27'),
(965, 4, '2026-05-13 00:15:27', 19, 'gan_php', 'php-v1', 0.69, '2026-05-14 13:15:27'),
(966, 4, '2026-05-13 01:15:27', 26, 'gan_php', 'php-v1', 0.76, '2026-05-14 13:15:27'),
(967, 4, '2026-05-13 02:15:27', 24, 'gan_php', 'php-v1', 0.74, '2026-05-14 13:15:27'),
(968, 4, '2026-05-13 03:15:27', 30, 'gan_php', 'php-v1', 0.80, '2026-05-14 13:15:27'),
(969, 4, '2026-05-13 04:15:27', 7, 'gan_php', 'php-v1', 0.57, '2026-05-14 13:15:27'),
(970, 4, '2026-05-13 05:15:27', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:15:27'),
(971, 4, '2026-05-13 06:15:27', 16, 'gan_php', 'php-v1', 0.66, '2026-05-14 13:15:27'),
(972, 4, '2026-05-13 07:15:27', 20, 'gan_php', 'php-v1', 0.70, '2026-05-14 13:15:27'),
(973, 4, '2026-05-13 08:15:27', 26, 'gan_php', 'php-v1', 0.76, '2026-05-14 13:15:27'),
(974, 4, '2026-05-13 09:15:27', 28, 'gan_php', 'php-v1', 0.78, '2026-05-14 13:15:27'),
(975, 4, '2026-05-13 10:15:27', 23, 'gan_php', 'php-v1', 0.73, '2026-05-14 13:15:27'),
(976, 4, '2026-05-13 11:15:27', 26, 'gan_php', 'php-v1', 0.76, '2026-05-14 13:15:27'),
(977, 4, '2026-05-13 12:15:27', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:15:27'),
(978, 4, '2026-05-13 13:15:27', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:15:27'),
(979, 4, '2026-05-13 14:15:27', 22, 'gan_php', 'php-v1', 0.72, '2026-05-14 13:15:27'),
(980, 4, '2026-05-13 15:15:27', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:15:27'),
(981, 4, '2026-05-13 16:15:27', 19, 'gan_php', 'php-v1', 0.69, '2026-05-14 13:15:27'),
(982, 4, '2026-05-13 17:15:27', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:15:27'),
(983, 4, '2026-05-13 18:15:27', 21, 'gan_php', 'php-v1', 0.71, '2026-05-14 13:15:27'),
(984, 4, '2026-05-13 19:15:27', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:15:27'),
(985, 4, '2026-05-13 20:15:27', 29, 'gan_php', 'php-v1', 0.79, '2026-05-14 13:15:27'),
(986, 4, '2026-05-13 21:15:27', 25, 'gan_php', 'php-v1', 0.75, '2026-05-14 13:15:27'),
(987, 4, '2026-05-13 22:15:27', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:15:27'),
(988, 4, '2026-05-13 23:15:27', 16, 'gan_php', 'php-v1', 0.66, '2026-05-14 13:15:27'),
(989, 4, '2026-05-14 00:15:27', 15, 'gan_php', 'php-v1', 0.65, '2026-05-14 13:15:27'),
(990, 4, '2026-05-14 01:15:27', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:15:27'),
(991, 4, '2026-05-14 02:15:27', 5, 'gan_php', 'php-v1', 0.55, '2026-05-14 13:15:27'),
(992, 4, '2026-05-14 03:15:27', 15, 'gan_php', 'php-v1', 0.65, '2026-05-14 13:15:27'),
(993, 4, '2026-05-14 04:15:27', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:15:27'),
(994, 4, '2026-05-14 05:15:27', 17, 'gan_php', 'php-v1', 0.67, '2026-05-14 13:15:27'),
(995, 4, '2026-05-14 06:15:27', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:15:27'),
(996, 4, '2026-05-14 07:15:27', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:15:27'),
(997, 4, '2026-05-14 08:15:27', 17, 'gan_php', 'php-v1', 0.67, '2026-05-14 13:15:27'),
(998, 4, '2026-05-14 09:15:27', 15, 'gan_php', 'php-v1', 0.65, '2026-05-14 13:15:27'),
(999, 4, '2026-05-14 10:15:27', 26, 'gan_php', 'php-v1', 0.76, '2026-05-14 13:15:27'),
(1000, 4, '2026-05-14 11:15:27', 16, 'gan_php', 'php-v1', 0.66, '2026-05-14 13:15:27'),
(1001, 5, '2026-05-12 10:15:27', 29, 'gan_php', 'php-v1', 0.79, '2026-05-14 13:15:27'),
(1002, 5, '2026-05-12 11:15:27', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:15:27'),
(1003, 5, '2026-05-12 12:15:27', 24, 'gan_php', 'php-v1', 0.74, '2026-05-14 13:15:27'),
(1004, 5, '2026-05-12 13:15:27', 15, 'gan_php', 'php-v1', 0.65, '2026-05-14 13:15:27'),
(1005, 5, '2026-05-12 14:15:27', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:15:27'),
(1006, 5, '2026-05-12 15:15:27', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:15:27'),
(1007, 5, '2026-05-12 16:15:27', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:15:27'),
(1008, 5, '2026-05-12 17:15:27', 28, 'gan_php', 'php-v1', 0.78, '2026-05-14 13:15:27'),
(1009, 5, '2026-05-12 18:15:27', 24, 'gan_php', 'php-v1', 0.74, '2026-05-14 13:15:27'),
(1010, 5, '2026-05-12 19:15:27', 25, 'gan_php', 'php-v1', 0.75, '2026-05-14 13:15:27'),
(1011, 5, '2026-05-12 20:15:27', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:15:27'),
(1012, 5, '2026-05-12 21:15:27', 31, 'gan_php', 'php-v1', 0.81, '2026-05-14 13:15:27'),
(1013, 5, '2026-05-12 22:15:27', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:15:27'),
(1014, 5, '2026-05-12 23:15:27', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:15:27'),
(1015, 5, '2026-05-13 00:15:27', 8, 'gan_php', 'php-v1', 0.58, '2026-05-14 13:15:27'),
(1016, 5, '2026-05-13 01:15:27', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:15:27'),
(1017, 5, '2026-05-13 02:15:27', 6, 'gan_php', 'php-v1', 0.56, '2026-05-14 13:15:27'),
(1018, 5, '2026-05-13 03:15:27', 15, 'gan_php', 'php-v1', 0.65, '2026-05-14 13:15:27'),
(1019, 5, '2026-05-13 04:15:27', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:15:27'),
(1020, 5, '2026-05-13 05:15:27', 5, 'gan_php', 'php-v1', 0.55, '2026-05-14 13:15:27'),
(1021, 5, '2026-05-13 06:15:27', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:15:27'),
(1022, 5, '2026-05-13 07:15:27', 23, 'gan_php', 'php-v1', 0.73, '2026-05-14 13:15:27'),
(1023, 5, '2026-05-13 08:15:27', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:15:27'),
(1024, 5, '2026-05-13 09:15:27', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:15:27'),
(1025, 5, '2026-05-13 10:15:27', 23, 'gan_php', 'php-v1', 0.73, '2026-05-14 13:15:27'),
(1026, 5, '2026-05-13 11:15:27', 15, 'gan_php', 'php-v1', 0.65, '2026-05-14 13:15:27'),
(1027, 5, '2026-05-13 12:15:27', 16, 'gan_php', 'php-v1', 0.66, '2026-05-14 13:15:27'),
(1028, 5, '2026-05-13 13:15:27', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:15:27'),
(1029, 5, '2026-05-13 14:15:27', 16, 'gan_php', 'php-v1', 0.66, '2026-05-14 13:15:27'),
(1030, 5, '2026-05-13 15:15:27', 20, 'gan_php', 'php-v1', 0.70, '2026-05-14 13:15:27'),
(1031, 5, '2026-05-13 16:15:27', 24, 'gan_php', 'php-v1', 0.74, '2026-05-14 13:15:27'),
(1032, 5, '2026-05-13 17:15:27', 8, 'gan_php', 'php-v1', 0.58, '2026-05-14 13:15:27'),
(1033, 5, '2026-05-13 18:15:27', 15, 'gan_php', 'php-v1', 0.65, '2026-05-14 13:15:27'),
(1034, 5, '2026-05-13 19:15:27', 23, 'gan_php', 'php-v1', 0.73, '2026-05-14 13:15:27'),
(1035, 5, '2026-05-13 20:15:27', 27, 'gan_php', 'php-v1', 0.77, '2026-05-14 13:15:27'),
(1036, 5, '2026-05-13 21:15:27', 25, 'gan_php', 'php-v1', 0.75, '2026-05-14 13:15:27'),
(1037, 5, '2026-05-13 22:15:27', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:15:27'),
(1038, 5, '2026-05-13 23:15:27', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:15:27'),
(1039, 5, '2026-05-14 00:15:27', 25, 'gan_php', 'php-v1', 0.75, '2026-05-14 13:15:27'),
(1040, 5, '2026-05-14 01:15:27', 26, 'gan_php', 'php-v1', 0.76, '2026-05-14 13:15:27'),
(1041, 5, '2026-05-14 02:15:27', 16, 'gan_php', 'php-v1', 0.66, '2026-05-14 13:15:27'),
(1042, 5, '2026-05-14 03:15:27', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:15:27'),
(1043, 5, '2026-05-14 04:15:27', 22, 'gan_php', 'php-v1', 0.72, '2026-05-14 13:15:27'),
(1044, 5, '2026-05-14 05:15:27', 24, 'gan_php', 'php-v1', 0.74, '2026-05-14 13:15:27'),
(1045, 5, '2026-05-14 06:15:27', 7, 'gan_php', 'php-v1', 0.57, '2026-05-14 13:15:27'),
(1046, 5, '2026-05-14 07:15:27', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:15:27'),
(1047, 5, '2026-05-14 08:15:27', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:15:27'),
(1048, 5, '2026-05-14 09:15:27', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:15:27'),
(1049, 5, '2026-05-14 10:15:27', 30, 'gan_php', 'php-v1', 0.80, '2026-05-14 13:15:27'),
(1050, 5, '2026-05-14 11:15:27', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:15:27'),
(1051, 6, '2026-05-12 10:15:27', 17, 'gan_php', 'php-v1', 0.67, '2026-05-14 13:15:27'),
(1052, 6, '2026-05-12 11:15:27', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:15:27'),
(1053, 6, '2026-05-12 12:15:27', 23, 'gan_php', 'php-v1', 0.73, '2026-05-14 13:15:27'),
(1054, 6, '2026-05-12 13:15:27', 29, 'gan_php', 'php-v1', 0.79, '2026-05-14 13:15:27'),
(1055, 6, '2026-05-12 14:15:27', 25, 'gan_php', 'php-v1', 0.75, '2026-05-14 13:15:27'),
(1056, 6, '2026-05-12 15:15:27', 29, 'gan_php', 'php-v1', 0.79, '2026-05-14 13:15:27'),
(1057, 6, '2026-05-12 16:15:27', 28, 'gan_php', 'php-v1', 0.78, '2026-05-14 13:15:27'),
(1058, 6, '2026-05-12 17:15:27', 7, 'gan_php', 'php-v1', 0.57, '2026-05-14 13:15:27'),
(1059, 6, '2026-05-12 18:15:27', 22, 'gan_php', 'php-v1', 0.72, '2026-05-14 13:15:27'),
(1060, 6, '2026-05-12 19:15:27', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:15:27'),
(1061, 6, '2026-05-12 20:15:27', 23, 'gan_php', 'php-v1', 0.73, '2026-05-14 13:15:27'),
(1062, 6, '2026-05-12 21:15:27', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:15:27'),
(1063, 6, '2026-05-12 22:15:27', 20, 'gan_php', 'php-v1', 0.70, '2026-05-14 13:15:27'),
(1064, 6, '2026-05-12 23:15:27', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:15:27'),
(1065, 6, '2026-05-13 00:15:27', 17, 'gan_php', 'php-v1', 0.67, '2026-05-14 13:15:27'),
(1066, 6, '2026-05-13 01:15:27', 34, 'gan_php', 'php-v1', 0.84, '2026-05-14 13:15:27'),
(1067, 6, '2026-05-13 02:15:27', 17, 'gan_php', 'php-v1', 0.67, '2026-05-14 13:15:27'),
(1068, 6, '2026-05-13 03:15:27', 27, 'gan_php', 'php-v1', 0.77, '2026-05-14 13:15:27'),
(1069, 6, '2026-05-13 04:15:27', 24, 'gan_php', 'php-v1', 0.74, '2026-05-14 13:15:27'),
(1070, 6, '2026-05-13 05:15:27', 22, 'gan_php', 'php-v1', 0.72, '2026-05-14 13:15:27'),
(1071, 6, '2026-05-13 06:15:27', 22, 'gan_php', 'php-v1', 0.72, '2026-05-14 13:15:27'),
(1072, 6, '2026-05-13 07:15:27', 27, 'gan_php', 'php-v1', 0.77, '2026-05-14 13:15:27'),
(1073, 6, '2026-05-13 08:15:27', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:15:27'),
(1074, 6, '2026-05-13 09:15:27', 27, 'gan_php', 'php-v1', 0.77, '2026-05-14 13:15:27'),
(1075, 6, '2026-05-13 10:15:27', 25, 'gan_php', 'php-v1', 0.75, '2026-05-14 13:15:27'),
(1076, 6, '2026-05-13 11:15:27', 23, 'gan_php', 'php-v1', 0.73, '2026-05-14 13:15:27'),
(1077, 6, '2026-05-13 12:15:27', 21, 'gan_php', 'php-v1', 0.71, '2026-05-14 13:15:27'),
(1078, 6, '2026-05-13 13:15:27', 21, 'gan_php', 'php-v1', 0.71, '2026-05-14 13:15:27'),
(1079, 6, '2026-05-13 14:15:27', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:15:27'),
(1080, 6, '2026-05-13 15:15:27', 20, 'gan_php', 'php-v1', 0.70, '2026-05-14 13:15:27'),
(1081, 6, '2026-05-13 16:15:27', 17, 'gan_php', 'php-v1', 0.67, '2026-05-14 13:15:27'),
(1082, 6, '2026-05-13 17:15:27', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:15:27'),
(1083, 6, '2026-05-13 18:15:27', 26, 'gan_php', 'php-v1', 0.76, '2026-05-14 13:15:27'),
(1084, 6, '2026-05-13 19:15:27', 28, 'gan_php', 'php-v1', 0.78, '2026-05-14 13:15:27'),
(1085, 6, '2026-05-13 20:15:27', 19, 'gan_php', 'php-v1', 0.69, '2026-05-14 13:15:27'),
(1086, 6, '2026-05-13 21:15:27', 23, 'gan_php', 'php-v1', 0.73, '2026-05-14 13:15:27'),
(1087, 6, '2026-05-13 22:15:27', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:15:27'),
(1088, 6, '2026-05-13 23:15:27', 16, 'gan_php', 'php-v1', 0.66, '2026-05-14 13:15:27'),
(1089, 6, '2026-05-14 00:15:27', 25, 'gan_php', 'php-v1', 0.75, '2026-05-14 13:15:27'),
(1090, 6, '2026-05-14 01:15:27', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:15:27'),
(1091, 6, '2026-05-14 02:15:27', 9, 'gan_php', 'php-v1', 0.59, '2026-05-14 13:15:27'),
(1092, 6, '2026-05-14 03:15:27', 7, 'gan_php', 'php-v1', 0.57, '2026-05-14 13:15:27'),
(1093, 6, '2026-05-14 04:15:27', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:15:27'),
(1094, 6, '2026-05-14 05:15:27', 23, 'gan_php', 'php-v1', 0.73, '2026-05-14 13:15:27'),
(1095, 6, '2026-05-14 06:15:27', 17, 'gan_php', 'php-v1', 0.67, '2026-05-14 13:15:27'),
(1096, 6, '2026-05-14 07:15:27', 21, 'gan_php', 'php-v1', 0.71, '2026-05-14 13:15:27'),
(1097, 6, '2026-05-14 08:15:27', 19, 'gan_php', 'php-v1', 0.69, '2026-05-14 13:15:27'),
(1098, 6, '2026-05-14 09:15:27', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:15:27'),
(1099, 6, '2026-05-14 10:15:27', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:15:27'),
(1100, 6, '2026-05-14 11:15:27', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:15:27'),
(1101, 7, '2026-05-12 10:15:27', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:15:27'),
(1102, 7, '2026-05-12 11:15:27', 25, 'gan_php', 'php-v1', 0.75, '2026-05-14 13:15:27'),
(1103, 7, '2026-05-12 12:15:28', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:15:28'),
(1104, 7, '2026-05-12 13:15:28', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:15:28'),
(1105, 7, '2026-05-12 14:15:28', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:15:28'),
(1106, 7, '2026-05-12 15:15:28', 19, 'gan_php', 'php-v1', 0.69, '2026-05-14 13:15:28'),
(1107, 7, '2026-05-12 16:15:28', 20, 'gan_php', 'php-v1', 0.70, '2026-05-14 13:15:28'),
(1108, 7, '2026-05-12 17:15:28', 21, 'gan_php', 'php-v1', 0.71, '2026-05-14 13:15:28'),
(1109, 7, '2026-05-12 18:15:28', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:15:28'),
(1110, 7, '2026-05-12 19:15:28', 22, 'gan_php', 'php-v1', 0.72, '2026-05-14 13:15:28'),
(1111, 7, '2026-05-12 20:15:28', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:15:28'),
(1112, 7, '2026-05-12 21:15:28', 21, 'gan_php', 'php-v1', 0.71, '2026-05-14 13:15:28'),
(1113, 7, '2026-05-12 22:15:28', 19, 'gan_php', 'php-v1', 0.69, '2026-05-14 13:15:28'),
(1114, 7, '2026-05-12 23:15:28', 23, 'gan_php', 'php-v1', 0.73, '2026-05-14 13:15:28'),
(1115, 7, '2026-05-13 00:15:28', 20, 'gan_php', 'php-v1', 0.70, '2026-05-14 13:15:28'),
(1116, 7, '2026-05-13 01:15:28', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:15:28'),
(1117, 7, '2026-05-13 02:15:28', 21, 'gan_php', 'php-v1', 0.71, '2026-05-14 13:15:28'),
(1118, 7, '2026-05-13 03:15:28', 19, 'gan_php', 'php-v1', 0.69, '2026-05-14 13:15:28'),
(1119, 7, '2026-05-13 04:15:28', 21, 'gan_php', 'php-v1', 0.71, '2026-05-14 13:15:28'),
(1120, 7, '2026-05-13 05:15:28', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:15:28'),
(1121, 7, '2026-05-13 06:15:28', 22, 'gan_php', 'php-v1', 0.72, '2026-05-14 13:15:28'),
(1122, 7, '2026-05-13 07:15:28', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:15:28'),
(1123, 7, '2026-05-13 08:15:28', 28, 'gan_php', 'php-v1', 0.78, '2026-05-14 13:15:28'),
(1124, 7, '2026-05-13 09:15:28', 22, 'gan_php', 'php-v1', 0.72, '2026-05-14 13:15:28'),
(1125, 7, '2026-05-13 10:15:28', 23, 'gan_php', 'php-v1', 0.73, '2026-05-14 13:15:28'),
(1126, 7, '2026-05-13 11:15:28', 20, 'gan_php', 'php-v1', 0.70, '2026-05-14 13:15:28'),
(1127, 7, '2026-05-13 12:15:28', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:15:28'),
(1128, 7, '2026-05-13 13:15:28', 22, 'gan_php', 'php-v1', 0.72, '2026-05-14 13:15:28'),
(1129, 7, '2026-05-13 14:15:28', 20, 'gan_php', 'php-v1', 0.70, '2026-05-14 13:15:28'),
(1130, 7, '2026-05-13 15:15:28', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:15:28'),
(1131, 7, '2026-05-13 16:15:28', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:15:28'),
(1132, 7, '2026-05-13 17:15:28', 17, 'gan_php', 'php-v1', 0.67, '2026-05-14 13:15:28'),
(1133, 7, '2026-05-13 18:15:28', 25, 'gan_php', 'php-v1', 0.75, '2026-05-14 13:15:28'),
(1134, 7, '2026-05-13 19:15:28', 21, 'gan_php', 'php-v1', 0.71, '2026-05-14 13:15:28'),
(1135, 7, '2026-05-13 20:15:28', 27, 'gan_php', 'php-v1', 0.77, '2026-05-14 13:15:28'),
(1136, 7, '2026-05-13 21:15:28', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:15:28'),
(1137, 7, '2026-05-13 22:15:28', 24, 'gan_php', 'php-v1', 0.74, '2026-05-14 13:15:28'),
(1138, 7, '2026-05-13 23:15:28', 20, 'gan_php', 'php-v1', 0.70, '2026-05-14 13:15:28'),
(1139, 7, '2026-05-14 00:15:28', 24, 'gan_php', 'php-v1', 0.74, '2026-05-14 13:15:28'),
(1140, 7, '2026-05-14 01:15:28', 15, 'gan_php', 'php-v1', 0.65, '2026-05-14 13:15:28'),
(1141, 7, '2026-05-14 02:15:28', 25, 'gan_php', 'php-v1', 0.75, '2026-05-14 13:15:28'),
(1142, 7, '2026-05-14 03:15:28', 8, 'gan_php', 'php-v1', 0.58, '2026-05-14 13:15:28'),
(1143, 7, '2026-05-14 04:15:28', 25, 'gan_php', 'php-v1', 0.75, '2026-05-14 13:15:28'),
(1144, 7, '2026-05-14 05:15:28', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:15:28'),
(1145, 7, '2026-05-14 06:15:28', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:15:28'),
(1146, 7, '2026-05-14 07:15:28', 17, 'gan_php', 'php-v1', 0.67, '2026-05-14 13:15:28'),
(1147, 7, '2026-05-14 08:15:28', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:15:28'),
(1148, 7, '2026-05-14 09:15:28', 8, 'gan_php', 'php-v1', 0.58, '2026-05-14 13:15:28'),
(1149, 7, '2026-05-14 10:15:28', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:15:28'),
(1150, 7, '2026-05-14 11:15:28', 24, 'gan_php', 'php-v1', 0.74, '2026-05-14 13:15:28'),
(1151, 8, '2026-05-12 10:15:28', 25, 'gan_php', 'php-v1', 0.75, '2026-05-14 13:15:28'),
(1152, 8, '2026-05-12 11:15:28', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:15:28'),
(1153, 8, '2026-05-12 12:15:28', 28, 'gan_php', 'php-v1', 0.78, '2026-05-14 13:15:28'),
(1154, 8, '2026-05-12 13:15:28', 20, 'gan_php', 'php-v1', 0.70, '2026-05-14 13:15:28'),
(1155, 8, '2026-05-12 14:15:28', 6, 'gan_php', 'php-v1', 0.56, '2026-05-14 13:15:28'),
(1156, 8, '2026-05-12 15:15:28', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:15:28'),
(1157, 8, '2026-05-12 16:15:28', 17, 'gan_php', 'php-v1', 0.67, '2026-05-14 13:15:28'),
(1158, 8, '2026-05-12 17:15:28', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:15:28'),
(1159, 8, '2026-05-12 18:15:28', 24, 'gan_php', 'php-v1', 0.74, '2026-05-14 13:15:28'),
(1160, 8, '2026-05-12 19:15:28', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:15:28'),
(1161, 8, '2026-05-12 20:15:28', 26, 'gan_php', 'php-v1', 0.76, '2026-05-14 13:15:28'),
(1162, 8, '2026-05-12 21:15:28', 28, 'gan_php', 'php-v1', 0.78, '2026-05-14 13:15:28'),
(1163, 8, '2026-05-12 22:15:28', 17, 'gan_php', 'php-v1', 0.67, '2026-05-14 13:15:28'),
(1164, 8, '2026-05-12 23:15:28', 21, 'gan_php', 'php-v1', 0.71, '2026-05-14 13:15:28'),
(1165, 8, '2026-05-13 00:15:28', 13, 'gan_php', 'php-v1', 0.63, '2026-05-14 13:15:28'),
(1166, 8, '2026-05-13 01:15:28', 26, 'gan_php', 'php-v1', 0.76, '2026-05-14 13:15:28'),
(1167, 8, '2026-05-13 02:15:28', 18, 'gan_php', 'php-v1', 0.68, '2026-05-14 13:15:28'),
(1168, 8, '2026-05-13 03:15:28', 8, 'gan_php', 'php-v1', 0.58, '2026-05-14 13:15:28'),
(1169, 8, '2026-05-13 04:15:28', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:15:28'),
(1170, 8, '2026-05-13 05:15:28', 15, 'gan_php', 'php-v1', 0.65, '2026-05-14 13:15:28'),
(1171, 8, '2026-05-13 06:15:28', 20, 'gan_php', 'php-v1', 0.70, '2026-05-14 13:15:28'),
(1172, 8, '2026-05-13 07:15:28', 14, 'gan_php', 'php-v1', 0.64, '2026-05-14 13:15:28'),
(1173, 8, '2026-05-13 08:15:28', 20, 'gan_php', 'php-v1', 0.70, '2026-05-14 13:15:28'),
(1174, 8, '2026-05-13 09:15:28', 25, 'gan_php', 'php-v1', 0.75, '2026-05-14 13:15:28'),
(1175, 8, '2026-05-13 10:15:28', 20, 'gan_php', 'php-v1', 0.70, '2026-05-14 13:15:28');
INSERT INTO `risk_scores_augmented` (`id`, `zone_id`, `synthetic_at`, `score`, `generation_method`, `generator_version`, `fidelity_score`, `created_at`) VALUES
(1176, 8, '2026-05-13 11:15:28', 17, 'gan_php', 'php-v1', 0.67, '2026-05-14 13:15:28'),
(1177, 8, '2026-05-13 12:15:28', 19, 'gan_php', 'php-v1', 0.69, '2026-05-14 13:15:28'),
(1178, 8, '2026-05-13 13:15:28', 7, 'gan_php', 'php-v1', 0.57, '2026-05-14 13:15:28'),
(1179, 8, '2026-05-13 14:15:28', 20, 'gan_php', 'php-v1', 0.70, '2026-05-14 13:15:28'),
(1180, 8, '2026-05-13 15:15:28', 17, 'gan_php', 'php-v1', 0.67, '2026-05-14 13:15:28'),
(1181, 8, '2026-05-13 16:15:28', 27, 'gan_php', 'php-v1', 0.77, '2026-05-14 13:15:28'),
(1182, 8, '2026-05-13 17:15:28', 11, 'gan_php', 'php-v1', 0.61, '2026-05-14 13:15:28'),
(1183, 8, '2026-05-13 18:15:28', 20, 'gan_php', 'php-v1', 0.70, '2026-05-14 13:15:28'),
(1184, 8, '2026-05-13 19:15:28', 5, 'gan_php', 'php-v1', 0.55, '2026-05-14 13:15:28'),
(1185, 8, '2026-05-13 20:15:28', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:15:28'),
(1186, 8, '2026-05-13 21:15:28', 22, 'gan_php', 'php-v1', 0.72, '2026-05-14 13:15:28'),
(1187, 8, '2026-05-13 22:15:28', 10, 'gan_php', 'php-v1', 0.60, '2026-05-14 13:15:28'),
(1188, 8, '2026-05-13 23:15:28', 23, 'gan_php', 'php-v1', 0.73, '2026-05-14 13:15:28'),
(1189, 8, '2026-05-14 00:15:28', 28, 'gan_php', 'php-v1', 0.78, '2026-05-14 13:15:28'),
(1190, 8, '2026-05-14 01:15:28', 5, 'gan_php', 'php-v1', 0.55, '2026-05-14 13:15:28'),
(1191, 8, '2026-05-14 02:15:28', 23, 'gan_php', 'php-v1', 0.73, '2026-05-14 13:15:28'),
(1192, 8, '2026-05-14 03:15:28', 17, 'gan_php', 'php-v1', 0.67, '2026-05-14 13:15:28'),
(1193, 8, '2026-05-14 04:15:28', 25, 'gan_php', 'php-v1', 0.75, '2026-05-14 13:15:28'),
(1194, 8, '2026-05-14 05:15:28', 7, 'gan_php', 'php-v1', 0.57, '2026-05-14 13:15:28'),
(1195, 8, '2026-05-14 06:15:28', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:15:28'),
(1196, 8, '2026-05-14 07:15:28', 34, 'gan_php', 'php-v1', 0.84, '2026-05-14 13:15:28'),
(1197, 8, '2026-05-14 08:15:28', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:15:28'),
(1198, 8, '2026-05-14 09:15:28', 16, 'gan_php', 'php-v1', 0.66, '2026-05-14 13:15:28'),
(1199, 8, '2026-05-14 10:15:28', 12, 'gan_php', 'php-v1', 0.62, '2026-05-14 13:15:28'),
(1200, 8, '2026-05-14 11:15:28', 30, 'gan_php', 'php-v1', 0.80, '2026-05-14 13:15:28');

-- --------------------------------------------------------

--
-- Structure de la table `school_absences`
--

CREATE TABLE `school_absences` (
  `id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `student_name` varchar(160) NOT NULL,
  `student_class` varchar(60) DEFAULT NULL,
  `absent_date` date NOT NULL,
  `reason` enum('respiratoire','allergie','fievre','oculaire','digestif','asthme','autre','non_precise') DEFAULT 'non_precise',
  `notes` text DEFAULT NULL,
  `reported_by` varchar(60) DEFAULT NULL,
  `reported_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `school_status`
--

CREATE TABLE `school_status` (
  `id` int(11) NOT NULL,
  `school_name` varchar(160) NOT NULL,
  `zone_id` int(11) DEFAULT NULL,
  `status` enum('normal','vigilance','danger','suspended') DEFAULT 'normal',
  `absentees` int(11) DEFAULT 0,
  `symptoms_count` int(11) DEFAULT 0,
  `last_update` datetime DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `school_status`
--

INSERT INTO `school_status` (`id`, `school_name`, `zone_id`, `status`, `absentees`, `symptoms_count`, `last_update`, `notes`) VALUES
(1, 'École primaire Ghannouch 1', 3, 'vigilance', 22, 14, '2026-06-01 21:45:40', 'Activités plein air suspendues'),
(2, 'Lycée Pilote Gabès', 1, 'vigilance', 8, 4, '2026-05-13 20:00:15', 'Surveillance accrue'),
(3, 'École Chott Essalem', 2, 'vigilance', 11, 6, '2026-06-01 21:45:42', 'Information aux parents envoyée'),
(4, 'Collège Mtorrech', 4, 'normal', 2, 1, '2026-05-13 20:00:15', NULL),
(5, 'École El Hicha', 5, 'normal', 0, 0, '2026-05-13 20:00:15', NULL),
(6, 'École Bou Chemma', 7, 'vigilance', 5, 3, '2026-05-13 20:00:15', 'Suivi en cours');

-- --------------------------------------------------------

--
-- Structure de la table `symptoms`
--

CREATE TABLE `symptoms` (
  `id` int(11) NOT NULL,
  `zone_id` int(11) DEFAULT NULL,
  `citizen_id` int(11) DEFAULT NULL,
  `citizen_name` varchar(120) DEFAULT 'Anonyme',
  `symptom` varchar(120) NOT NULL,
  `severity` enum('mild','moderate','severe') DEFAULT 'mild',
  `notes` text DEFAULT NULL,
  `triage_text` mediumtext DEFAULT NULL,
  `triage_urgency` varchar(20) DEFAULT NULL,
  `triage_at` datetime DEFAULT NULL,
  `status` enum('new','in_progress','resolved') NOT NULL DEFAULT 'new',
  `reported_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `symptoms`
--

INSERT INTO `symptoms` (`id`, `zone_id`, `citizen_id`, `citizen_name`, `symptom`, `severity`, `notes`, `triage_text`, `triage_urgency`, `triage_at`, `status`, `reported_at`) VALUES
(1, 3, NULL, 'Anonyme', 'Toux sèche', 'moderate', 'Persiste depuis ce matin', NULL, NULL, NULL, 'new', '2026-05-13 17:00:15'),
(2, 3, NULL, 'Anonyme', 'Maux de tête', 'mild', NULL, NULL, NULL, NULL, 'in_progress', '2026-05-13 15:00:15'),
(3, 2, NULL, 'Anonyme', 'Irritation des yeux', 'moderate', 'Yeux rouges et larmoyants', NULL, NULL, NULL, 'new', '2026-05-13 14:00:15'),
(4, 1, NULL, 'Anonyme', 'Difficulté respiratoire', 'severe', 'Essoufflement à l\'effort', NULL, NULL, NULL, 'new', '2026-05-12 20:00:15'),
(5, 4, NULL, 'Anonyme', 'Nausée', 'mild', NULL, NULL, NULL, NULL, 'new', '2026-05-13 08:00:15'),
(6, 3, NULL, 'Anonyme', 'Toux sèche', 'severe', 'Crise nocturne', 'Likely causes: pollution, respiratory infection, asthma exacerbation. Immediate advice: stay hydrated, use a mask outdoors. When to consult: severe breathing difficulty, chest pain. Disclaimer: This orientation is not a medical diagnosis. Please consult a healthcare professional if in doubt.', 'severe', '2026-05-13 23:17:33', 'resolved', '2026-05-13 18:00:15'),
(7, 2, NULL, 'Anonyme', 'Maux de gorge', 'mild', NULL, NULL, NULL, NULL, 'new', '2026-05-13 16:00:15'),
(8, 5, 4, 'Ahmed Ben Ali', 'Dry Cough', 'mild', 'test', NULL, NULL, NULL, 'in_progress', '2026-05-13 23:42:45');

-- --------------------------------------------------------

--
-- Structure de la table `symptom_messages`
--

CREATE TABLE `symptom_messages` (
  `id` int(11) NOT NULL,
  `symptom_id` int(11) NOT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `sender_role` enum('citizen','health','admin') NOT NULL,
  `sender_name` varchar(120) DEFAULT NULL,
  `message` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `symptom_messages`
--

INSERT INTO `symptom_messages` (`id`, `symptom_id`, `sender_id`, `sender_role`, `sender_name`, `message`, `created_at`, `read_at`) VALUES
(1, 8, 1, 'admin', 'Administrateur Global', 'bara or9od tw tno4 labes', '2026-06-01 21:32:58', NULL),
(2, 2, 1, 'admin', 'Administrateur Global', 'la labes', '2026-06-01 21:33:12', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `telemed_requests`
--

CREATE TABLE `telemed_requests` (
  `id` int(11) NOT NULL,
  `citizen_id` int(11) NOT NULL,
  `room` varchar(80) NOT NULL,
  `status` enum('waiting','joined','closed','expired') NOT NULL DEFAULT 'waiting',
  `joined_health_id` int(11) DEFAULT NULL,
  `requested_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `pre_consult` text DEFAULT NULL,
  `post_consult` text DEFAULT NULL,
  `joined_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `telemed_requests`
--

INSERT INTO `telemed_requests` (`id`, `citizen_id`, `room`, `status`, `joined_health_id`, `requested_at`, `expires_at`, `pre_consult`, `post_consult`, `joined_at`, `closed_at`) VALUES
(1, 4, 'NafassGabes-4-41882c711573', 'closed', 2, '2026-05-03 13:41:36', '2026-05-03 13:56:36', NULL, NULL, '2026-05-03 13:42:14', '2026-05-03 13:42:48'),
(2, 4, 'NafassGabes-4-3ad742d1a0d7', 'closed', 2, '2026-05-03 18:44:29', '2026-05-03 18:59:29', NULL, '{\"diagnosis\":\"test\",\"recommendations\":\"test\",\"prescription\":\"doliprane\",\"follow_up_days\":3,\"doctor_name\":\"Direction Régionale de la Santé\",\"finalized_at\":\"2026-05-03 17:46:19\"}', '2026-05-03 18:44:50', '2026-05-03 18:46:19'),
(3, 4, 'NafassGabes-4-05835b02ed7e', 'closed', 2, '2026-05-03 18:48:33', '2026-05-03 19:03:33', '{\"temperature\":17.5,\"pulse\":180,\"oxygen_sat\":90,\"symptoms\":\"test avant consultation\",\"notes\":\"test avant consultation\",\"photo_url\":null}', NULL, '2026-05-03 18:48:53', '2026-05-03 18:50:10'),
(4, 4, 'NafassGabes-4-2a8ffce805a9', 'closed', NULL, '2026-05-03 18:50:27', '2026-05-03 19:05:27', '{\"temperature\":17.5,\"pulse\":180,\"oxygen_sat\":90,\"symptoms\":\"test avant consultation\",\"notes\":\"test avant consultation\",\"photo_url\":null}', '{\"diagnosis\":\"tesrt\",\"recommendations\":\"apres consulatation\",\"prescription\":\"apres\",\"follow_up_days\":5,\"doctor_name\":\"Direction Régionale de la Santé\",\"finalized_at\":\"2026-05-03 17:50:58\"}', NULL, '2026-05-03 18:50:58'),
(5, 4, 'NafassGabes-4-a7976d2a7d37', 'closed', 2, '2026-05-03 18:52:16', '2026-05-03 19:07:16', '{\"temperature\":17.5,\"pulse\":180,\"oxygen_sat\":90,\"symptoms\":\"test avant consultation\",\"notes\":\"test avant consultation\",\"photo_url\":null}', '{\"diagnosis\":\"test\",\"recommendations\":\"test\",\"prescription\":\"apres\",\"follow_up_days\":7,\"doctor_name\":\"Direction Régionale de la Santé\",\"finalized_at\":\"2026-05-03 17:53:03\"}', '2026-05-03 18:52:40', '2026-05-03 18:53:03'),
(6, 4, 'NafassGabes-4-aa61ddc9b659', 'closed', 2, '2026-05-03 18:53:38', '2026-05-03 19:08:38', '{\"temperature\":17.5,\"pulse\":180,\"oxygen_sat\":90,\"symptoms\":\"test avant consultation\",\"notes\":\"test avant consultation\",\"photo_url\":null}', '{\"diagnosis\":\"\",\"recommendations\":\"\",\"prescription\":\"\",\"follow_up_days\":null,\"doctor_name\":\"Direction Régionale de la Santé\",\"finalized_at\":\"2026-05-03 17:54:09\"}', '2026-05-03 18:53:47', '2026-05-03 18:54:09'),
(7, 4, 'NafassGabes-4-b5fe4258f6c1', 'closed', 2, '2026-05-06 09:28:16', '2026-05-06 09:43:16', '{\"temperature\":54,\"pulse\":84,\"oxygen_sat\":5,\"symptoms\":\"test\",\"notes\":\"test\",\"photo_url\":null}', '{\"diagnosis\":\"hgh\",\"recommendations\":\"\",\"prescription\":\"\",\"follow_up_days\":null,\"doctor_name\":\"Direction Régionale de la Santé\",\"finalized_at\":\"2026-05-06 08:29:18\"}', '2026-05-06 09:28:49', '2026-05-06 09:29:18');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(60) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(120) DEFAULT NULL,
  `email` varchar(160) DEFAULT NULL,
  `role` enum('citizen','health','school','admin') NOT NULL DEFAULT 'citizen',
  `zone_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `full_name`, `email`, `role`, `zone_id`, `is_active`, `created_at`) VALUES
(1, 'admin', '$2y$10$pvgTPyloVsCj803VFkxzbOK6dcCMpBck1eFfuxJwV97YNbxXqUCxy', 'Administrateur Global', 'admin@gabes-tatenafas.local', 'admin', NULL, 1, '2026-05-13 20:00:15'),
(2, 'health', '$2y$10$EPKzkdMZoGjFpPh0dclhAehWnW8LkmdEsuoHfN.bNTG5Rg15aA7Hi', 'Direction Régionale de la Santé', 'sante@gabes-tatenafas.local', 'health', 1, 1, '2026-05-13 20:00:15'),
(3, 'school', '$2y$10$c2kETQmRlo7cCSINCf8trOnOUsNA0duDAbCKMG2.xYzhzODeUVgU.', 'Directeur École Ghannouch 1', 'ecole.ghannouch@gabes-tatenafas.local', 'school', 3, 1, '2026-05-13 20:00:15'),
(4, 'citizen1', '$2y$10$9yomqQM8EAcvfqED/Qgu8.y6PKc9MuZ2k5ZGXZ03tODU4KK8Q4MXe', 'Ahmed Ben Ali', 'ahmed@example.tn', 'citizen', 3, 1, '2026-05-13 20:00:15');

-- --------------------------------------------------------

--
-- Structure de la table `users_roles`
--

CREATE TABLE `users_roles` (
  `id` int(11) NOT NULL,
  `role_key` varchar(40) NOT NULL,
  `label_fr` varchar(100) NOT NULL,
  `label_ar` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `users_roles`
--

INSERT INTO `users_roles` (`id`, `role_key`, `label_fr`, `label_ar`, `description`, `permissions`) VALUES
(1, 'citizen', 'Citoyen', 'المواطن', 'Habitant de Gabès', '[\"view_air\", \"report\", \"symptoms\", \"chatbot\"]'),
(2, 'health', 'Autorité Sanitaire', 'المسؤول الصحي', 'Décideur santé publique', '[\"dashboard\", \"stats\", \"reports\", \"alerts\"]'),
(3, 'school', 'Directeur d\'école', 'مدير المدرسة', 'Responsable établissement scolaire', '[\"school_mode\", \"alerts\", \"reports\"]'),
(4, 'admin', 'Administrateur', 'المسؤول العام', 'Gestion globale', '[\"all\"]');

-- --------------------------------------------------------

--
-- Structure de la table `waqi_cache`
--

CREATE TABLE `waqi_cache` (
  `zone_id` int(11) NOT NULL,
  `aqi` int(11) NOT NULL,
  `pollution` int(11) NOT NULL,
  `station_name` varchar(140) DEFAULT NULL,
  `fetched_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `weekly_summaries`
--

CREATE TABLE `weekly_summaries` (
  `id` int(11) NOT NULL,
  `week_start` date NOT NULL,
  `week_end` date NOT NULL,
  `generated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `model` varchar(80) DEFAULT NULL,
  `summary_md` mediumtext NOT NULL,
  `metrics_json` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `weekly_summaries`
--

INSERT INTO `weekly_summaries` (`id`, `week_start`, `week_end`, `generated_at`, `model`, `summary_md`, `metrics_json`) VALUES
(1, '2026-05-11', '2026-05-17', '2026-05-13 20:25:08', 'llama-3.3-70b-versatile', '# Weekly Summary — 2026-05-11 → 2026-05-17\n\n## In Brief\nThe past week has seen a notable number of alerts and citizen reports, indicating a potential health concern in Gabès, Tunisia. A total of 1 critical, 2 danger, and 3 watch alerts were issued, along with 6 citizen reports. The reported symptoms suggest a possible link to air quality issues.\n\n## Key Indicators\n- 1 critical alert issued\n- 2 danger alerts issued\n- 3 watch alerts issued\n- 6 citizen reports received\n- 7 reported symptoms, including respiratory issues and eye irritation\n\n## Zones to Monitor\n- Ghannouch — 88 — high average score, potential health risk\n- Chott Essalem — 78 — moderate to high average score, requires attention\n- Bou Chemma — 52 — moderate average score, ongoing monitoring needed\n- Centre-ville — 48 — moderate average score, continued observation required\n- Mtorrech — 41 — low to moderate average score, still warrants monitoring\n\n## Health Trends\nThe dominant symptoms reported include dry cough, headaches, and respiratory difficulties, which may be linked to poor air quality. The presence of eye irritation and nausea also suggests that pollution could be a contributing factor to the health issues experienced by citizens.\n\n## Operational Recommendations\n- Increase air quality monitoring in Ghannouch and Chott Essalem to better understand the sources of pollution\n- Launch a public awareness campaign to educate citizens on the potential health risks associated with poor air quality\n- Collaborate with local authorities to implement measures to reduce pollution in high-risk areas, such as improving waste management and regulating industrial emissions', '{\"alerts\":{\"info\":0,\"warning\":3,\"danger\":2,\"critical\":1},\"reports\":6,\"symptoms\":7,\"top_zones\":[{\"name\":\"Ghannouch\",\"avg_s\":\"88.0000\",\"n\":1},{\"name\":\"Chott Essalem\",\"avg_s\":\"78.0000\",\"n\":1},{\"name\":\"Bou Chemma\",\"avg_s\":\"52.0000\",\"n\":1},{\"name\":\"Centre-ville\",\"avg_s\":\"48.0000\",\"n\":1},{\"name\":\"Mtorrech\",\"avg_s\":\"41.0000\",\"n\":1}],\"top_symptoms\":[{\"symptom\":\"Toux sèche\",\"n\":2},{\"symptom\":\"Maux de tête\",\"n\":1},{\"symptom\":\"Irritation des yeux\",\"n\":1},{\"symptom\":\"Difficulté respiratoire\",\"n\":1},{\"symptom\":\"Nausée\",\"n\":1}]}'),
(2, '2026-04-27', '2026-05-03', '2026-05-14 12:58:40', 'llama-3.3-70b-versatile', '# Weekly Summary — 2026-04-27 → 2026-05-03\n\n## In Brief\nThe period from 2026-04-27 to 2026-05-03 in Gabès, Tunisia, has seen no critical alerts or citizen reports, indicating a stable health and air quality situation. The fuzzy-logic risk assessment for a citizen in the area shows a low urgency level with a crisp risk score of 11.6 out of 100. No vulnerability flags were identified.\n\n## Key Indicators\n- No critical alerts issued\n- No danger alerts issued\n- No watch alerts issued\n- No info alerts issued\n- No citizen reports\n- No reported symptoms\n\n## Zones to Monitor\n- No zones have been identified as requiring monitoring due to insufficient data on top zones.\n\n## Health Trends\nThe absence of reported symptoms and alerts suggests that the dominant health trend in the area is stable, with no apparent links to pollution. The low risk score further supports this observation, indicating minimal health risks associated with air quality.\n\n## Operational Recommendations\n- Continue monitoring air quality and health reports to quickly identify any changes in the situation.\n- Maintain the current surveillance level, as the low risk score and lack of alerts or reports do not necessitate an increase in surveillance.\n- Review and update the fuzzy-logic risk assessment model as necessary to ensure it remains effective in evaluating health risks in the area.', '{\"alerts\":{\"info\":0,\"warning\":0,\"danger\":0,\"critical\":0},\"reports\":0,\"symptoms\":0,\"top_zones\":[],\"top_symptoms\":[]}'),
(3, '2026-05-25', '2026-05-31', '2026-05-25 10:46:36', 'llama-3.3-70b-versatile', '# Weekly Summary — 2026-05-25 → 2026-05-31\n\n## In Brief\nThe overall air quality in Gabès, Tunisia, from 2026-05-25 to 2026-05-31, was relatively stable with no critical alerts issued. The citizen\'s risk score was 11.6 out of 100, indicating a low urgency level. \n\n## Key Indicators\n- 0 critical alerts\n- 0 danger alerts\n- 0 watch alerts\n- 0 info alerts\n- 0 citizen reports\n- 0 reported symptoms\n\n## Zones to Monitor\n- Centre Ville — 11 — average score over 2 measurements\n- Chatt Salem — 11 — average score over 1 measurement\n- Bouchamma — 11 — average score over 2 measurements\n- Teboulbou — 11 — average score over 2 measurements\n- Chenini — 11 — average score over 1 measurement\n\n## Health Trends\nThe dominant symptoms were not reported during this period. However, the low risk score and lack of reported symptoms suggest that the air quality had minimal impact on the citizens\' health. The stable air quality may be attributed to various factors, including weather conditions and pollution levels.\n\n## Operational Recommendations\n- Continue monitoring air quality in the top zones to identify potential trends and changes.\n- Maintain public awareness campaigns to encourage citizens to report any health symptoms or concerns.\n- Review and update the fuzzy-logic risk assessment model to ensure it remains effective in predicting potential health risks.', '{\"alerts\":{\"info\":0,\"warning\":0,\"danger\":0,\"critical\":0},\"reports\":0,\"symptoms\":0,\"top_zones\":[{\"name\":\"Centre Ville\",\"avg_s\":\"11.0000\",\"n\":2},{\"name\":\"Chatt Salem\",\"avg_s\":\"11.0000\",\"n\":1},{\"name\":\"Bouchamma\",\"avg_s\":\"11.0000\",\"n\":2},{\"name\":\"Teboulbou (archive)\",\"avg_s\":\"11.0000\",\"n\":2},{\"name\":\"Chenini\",\"avg_s\":\"11.0000\",\"n\":1}],\"top_symptoms\":[]}'),
(4, '2026-06-01', '2026-06-07', '2026-06-01 21:41:17', 'llama-3.3-70b-versatile', '# Weekly Summary — 2026-06-01 → 2026-06-07\n\n## In Brief\nThe overall air quality and health risk assessment for the period from 2026-06-01 to 2026-06-07 in Gabès, Tunisia, indicates a low urgency level with no critical alerts issued. The citizen\'s risk score is 11.6 out of 100, suggesting a relatively safe environment. \n\n## Key Indicators\n- Alerts issued: 0 critical, 0 danger, 0 watch, 0 info\n- Citizen reports: 0\n- Reported symptoms: 0\n\n## Zones to Monitor\n- No zones are currently identified as high-risk areas, as there are no reported symptoms or alerts.\n\n## Health Trends\nThe dominant trend for this period is the absence of reported symptoms, which may be linked to the overall good air quality in Gabès. The low risk score and lack of vulnerability flags suggest that pollution levels are not significantly impacting the health of citizens.\n\n## Operational Recommendations\n- Continue monitoring air quality levels to ensure the ongoing safety of citizens.\n- Maintain the current surveillance system to quickly identify any potential health risks.\n- Review and update the fuzzy-logic risk assessment model as necessary to ensure its continued effectiveness in evaluating health risks.', '{\"alerts\":{\"info\":0,\"warning\":0,\"danger\":0,\"critical\":0},\"reports\":0,\"symptoms\":0,\"top_zones\":[],\"top_symptoms\":[]}');

-- --------------------------------------------------------

--
-- Structure de la table `zones`
--

CREATE TABLE `zones` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `name_ar` varchar(120) NOT NULL,
  `category` varchar(60) DEFAULT 'urban',
  `population` int(11) DEFAULT 0,
  `pollution_level` int(11) DEFAULT 0,
  `pollution_updated_at` datetime DEFAULT NULL,
  `status` enum('safe','warning','critical') DEFAULT 'safe',
  `lat` decimal(10,6) DEFAULT NULL,
  `lng` decimal(10,6) DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `zones`
--

INSERT INTO `zones` (`id`, `name`, `name_ar`, `category`, `population`, `pollution_level`, `pollution_updated_at`, `status`, `lat`, `lng`, `description`) VALUES
(1, 'Centre Ville', 'وسط المدينة', 'urban', 75000, 22, '2026-05-25 10:35:17', 'safe', 33.885889, 10.107319, 'Downtown Gabès — Bab Bhar area, commerce and traffic.'),
(2, 'Chatt Salem', 'شط السلام', 'industrial', 45000, 22, '2026-05-25 10:34:35', 'safe', 33.901649, 10.100321, 'Downwind of the chemical complex, frequent SO2 exposure.'),
(3, 'Ghannouche', 'غنوش', 'industrial', 32000, 22, '2026-05-25 10:45:56', 'safe', 33.943053, 10.066739, 'Industrial zone — phosphate complex emissions hotspot.'),
(4, 'Chenini', 'شنني', 'rural', 18000, 22, '2026-05-25 10:35:26', 'safe', 33.879796, 10.063941, 'Chenini Nahal — semi-rural oasis village west of Gabès.'),
(5, 'El Bled', 'البلد', 'urban', 28000, 22, '2026-05-25 10:35:28', 'safe', 33.891530, 10.089126, 'Old town of Gabès (l\'ancien Bled), dense residential core.'),
(6, 'Bouchamma', 'بوشمة', 'urban', 22000, 22, '2026-05-25 10:35:31', 'safe', 33.902802, 10.052750, 'Bouchamma — mixed residential western district.'),
(7, 'Teboulbou (archive)', 'طبلبو', 'coastal', 0, 22, '2026-05-25 10:35:33', 'safe', 33.794200, 10.158200, 'Coastal — archived, not displayed in main map.'),
(8, 'Métouia (archive)', 'مطوية', 'rural', 0, 22, '2026-05-25 10:35:35', 'safe', 33.964800, 10.007200, 'Rural north — archived, not displayed in main map.');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `alerts`
--
ALTER TABLE `alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `zone_id` (`zone_id`);

--
-- Index pour la table `api_config`
--
ALTER TABLE `api_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `api_name` (`api_name`);

--
-- Index pour la table `api_readings`
--
ALTER TABLE `api_readings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_city_ts` (`city_id`,`timestamp`);

--
-- Index pour la table `api_verification_log`
--
ALTER TABLE `api_verification_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_api_zone` (`zone_id`,`verified_at`),
  ADD KEY `idx_api_src` (`source`,`verified_at`);

--
-- Index pour la table `chatbot_logs`
--
ALTER TABLE `chatbot_logs`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `daily_tips`
--
ALTER TABLE `daily_tips`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `u_tip_day_lang` (`tip_date`,`language`);

--
-- Index pour la table `forecast_metrics`
--
ALTER TABLE `forecast_metrics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_metric_model` (`model_name`,`trained_at`);

--
-- Index pour la table `forecast_predictions`
--
ALTER TABLE `forecast_predictions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fcst_zone` (`zone_id`,`horizon_hours`,`computed_at`);

--
-- Index pour la table `fragile_profiles`
--
ALTER TABLE `fragile_profiles`
  ADD PRIMARY KEY (`user_id`);

--
-- Index pour la table `fuzzy_assessments`
--
ALTER TABLE `fuzzy_assessments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_city_ts` (`city_id`,`timestamp`),
  ADD KEY `fk_fuzzy_reading` (`reading_id`);

--
-- Index pour la table `fuzzy_reco_logs`
--
ALTER TABLE `fuzzy_reco_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fuzzy_user` (`user_id`),
  ADD KEY `idx_fuzzy_at` (`computed_at`);

--
-- Index pour la table `learn_resources`
--
ALTER TABLE `learn_resources`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_learn_kind` (`kind`),
  ADD KEY `idx_learn_category` (`category`),
  ADD KEY `idx_learn_lang` (`language`),
  ADD KEY `idx_learn_pub` (`is_published`);

--
-- Index pour la table `model_performance`
--
ALTER TABLE `model_performance`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_user` (`target_user_id`);

--
-- Index pour la table `personal_diary`
--
ALTER TABLE `personal_diary`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `u_diary_user_day` (`user_id`,`diary_date`),
  ADD KEY `idx_diary_user_date` (`user_id`,`diary_date`);

--
-- Index pour la table `pollution_forecast`
--
ALTER TABLE `pollution_forecast`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_forecast_zone_horizon` (`zone_id`,`horizon_hours`,`computed_at`);

--
-- Index pour la table `rate_limits`
--
ALTER TABLE `rate_limits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rl_scope_time` (`scope_key`,`occurred_at`);

--
-- Index pour la table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reports_image_hash` (`image_hash`),
  ADD KEY `zone_id` (`zone_id`);

--
-- Index pour la table `reports_pdf`
--
ALTER TABLE `reports_pdf`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `risk_scores`
--
ALTER TABLE `risk_scores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `zone_id` (`zone_id`);

--
-- Index pour la table `risk_scores_augmented`
--
ALTER TABLE `risk_scores_augmented`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_aug_zone` (`zone_id`,`synthetic_at`),
  ADD KEY `idx_aug_meth` (`generation_method`);

--
-- Index pour la table `school_absences`
--
ALTER TABLE `school_absences`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_school_date` (`school_id`,`absent_date`),
  ADD KEY `idx_date` (`absent_date`);

--
-- Index pour la table `school_status`
--
ALTER TABLE `school_status`
  ADD PRIMARY KEY (`id`),
  ADD KEY `zone_id` (`zone_id`);

--
-- Index pour la table `symptoms`
--
ALTER TABLE `symptoms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_symptoms_citizen` (`citizen_id`),
  ADD KEY `zone_id` (`zone_id`);

--
-- Index pour la table `symptom_messages`
--
ALTER TABLE `symptom_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_smsg_symptom` (`symptom_id`),
  ADD KEY `idx_smsg_sender` (`sender_id`);

--
-- Index pour la table `telemed_requests`
--
ALTER TABLE `telemed_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_telemed_status` (`status`),
  ADD KEY `idx_telemed_citizen` (`citizen_id`,`status`),
  ADD KEY `idx_telemed_expires` (`expires_at`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `zone_id` (`zone_id`);

--
-- Index pour la table `users_roles`
--
ALTER TABLE `users_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_key` (`role_key`);

--
-- Index pour la table `waqi_cache`
--
ALTER TABLE `waqi_cache`
  ADD PRIMARY KEY (`zone_id`);

--
-- Index pour la table `weekly_summaries`
--
ALTER TABLE `weekly_summaries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `u_weekly_period` (`week_start`,`week_end`);

--
-- Index pour la table `zones`
--
ALTER TABLE `zones`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `alerts`
--
ALTER TABLE `alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `api_config`
--
ALTER TABLE `api_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `api_readings`
--
ALTER TABLE `api_readings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `api_verification_log`
--
ALTER TABLE `api_verification_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT pour la table `chatbot_logs`
--
ALTER TABLE `chatbot_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT pour la table `daily_tips`
--
ALTER TABLE `daily_tips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `forecast_metrics`
--
ALTER TABLE `forecast_metrics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=232;

--
-- AUTO_INCREMENT pour la table `forecast_predictions`
--
ALTER TABLE `forecast_predictions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=232;

--
-- AUTO_INCREMENT pour la table `fuzzy_assessments`
--
ALTER TABLE `fuzzy_assessments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `fuzzy_reco_logs`
--
ALTER TABLE `fuzzy_reco_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=702;

--
-- AUTO_INCREMENT pour la table `learn_resources`
--
ALTER TABLE `learn_resources`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `model_performance`
--
ALTER TABLE `model_performance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `personal_diary`
--
ALTER TABLE `personal_diary`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `pollution_forecast`
--
ALTER TABLE `pollution_forecast`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `rate_limits`
--
ALTER TABLE `rate_limits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `reports_pdf`
--
ALTER TABLE `reports_pdf`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `risk_scores`
--
ALTER TABLE `risk_scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT pour la table `risk_scores_augmented`
--
ALTER TABLE `risk_scores_augmented`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1201;

--
-- AUTO_INCREMENT pour la table `school_absences`
--
ALTER TABLE `school_absences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `school_status`
--
ALTER TABLE `school_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `symptoms`
--
ALTER TABLE `symptoms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `symptom_messages`
--
ALTER TABLE `symptom_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `telemed_requests`
--
ALTER TABLE `telemed_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `users_roles`
--
ALTER TABLE `users_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `weekly_summaries`
--
ALTER TABLE `weekly_summaries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `zones`
--
ALTER TABLE `zones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `alerts`
--
ALTER TABLE `alerts`
  ADD CONSTRAINT `alerts_ibfk_1` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `fragile_profiles`
--
ALTER TABLE `fragile_profiles`
  ADD CONSTRAINT `fk_fragile_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `fuzzy_assessments`
--
ALTER TABLE `fuzzy_assessments`
  ADD CONSTRAINT `fk_fuzzy_reading` FOREIGN KEY (`reading_id`) REFERENCES `api_readings` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `personal_diary`
--
ALTER TABLE `personal_diary`
  ADD CONSTRAINT `fk_diary_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `pollution_forecast`
--
ALTER TABLE `pollution_forecast`
  ADD CONSTRAINT `fk_forecast_zone` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `risk_scores`
--
ALTER TABLE `risk_scores`
  ADD CONSTRAINT `risk_scores_ibfk_1` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `school_status`
--
ALTER TABLE `school_status`
  ADD CONSTRAINT `school_status_ibfk_1` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `symptoms`
--
ALTER TABLE `symptoms`
  ADD CONSTRAINT `symptoms_ibfk_1` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `symptoms_ibfk_2` FOREIGN KEY (`citizen_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `symptom_messages`
--
ALTER TABLE `symptom_messages`
  ADD CONSTRAINT `symptom_messages_ibfk_1` FOREIGN KEY (`symptom_id`) REFERENCES `symptoms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `symptom_messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
