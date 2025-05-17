-- phpMyAdmin SQL Dump
-- version 5.1.1deb5ubuntu1
-- https://www.phpmyadmin.net/
--
-- Хост: localhost
-- Время создания: Май 17 2025 г., 03:15
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
-- Структура таблицы `category`
--

CREATE TABLE `category` (
  `id` int NOT NULL,
  `title` varchar(64) NOT NULL COMMENT 'Название',
  `link` varchar(64) NOT NULL,
  `status` int NOT NULL DEFAULT '0' COMMENT '0 - вкл 1 - выкл',
  `sort` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Дамп данных таблицы `category`
--

INSERT INTO `category` (`id`, `title`, `link`, `status`, `sort`) VALUES
(1, 'Сыпучие', 'sypychie', 0, 2),
(2, 'Камень', 'kamen', 1, 0),
(3, 'Отделочные материалы', 'otdelochnye-material', 0, 3),
(4, 'Гипсокартон', 'gipsokarton', 1, 7),
(5, 'Утеплители', 'utepliteli', 0, 4),
(6, 'Общестрой', 'obschestroy', 0, 1),
(7, 'Кровельные материалы', 'krovelnye-materialy', 1, 0),
(8, 'МеталлоПродукция', 'metalloprodukciya', 1, 0),
(9, 'Бетон', 'beton', 1, 0),
(10, 'Химия', 'himiya', 1, 0),
(12, 'Чернозём', 'chernozem', 1, 0),
(13, 'Гидроизоляция', 'gidroizolyatcya', 1, 0),
(14, 'Электрика', 'electro', 0, 5),
(15, 'Оборудование', 'instrument-oborud', 0, 6),
(16, 'Электроинструмент', 'electro-instrument', 0, 6),
(17, 'Садовое оборудование', 'sadovodstvo', 0, 6),
(18, 'Расходные материалы', 'rashodniki', 0, 6),
(19, 'Инвентарь', 'inventar', 0, 6),
(20, 'Строительная химия', 'stroitelnaya-himiya', 0, 6);

-- --------------------------------------------------------

--
-- Структура таблицы `sub_category`
--

CREATE TABLE `sub_category` (
  `id` int NOT NULL,
  `title` varchar(64) NOT NULL,
  `link` varchar(64) NOT NULL,
  `pcid` int NOT NULL COMMENT 'id Родительской категории',
  `status` int NOT NULL DEFAULT '0' COMMENT '0 - вкл 1 - выкл'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Дамп данных таблицы `sub_category`
--

INSERT INTO `sub_category` (`id`, `title`, `link`, `pcid`, `status`) VALUES
(1, 'Песок', 'pesok', 1, 0),
(2, 'Щебень', 'scheben', 1, 0),
(3, 'Цемент', 'cement', 1, 1),
(4, 'Отсев', 'otsevy', 1, 0),
(5, 'Бут', 'but', 1, 0),
(7, 'Керамзит', 'keramzit', 1, 0),
(8, 'Кирпич', 'kirpich', 2, 0),
(9, 'Ракушка', 'rakushka', 2, 1),
(10, 'Штукатурки и шпаклёвки', 'shtukaturki-i-shpaklevki', 3, 0),
(11, 'Клей для плитки', 'kley-dlya-plitki', 3, 0),
(12, 'Теплоизоляционный клей', 'teploizolyacionnyy-kley\r\n', 3, 1),
(14, 'Грунтовые краски', 'gruntovye-kraski', 3, 1),
(15, 'Гипсокартон', 'gipsokarton', 4, 0),
(16, 'Профиля', 'profilya', 4, 0),
(17, 'Минвата, каменная вата', 'vata', 5, 0),
(18, 'Пенопласт', 'penoplast', 5, 0),
(19, 'Экструдер', 'ekstruder', 5, 0),
(20, 'Тачки', 'tachki', 6, 1),
(21, 'Бетономешалки', 'betonomeshalki', 6, 1),
(22, 'Сетки', 'setki', 8, 0),
(23, 'Арматура', 'armatura', 8, 0),
(24, 'Вязальная проволка', 'vyazalnaya-provolka\r\n', 8, 1),
(25, 'Рабица', 'rabica', 8, 1),
(26, 'Бетон', 'beton', 9, 0),
(27, 'Стяжка', 'styazhka', 9, 0),
(28, 'Известковый раствор', 'izvestkovyy-rastvor', 9, 0),
(29, 'Крошка', 'kroshka', 1, 0),
(30, 'Растворо-бетонные смеси', 'rastvor-beton', 6, 0),
(31, 'Камень', 'kameny', 6, 0),
(32, 'Кровельные материалы, пленки', 'crovlya-plenki', 6, 0),
(33, 'Цемент', 'cements', 6, 0),
(34, 'Арматура, сетки', 'arma-setki', 6, 0),
(35, 'Крепёж', 'krepeshi', 6, 0),
(36, 'Тросы, канаты', 'tros-kanat', 6, 0),
(37, 'Строительные добавки', 'stroi-dobavki', 20, 0),
(38, 'Грунтовки', 'grunt', 3, 1),
(39, 'Смеси для пола', 'smesi-pol', 3, 0),
(40, 'Гипсокартон, древесные плиты, профиля', 'gipsa-derevo-profil', 3, 0),
(41, 'Маяки, перфоуголки', 'mayak-perfougol', 3, 0),
(42, 'Крепежи для утеплителя', 'krepesh-uteplitel', 5, 0),
(44, 'Уголь', 'ugol', 1, 0),
(45, 'Чернозём', 'chernozem', 1, 0),
(46, 'Известь', 'izvest', 1, 0),
(47, 'Смеси огнеупорные', 'smesi-ogneuprnye', 3, 0),
(48, 'Монтажные смеси', 'montazhnie-smesi', 3, 0),
(49, 'Электродрели', 'electrodreli', 16, 0),
(50, 'Шуруповерты', 'shurupoverty', 16, 0),
(51, 'Измерительное оборудование', 'izmeritelnoe-oborudovanie', 15, 0),
(52, 'Генераторы', 'generatory', 15, 0),
(53, 'Сварочное оборудование', 'svarochnoe-oborudovanie', 15, 0),
(54, 'Верстаки', 'verstaky', 15, 0),
(55, 'Устройства для работы с плиткой', 'ustroystva-dlya-raboty-s-plitkoy', 15, 0),
(56, 'Компрессоры', 'compressory', 15, 0),
(57, 'Зарядные устройства', 'zarydnye-ustroistva', 14, 0),
(58, 'Строительное оборудование', 'stroitelnoe-oborudovanie', 15, 0),
(59, 'Бетономешалки', 'betonomeshalki', 15, 0),
(60, 'Круги и диски', 'krugi-i-diski', 18, 0),
(61, 'Ножи и лезвия', 'nozhi-i-lezviya', 18, 0),
(62, 'Шлифовальные листы и шкурки', 'shlifovalnye-listy-i-shkurki', 18, 0),
(63, 'Пленки и тенты', 'plenki-i-tenty', 18, 0),
(64, 'Для дрелей, гравёров и шуруповертов', 'dlya-drelei-graveryov-i-shurupovertov', 18, 0),
(65, 'УШМ (Болгарки)', 'bolgarki-ushm', 16, 0),
(66, 'Ведра', 'vedra', 19, 0),
(67, 'Лестницы', 'lestnici', 19, 0),
(68, 'Электроды', 'electrody', 18, 0),
(69, 'Перчатки', 'perchatky', 18, 0),
(70, 'Тачки', 'tachki', 19, 0),
(71, 'Саморезы', 'samorezy', 18, 0),
(72, 'Пена монтажная', 'pena-montazhnaya', 20, 0),
(73, 'Удлинители', 'udliniteli', 14, 0);

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `category`
--
ALTER TABLE `category`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `sub_category`
--
ALTER TABLE `sub_category`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `category`
--
ALTER TABLE `category`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT для таблицы `sub_category`
--
ALTER TABLE `sub_category`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
