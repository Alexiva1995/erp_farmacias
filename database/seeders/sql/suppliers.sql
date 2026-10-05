-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 05-10-2026 a las 15:30:54
-- Versión del servidor: 8.0.46
-- Versión de PHP: 8.4.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `farmaciabs_erp2`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `suppliers`
--

CREATE TABLE `suppliers` (
  `id` bigint UNSIGNED NOT NULL,
  `public_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `social_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'drogueria',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `rif` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `sales_phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `collections_phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `credit_days` int DEFAULT NULL COMMENT 'Si es nulo, la factura define los días',
  `min_order_amount` decimal(12,2) DEFAULT '0.00',
  `dispatch_days` json NOT NULL,
  `order_days` json NOT NULL,
  `payment_method` enum('Bs','Divisas') COLLATE utf8mb4_unicode_ci DEFAULT 'Bs',
  `is_indexed` tinyint(1) NOT NULL DEFAULT '0',
  `cash_payment` tinyint DEFAULT '0',
  `charges_igtf` tinyint DEFAULT '0',
  `rating` decimal(5,2) DEFAULT '0.00',
  `is_deleted` tinyint DEFAULT '0',
  `payment_due_type` enum('invoice_date','early_payment','custom') COLLATE utf8mb4_unicode_ci NOT NULL,
  `custom_due_days` int DEFAULT NULL,
  `payment_due_reference` enum('receipt_date','issue_date') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invoice_date_reference` enum('receipt_date','expiration_date','issue_date') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `suppliers`
--

INSERT INTO `suppliers` (`id`, `public_token`, `name`, `social_reason`, `type`, `is_active`, `rif`, `address`, `sales_phone`, `collections_phone`, `payment_email`, `credit_days`, `min_order_amount`, `dispatch_days`, `order_days`, `payment_method`, `is_indexed`, `cash_payment`, `charges_igtf`, `rating`, `is_deleted`, `payment_due_type`, `custom_due_days`, `payment_due_reference`, `invoice_date_reference`, `deleted_at`, `created_at`, `updated_at`) VALUES
(1001, NULL, 'DROCERCA', 'DROGUERIA CER C.A', 'drogueria', 1, 'J314356275', 'Calle La Orquídea Local N°8, Sector pio X, Centro empresarial La pedregosa', '584123027113', '584123068505', 'alexisjoseva95@gmail.com', 0, 0.00, '[\"wednesday\", \"friday\"]', '{\"friday\": [\"monday\", \"tuesday\"], \"wednesday\": [\"thursday\", \"friday\"]}', 'Bs', 1, 0, 0, 0.00, 0, 'early_payment', NULL, 'receipt_date', NULL, NULL, '2023-01-18 02:09:03', '2026-09-23 01:36:48'),
(1002, NULL, 'CRISTALMEDICALS', 'Crist Medicals C.A.', 'drogueria', 1, 'J412236709', 'Calle pasaje Barcelona local galpón #3', '584124264207', '584247241528', 'ventas@cristmedicals.com', 0, 0.00, '[\"monday\", \"tuesday\", \"wednesday\", \"thursday\", \"friday\"]', '{\"friday\": [\"thursday\"], \"monday\": [\"friday\"], \"tuesday\": [\"monday\"], \"thursday\": [\"wednesday\"], \"wednesday\": [\"tuesday\"]}', 'Bs', 1, 0, 0, 0.00, 0, 'invoice_date', NULL, NULL, 'expiration_date', NULL, '2023-01-18 02:09:51', '2026-09-23 01:37:00'),
(1003, '12uVKplnxa6mxdJwuCXSaNlySyYXUuDgcewbAioQ', 'DROGUERIA JAYE', 'DROGUERIA JAYE C.A.', 'drogueria', 1, 'J501572488', 'Calle 3 local bermeja y tocuyito urb merida san Cristóbal Tachira', '584247261204', '584247261204', NULL, NULL, 0.00, '[]', '[]', 'Bs', 1, 0, 0, 0.00, 0, 'early_payment', NULL, 'receipt_date', NULL, NULL, '2023-01-19 02:05:34', '2026-09-23 01:41:07'),
(1004, 'pNlpifnF72WyvUCcvGAsXzZvRgsr7KbRkm0TOyZr', 'DROGUERIA ANDINA', 'DROGUERIA ANDINA C.A', 'drogueria', 1, 'J412673963', 'Calle 5 coon carrera 3edificio Sarahi Piso 1 apto Nro A-2 Barrio Urdaneta', '584141774244', NULL, NULL, NULL, 0.00, '[\"saturday\"]', '{\"saturday\": [\"monday\", \"tuesday\", \"wednesday\", \"thursday\", \"friday\"]}', 'Bs', 1, 0, 0, 0.00, 0, 'invoice_date', NULL, NULL, 'expiration_date', NULL, '2023-01-19 02:05:34', '2026-09-23 01:38:02'),
(1005, NULL, 'DROMEGA', 'Dromega C.A', 'drogueria', 1, 'J307847905', 'calle lera Zona Industrial, los curos, Galpon N° B-13', '584147546671', '584149145444', NULL, 0, 0.00, '[\"wednesday\", \"saturday\"]', '{\"saturday\": [\"wednesday\", \"thursday\", \"friday\"], \"wednesday\": [\"monday\", \"tuesday\"]}', 'Bs', 0, 0, 0, 0.00, 0, 'invoice_date', NULL, 'issue_date', 'expiration_date', NULL, '2023-01-19 17:14:10', '2026-03-23 23:45:12'),
(1006, 'jqGjv6Vj4ddzlRIR0DldHS8JRVIQlQMVAyz0tEng', 'DROSYMCA', NULL, 'drogueria', 1, NULL, NULL, '582763469052', NULL, NULL, NULL, 0.00, '[]', '[]', 'Bs', 0, 0, 0, 0.00, 0, 'invoice_date', NULL, 'issue_date', 'expiration_date', NULL, '2023-01-19 17:21:06', '2026-09-16 15:57:45'),
(1007, 'ZKelvRHBrAgomKa5DizaPIS0C8DAm88wL7yweuHg', 'DROVIDA', NULL, 'drogueria', 0, NULL, NULL, '582763469052', NULL, NULL, NULL, 0.00, '[]', '[]', 'Bs', 0, 0, 0, 0.00, 0, 'invoice_date', NULL, 'issue_date', NULL, NULL, '2023-01-19 17:21:06', '2026-09-23 01:46:21'),
(1008, 'DOh2Lc9rlLzkrAySL5Q0V5E0waTXpW2g22rj5l9T', 'SUMIANDES', 'SUMIANDES 2023 C.A', 'drogueria', 1, 'J504387410', 'Cr. con Calle 6, Edig. Isabel Teresa Nro. 5-87', '584247534166', NULL, NULL, NULL, 0.00, '[]', '[]', 'Bs', 0, 0, 0, 0.00, 0, 'invoice_date', NULL, 'issue_date', 'expiration_date', NULL, '2023-01-23 08:15:33', '2026-09-16 15:57:54'),
(1009, NULL, 'VITALCLINIC', 'DROGUERIA VITALCLINIC C.A.', 'drogueria', 0, 'J412002260', 'AVENIDA ROMULO GALLEGOS - LOCAL EMPRESARIAL NRO 1 Y 2', '584147411337', '58', NULL, NULL, 0.00, '[\"monday\"]', '{\"monday\": []}', 'Divisas', 0, 0, 1, 0.00, 0, 'invoice_date', NULL, 'issue_date', NULL, NULL, '2023-02-07 00:31:52', '2026-09-23 01:53:19'),
(1010, 'rGkL8CS7jxL6SBwg7fam7ztJzhUaEaNUgWvaoT4x', 'DROTACA', NULL, 'drogueria', 1, NULL, NULL, '584141774244', NULL, 'creditoycobranzas.tachira@gmail.com', NULL, 0.00, '[]', '[]', 'Bs', 0, 0, 0, 0.00, 0, 'early_payment', NULL, 'receipt_date', NULL, NULL, '2023-04-04 00:02:30', '2026-09-23 01:45:29'),
(1011, NULL, 'MAFARTA', 'C.A. MAFARTA', 'drogueria', 1, 'J070012250', 'Calle principal riberas del Torbes local Galpón Nro- L04', '584125043109', NULL, NULL, NULL, 0.00, '[\"monday\"]', '{\"monday\": [\"friday\"]}', 'Bs', 0, 0, 0, 0.00, 0, 'invoice_date', NULL, 'issue_date', 'expiration_date', NULL, '2023-04-26 23:15:29', '2026-03-17 19:50:26'),
(1012, NULL, 'INFORMAL', NULL, 'drogueria', 1, NULL, NULL, '573115515421', NULL, NULL, NULL, 0.00, '[]', '[]', 'Bs', 0, 0, 0, 0.00, 0, 'invoice_date', NULL, 'issue_date', 'expiration_date', NULL, '2023-04-26 23:22:30', '2026-09-23 01:51:05'),
(1013, 'Wh3N2M3aPx3BhJ7hA9aqWBYSoHznYSIfgPqmly94', 'LA ESENCIAL', NULL, 'drogueria', 1, NULL, NULL, '573144016077', NULL, 'droguerialaesencialtachira@gmail.com', NULL, 0.00, '[]', '[]', 'Bs', 0, 0, 0, 0.00, 0, 'early_payment', NULL, 'receipt_date', NULL, NULL, '2023-05-09 04:55:45', '2026-09-17 19:34:35'),
(1014, NULL, 'DRONENA', 'Drogueria Nena C.A.', 'drogueria', 1, 'J085189777', 'CR3 con Calle 3, Edif. Dronena', '04269315724', '04269315724', NULL, NULL, 0.00, '[\"monday\", \"tuesday\", \"wednesday\", \"thursday\", \"friday\", \"saturday\"]', '{\"friday\": [\"monday\"], \"monday\": [\"monday\"], \"tuesday\": [\"monday\"], \"saturday\": [\"monday\"], \"thursday\": [\"monday\"], \"wednesday\": [\"monday\"]}', 'Bs', 0, 0, 1, 0.00, 0, 'invoice_date', NULL, 'issue_date', 'expiration_date', NULL, '2023-09-01 05:33:03', '2026-03-30 19:11:57'),
(1015, '3AmValjtNbTn6rqOfaksrM8kIwaKr5dNREUbGVx3', 'DROGUERÍA RR FARMACOS OTC', NULL, 'drogueria', 0, NULL, NULL, '123123', '123123', NULL, NULL, 0.00, '[]', '[]', 'Bs', 0, 1, 0, 0.00, 0, 'invoice_date', NULL, 'issue_date', NULL, NULL, '2023-09-01 05:33:03', '2026-09-23 01:52:38'),
(1016, 'rbbxvXZxF6yTobTVJDHw1Gws21V7T6iwxAuLBz6J', 'VICKMEDICALS', 'VICKMEDICAL C.A DROGUERIA', 'drogueria', 1, 'J412279149', 'CALLE 18, CASA NRO 11-17, BARRIO LA ROMERA, SAN CRISTOBAL', '04247778598', '04147003032', NULL, 0, 0.00, '[\"thursday\", \"wednesday\", \"tuesday\", \"monday\", \"friday\"]', '{\"friday\": [\"monday\"], \"monday\": [\"friday\"], \"tuesday\": [\"monday\"], \"thursday\": [\"wednesday\"], \"wednesday\": [\"tuesday\"]}', 'Bs', 0, 0, 1, 0.00, 0, 'invoice_date', NULL, 'issue_date', 'expiration_date', NULL, '2023-09-01 05:33:03', '2026-03-19 16:34:16'),
(1017, NULL, 'JOSKAR', NULL, 'drogueria', 0, NULL, NULL, '123', '123123', NULL, NULL, 0.00, '[\"monday\", \"tuesday\", \"wednesday\", \"thursday\"]', '[]', 'Bs', 0, 0, 1, 0.00, 0, 'invoice_date', NULL, 'issue_date', NULL, NULL, '2023-09-01 05:33:03', '2026-09-16 15:56:44'),
(1018, 'vD739cxiOUO4bFhYGJ9zg5AYpLrl3C5LOpJva5KB', 'MEGAFAR', 'DROGUERIA MEGAFAR C.A.', 'drogueria', 1, 'J505842099', 'Av Universidad Qta megafar sector La castellana', '584269315724', '584269315724', NULL, 0, 0.00, '[\"monday\"]', '{\"monday\": [\"wednesday\"]}', 'Bs', 0, 0, 0, 0.00, 0, 'early_payment', NULL, 'issue_date', NULL, NULL, '2026-01-06 04:02:15', '2026-04-16 14:42:09'),
(1019, NULL, 'DROANDES', '8789787', 'drogueria', 0, NULL, NULL, '584269315724', '584269315724', NULL, 0, 0.00, '[\"monday\"]', '{\"monday\": [\"tuesday\"]}', 'Bs', 0, 0, 0, 0.00, 0, 'invoice_date', NULL, 'issue_date', 'receipt_date', NULL, '2026-01-06 04:09:01', '2026-09-16 15:55:55'),
(1020, NULL, 'Pintuandes C.A.', 'Pintuandes C.A.', 'drogueria', 1, 'J301197372', 'Carrera 10 vs esquina ciudad comercial metropolitana local L-15', '04269375124', '426315729', NULL, NULL, 0.00, '[\"monday\"]', '{\"monday\": [\"tuesday\"]}', 'Divisas', 0, 0, 0, 0.00, 0, 'invoice_date', NULL, 'issue_date', 'expiration_date', NULL, '2026-03-18 15:52:45', '2026-09-23 01:51:15'),
(1021, NULL, 'Pintuandes C.A.', 'Pintuandes C.A.', 'drogueria', 1, 'J301197372', 'Carrera 10 vs esquina ciudad comercial metropolitana local L-15', '04269375124', '426315729', NULL, NULL, 0.00, '[\"monday\"]', '{\"monday\": [\"tuesday\"]}', 'Divisas', 0, 0, 0, 0.00, 0, 'invoice_date', NULL, 'issue_date', 'expiration_date', NULL, '2026-03-18 15:52:46', '2026-09-23 01:51:26'),
(1022, NULL, 'litografía Nuevo mundo C.A.', 'litografía y gráficas nuevo mundo C.A.', 'externo', 0, 'J299650404', 'calle principal local galpón N|32, zona industrial de puente real', NULL, NULL, NULL, NULL, 0.00, '[]', '[]', 'Bs', 0, 0, 0, 0.00, 0, 'invoice_date', NULL, 'issue_date', 'issue_date', NULL, '2026-05-02 23:55:03', '2026-08-25 18:38:32'),
(1023, NULL, 'La proveduria', 'Javier Alexis Maldonado Guerrero', 'externo', 0, 'V091907140', 'cr5 entre calles 4 y 5 local nro 4-5 caso central la fria', NULL, NULL, NULL, NULL, 0.00, '[]', '[]', 'Bs', 0, 0, 0, 0.00, 0, 'invoice_date', NULL, 'issue_date', 'issue_date', NULL, '2026-05-03 00:02:45', '2026-08-25 18:38:42'),
(1024, NULL, 'Andrexis', 'InversionesAndrexis', 'externo', 0, 'V139733777', 'sector casco central - calle 5 entre carreras 6 y 7 - n°6-47 -la fria', NULL, NULL, NULL, NULL, 0.00, '[]', '[]', 'Bs', 0, 0, 0, 0.00, 0, 'invoice_date', NULL, 'issue_date', 'issue_date', NULL, '2026-05-03 00:06:49', '2026-08-25 18:17:30'),
(1025, NULL, 'ANDRADE', 'OFICINA TECNICA CONTABLE ANDRADE - RINCON', 'externo', 0, 'V-039602861', 'CALLE 1 No 4-81 la fría - Táchira', NULL, NULL, NULL, NULL, 0.00, '[]', '[]', 'Bs', 0, 0, 0, 0.00, 0, 'invoice_date', NULL, 'issue_date', 'issue_date', NULL, '2026-05-26 17:20:20', '2026-08-25 18:17:24'),
(1026, NULL, 'Abastos D&U', 'jose juan duque mejia abasto hermanos D&U', 'externo', 0, 'V203679129', 'calle 6 entre careras 9 y 10 local galpón nro 9-64', NULL, NULL, NULL, NULL, 0.00, '[]', '[]', 'Bs', 0, 0, 0, 0.00, 0, 'invoice_date', NULL, 'issue_date', 'issue_date', NULL, '2026-06-04 17:36:36', '2026-08-25 18:17:21'),
(1027, NULL, 'Johan Colombiano', 'Johan Colombiano', 'drogueria', 1, 'V-000000', 'LA fria', '+584121741799', '+584121741799', NULL, NULL, 0.00, '[\"monday\"]', '{\"monday\": [\"friday\"]}', 'Divisas', 0, 0, 0, 0.00, 0, 'invoice_date', NULL, 'issue_date', 'expiration_date', NULL, '2026-07-23 15:12:04', '2026-07-23 15:12:04'),
(1028, NULL, 'Distribuidora Kola fria C.A.', 'Distribuidora kola fria C.A.', 'externo', 1, 'J306646000', NULL, '000000', '00000', NULL, 0, 0.00, '[]', '[]', 'Bs', 0, 0, 0, 0.00, 0, 'invoice_date', NULL, 'issue_date', NULL, NULL, '2026-08-21 11:42:26', '2026-09-23 01:52:25'),
(1029, NULL, 'Informal', 'Informal *', 'drogueria', 0, 'J123456789', NULL, NULL, NULL, NULL, 0, 0.00, '[]', '[]', 'Bs', 0, 0, 0, 0.00, 0, 'invoice_date', NULL, 'issue_date', NULL, NULL, '2026-08-21 11:42:27', '2026-09-09 22:30:16'),
(1030, NULL, 'Abastos D&U', 'Abastos D&U', 'externo', 0, 'S/R', NULL, NULL, NULL, NULL, 0, 0.00, '[]', '[]', 'Bs', 0, 0, 0, 0.00, 0, 'invoice_date', NULL, 'issue_date', NULL, NULL, '2026-08-21 11:42:27', '2026-09-22 15:37:30'),
(1031, NULL, 'Frutas', 'Frutas', 'externo', 1, 'V-FRUTAS-01', NULL, NULL, NULL, NULL, 0, 0.00, '[]', '[]', 'Bs', 0, 0, 0, 0.00, 0, 'invoice_date', NULL, 'issue_date', NULL, NULL, '2026-08-21 11:42:27', '2026-09-23 01:52:53'),
(1032, NULL, 'CONTINETAL', 'dROGUERIA CONTINELTAL', 'drogueria', 0, '12154546', NULL, NULL, NULL, NULL, NULL, 0.00, '[]', '[]', 'Bs', 0, 0, 0, 0.00, 0, 'invoice_date', NULL, 'issue_date', 'issue_date', NULL, '2026-09-04 14:33:44', '2026-09-22 15:38:07'),
(1033, NULL, 'DROGUERIA CERCA (DROCERCA)', 'DROGUERIA CERCA (DROCERCA)', 'drogueria', 1, 'J-50540695-7', NULL, NULL, NULL, NULL, 0, 0.00, '[]', '[]', 'Bs', 0, 0, 0, 0.00, 0, 'invoice_date', NULL, 'issue_date', NULL, '2026-09-09 22:00:55', '2026-09-04 15:01:53', '2026-09-09 22:00:55'),
(1034, NULL, 'DISTRIROSHI', 'DISTRIROSHI', 'drogueria', 0, 'J503775530', NULL, '04247281320', NULL, NULL, NULL, 0.00, '[]', '[]', 'Bs', 0, 0, 0, 0.00, 0, 'invoice_date', NULL, 'issue_date', 'issue_date', NULL, '2026-09-09 19:00:18', '2026-09-16 15:58:26'),
(1035, NULL, 'Drogueria Autana', 'Drogueria Autana', 'drogueria', 0, 'J501715718', NULL, '04121120951', NULL, NULL, NULL, 0.00, '[]', '[]', 'Bs', 0, 0, 0, 0.00, 0, 'invoice_date', NULL, 'issue_date', 'issue_date', NULL, '2026-09-09 20:13:12', '2026-09-16 15:58:33'),
(1036, NULL, 'Inmedical Fenix', 'IN', 'drogueria', 1, 'J507114082', NULL, '04141791121', NULL, NULL, NULL, 0.00, '[]', '[]', 'Bs', 0, 0, 0, 0.00, 0, 'invoice_date', NULL, 'issue_date', 'issue_date', NULL, '2026-09-09 20:24:30', '2026-09-09 20:24:30'),
(1037, NULL, 'Droandes', 'Droeandes', 'drogueria', 1, 'j454545', NULL, '4545454', NULL, NULL, NULL, 0.00, '[]', '[]', 'Bs', 0, 0, 0, 0.00, 0, 'invoice_date', NULL, 'issue_date', 'issue_date', '2026-09-09 22:00:30', '2026-09-09 20:52:18', '2026-09-09 22:00:30'),
(1038, NULL, 'AldMedical', 'ald', 'drogueria', 0, 'j555', NULL, '545', NULL, NULL, NULL, 200.00, '[]', '[]', 'Bs', 0, 0, 0, 0.00, 0, 'invoice_date', NULL, 'issue_date', 'issue_date', NULL, '2026-09-09 21:21:39', '2026-09-22 15:37:08'),
(1039, NULL, 'MD Llanos', 'md', 'drogueria', 0, 'J54878', NULL, '454', NULL, NULL, NULL, 0.00, '[]', '[]', 'Bs', 0, 0, 0, 0.00, 0, 'invoice_date', NULL, 'issue_date', 'issue_date', NULL, '2026-09-09 21:25:13', '2026-09-23 01:53:10');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `suppliers_public_token_unique` (`public_token`),
  ADD KEY `idx_supplier_active` (`is_deleted`),
  ADD KEY `idx_supplier_rating` (`rating`),
  ADD KEY `suppliers_is_active_index` (`is_active`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1040;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
