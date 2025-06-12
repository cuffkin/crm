-- phpMyAdmin SQL Dump
-- version 5.1.1deb5ubuntu1
-- https://www.phpmyadmin.net/
--
-- Хост: localhost
-- Время создания: Июн 12 2025 г., 07:06
-- Версия сервера: 8.0.42-0ubuntu0.22.04.1
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
-- Структура таблицы `PCRM_AccessRights`
--

CREATE TABLE `PCRM_AccessRights` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
  `status` varchar(50) DEFAULT 'active',
  `deleted` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Помечен как удаленный'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
  `kpp` varchar(20) DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Помечен как удаленный'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
  `phone` varchar(50) DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Помечен как удаленный'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
  `description` text,
  `conducted` tinyint NOT NULL DEFAULT '0',
  `user_id` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expense_category` varchar(50) DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Помечен как удаленный'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
  `status` varchar(50) DEFAULT 'active' COMMENT 'active/inactive',
  `deleted` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Помечен как удаленный'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_Measurement`
--

CREATE TABLE `PCRM_Measurement` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `short_name` varchar(20) NOT NULL,
  `description` text,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_Order`
--

CREATE TABLE `PCRM_Order` (
  `id` int NOT NULL,
  `organization` int DEFAULT NULL,
  `order_number` varchar(50) NOT NULL,
  `customer` int DEFAULT NULL,
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
  `driver_id` int DEFAULT NULL,
  `contacts` varchar(255) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
  `status` varchar(50) DEFAULT 'active',
  `default_measurement_id` int DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Помечен как удаленный'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Помечен как удаленный'
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
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Помечен как удаленный'
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
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Помечен как удаленный'
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
-- Структура таблицы `PCRM_Product_Measurement`
--

CREATE TABLE `PCRM_Product_Measurement` (
  `id` int NOT NULL,
  `product_id` int NOT NULL,
  `measurement_id` int NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `conversion_factor` decimal(10,4) DEFAULT '1.0000'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_PurchaseOrder`
--

