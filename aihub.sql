-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Apr 30, 2026 at 03:55 PM
-- Server version: 8.3.0
-- PHP Version: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `aihub`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
CREATE TABLE IF NOT EXISTS `admins` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `created_at`) VALUES
(2, 'admin', '$2y$10$YvshNHjdQfCoaU5ilJl94./iKP3q/zVmzcRZLiqzAyj/uOVPT9QUO', '2026-04-05 10:43:04');

-- --------------------------------------------------------

--
-- Table structure for table `ai_tools`
--

DROP TABLE IF EXISTS `ai_tools`;
CREATE TABLE IF NOT EXISTS `ai_tools` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 0xF09FA496,
  `category_id` int DEFAULT NULL,
  `credit_cost` int DEFAULT '10',
  `is_active` tinyint DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`)
) ENGINE=MyISAM AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ai_tools`
--

INSERT INTO `ai_tools` (`id`, `name`, `description`, `url`, `icon`, `category_id`, `credit_cost`, `is_active`) VALUES
(1, 'ChatGPT', 'AI chatbot for text generation.', 'https://chat.openai.com', '🤖', 1, 10, 1),
(2, 'Claude', 'Advanced reasoning AI by Anthropic.', 'https://claude.ai', '🧠', 1, 10, 1),
(3, 'Google Gemini', 'Multimodal AI assistant by Google.', 'https://gemini.google.com', '✨', 1, 10, 1),
(4, 'Midjourney', 'AI image generation tool.', 'https://www.midjourney.com', '🎨', 2, 10, 1),
(5, 'Stable Diffusion', 'Open-source AI image generator.', 'https://stablediffusionweb.com', '🖼', 2, 10, 1),
(6, 'DALL-E', 'Image generation by OpenAI.', 'https://labs.openai.com', '🎭', 2, 10, 1),
(7, 'GitHub Copilot', 'AI code assistant for developers.', 'https://github.com/features/copilot', '💻', 3, 10, 1),
(8, 'Replit AI', 'AI coding in the browser.', 'https://replit.com', '⚡', 3, 10, 1),
(9, 'ElevenLabs', 'AI voice and audio synthesis.', 'https://elevenlabs.io', '🎙', 4, 10, 1),
(10, 'Suno', 'AI music generation.', 'https://suno.ai', '🎵', 4, 10, 1),
(11, 'Perplexity', 'AI-powered search engine.', 'https://www.perplexity.ai', '🔍', 5, 10, 1),
(12, 'Runway', 'AI video generation and editing.', 'https://runwayml.com', '🎬', 6, 10, 1),
(15, 'Cursor', 'Built to make you extraordinarily productive, Cursor is the best way to code with AI.', 'https://cursor.com/', '🖱️', 3, 100, 1);

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `icon`, `created_at`) VALUES
(1, 'Chat & Text', '💬', '2026-04-05 11:04:02'),
(2, 'Image & Art', '🎨', '2026-04-05 11:04:02'),
(3, 'Code & Dev', '💻', '2026-04-05 11:04:02'),
(4, 'Audio & Voice', '🎙', '2026-04-05 11:04:02'),
(5, 'Search & Research', '🔍', '2026-04-05 11:04:02'),
(6, 'Video', '🎬', '2026-04-05 11:04:02');

-- --------------------------------------------------------

--
-- Table structure for table `credit_purchases`
--

