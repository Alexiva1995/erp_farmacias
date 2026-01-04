-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Versión del servidor:         8.4.3 - MySQL Community Server - GPL
-- SO del servidor:              Win64
-- HeidiSQL Versión:             12.8.0.6908
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Volcando estructura para tabla migrable.users
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `last_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cedula` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `salary` double DEFAULT NULL,
  `currency_salary` tinyint NOT NULL DEFAULT '0' COMMENT '0: USD, 1: BS 2: COP',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `active_product_units` tinyint DEFAULT NULL COMMENT '0 - all products, 1 - 0 units',
  `active_product_modules_units` tinyint DEFAULT NULL COMMENT '0 - all products, 1 - 0 units',
  `token_login` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_cedula_unique` (`cedula`)
) ENGINE=InnoDB AUTO_INCREMENT=89 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla migrable.users: ~10 rows (aproximadamente)
INSERT INTO `users` (`id`, `first_name`, `email`, `email_verified_at`, `password`, `remember_token`, `is_admin`, `created_at`, `updated_at`, `last_name`, `cedula`, `photo`, `salary`, `currency_salary`, `is_active`, `active_product_units`, `active_product_modules_units`, `token_login`) VALUES
	(1, 'Administrador', 'admin@farmaciabs.com', '2022-09-03 21:00:04', '$2y$10$tAheLN4ShIRxKeSAh9bc7.6Y9N..YI5NINXQSjNw5p8P8JvxTx2SW', NULL, 1, '2022-09-03 21:00:04', '2025-07-24 19:45:20', 'SOPORTE', '0', NULL, 40000, 2, 1, NULL, 0, 'XTC3HYQSLQJN2SGL'),
	(2, 'User', 'user@farmaciabs.com', '2022-09-03 21:00:04', '$2y$10$3HuMDaO1OUfYeZEQjNthKeVKeP3G/5uhyL3adAJbuYlBMKzFVge1u', NULL, 0, '2022-09-03 21:00:04', '2025-07-24 14:49:07', 'SOPORTE', NULL, NULL, NULL, 0, 1, 0, NULL, 'MHQA2FGCVE6GST4J'),
	(59, 'Alexis', 'alexis@farmaciabs.com', '2023-08-15 19:34:24', '$2y$10$7TNsU8WqWxTbpkczuCmU0eZaUTMMsmM8eclMwkG3QFIxQ7o1ON7hC', NULL, 0, '2023-08-15 19:34:24', '2025-08-05 13:59:35', 'Valera', '241509805', NULL, 100, 0, 1, 0, NULL, 'YY7QAWUJIWBB4MZ4'),
	(70, 'yenireth', 'yenirethitanare@farmaciabs.com', '2024-10-18 23:30:51', '$2y$10$BZvQmfWJTAGCxjYKEBxvAOA/U1sZKzJwAqucoDXYNFAGMGqccQV6O', NULL, 0, '2024-10-18 23:30:51', '2025-07-29 19:24:44', 'itanare', '30335463', NULL, 400000, 0, 1, 0, NULL, 'CM5AOA7LLMRQSO5A'),
	(77, 'Maria', 'mariamartinez@farmaciabs.com', '2025-03-03 15:45:39', '$2y$10$3b3LYRM2EsNzUnxjPnGewO6G5U1RxIIq1/PtDvJkDu10Q8H7KyuXW', NULL, 0, '2025-03-03 15:45:39', '2025-05-30 16:24:01', 'Martinez', '32130078', NULL, 450000, 0, 1, 0, NULL, '2ONKZULYAVQYG6D5'),
	(81, 'Jackeline', 'jackelinvarela@farmaciabs.com', '2025-04-30 13:46:40', '$2y$10$tAheLN4ShIRxKeSAh9bc7.6Y9N..YI5NINXQSjNw5p8P8JvxTx2SW', NULL, 0, '2025-04-30 13:46:40', '2025-11-04 22:13:45', 'Varela', '34917767', NULL, 450000, 0, 1, 0, NULL, '6EYJFIABGOFZD4S3'),
	(82, 'Paola', 'paolabarreto@farmaciabs.com', '2025-05-14 21:46:06', '$2y$10$Ko8XI0hl933WtBCmoolhweRUiyz.rHNyfHICUuPvBXhyWv.i72Hyq', NULL, 0, '2025-05-14 21:46:06', '2025-11-04 20:47:40', 'Barreto', '28017946', NULL, 450000, 0, 1, 0, NULL, '2BZB2AMWWRHPA2IK'),
	(84, 'Estefanny', 'estefannytorrado@farmaciabs.com', '2025-06-11 04:27:18', '$2y$10$5Aj/mb13TRgtCHP.h.nzK.VHaE/ByC1T6xOGKFzjUQixe8nz1QWXO', NULL, 1, '2025-06-11 04:27:18', '2025-06-11 04:29:45', 'Torrado', '27108387', NULL, 350000, 0, 1, NULL, NULL, '7PF2UGCXQYJNW3VF'),
	(87, 'ORIANA ANELIX', 'orianabarboza@farmaciabs.com', '2025-08-24 16:18:11', '$2y$10$N0xLJH38B4YUcyyLxB4dROuE7xPjzJeO9Nb64UGjfa1gQ13O6cu/C', NULL, 0, '2025-08-24 16:18:11', '2025-11-25 14:22:29', 'BARBOZA COLMENARES', '32394926', NULL, 400000, 0, 1, NULL, NULL, 'RYKPVH2NMMPUEKS6'),
	(88, 'Mayela', 'mayelamorales@farmaciabs.com', '2025-09-02 19:53:11', '$2y$10$cj31kHRaqpOWj5rwO9WQaeKxR.f2BYg1ggOfKxKI4tPUfaC8PGaoS', NULL, 0, '2025-09-02 19:53:11', '2025-09-02 19:55:42', 'Morales', '9351893', NULL, 115, 0, 1, NULL, NULL, 'TZGKTF4YJE2NU6JU');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