CREATE TABLE `PCRM_PurchaseOrder` (
  `id` int NOT NULL,
  `organization` int DEFAULT NULL,
  `order_num` varchar(50) DEFAULT NULL,
  `supplier_id` int NOT NULL,
  `warehouse_id` int DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  `delivery_address` varchar(255) DEFAULT NULL,
  `comment` text,
  `status` varchar(50) DEFAULT 'draft',
  `total_amount` decimal(10,2) DEFAULT '0.00',
  `purchase_order_number` varchar(50) DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `deleted` tinyint DEFAULT '0',
  `conducted` tinyint DEFAULT '0'
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
  `comment` text,
  `deleted` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Помечен как удаленный'
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
-- Структура таблицы `PCRM_RecentProducts`
--

CREATE TABLE `PCRM_RecentProducts` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `product_id` int NOT NULL,
  `context` varchar(50) NOT NULL DEFAULT 'sale',
  `last_used` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Помечен как удаленный'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_Settings`
--

CREATE TABLE `PCRM_Settings` (
  `id` int NOT NULL,
  `key` varchar(100) NOT NULL,
  `value` text,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
  `comment` text,
  `deleted` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Помечен как удаленный'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_ShipmentItem`
--

CREATE TABLE `PCRM_ShipmentItem` (
  `id` int NOT NULL,
  `shipment_header_id` int NOT NULL COMMENT 'ID документа отгрузки',
  `product_id` int NOT NULL COMMENT 'ID товара',
  `quantity` decimal(10,3) NOT NULL DEFAULT '0.000' COMMENT 'Количество',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Цена',
  `discount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Скидка',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Позиции документов отгрузки';

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_Stock`
--

CREATE TABLE `PCRM_Stock` (
  `id` int NOT NULL,
  `prod_id` int NOT NULL,
  `warehouse` int NOT NULL,
  `quantity` decimal(10,2) DEFAULT '0.00',
  `deleted` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Помечен как удаленный'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Помечен как удаленный'
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
-- Структура таблицы `PCRM_TrashItems`
--

CREATE TABLE `PCRM_TrashItems` (
  `id` int NOT NULL,
  `item_type` enum('document','reference') NOT NULL COMMENT 'Тип элемента: документ или справочник',
  `document_type` varchar(50) NOT NULL COMMENT 'Тип документа/справочника',
  `document_id` int NOT NULL COMMENT 'ID удаленного элемента',
  `document_data` json DEFAULT NULL COMMENT 'Сохраненные данные для восстановления',
  `related_data` json DEFAULT NULL COMMENT 'Связанные данные (позиции, детали)',
  `original_name` varchar(255) DEFAULT NULL COMMENT 'Название/номер для отображения',
  `deleted_by` int NOT NULL COMMENT 'ID пользователя, который удалил',
  `deleted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата удаления',
  `reason` varchar(255) DEFAULT NULL COMMENT 'Причина удаления',
  `can_restore` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Можно ли восстановить',
  `auto_delete_at` datetime NOT NULL COMMENT 'Дата автоматического удаления (через месяц)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Корзина для удаленных элементов';

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_TrashLog`
--

CREATE TABLE `PCRM_TrashLog` (
  `id` int NOT NULL,
  `trash_item_id` int DEFAULT NULL COMMENT 'ID элемента в корзине',
  `action` enum('moved_to_trash','restored','permanently_deleted','auto_deleted') NOT NULL COMMENT 'Действие',
  `document_type` varchar(50) NOT NULL COMMENT 'Тип документа',
  `document_id` int NOT NULL COMMENT 'ID документа',
  `user_id` int NOT NULL COMMENT 'ID пользователя',
  `details` json DEFAULT NULL COMMENT 'Дополнительные детали',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Лог операций с корзиной';

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

-- --------------------------------------------------------

--
-- Структура таблицы `PCRM_UserAccessRights`
--

CREATE TABLE `PCRM_UserAccessRights` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `access_right_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
  `status` varchar(50) DEFAULT 'active',
  `deleted` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Помечен как удаленный'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `PCRM_AccessRights`
--
ALTER TABLE `PCRM_AccessRights`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

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
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_deleted` (`deleted`);

--
-- Индексы таблицы `PCRM_Counterparty`
--
ALTER TABLE `PCRM_Counterparty`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_deleted` (`deleted`);

--
-- Индексы таблицы `PCRM_DocumentRelation`
--
ALTER TABLE `PCRM_DocumentRelation`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `PCRM_Drivers`
--
ALTER TABLE `PCRM_Drivers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_deleted` (`deleted`);

--
-- Индексы таблицы `PCRM_FinancialTransaction`
--
ALTER TABLE `PCRM_FinancialTransaction`
  ADD PRIMARY KEY (`id`),
  ADD KEY `counterparty_id` (`counterparty_id`),
  ADD KEY `cash_register_id` (`cash_register_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_deleted` (`deleted`);

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
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_deleted` (`deleted`);

--
-- Индексы таблицы `PCRM_Order`
--
ALTER TABLE `PCRM_Order`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer` (`customer`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `driver` (`driver`),
  ADD KEY `warehouse` (`warehouse`),
  ADD KEY `idx_deleted` (`deleted`);

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
  ADD KEY `subcategory` (`subcategory`),
  ADD KEY `idx_deleted` (`deleted`);

--
-- Индексы таблицы `PCRM_ProductImages`
--
ALTER TABLE `PCRM_ProductImages`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `PCRM_ProductionOperation`
--
ALTER TABLE `PCRM_ProductionOperation`
  ADD KEY `idx_deleted` (`deleted`);

--
-- Индексы таблицы `PCRM_ProductionOrder`
--
ALTER TABLE `PCRM_ProductionOrder`
  ADD KEY `idx_deleted` (`deleted`);

--
-- Индексы таблицы `PCRM_ProductionRecipe`
--
ALTER TABLE `PCRM_ProductionRecipe`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `status` (`status`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Индексы таблицы `PCRM_ProductionRecipeItem`
--
ALTER TABLE `PCRM_ProductionRecipeItem`
  ADD PRIMARY KEY (`id`),
  ADD KEY `recipe_id` (`recipe_id`),
  ADD KEY `ingredient_id` (`ingredient_id`),
  ADD KEY `idx_recipe_id` (`recipe_id`),
  ADD KEY `idx_ingredient_id` (`ingredient_id`);

--
-- Индексы таблицы `PCRM_PurchaseOrder`
--
ALTER TABLE `PCRM_PurchaseOrder`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_deleted` (`deleted`);

--
-- Индексы таблицы `PCRM_PurchaseOrderItem`
--
ALTER TABLE `PCRM_PurchaseOrderItem`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `PCRM_ReceiptHeader`
--
ALTER TABLE `PCRM_ReceiptHeader`
  ADD KEY `idx_deleted` (`deleted`);

--
-- Индексы таблицы `PCRM_RecentProducts`
--
ALTER TABLE `PCRM_RecentProducts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_product_context` (`user_id`,`product_id`,`context`),
  ADD KEY `idx_user_context_time` (`user_id`,`context`,`last_used`);

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
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_deleted` (`deleted`);

--
-- Индексы таблицы `PCRM_ReturnItem`
--
ALTER TABLE `PCRM_ReturnItem`
  ADD PRIMARY KEY (`id`),
  ADD KEY `return_id` (`return_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Индексы таблицы `PCRM_Settings`
--
ALTER TABLE `PCRM_Settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key` (`key`);

--
-- Индексы таблицы `PCRM_ShipmentHeader`
--
ALTER TABLE `PCRM_ShipmentHeader`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `warehouse_id` (`warehouse_id`),
  ADD KEY `idx_deleted` (`deleted`);

--
-- Индексы таблицы `PCRM_ShipmentItem`
--
ALTER TABLE `PCRM_ShipmentItem`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_shipment_header_id` (`shipment_header_id`),
  ADD KEY `idx_product_id` (`product_id`);

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
  ADD KEY `idx_deleted` (`deleted`);

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
-- Индексы таблицы `PCRM_TrashItems`
--
ALTER TABLE `PCRM_TrashItems`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_item_type` (`item_type`),
  ADD KEY `idx_document_type` (`document_type`),
  ADD KEY `idx_deleted_by` (`deleted_by`),
  ADD KEY `idx_auto_delete` (`auto_delete_at`);

--
-- Индексы таблицы `PCRM_TrashLog`
--
ALTER TABLE `PCRM_TrashLog`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_document_type` (`document_type`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Индексы таблицы `PCRM_User`
--
ALTER TABLE `PCRM_User`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `PCRM_UserAccessRights`
--
ALTER TABLE `PCRM_UserAccessRights`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_right` (`user_id`,`access_right_id`),
  ADD KEY `access_right_id` (`access_right_id`);

--
-- Индексы таблицы `PCRM_Warehouse`
--
ALTER TABLE `PCRM_Warehouse`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_deleted` (`deleted`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `PCRM_AccessRights`
--
ALTER TABLE `PCRM_AccessRights`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_Categories`
--
ALTER TABLE `PCRM_Categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_Counterparty`
--
ALTER TABLE `PCRM_Counterparty`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_DocumentRelation`
--
ALTER TABLE `PCRM_DocumentRelation`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_Drivers`
--
ALTER TABLE `PCRM_Drivers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_FinancialTransaction`
--
ALTER TABLE `PCRM_FinancialTransaction`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_FormState`
--
ALTER TABLE `PCRM_FormState`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_Order`
--
ALTER TABLE `PCRM_Order`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_OrderHistory`
--
ALTER TABLE `PCRM_OrderHistory`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_OrderItem`
--
ALTER TABLE `PCRM_OrderItem`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_Organization`
--
ALTER TABLE `PCRM_Organization`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_Product`
--
ALTER TABLE `PCRM_Product`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_ProductImages`
--
ALTER TABLE `PCRM_ProductImages`
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
-- AUTO_INCREMENT для таблицы `PCRM_RecentProducts`
--
ALTER TABLE `PCRM_RecentProducts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_RelatedDocuments`
--
ALTER TABLE `PCRM_RelatedDocuments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_ReturnHeader`
--
ALTER TABLE `PCRM_ReturnHeader`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_ReturnItem`
--
ALTER TABLE `PCRM_ReturnItem`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_Settings`
--
ALTER TABLE `PCRM_Settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_ShipmentHeader`
--
ALTER TABLE `PCRM_ShipmentHeader`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_ShipmentItem`
--
ALTER TABLE `PCRM_ShipmentItem`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_Stock`
--
ALTER TABLE `PCRM_Stock`
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
-- AUTO_INCREMENT для таблицы `PCRM_TrashItems`
--
ALTER TABLE `PCRM_TrashItems`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_TrashLog`
--
ALTER TABLE `PCRM_TrashLog`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_User`
--
ALTER TABLE `PCRM_User`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_UserAccessRights`
--
ALTER TABLE `PCRM_UserAccessRights`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `PCRM_Warehouse`
--
ALTER TABLE `PCRM_Warehouse`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

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
-- Ограничения внешнего ключа таблицы `PCRM_ShipmentItem`
--
ALTER TABLE `PCRM_ShipmentItem`
  ADD CONSTRAINT `fk_shipment_item_header` FOREIGN KEY (`shipment_header_id`) REFERENCES `PCRM_ShipmentHeader` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_shipment_item_product` FOREIGN KEY (`product_id`) REFERENCES `PCRM_Product` (`id`);

--
-- Ограничения внешнего ключа таблицы `PCRM_Stock`
--
ALTER TABLE `PCRM_Stock`
  ADD CONSTRAINT `PCRM_Stock_ibfk_1` FOREIGN KEY (`prod_id`) REFERENCES `PCRM_Product` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `PCRM_Stock_ibfk_2` FOREIGN KEY (`warehouse`) REFERENCES `PCRM_Warehouse` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

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

--
-- Ограничения внешнего ключа таблицы `PCRM_TrashItems`
--
ALTER TABLE `PCRM_TrashItems`
  ADD CONSTRAINT `PCRM_TrashItems_ibfk_1` FOREIGN KEY (`deleted_by`) REFERENCES `PCRM_User` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `PCRM_TrashLog`
--
ALTER TABLE `PCRM_TrashLog`
  ADD CONSTRAINT `PCRM_TrashLog_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `PCRM_User` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `PCRM_UserAccessRights`
--
ALTER TABLE `PCRM_UserAccessRights`
  ADD CONSTRAINT `PCRM_UserAccessRights_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `PCRM_User` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `PCRM_UserAccessRights_ibfk_2` FOREIGN KEY (`access_right_id`) REFERENCES `PCRM_AccessRights` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