DROP TABLE IF EXISTS `credit_purchases`;
CREATE TABLE IF NOT EXISTS `credit_purchases` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `credits_added` int NOT NULL,
  `plan` varchar(50) DEFAULT NULL,
  `purchased_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `credit_purchases`
--

INSERT INTO `credit_purchases` (`id`, `user_id`, `credits_added`, `plan`, `purchased_at`) VALUES
(1, 5, 5000, 'basic', '2026-04-04 10:44:32'),
(2, 5, 15000, 'pro', '2026-04-04 10:44:37'),
(3, 5, 50000, 'premium', '2026-04-04 10:44:40'),
(4, 5, 50000, 'premium', '2026-04-04 10:44:43'),
(5, 1, 5000, 'basic', '2026-04-05 08:38:13'),
(6, 1, 1001, 'admin', '2026-04-05 10:52:11'),
(7, 1, 5000, 'basic', '2026-04-05 10:54:32'),
(8, 1, 500, 'admin', '2026-04-05 10:55:05'),
(9, 1, 5000, 'basic', '2026-04-05 10:55:10'),
(10, 1, 5000, 'basic', '2026-04-09 06:49:32'),
(11, 1, 1, 'admin', '2026-04-11 15:16:39'),
(12, 1, 5000, 'basic', '2026-04-26 11:34:31');

-- --------------------------------------------------------

--
-- Table structure for table `favorites`
--

DROP TABLE IF EXISTS `favorites`;
CREATE TABLE IF NOT EXISTS `favorites` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `tool_id` int NOT NULL,
  `added_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_fav` (`user_id`,`tool_id`),
  KEY `tool_id` (`tool_id`)
) ENGINE=MyISAM AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `favorites`
--

INSERT INTO `favorites` (`id`, `user_id`, `tool_id`, `added_at`) VALUES
(15, 8, 1, '2026-04-29 17:47:17'),
(18, 11, 1, '2026-04-30 09:25:27');

-- --------------------------------------------------------

--
-- Table structure for table `otp_verification`
--

DROP TABLE IF EXISTS `otp_verification`;
CREATE TABLE IF NOT EXISTS `otp_verification` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `otp` varchar(10) NOT NULL,
  `expiry` datetime NOT NULL,
  `attempts` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_email` (`email`),
  KEY `idx_email_expiry` (`email`,`expiry`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `usage_logs`
--

DROP TABLE IF EXISTS `usage_logs`;
CREATE TABLE IF NOT EXISTS `usage_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `tool_name` varchar(100) NOT NULL,
  `tool_url` varchar(255) DEFAULT NULL,
  `credits_used` int DEFAULT '10',
  `visited_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=71 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `usage_logs`
--

INSERT INTO `usage_logs` (`id`, `user_id`, `tool_name`, `tool_url`, `credits_used`, `visited_at`) VALUES
(57, 8, 'ChatGPT', 'https://chat.openai.com', 10, '2026-04-25 17:23:57'),
(67, 11, 'Cursor', 'https://cursor.com/', 100, '2026-04-30 09:09:53'),
(68, 11, 'ChatGPT', 'https://chat.openai.com', 10, '2026-04-30 09:24:27'),
(69, 11, 'Cursor', 'https://cursor.com/', 100, '2026-04-30 09:24:54'),
(70, 8, 'Runway', 'https://runwayml.com', 10, '2026-04-30 15:44:16'),
(66, 8, 'Cursor', 'https://cursor.com/', 100, '2026-04-29 17:46:52'),
(65, 8, 'ChatGPT', 'https://chat.openai.com', 10, '2026-04-29 17:46:46'),
(64, 8, 'Google Gemini', 'https://gemini.google.com', 10, '2026-04-29 17:46:34'),
(38, 8, 'Stable Diffusion', 'https://stablediffusionweb.com', 10, '2026-04-22 14:42:14'),
(63, 8, 'Runway', 'https://runwayml.com', 10, '2026-04-27 17:44:16');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `credits` int DEFAULT '1000',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `credits`, `created_at`) VALUES
(11, 'joy', 'joy123@gmail.com', '$2y$10$Y.acriLVh.enOzxAS8Z8c.n/MjIOX1EvbG9KqTXQqn0wF1XFBbgYa', 90, '2026-04-30 09:09:36'),
(8, 'varun', 'varun123@gmail.com', '$2y$10$NAV/r5SEI0OkRIEU21MEe.InrCqTVQstNOXhtWAQzwNl20tSK9R8i', 840, '2026-04-22 14:39:04'),
(9, 'naveen', 'naveen123@gmail.com', '$2y$10$8uDYP5h5/Ua2K7ojIIckMO.WhTnZfNIsR0NE8npty5CX/SztsEXtm', 1000, '2026-04-25 04:17:48');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
