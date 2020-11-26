-- phpMyAdmin SQL Dump
-- version 3.5.8.1

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

-- --------------------------------------------------------

--
-- Структура таблицы `predict`
--

CREATE TABLE IF NOT EXISTS `predict` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `game` int(10) DEFAULT NULL,
  `team1Id` int(10) DEFAULT NULL,
  `team2Id` int(10) DEFAULT NULL,
  `team1Name` varchar(100) DEFAULT NULL,
  `team2Name` varchar(100) DEFAULT NULL,
  `team1Rating` varchar(10) NOT NULL,
  `team2Rating` varchar(10) NOT NULL,
  `predict` int(2) DEFAULT NULL,
  `chance` int(3) DEFAULT NULL,
  `winner` int(2) DEFAULT NULL,
  `score` varchar(10) DEFAULT NULL,
  `predictData` varchar(10000) DEFAULT NULL,
  `pickBan` varchar(10000) DEFAULT NULL,
  `link` varchar(1000) DEFAULT NULL,
  `tournament` varchar(1000) DEFAULT NULL,
  `statusUpdate` int(1) DEFAULT '0',
  `statusSend` int(1) NOT NULL DEFAULT '0',
  `startDate` date DEFAULT NULL,
  `startTime` time DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=cp1251 AUTO_INCREMENT=965 ;

-- --------------------------------------------------------

--
-- Структура таблицы `teams`
--

CREATE TABLE IF NOT EXISTS `teams` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `team_id` int(10) NOT NULL,
  `name` varchar(1000) NOT NULL,
  `tag` varchar(10) NOT NULL,
  `status` int(1) NOT NULL DEFAULT '0',
  UNIQUE KEY `id_2` (`id`),
  KEY `id` (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=cp1251 AUTO_INCREMENT=1001 ;


-- --------------------------------------------------------

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
