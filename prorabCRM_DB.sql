-- phpMyAdmin SQL Dump
-- version 5.1.1deb5ubuntu1
-- https://www.phpmyadmin.net/
--
-- Хост: localhost
-- Время создания: Апр 25 2025 г., 16:50
-- Версия сервера: 8.0.41-0ubuntu0.22.04.1
-- Версия PHP: 8.1.2-1ubuntu2.21

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `prorab`
--

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_Adjustments`
--

CREATE TABLE `PCRM_Adjustments` (
  `id` int NOT NULL,
  `type` varchar(50) NOT NULL,
  `product` int NOT NULL,
  `warehouse_id` int NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `date` datetime NOT NULL,
  `user_id` int NOT NULL,
  `comment` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_AnalyticsDashboard`
--

CREATE TABLE `PCRM_AnalyticsDashboard` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `created_by` int DEFAULT NULL,
  `filters` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_CashRegister`
--

CREATE TABLE `PCRM_CashRegister` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `PCRM_CashRegister`
--

INSERT INTO `PCRM_CashRegister` (`id`, `name`, `description`, `status`, `created_at`) VALUES
(1, 'Cash Register 1', 'Main office cash register', 'active', '2025-03-17 02:19:39'),
(2, 'Cash Register 2', 'Secondary cash register', 'active', '2025-03-17 02:19:39');

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_Categories`
--

CREATE TABLE `PCRM_Categories` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `type` varchar(50) NOT NULL,
  `pc_id` int DEFAULT NULL,
  `status` varchar(50) DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `PCRM_Categories`
--

INSERT INTO `PCRM_Categories` (`id`, `name`, `description`, `type`, `pc_id`, `status`) VALUES
(1, 'Сыпучие', '', 'category', NULL, 'inactive'),
(2, 'Песок', '', 'subcategory', 1, 'inactive'),
(3, 'тест', '', 'category', NULL, 'active'),
(4, 'под_тест', '', 'subcategory', 3, 'active'),
(5, 'под_тест2', '', 'subcategory', 3, 'active'),
(6, 'не_тест', '', 'category', NULL, 'active'),
(7, 'не_под_тест', '', 'subcategory', 6, 'active');

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_Counterparty`
--

CREATE TABLE `PCRM_Counterparty` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(50) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `inn` varchar(20) DEFAULT NULL,
  `kpp` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `PCRM_Counterparty`
--

INSERT INTO `PCRM_Counterparty` (`id`, `name`, `type`, `phone`, `email`, `address`, `inn`, `kpp`) VALUES
(1, 'тестовый контрагент', 'физлицо', '+79781000000', 'test@gmail.com', 'Тестовая, д.5', '', ''),
(2, 'Тест заказов', 'физлицо', '', '', 'Петропавловская 222', '', '');

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_DocumentRelation`
--

