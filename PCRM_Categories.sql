-- phpMyAdmin SQL Dump
-- version 5.1.1deb5ubuntu1
-- https://www.phpmyadmin.net/
--
-- Хост: localhost
-- Время создания: Май 19 2025 г., 05:51
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
(26, 'Сыпучие', 'Сыпучие материалы (навалом)', 'Товарная категория', NULL, 'active'),
(27, 'Фасовка', 'Фасованные материалы (в мешках и др.)', 'Товарная категория', NULL, 'active'),
(28, 'Смеси', 'Строительные смеси', 'Товарная категория', NULL, 'active'),
(29, 'Утеплители', 'Теплоизоляционные материалы', 'Товарная категория', NULL, 'active'),
(30, 'Строительная химия', 'Химические составы для строительства', 'Товарная категория', NULL, 'active'),
(31, 'Металл', 'Металлопродукция для строительства', 'Товарная категория', NULL, 'active'),
(32, 'Песок', 'Песок речной, карьерный (навалом)', 'Товарная категория', 26, 'active'),
(33, 'Щебень', 'Щебень гранитный, известняковый (навалом)', 'Товарная категория', 26, 'active'),
(34, 'Отсев', 'Отсев дробления (навалом)', 'Товарная категория', 26, 'active'),
(35, 'Бут', 'Бутовый камень', 'Товарная категория', 26, 'active'),
(36, 'Керамзит', 'Керамзит (навалом)', 'Товарная категория', 26, 'active'),
(37, 'Крошка', 'Мраморная, гранитная крошка (навалом)', 'Товарная категория', 26, 'active'),
(38, 'Уголь', 'Уголь каменный, древесный (навалом)', 'Товарная категория', 26, 'active'),
(39, 'Чернозём', 'Плодородный грунт (навалом)', 'Товарная категория', 26, 'active'),
(40, 'Известь', 'Известь гашеная, негашеная (навалом)', 'Товарная категория', 26, 'active'),
(41, 'Другое', 'Прочие сыпучие материалы', 'Товарная категория', 26, 'active'),
(42, 'Песок', 'Песок в мешках', 'Товарная категория', 27, 'active'),
(43, 'Щебень', 'Щебень в мешках', 'Товарная категория', 27, 'active'),
(44, 'Отсев', 'Отсев в мешках', 'Товарная категория', 27, 'active'),
(45, 'Другое', 'Прочие фасованные материалы', 'Товарная категория', 27, 'active'),
(46, 'Цемент', 'Портландцемент, шлакопортландцемент', 'Товарная категория', 28, 'active'),
(47, 'Штукатурки и шпаклёвки', 'Гипсовые, цементные, полимерные смеси', 'Товарная категория', 28, 'active'),
(48, 'Клей для плитки', 'Для керамической плитки, керамогранита, камня', 'Товарная категория', 28, 'active'),
(49, 'Смеси для пола', 'Стяжки, наливные полы', 'Товарная категория', 28, 'active'),
(50, 'Смеси огнеупорные', 'Для кладки печей, каминов', 'Товарная категория', 28, 'active'),
(51, 'Монтажные смеси', 'Для анкеровки, ремонта бетона', 'Товарная категория', 28, 'active'),
(52, 'Растворо-бетонные смеси', 'Готовые кладочные, штукатурные растворы', 'Товарная категория', 28, 'active'),
(53, 'Минвата, каменная вата', 'Рулоны, плиты (базальтовая вата)', 'Товарная категория', 29, 'active'),
(54, 'Пенопласт', 'Плиты ППС', 'Товарная категория', 29, 'active'),
(55, 'Экструдер', 'Плиты XPS (экструдированный пенополистирол)', 'Товарная категория', 29, 'active'),
(56, 'Строительные добавки', 'Пластификаторы, антифризы и т.д.', 'Товарная категория', 30, 'active'),
(57, 'Пена монтажная', 'Полиуретановая пена', 'Товарная категория', 30, 'active'),
(58, 'Арматура', 'Арматурная сталь А1, А3', 'Товарная категория', 31, 'active'),
(59, 'Сетки', 'Кладочная, сварная, рабица', 'Товарная категория', 31, 'active'),
(60, 'Вязальная проволока', 'Проволока для вязки арматуры', 'Товарная категория', 31, 'inactive');

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `PCRM_Categories`
--
ALTER TABLE `PCRM_Categories`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `PCRM_Categories`
--
ALTER TABLE `PCRM_Categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