CREATE TABLE `PCRM_DocumentRelation` (
  `id` int NOT NULL,
  `parent_type` varchar(50) NOT NULL,
  `parent_id` int NOT NULL,
  `child_type` varchar(50) NOT NULL,
  `child_id` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_Drivers`
--

CREATE TABLE `PCRM_Drivers` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `vehicle_name` varchar(255) DEFAULT NULL,
  `load_capacity` decimal(10,2) DEFAULT '0.00',
  `max_volume` decimal(10,3) DEFAULT '0.000',
  `phone` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `PCRM_Drivers`
--

INSERT INTO `PCRM_Drivers` (`id`, `name`, `vehicle_name`, `load_capacity`, `max_volume`, `phone`) VALUES
(1, 'Тестовый Водила Иванович', 'Зил', '7.00', '0.000', '+79781234567');

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_FinancialTransaction`
--

CREATE TABLE `PCRM_FinancialTransaction` (
  `id` int NOT NULL,
  `transaction_number` varchar(50) NOT NULL,
  `transaction_date` datetime NOT NULL,
  `transaction_type` enum('income','expense') NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `counterparty_id` int NOT NULL,
  `cash_register_id` int NOT NULL,
  `payment_method` enum('cash','card','transfer_rncb','transfer_other','bank_account','hybrid') NOT NULL DEFAULT 'cash',
  `expense_category` varchar(50) DEFAULT NULL,
  `description` text,
  `conducted` tinyint NOT NULL DEFAULT '0',
  `user_id` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `PCRM_FinancialTransaction`
--

INSERT INTO `PCRM_FinancialTransaction` (`id`, `transaction_number`, `transaction_date`, `transaction_type`, `amount`, `counterparty_id`, `cash_register_id`, `payment_method`, `expense_category`, `description`, `conducted`, `user_id`, `created_at`) VALUES
(7, 'IN-000007', '2025-03-28 00:24:00', 'income', '300.00', 2, 1, 'hybrid', NULL, '', 1, 1, '2025-03-28 00:24:58'),
(8, 'IN-000007', '2025-03-28 00:43:00', 'income', '200.00', 1, 1, 'cash', '0', '', 1, 1, '2025-03-28 00:43:16');

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_FormState`
--

CREATE TABLE `PCRM_FormState` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `state_key` varchar(255) NOT NULL,
  `state_data` longtext,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `PCRM_FormState`
--

INSERT INTO `PCRM_FormState` (`id`, `user_id`, `state_key`, `state_data`, `created_at`, `updated_at`) VALUES
(10777, 1, 'tabs', '[{\"tabId\":\"tab-production-orders-list\",\"contentId\":\"content-production-orders-list\",\"title\":\"production\\/orders\\/list\",\"data\":{\"module\":\"production\\/orders\\/list\",\"bsToggle\":\"tab\"},\"isActive\":false},{\"tabId\":\"tab-production-recipes-list\",\"contentId\":\"content-production-recipes-list\",\"title\":\"production\\/recipes\\/list\",\"data\":{\"module\":\"production\\/recipes\\/list\",\"bsToggle\":\"tab\"},\"isActive\":false},{\"tabId\":\"tab-production-operations-list\",\"contentId\":\"content-production-operations-list\",\"title\":\"production\\/operations\\/list\",\"data\":{\"module\":\"production\\/operations\\/list\",\"bsToggle\":\"tab\"},\"isActive\":false},{\"tabId\":\"tab-corrections-inventory-list\",\"contentId\":\"content-corrections-inventory-list\",\"title\":\"\\u0418\\u043d\\u0432\\u0435\\u043d\\u0442\\u0430\\u0440\\u0438\\u0437\\u0430\\u0446\\u0438\\u044f\",\"data\":{\"module\":\"corrections\\/inventory\\/list\",\"bsToggle\":\"tab\"},\"isActive\":false},{\"tabId\":\"tab-products-list\",\"contentId\":\"content-products-list\",\"title\":\"\\u0421\\u043f\\u0438\\u0441\\u043e\\u043a \\u0442\\u043e\\u0432\\u0430\\u0440\\u043e\\u0432\",\"data\":{\"module\":\"products\\/list\",\"bsToggle\":\"tab\"},\"isActive\":false},{\"tabId\":\"tab-sales-orders-list\",\"contentId\":\"content-sales-orders-list\",\"title\":\"\\u0417\\u0430\\u043a\\u0430\\u0437\\u044b \\u043f\\u043e\\u043a\\u0443\\u043f\\u0430\\u0442\\u0435\\u043b\\u0435\\u0439\",\"data\":{\"module\":\"sales\\/orders\\/list\",\"bsToggle\":\"tab\"},\"isActive\":true}]', '2025-04-23 07:59:05', '2025-04-24 02:34:12'),
(10781, 1, 'forms', '{\"content-production-orders-list\":{\"tabContentId\":\"content-production-orders-list\",\"documentType\":null,\"documentId\":null,\"timestamp\":\"2025-04-24T02:34:11.141Z\",\"values\":[]},\"content-production-recipes-list\":{\"tabContentId\":\"content-production-recipes-list\",\"documentType\":null,\"documentId\":null,\"timestamp\":\"2025-04-24T02:34:11.141Z\",\"values\":[]},\"content-production-operations-list\":{\"tabContentId\":\"content-production-operations-list\",\"documentType\":null,\"documentId\":null,\"timestamp\":\"2025-04-24T02:34:11.141Z\",\"values\":[]},\"content-corrections-inventory-list\":{\"tabContentId\":\"content-corrections-inventory-list\",\"documentType\":null,\"documentId\":null,\"timestamp\":\"2025-04-24T02:34:11.141Z\",\"values\":[]},\"content-products-list\":{\"tabContentId\":\"content-products-list\",\"documentType\":null,\"documentId\":null,\"timestamp\":\"2025-04-24T02:34:11.141Z\",\"values\":[]},\"content-sales-orders-list\":{\"tabContentId\":\"content-sales-orders-list\",\"documentType\":null,\"documentId\":null,\"timestamp\":\"2025-04-24T02:34:11.142Z\",\"values\":[]}}', '2025-04-23 07:59:45', '2025-04-24 02:34:12');

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_InboundOperations`
--

CREATE TABLE `PCRM_InboundOperations` (
  `id` int NOT NULL,
  `type` varchar(50) NOT NULL,
  `product` int NOT NULL,
  `warehouse_to` int NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `date` datetime NOT NULL,
  `user_id` int NOT NULL,
  `comment` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_InboundReturns`
--

CREATE TABLE `PCRM_InboundReturns` (
  `id` int NOT NULL,
  `type` varchar(50) NOT NULL,
  `product` int NOT NULL,
  `warehouse_to` int NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `date` datetime NOT NULL,
  `user_id` int NOT NULL,
  `comment` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_Loaders`
--

CREATE TABLE `PCRM_Loaders` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL COMMENT 'Имя или ФИО грузчика',
  `phone` varchar(50) DEFAULT NULL COMMENT 'Телефон',
  `status` varchar(50) DEFAULT 'active' COMMENT 'active/inactive'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Дамп данных таблицы `PCRM_Loaders`
--

INSERT INTO `PCRM_Loaders` (`id`, `name`, `phone`, `status`) VALUES
(1, 'Тестовый Вася', '+79781112345', 'active');

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_Order`
--

CREATE TABLE `PCRM_Order` (
  `id` int NOT NULL,
  `organization` int DEFAULT NULL,
  `order_number` varchar(50) NOT NULL,
  `customer` int DEFAULT NULL,
  `contacts` varchar(255) DEFAULT '',
  `created_by` int DEFAULT NULL,
  `order_date` datetime NOT NULL,
  `status` enum('new','confirmed','in_transit','completed','cancelled') NOT NULL DEFAULT 'new',
  `driver` int DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT '0.00',
  `warehouse` int DEFAULT NULL,
  `delivery_address` varchar(255) DEFAULT NULL,
  `comment` varchar(255) DEFAULT NULL,
  `deleted` tinyint DEFAULT '0',
  `conducted` tinyint NOT NULL DEFAULT '0' COMMENT '0=неактивен, 1=активен(не проведён), 2=активен(проведён)',
  `driver_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `PCRM_Order`
--

INSERT INTO `PCRM_Order` (`id`, `organization`, `order_number`, `customer`, `contacts`, `created_by`, `order_date`, `status`, `driver`, `total_amount`, `warehouse`, `delivery_address`, `comment`, `deleted`, `conducted`, `driver_id`) VALUES
(8, 1, 'SO-000001', 2, '', 1, '2025-03-13 01:57:00', 'confirmed', NULL, '100.00', 1, '', '', 1, 0, NULL),
(9, 1, 'SO-000009', 2, '', 1, '2025-03-24 04:37:00', 'completed', NULL, '100.00', 1, 'тестовая 5', '', 0, 2, 1),
(10, 1, 'SO-000010', 1, '+79781000000', 1, '2025-03-28 00:20:00', 'completed', NULL, '200.00', 1, '', '', 0, 2, NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_OrderHistory`
--

CREATE TABLE `PCRM_OrderHistory` (
  `id` int NOT NULL,
  `order_id` int NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `action_details` text,
  `user_id` int NOT NULL,
  `timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `PCRM_OrderHistory`
--

INSERT INTO `PCRM_OrderHistory` (`id`, `order_id`, `action_type`, `action_details`, `user_id`, `timestamp`) VALUES
(1, 8, 'update', 'Изменен статус заказа на \"Подтверждён\"', 1, '2025-03-16 22:40:08');

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_OrderItem`
--

CREATE TABLE `PCRM_OrderItem` (
  `id` int NOT NULL,
  `order_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` decimal(10,3) NOT NULL DEFAULT '0.000',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `discount` decimal(10,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Дамп данных таблицы `PCRM_OrderItem`
--

INSERT INTO `PCRM_OrderItem` (`id`, `order_id`, `product_id`, `quantity`, `price`, `discount`) VALUES
(10, 6, 1, '1.000', '100.00', '0.00'),
(12, 7, 1, '15.000', '100.00', '0.00'),
(19, 8, 1, '1.000', '100.00', '0.00'),
(33, 9, 1, '1.000', '100.00', '0.00'),
(36, 10, 1, '1.000', '100.00', '0.00'),
(37, 10, 1, '1.000', '100.00', '0.00');

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_Organization`
--

CREATE TABLE `PCRM_Organization` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL COMMENT 'Название организации (юридическое)',
  `inn` varchar(12) DEFAULT NULL COMMENT 'ИНН',
  `kpp` varchar(9) DEFAULT NULL COMMENT 'КПП',
  `ogrn` varchar(15) DEFAULT NULL COMMENT 'ОГРН, если нужно',
  `address` varchar(255) DEFAULT NULL COMMENT 'Юридический/почтовый адрес',
  `phone` varchar(50) DEFAULT NULL COMMENT 'Телефон',
  `email` varchar(100) DEFAULT NULL COMMENT 'E-mail',
  `status` varchar(50) DEFAULT 'active' COMMENT 'active / inactive и т.д.'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Дамп данных таблицы `PCRM_Organization`
--

INSERT INTO `PCRM_Organization` (`id`, `name`, `inn`, `kpp`, `ogrn`, `address`, `phone`, `email`, `status`) VALUES
(1, 'ООО \"СтройГигант\"', '7712345678', '771301001', '1157746382975', 'г. Москва, ул. Ленина, д.1, оф.101', '+7 (495) 123-45-67', 'info@stroygiant.ru', 'active');

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_OutboundOperations`
--

CREATE TABLE `PCRM_OutboundOperations` (
  `id` int NOT NULL,
  `type` varchar(50) NOT NULL,
  `product` int NOT NULL,
  `warehouse_from` int NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `date` datetime NOT NULL,
  `user_id` int NOT NULL,
  `comment` varchar(255) DEFAULT NULL,
  `deleted` tinyint DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_OutboundReturns`
--

CREATE TABLE `PCRM_OutboundReturns` (
  `id` int NOT NULL,
  `type` varchar(50) NOT NULL,
  `product` int NOT NULL,
  `warehouse_from` int NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `date` datetime NOT NULL,
  `user_id` int NOT NULL,
  `comment` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_PaymentMethodDetails`
--

CREATE TABLE `PCRM_PaymentMethodDetails` (
  `id` int NOT NULL,
  `transaction_id` int NOT NULL,
  `payment_method` enum('cash','card','transfer_rncb','transfer_other','bank_account') NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `description` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `PCRM_PaymentMethodDetails`
--

INSERT INTO `PCRM_PaymentMethodDetails` (`id`, `transaction_id`, `payment_method`, `amount`, `description`, `created_at`) VALUES
(1, 7, 'cash', '100.00', '', '2025-03-28 00:24:58'),
(2, 7, 'card', '200.00', '', '2025-03-28 00:24:58');

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_Product`
--

CREATE TABLE `PCRM_Product` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `description` text,
  `category` int DEFAULT NULL,
  `subcategory` int DEFAULT NULL,
  `price` decimal(10,2) DEFAULT '0.00',
  `cost_price` decimal(10,2) DEFAULT '0.00',
  `unit_of_measure` varchar(20) DEFAULT 'шт',
  `weight` decimal(10,3) DEFAULT '0.000',
  `volume` decimal(10,3) DEFAULT '0.000',
  `status` varchar(50) DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `PCRM_Product`
--

INSERT INTO `PCRM_Product` (`id`, `name`, `sku`, `description`, `category`, `subcategory`, `price`, `cost_price`, `unit_of_measure`, `weight`, `volume`, `status`) VALUES
(1, 'тест', '2212123', '', 6, 7, '100.00', '100.00', 'шт', '0.000', '0.000', 'active');

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_ProductImages`
--

CREATE TABLE `PCRM_ProductImages` (
  `id` int NOT NULL,
  `product_id` int NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `is_main` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_ProductionOperation`
--

CREATE TABLE `PCRM_ProductionOperation` (
  `id` int NOT NULL,
  `operation_number` varchar(50) NOT NULL COMMENT 'Номер операции производства',
  `order_id` int DEFAULT NULL COMMENT 'ID заказа на производство (если создано из заказа)',
  `production_date` datetime NOT NULL COMMENT 'Дата производства',
  `warehouse_id` int NOT NULL COMMENT 'ID склада',
  `product_id` int NOT NULL COMMENT 'ID производимого продукта',
  `output_quantity` decimal(10,3) NOT NULL COMMENT 'Количество произведенного продукта',
  `status` enum('draft','completed','cancelled') NOT NULL DEFAULT 'draft',
  `conducted` tinyint NOT NULL DEFAULT '0' COMMENT '0=не проведен, 1=проведен',
  `comment` text COMMENT 'Комментарий',
  `created_by` int DEFAULT NULL COMMENT 'Кто создал',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_ProductionOperationItem`
--

CREATE TABLE `PCRM_ProductionOperationItem` (
  `id` int NOT NULL,
  `operation_id` int NOT NULL COMMENT 'ID операции производства',
  `ingredient_id` int NOT NULL COMMENT 'ID ингредиента (товара)',
  `quantity` decimal(10,3) NOT NULL COMMENT 'Количество ингредиента'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_ProductionOrder`
--

CREATE TABLE `PCRM_ProductionOrder` (
  `id` int NOT NULL,
  `order_number` varchar(50) NOT NULL COMMENT 'Номер заказа на производство',
  `recipe_id` int NOT NULL COMMENT 'ID рецепта',
  `planned_date` datetime NOT NULL COMMENT 'Планируемая дата производства',
  `status` enum('new','in_progress','completed','cancelled') NOT NULL DEFAULT 'new',
  `warehouse_id` int NOT NULL COMMENT 'ID склада для операции',
  `quantity` decimal(10,3) NOT NULL DEFAULT '1.000' COMMENT 'Количество для производства',
  `comment` text COMMENT 'Комментарий',
  `conducted` tinyint NOT NULL DEFAULT '0' COMMENT '0=не проведен, 1=проведен',
  `created_by` int DEFAULT NULL COMMENT 'Кто создал',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_ProductionRecipe`
--

CREATE TABLE `PCRM_ProductionRecipe` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL COMMENT 'Название рецепта',
  `product_id` int NOT NULL COMMENT 'ID производимого товара',
  `output_quantity` decimal(10,3) NOT NULL DEFAULT '1.000' COMMENT 'Количество производимого товара',
  `description` text COMMENT 'Описание процесса производства',
  `status` varchar(50) NOT NULL DEFAULT 'active' COMMENT 'Статус рецепта: active/inactive',
  `created_by` int DEFAULT NULL COMMENT 'Кто создал',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_ProductionRecipeItem`
--

CREATE TABLE `PCRM_ProductionRecipeItem` (
  `id` int NOT NULL,
  `recipe_id` int NOT NULL COMMENT 'ID рецепта',
  `ingredient_id` int NOT NULL COMMENT 'ID ингредиента (товара)',
  `quantity` decimal(10,3) NOT NULL COMMENT 'Количество ингредиента'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_PurchaseOrder`
--

CREATE TABLE `PCRM_PurchaseOrder` (
  `id` int NOT NULL,
  `purchase_order_number` varchar(50) DEFAULT NULL,
  `supplier_id` int NOT NULL,
  `date` datetime DEFAULT NULL,
  `status` varchar(50) DEFAULT 'draft',
  `total_amount` decimal(10,2) DEFAULT '0.00',
  `organization` int DEFAULT NULL,
  `warehouse_id` int DEFAULT NULL,
  `delivery_address` varchar(255) DEFAULT NULL,
  `comment` varchar(255) DEFAULT NULL,
  `deleted` tinyint DEFAULT '0',
  `conducted` tinyint DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_PurchaseOrderItem`
--

CREATE TABLE `PCRM_PurchaseOrderItem` (
  `id` int NOT NULL,
  `purchase_order_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` decimal(10,3) NOT NULL DEFAULT '0.000',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `discount` decimal(10,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_ReceiptHeader`
--

CREATE TABLE `PCRM_ReceiptHeader` (
  `id` int NOT NULL,
  `receipt_number` varchar(50) NOT NULL,
  `receipt_date` datetime NOT NULL,
  `purchase_order_id` int DEFAULT NULL,
  `warehouse_id` int NOT NULL,
  `loader_id` int DEFAULT NULL,
  `status` varchar(20) DEFAULT 'new',
  `conducted` tinyint NOT NULL DEFAULT '0',
  `created_by` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `comment` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_ReceiptItem`
--

CREATE TABLE `PCRM_ReceiptItem` (
  `id` int NOT NULL,
  `receipt_header_id` int DEFAULT NULL,
  `product_id` int NOT NULL,
  `quantity` decimal(10,3) NOT NULL DEFAULT '0.000',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `discount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `conducted` tinyint NOT NULL DEFAULT '0',
  `unloaded_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_RelatedDocuments`
--

CREATE TABLE `PCRM_RelatedDocuments` (
  `id` int NOT NULL,
  `source_type` varchar(20) NOT NULL COMMENT 'order, shipment, finance, return',
  `source_id` int NOT NULL,
  `related_type` varchar(20) NOT NULL COMMENT 'order, shipment, finance, return',
  `related_id` int NOT NULL,
  `relation_type` varchar(50) NOT NULL COMMENT 'created_from, created_to',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `PCRM_RelatedDocuments`
--

INSERT INTO `PCRM_RelatedDocuments` (`id`, `source_type`, `source_id`, `related_type`, `related_id`, `relation_type`, `created_at`) VALUES
(1, 'order', 8, 'finance', 2, 'created_to', '2025-03-24 04:17:20'),
(2, 'order', 8, '0', 1, 'created_from', '2025-03-24 04:19:13'),
(4, 'order', 9, 'shipment', 4, 'created_from', '2025-03-24 04:39:06'),
(9, 'return', 4, '0', 6, 'created_to', '2025-03-24 10:10:12'),
(10, 'finance', 6, '0', 4, 'created_from', '2025-03-24 10:10:12'),
(11, 'order', 10, 'shipment', 5, 'created_from', '2025-03-28 00:42:08'),
(12, 'order', 10, 'finance', 8, 'created_to', '2025-03-28 00:43:16'),
(13, 'finance', 8, 'order', 10, 'created_from', '2025-03-28 00:43:16'),
(14, 'order', 10, 'shipment', 6, 'created_from', '2025-04-05 17:16:39'),
(15, 'order', 10, 'shipment', 7, 'created_from', '2025-04-05 17:21:04');

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_ReturnHeader`
--

CREATE TABLE `PCRM_ReturnHeader` (
  `id` int NOT NULL,
  `return_number` varchar(50) NOT NULL,
  `return_date` datetime NOT NULL,
  `order_id` int DEFAULT NULL,
  `warehouse_id` int NOT NULL,
  `loader_id` int DEFAULT NULL,
  `reason` enum('Брак','Лишнее','Не соответствует ожиданиям','Перепутал','Другое') NOT NULL,
  `notes` text,
  `status` varchar(20) DEFAULT 'new',
  `conducted` tinyint DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `PCRM_ReturnHeader`
--

INSERT INTO `PCRM_ReturnHeader` (`id`, `return_number`, `return_date`, `order_id`, `warehouse_id`, `loader_id`, `reason`, `notes`, `status`, `conducted`, `created_by`, `created_at`, `updated_at`) VALUES
(4, 'RET-000001', '2025-03-24 10:10:00', 9, 1, 1, 'Брак', '', '0', 1, 1, '2025-03-24 10:10:12', '2025-03-24 10:10:12');

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_ReturnItem`
--

CREATE TABLE `PCRM_ReturnItem` (
  `id` int NOT NULL,
  `return_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` decimal(10,3) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT '0.00',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `PCRM_ReturnItem`
--

INSERT INTO `PCRM_ReturnItem` (`id`, `return_id`, `product_id`, `quantity`, `price`, `discount`, `created_at`, `updated_at`) VALUES
(4, 4, 1, '1.000', '100.00', '0.00', '2025-03-24 10:10:12', '2025-03-24 10:10:12');

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_ShipmentHeader`
--

CREATE TABLE `PCRM_ShipmentHeader` (
  `id` int NOT NULL,
  `shipment_number` varchar(50) NOT NULL,
  `shipment_date` datetime NOT NULL,
  `order_id` int DEFAULT NULL,
  `warehouse_id` int NOT NULL,
  `loader_id` int DEFAULT NULL,
  `status` varchar(20) DEFAULT 'new',
  `conducted` tinyint NOT NULL DEFAULT '0',
  `created_by` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `comment` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `PCRM_ShipmentHeader`
--

INSERT INTO `PCRM_ShipmentHeader` (`id`, `shipment_number`, `shipment_date`, `order_id`, `warehouse_id`, `loader_id`, `status`, `conducted`, `created_by`, `created_at`, `comment`) VALUES
(4, 'SH-000001', '2025-03-24 04:38:00', 9, 1, 1, '0', 1, 1, '2025-03-24 04:39:06', ''),
(5, 'SH-000005', '2025-03-28 00:41:00', 10, 1, 1, '0', 1, 1, '2025-03-28 00:42:08', ''),
(6, 'SH-000006', '2025-04-05 17:16:00', 10, 1, NULL, '0', 0, 1, '2025-04-05 17:16:39', ''),
(7, 'SH-000006', '2025-04-05 17:21:00', 10, 1, 1, '0', 0, 1, '2025-04-05 17:21:04', '');

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_Shipments`
--

CREATE TABLE `PCRM_Shipments` (
  `id` int NOT NULL,
  `shipment_header_id` int DEFAULT NULL,
  `product_id` int NOT NULL,
  `quantity` decimal(10,3) NOT NULL DEFAULT '0.000',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `discount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `conducted` tinyint NOT NULL DEFAULT '0',
  `unloaded_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `PCRM_Shipments`
--

INSERT INTO `PCRM_Shipments` (`id`, `shipment_header_id`, `product_id`, `quantity`, `price`, `discount`, `conducted`, `unloaded_by`, `created_at`, `updated_at`) VALUES
(4, 4, 1, '1.000', '100.00', '0.00', 0, NULL, '2025-03-24 04:39:06', '2025-03-24 04:39:06'),
(5, 5, 1, '1.000', '100.00', '0.00', 0, NULL, '2025-03-28 00:42:08', '2025-03-28 00:42:08'),
(6, 5, 1, '1.000', '100.00', '0.00', 0, NULL, '2025-03-28 00:42:08', '2025-03-28 00:42:08'),
(7, 6, 1, '1.000', '100.00', '0.00', 0, NULL, '2025-04-05 17:16:39', '2025-04-05 17:16:39'),
(8, 6, 1, '1.000', '100.00', '0.00', 0, NULL, '2025-04-05 17:16:39', '2025-04-05 17:16:39'),
(9, 7, 1, '1.000', '100.00', '0.00', 0, NULL, '2025-04-05 17:21:04', '2025-04-05 17:21:04'),
(10, 7, 1, '1.000', '100.00', '0.00', 0, NULL, '2025-04-05 17:21:04', '2025-04-05 17:21:04');

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_Stock`
--

CREATE TABLE `PCRM_Stock` (
  `id` int NOT NULL,
  `prod_id` int NOT NULL,
  `warehouse` int NOT NULL,
  `quantity` decimal(10,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `PCRM_Stock`
--

INSERT INTO `PCRM_Stock` (`id`, `prod_id`, `warehouse`, `quantity`) VALUES
(1, 1, 1, '15.00');

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_SupplierReturnHeader`
--

CREATE TABLE `PCRM_SupplierReturnHeader` (
  `id` int NOT NULL,
  `return_number` varchar(50) NOT NULL,
  `return_date` datetime NOT NULL,
  `purchase_order_id` int DEFAULT NULL,
  `warehouse_id` int NOT NULL,
  `loader_id` int DEFAULT NULL,
  `reason` enum('Брак','Лишнее','Не соответствует ожиданиям','Перепутал','Другое') NOT NULL,
  `notes` text,
  `status` varchar(20) DEFAULT 'new',
  `conducted` tinyint DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_SupplierReturnItem`
--

CREATE TABLE `PCRM_SupplierReturnItem` (
  `id` int NOT NULL,
  `return_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` decimal(10,3) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT '0.00',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_TrainingMaterial`
--

CREATE TABLE `PCRM_TrainingMaterial` (
  `id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text,
  `attachment` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `author` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_Transfers`
--

CREATE TABLE `PCRM_Transfers` (
  `id` int NOT NULL,
  `type` varchar(50) NOT NULL,
  `product` int NOT NULL,
  `warehouse_from` int NOT NULL,
  `warehouse_to` int NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `date` datetime NOT NULL,
  `user_id` int NOT NULL,
  `comment` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_User`
--

CREATE TABLE `PCRM_User` (
  `id` int NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `PCRM_User`
--

INSERT INTO `PCRM_User` (`id`, `username`, `email`, `password`, `first_name`, `last_name`, `phone`, `role`, `status`) VALUES
(1, 'admin', 'admin@prorab82.ru', '$2y$10$C0pyThiSHaSh$', NULL, NULL, NULL, 'admin', 'active'),
(2, 'admin', 'admin@prorab82.ru', '$2y$10$dBp10USL29b29ZeIG1fKwOhrJRLKUPvzAkG10N14mECZkFB6boH36', NULL, NULL, NULL, 'admin', 'active');

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_UserSession`
--

CREATE TABLE `PCRM_UserSession` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `last_activity` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `data` longtext,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_Warehouse`
--

CREATE TABLE `PCRM_Warehouse` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `location` varchar(255) NOT NULL,
  `status` varchar(50) DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `PCRM_Warehouse`
--

INSERT INTO `PCRM_Warehouse` (`id`, `name`, `location`, `status`) VALUES
(1, 'Кубанская основной', 'Кубанская, 21Б', 'active');

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `PCRM_Adjustments`
--
ALTER TABLE `PCRM_Adjustments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product` (`product`),
  ADD KEY `warehouse_id` (`warehouse_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `PCRM_AnalyticsDashboard`
--
ALTER TABLE `PCRM_AnalyticsDashboard`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Индексы таблицы `PCRM_CashRegister`
--
ALTER TABLE `PCRM_CashRegister`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `PCRM_Categories`
--
ALTER TABLE `PCRM_Categories`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `PCRM_Counterparty`
--
ALTER TABLE `PCRM_Counterparty`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `PCRM_DocumentRelation`
--
ALTER TABLE `PCRM_DocumentRelation`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `PCRM_Drivers`
--
ALTER TABLE `PCRM_Drivers`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `PCRM_FinancialTransaction`
--
ALTER TABLE `PCRM_FinancialTransaction`
  ADD PRIMARY KEY (`id`),
  ADD KEY `counterparty_id` (`counterparty_id`),
  ADD KEY `cash_register_id` (`cash_register_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `PCRM_FormState`
--
ALTER TABLE `PCRM_FormState`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`state_key`);

--
-- Индексы таблицы `PCRM_InboundOperations`
--
ALTER TABLE `PCRM_InboundOperations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product` (`product`),
  ADD KEY `warehouse_to` (`warehouse_to`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `PCRM_InboundReturns`
--
ALTER TABLE `PCRM_InboundReturns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product` (`product`),
  ADD KEY `warehouse_to` (`warehouse_to`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `PCRM_Loaders`
--
ALTER TABLE `PCRM_Loaders`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `PCRM_Order`
--
ALTER TABLE `PCRM_Order`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer` (`customer`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `driver` (`driver`),
  ADD KEY `warehouse` (`warehouse`);

--
-- Индексы таблицы `PCRM_OrderHistory`
--
ALTER TABLE `PCRM_OrderHistory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Индексы таблицы `PCRM_OrderItem`
--
ALTER TABLE `PCRM_OrderItem`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_idx` (`order_id`),
  ADD KEY `product_idx` (`product_id`);

--
-- Индексы таблицы `PCRM_Organization`
--
ALTER TABLE `PCRM_Organization`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `PCRM_OutboundOperations`
--
ALTER TABLE `PCRM_OutboundOperations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product` (`product`),
  ADD KEY `warehouse_from` (`warehouse_from`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `PCRM_OutboundReturns`
--
ALTER TABLE `PCRM_OutboundReturns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product` (`product`),
  ADD KEY `warehouse_from` (`warehouse_from`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `PCRM_PaymentMethodDetails`
--
ALTER TABLE `PCRM_PaymentMethodDetails`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaction_id` (`transaction_id`);

--
-- Индексы таблицы `PCRM_Product`
--
ALTER TABLE `PCRM_Product`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `category` (`category`),
  ADD KEY `subcategory` (`subcategory`);

--
-- Индексы таблицы `PCRM_ProductImages`
--
ALTER TABLE `PCRM_ProductImages`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `PCRM_ProductionOperation`
--
ALTER TABLE `PCRM_ProductionOperation`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `warehouse_id` (`warehouse_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Индексы таблицы `PCRM_ProductionOperationItem`
--
ALTER TABLE `PCRM_ProductionOperationItem`
  ADD PRIMARY KEY (`id`),
  ADD KEY `operation_id` (`operation_id`),
  ADD KEY `ingredient_id` (`ingredient_id`);

--
-- Индексы таблицы `PCRM_ProductionOrder`
--
ALTER TABLE `PCRM_ProductionOrder`
  ADD PRIMARY KEY (`id`),
  ADD KEY `recipe_id` (`recipe_id`),
  ADD KEY `warehouse_id` (`warehouse_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Индексы таблицы `PCRM_ProductionRecipe`
--
ALTER TABLE `PCRM_ProductionRecipe`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Индексы таблицы `PCRM_ProductionRecipeItem`
--
ALTER TABLE `PCRM_ProductionRecipeItem`
  ADD PRIMARY KEY (`id`),
  ADD KEY `recipe_id` (`recipe_id`),
  ADD KEY `ingredient_id` (`ingredient_id`);

--
-- Индексы таблицы `PCRM_PurchaseOrder`
--
ALTER TABLE `PCRM_PurchaseOrder`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `PCRM_PurchaseOrderItem`
--
ALTER TABLE `PCRM_PurchaseOrderItem`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_order_id` (`purchase_order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Индексы таблицы `PCRM_ReceiptHeader`
--
ALTER TABLE `PCRM_ReceiptHeader`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_order_id` (`purchase_order_id`),
  ADD KEY `warehouse_id` (`warehouse_id`);

--
-- Индексы таблицы `PCRM_ReceiptItem`
--
ALTER TABLE `PCRM_ReceiptItem`
  ADD PRIMARY KEY (`id`),
  ADD KEY `receipt_header_id` (`receipt_header_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `unloaded_by` (`unloaded_by`);

--
-- Индексы таблицы `PCRM_RelatedDocuments`
--
ALTER TABLE `PCRM_RelatedDocuments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_relation` (`source_type`,`source_id`,`related_type`,`related_id`);

--
-- Индексы таблицы `PCRM_ReturnHeader`
--
ALTER TABLE `PCRM_ReturnHeader`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `warehouse_id` (`warehouse_id`),
  ADD KEY `loader_id` (`loader_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Индексы таблицы `PCRM_ReturnItem`
--
ALTER TABLE `PCRM_ReturnItem`
  ADD PRIMARY KEY (`id`),
  ADD KEY `return_id` (`return_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Индексы таблицы `PCRM_ShipmentHeader`
--
ALTER TABLE `PCRM_ShipmentHeader`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `warehouse_id` (`warehouse_id`);

--
-- Индексы таблицы `PCRM_Shipments`
--
ALTER TABLE `PCRM_Shipments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `unloaded_by` (`unloaded_by`),
  ADD KEY `shipment_header_id` (`shipment_header_id`);

--
-- Индексы таблицы `PCRM_Stock`
--
ALTER TABLE `PCRM_Stock`
  ADD PRIMARY KEY (`id`),
  ADD KEY `prod_id` (`prod_id`),
  ADD KEY `warehouse` (`warehouse`);

--
-- Индексы таблицы `PCRM_SupplierReturnHeader`
--
ALTER TABLE `PCRM_SupplierReturnHeader`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_order_id` (`purchase_order_id`),
  ADD KEY `warehouse_id` (`warehouse_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Индексы таблицы `PCRM_SupplierReturnItem`
--
ALTER TABLE `PCRM_SupplierReturnItem`
  ADD PRIMARY KEY (`id`),
  ADD KEY `return_id` (`return_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Индексы таблицы `PCRM_TrainingMaterial`
--
ALTER TABLE `PCRM_TrainingMaterial`
  ADD PRIMARY KEY (`id`),
  ADD KEY `author` (`author`);

--
-- Индексы таблицы `PCRM_Transfers`
--
ALTER TABLE `PCRM_Transfers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product` (`product`),
  ADD KEY `warehouse_from` (`warehouse_from`),
  ADD KEY `warehouse_to` (`warehouse_to`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `PCRM_User`
--
ALTER TABLE `PCRM_User`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `PCRM_UserSession`
--
ALTER TABLE `PCRM_UserSession`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`session_id`);

--
-- Индексы таблицы `PCRM_Warehouse`
--
ALTER TABLE `PCRM_Warehouse`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `PCRM_Adjustments`
--
ALTER TABLE `PCRM_Adjustments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_AnalyticsDashboard`
--
ALTER TABLE `PCRM_AnalyticsDashboard`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_CashRegister`
--
ALTER TABLE `PCRM_CashRegister`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `PCRM_Categories`
--
ALTER TABLE `PCRM_Categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT для таблицы `PCRM_Counterparty`
--
ALTER TABLE `PCRM_Counterparty`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `PCRM_DocumentRelation`
--
ALTER TABLE `PCRM_DocumentRelation`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_Drivers`
--
ALTER TABLE `PCRM_Drivers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `PCRM_FinancialTransaction`
--
ALTER TABLE `PCRM_FinancialTransaction`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT для таблицы `PCRM_FormState`
--
ALTER TABLE `PCRM_FormState`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12378;

--
-- AUTO_INCREMENT для таблицы `PCRM_InboundOperations`
--
ALTER TABLE `PCRM_InboundOperations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_InboundReturns`
--
ALTER TABLE `PCRM_InboundReturns`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_Loaders`
--
ALTER TABLE `PCRM_Loaders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `PCRM_Order`
--
ALTER TABLE `PCRM_Order`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT для таблицы `PCRM_OrderHistory`
--
ALTER TABLE `PCRM_OrderHistory`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `PCRM_OrderItem`
--
ALTER TABLE `PCRM_OrderItem`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT для таблицы `PCRM_Organization`
--
ALTER TABLE `PCRM_Organization`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `PCRM_OutboundOperations`
--
ALTER TABLE `PCRM_OutboundOperations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_OutboundReturns`
--
ALTER TABLE `PCRM_OutboundReturns`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_PaymentMethodDetails`
--
ALTER TABLE `PCRM_PaymentMethodDetails`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `PCRM_Product`
--
ALTER TABLE `PCRM_Product`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `PCRM_ProductImages`
--
ALTER TABLE `PCRM_ProductImages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_ProductionOperation`
--
ALTER TABLE `PCRM_ProductionOperation`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_ProductionOperationItem`
--
ALTER TABLE `PCRM_ProductionOperationItem`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_ProductionOrder`
--
ALTER TABLE `PCRM_ProductionOrder`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_ProductionRecipe`
--
ALTER TABLE `PCRM_ProductionRecipe`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_ProductionRecipeItem`
--
ALTER TABLE `PCRM_ProductionRecipeItem`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_PurchaseOrder`
--
ALTER TABLE `PCRM_PurchaseOrder`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_PurchaseOrderItem`
--
ALTER TABLE `PCRM_PurchaseOrderItem`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_ReceiptHeader`
--
ALTER TABLE `PCRM_ReceiptHeader`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_ReceiptItem`
--
ALTER TABLE `PCRM_ReceiptItem`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_RelatedDocuments`
--
ALTER TABLE `PCRM_RelatedDocuments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT для таблицы `PCRM_ReturnHeader`
--
ALTER TABLE `PCRM_ReturnHeader`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `PCRM_ReturnItem`
--
ALTER TABLE `PCRM_ReturnItem`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `PCRM_ShipmentHeader`
--
ALTER TABLE `PCRM_ShipmentHeader`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT для таблицы `PCRM_Shipments`
--
ALTER TABLE `PCRM_Shipments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT для таблицы `PCRM_Stock`
--
ALTER TABLE `PCRM_Stock`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `PCRM_SupplierReturnHeader`
--
ALTER TABLE `PCRM_SupplierReturnHeader`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_SupplierReturnItem`
--
ALTER TABLE `PCRM_SupplierReturnItem`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_TrainingMaterial`
--
ALTER TABLE `PCRM_TrainingMaterial`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_Transfers`
--
ALTER TABLE `PCRM_Transfers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_User`
--
ALTER TABLE `PCRM_User`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `PCRM_UserSession`
--
ALTER TABLE `PCRM_UserSession`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_Warehouse`
--
ALTER TABLE `PCRM_Warehouse`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `PCRM_Adjustments`
--
ALTER TABLE `PCRM_Adjustments`
  ADD CONSTRAINT `PCRM_Adjustments_ibfk_1` FOREIGN KEY (`product`) REFERENCES `PCRM_Product` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `PCRM_Adjustments_ibfk_2` FOREIGN KEY (`warehouse_id`) REFERENCES `PCRM_Warehouse` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `PCRM_Adjustments_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `PCRM_User` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `PCRM_AnalyticsDashboard`
--
ALTER TABLE `PCRM_AnalyticsDashboard`
  ADD CONSTRAINT `PCRM_AnalyticsDashboard_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `PCRM_User` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `PCRM_FinancialTransaction`
--
ALTER TABLE `PCRM_FinancialTransaction`
  ADD CONSTRAINT `PCRM_FinancialTransaction_ibfk_1` FOREIGN KEY (`counterparty_id`) REFERENCES `PCRM_Counterparty` (`id`),
  ADD CONSTRAINT `PCRM_FinancialTransaction_ibfk_2` FOREIGN KEY (`cash_register_id`) REFERENCES `PCRM_CashRegister` (`id`),
  ADD CONSTRAINT `PCRM_FinancialTransaction_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `PCRM_User` (`id`);

--
-- Ограничения внешнего ключа таблицы `PCRM_InboundOperations`
--
ALTER TABLE `PCRM_InboundOperations`
  ADD CONSTRAINT `PCRM_InboundOperations_ibfk_1` FOREIGN KEY (`product`) REFERENCES `PCRM_Product` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `PCRM_InboundOperations_ibfk_2` FOREIGN KEY (`warehouse_to`) REFERENCES `PCRM_Warehouse` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `PCRM_InboundOperations_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `PCRM_User` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `PCRM_InboundReturns`
--
ALTER TABLE `PCRM_InboundReturns`
  ADD CONSTRAINT `PCRM_InboundReturns_ibfk_1` FOREIGN KEY (`product`) REFERENCES `PCRM_Product` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `PCRM_InboundReturns_ibfk_2` FOREIGN KEY (`warehouse_to`) REFERENCES `PCRM_Warehouse` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `PCRM_InboundReturns_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `PCRM_User` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `PCRM_Order`
--
ALTER TABLE `PCRM_Order`
  ADD CONSTRAINT `PCRM_Order_ibfk_1` FOREIGN KEY (`customer`) REFERENCES `PCRM_Counterparty` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `PCRM_Order_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `PCRM_User` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `PCRM_Order_ibfk_3` FOREIGN KEY (`driver`) REFERENCES `PCRM_Drivers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `PCRM_Order_ibfk_4` FOREIGN KEY (`warehouse`) REFERENCES `PCRM_Warehouse` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `PCRM_OrderHistory`
--
ALTER TABLE `PCRM_OrderHistory`
  ADD CONSTRAINT `PCRM_OrderHistory_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `PCRM_Order` (`id`);

--
-- Ограничения внешнего ключа таблицы `PCRM_OutboundOperations`
--
ALTER TABLE `PCRM_OutboundOperations`
  ADD CONSTRAINT `PCRM_OutboundOperations_ibfk_1` FOREIGN KEY (`product`) REFERENCES `PCRM_Product` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `PCRM_OutboundOperations_ibfk_2` FOREIGN KEY (`warehouse_from`) REFERENCES `PCRM_Warehouse` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `PCRM_OutboundOperations_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `PCRM_User` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `PCRM_OutboundReturns`
--
ALTER TABLE `PCRM_OutboundReturns`
  ADD CONSTRAINT `PCRM_OutboundReturns_ibfk_1` FOREIGN KEY (`product`) REFERENCES `PCRM_Product` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `PCRM_OutboundReturns_ibfk_2` FOREIGN KEY (`warehouse_from`) REFERENCES `PCRM_Warehouse` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `PCRM_OutboundReturns_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `PCRM_User` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `PCRM_PaymentMethodDetails`
--
ALTER TABLE `PCRM_PaymentMethodDetails`
  ADD CONSTRAINT `PCRM_PaymentMethodDetails_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `PCRM_FinancialTransaction` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `PCRM_Product`
--
ALTER TABLE `PCRM_Product`
  ADD CONSTRAINT `PCRM_Product_ibfk_1` FOREIGN KEY (`category`) REFERENCES `PCRM_Categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `PCRM_Product_ibfk_2` FOREIGN KEY (`subcategory`) REFERENCES `PCRM_Categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `PCRM_ProductionOperation`
--
ALTER TABLE `PCRM_ProductionOperation`
  ADD CONSTRAINT `PCRM_ProductionOperation_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `PCRM_ProductionOrder` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `PCRM_ProductionOperation_ibfk_2` FOREIGN KEY (`warehouse_id`) REFERENCES `PCRM_Warehouse` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `PCRM_ProductionOperation_ibfk_3` FOREIGN KEY (`product_id`) REFERENCES `PCRM_Product` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `PCRM_ProductionOperation_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `PCRM_User` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `PCRM_ProductionOperationItem`
--
ALTER TABLE `PCRM_ProductionOperationItem`
  ADD CONSTRAINT `PCRM_ProductionOperationItem_ibfk_1` FOREIGN KEY (`operation_id`) REFERENCES `PCRM_ProductionOperation` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `PCRM_ProductionOperationItem_ibfk_2` FOREIGN KEY (`ingredient_id`) REFERENCES `PCRM_Product` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `PCRM_ProductionOrder`
--
ALTER TABLE `PCRM_ProductionOrder`
  ADD CONSTRAINT `PCRM_ProductionOrder_ibfk_1` FOREIGN KEY (`recipe_id`) REFERENCES `PCRM_ProductionRecipe` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `PCRM_ProductionOrder_ibfk_2` FOREIGN KEY (`warehouse_id`) REFERENCES `PCRM_Warehouse` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `PCRM_ProductionOrder_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `PCRM_User` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `PCRM_ProductionRecipe`
--
ALTER TABLE `PCRM_ProductionRecipe`
  ADD CONSTRAINT `PCRM_ProductionRecipe_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `PCRM_Product` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `PCRM_ProductionRecipe_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `PCRM_User` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `PCRM_ProductionRecipeItem`
--
ALTER TABLE `PCRM_ProductionRecipeItem`
  ADD CONSTRAINT `PCRM_ProductionRecipeItem_ibfk_1` FOREIGN KEY (`recipe_id`) REFERENCES `PCRM_ProductionRecipe` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `PCRM_ProductionRecipeItem_ibfk_2` FOREIGN KEY (`ingredient_id`) REFERENCES `PCRM_Product` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `PCRM_PurchaseOrderItem`
--
ALTER TABLE `PCRM_PurchaseOrderItem`
  ADD CONSTRAINT `PCRM_PurchaseOrderItem_ibfk_1` FOREIGN KEY (`purchase_order_id`) REFERENCES `PCRM_PurchaseOrder` (`id`),
  ADD CONSTRAINT `PCRM_PurchaseOrderItem_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `PCRM_Product` (`id`);

--
-- Ограничения внешнего ключа таблицы `PCRM_ReceiptHeader`
--
ALTER TABLE `PCRM_ReceiptHeader`
  ADD CONSTRAINT `PCRM_ReceiptHeader_ibfk_1` FOREIGN KEY (`purchase_order_id`) REFERENCES `PCRM_PurchaseOrder` (`id`),
  ADD CONSTRAINT `PCRM_ReceiptHeader_ibfk_2` FOREIGN KEY (`warehouse_id`) REFERENCES `PCRM_Warehouse` (`id`);

--
-- Ограничения внешнего ключа таблицы `PCRM_ReceiptItem`
--
ALTER TABLE `PCRM_ReceiptItem`
  ADD CONSTRAINT `PCRM_ReceiptItem_ibfk_1` FOREIGN KEY (`receipt_header_id`) REFERENCES `PCRM_ReceiptHeader` (`id`),
  ADD CONSTRAINT `PCRM_ReceiptItem_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `PCRM_Product` (`id`);

--
-- Ограничения внешнего ключа таблицы `PCRM_ReturnHeader`
--
ALTER TABLE `PCRM_ReturnHeader`
  ADD CONSTRAINT `PCRM_ReturnHeader_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `PCRM_Order` (`id`),
  ADD CONSTRAINT `PCRM_ReturnHeader_ibfk_2` FOREIGN KEY (`warehouse_id`) REFERENCES `PCRM_Warehouse` (`id`),
  ADD CONSTRAINT `PCRM_ReturnHeader_ibfk_3` FOREIGN KEY (`loader_id`) REFERENCES `PCRM_Loaders` (`id`),
  ADD CONSTRAINT `PCRM_ReturnHeader_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `PCRM_User` (`id`);

--
-- Ограничения внешнего ключа таблицы `PCRM_ReturnItem`
--
ALTER TABLE `PCRM_ReturnItem`
  ADD CONSTRAINT `PCRM_ReturnItem_ibfk_1` FOREIGN KEY (`return_id`) REFERENCES `PCRM_ReturnHeader` (`id`),
  ADD CONSTRAINT `PCRM_ReturnItem_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `PCRM_Product` (`id`);

--
-- Ограничения внешнего ключа таблицы `PCRM_ShipmentHeader`
--
ALTER TABLE `PCRM_ShipmentHeader`
  ADD CONSTRAINT `PCRM_ShipmentHeader_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `PCRM_Order` (`id`),
  ADD CONSTRAINT `PCRM_ShipmentHeader_ibfk_2` FOREIGN KEY (`warehouse_id`) REFERENCES `PCRM_Warehouse` (`id`);

--
-- Ограничения внешнего ключа таблицы `PCRM_Shipments`
--
ALTER TABLE `PCRM_Shipments`
  ADD CONSTRAINT `PCRM_Shipments_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `PCRM_Product` (`id`),
  ADD CONSTRAINT `PCRM_Shipments_ibfk_3` FOREIGN KEY (`unloaded_by`) REFERENCES `PCRM_Loaders` (`id`),
  ADD CONSTRAINT `PCRM_Shipments_ibfk_4` FOREIGN KEY (`shipment_header_id`) REFERENCES `PCRM_ShipmentHeader` (`id`);

--
-- Ограничения внешнего ключа таблицы `PCRM_Stock`
--
ALTER TABLE `PCRM_Stock`
  ADD CONSTRAINT `PCRM_Stock_ibfk_1` FOREIGN KEY (`prod_id`) REFERENCES `PCRM_Product` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `PCRM_Stock_ibfk_2` FOREIGN KEY (`warehouse`) REFERENCES `PCRM_Warehouse` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `PCRM_SupplierReturnHeader`
--
ALTER TABLE `PCRM_SupplierReturnHeader`
  ADD CONSTRAINT `PCRM_SupplierReturnHeader_ibfk_1` FOREIGN KEY (`purchase_order_id`) REFERENCES `PCRM_PurchaseOrder` (`id`),
  ADD CONSTRAINT `PCRM_SupplierReturnHeader_ibfk_2` FOREIGN KEY (`warehouse_id`) REFERENCES `PCRM_Warehouse` (`id`);

--
-- Ограничения внешнего ключа таблицы `PCRM_SupplierReturnItem`
--
ALTER TABLE `PCRM_SupplierReturnItem`
  ADD CONSTRAINT `PCRM_SupplierReturnItem_ibfk_1` FOREIGN KEY (`return_id`) REFERENCES `PCRM_SupplierReturnHeader` (`id`),
  ADD CONSTRAINT `PCRM_SupplierReturnItem_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `PCRM_Product` (`id`);

--
-- Ограничения внешнего ключа таблицы `PCRM_TrainingMaterial`
--
ALTER TABLE `PCRM_TrainingMaterial`
  ADD CONSTRAINT `PCRM_TrainingMaterial_ibfk_1` FOREIGN KEY (`author`) REFERENCES `PCRM_User` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `PCRM_Transfers`
--
ALTER TABLE `PCRM_Transfers`
  ADD CONSTRAINT `PCRM_Transfers_ibfk_1` FOREIGN KEY (`product`) REFERENCES `PCRM_Product` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `PCRM_Transfers_ibfk_2` FOREIGN KEY (`warehouse_from`) REFERENCES `PCRM_Warehouse` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `PCRM_Transfers_ibfk_3` FOREIGN KEY (`warehouse_to`) REFERENCES `PCRM_Warehouse` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `PCRM_Transfers_ibfk_4` FOREIGN KEY (`user_id`) REFERENCES `PCRM_User` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
