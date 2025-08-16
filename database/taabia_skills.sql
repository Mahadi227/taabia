-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 06, 2025 at 04:48 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `taabia_skills`
--

-- --------------------------------------------------------

--
-- Table structure for table `blog_categories`
--

CREATE TABLE `blog_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `blog_categories`
--

INSERT INTO `blog_categories` (`id`, `name`, `slug`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Formation', 'formation', 'Articles sur les tendances et innovations en formation', 'active', '2025-08-03 02:39:13', '2025-08-03 02:39:13'),
(2, 'Développement Personnel', 'developpement-personnel', 'Conseils et stratégies pour le développement personnel', 'active', '2025-08-03 02:39:13', '2025-08-03 02:39:13'),
(3, 'Technologie', 'technologie', 'Actualités et innovations technologiques', 'active', '2025-08-03 02:39:13', '2025-08-03 02:39:13'),
(4, 'Entreprise', 'entreprise', 'Conseils et stratégies pour les entreprises', 'active', '2025-08-03 02:39:13', '2025-08-03 02:39:13'),
(5, 'Événements', 'evenements', 'Actualités et comptes-rendus d\'événements', 'active', '2025-08-03 02:39:13', '2025-08-03 02:39:13');

-- --------------------------------------------------------

--
-- Table structure for table `blog_posts`
--

CREATE TABLE `blog_posts` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `excerpt` text DEFAULT NULL,
  `featured_image` varchar(255) DEFAULT NULL,
  `author_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `status` enum('published','draft','archived') DEFAULT 'draft',
  `published_at` timestamp NULL DEFAULT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `view_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `blog_posts`
--

INSERT INTO `blog_posts` (`id`, `title`, `slug`, `content`, `excerpt`, `featured_image`, `author_id`, `category_id`, `status`, `published_at`, `meta_title`, `meta_description`, `view_count`, `created_at`, `updated_at`) VALUES
(1, 'Les Tendances de la Formation en 2025', 'les-tendances-de-la-formation-en-2025', '<h2>Introduction</h2>\r\n<p>L\'ann&eacute;e 2024 marque un tournant majeur dans le domaine de la formation professionnelle...</p>\r\n<h2>1. L\'Intelligence Artificielle dans la Formation</h2>\r\n<p>L\'IA r&eacute;volutionne la fa&ccedil;on dont nous apprenons...</p>\r\n<h2>2. La Formation Hybride</h2>\r\n<p>La combinaison du pr&eacute;sentiel et du digital...</p>\r\n<h2>3. L\'Apprentissage Personnalis&eacute;</h2>\r\n<p>Chaque apprenant a des besoins uniques...</p>\r\n<h2>Conclusion</h2>\r\n<p>Ces tendances fa&ccedil;onnent l\'avenir de la formation...</p>', 'Découvrez les nouvelles approches de formation qui révolutionnent l\'apprentissage professionnel et améliorent l\'engagement des apprenants.', NULL, 1, 4, 'published', '2025-08-04 20:04:37', 'Tendances Formation 2025', 'Découvrez les nouvelles tendances en formation professionnelle pour 2025', 2, '2025-08-03 02:39:51', '2025-08-04 18:04:37'),
(2, 'L\'Importance de la Formation Continue', 'importance-formation-continue', '<h2>Pourquoi la Formation Continue est Essentielle</h2><p>Dans un monde en constante évolution...</p><h2>Avantages de la Formation Continue</h2><ul><li>Mise à jour des compétences</li><li>Adaptation aux nouvelles technologies</li><li>Amélioration de la productivité</li></ul><h2>Stratégies de Formation Continue</h2><p>Voici comment intégrer la formation continue...</p>', 'Pourquoi la formation continue est essentielle pour maintenir la compétitivité dans un marché en constante évolution.', NULL, 1, 2, 'published', '2024-01-10 14:30:00', 'Formation Continue', 'L\'importance de la formation continue dans le monde professionnel', 2, '2025-08-03 02:39:51', '2025-08-04 17:37:33'),
(3, 'Formation à Distance : Bonnes Pratiques', 'formation-distance-bonnes-pratiques', '<h2>Les Défis de la Formation à Distance</h2><p>La formation à distance présente des défis uniques...</p><h2>Bonnes Pratiques</h2><ol><li>Créer un environnement d\'apprentissage engageant</li><li>Utiliser des outils interactifs</li><li>Maintenir une communication régulière</li></ol><h2>Outils Recommandés</h2><p>Voici les outils les plus efficaces...</p>', 'Conseils et stratégies pour optimiser l\'efficacité de vos programmes de formation à distance.', NULL, 1, 1, 'published', '2024-01-05 09:15:00', 'Formation à Distance', 'Bonnes pratiques pour la formation à distance', 0, '2025-08-03 02:39:51', '2025-08-03 02:39:51');

-- --------------------------------------------------------

--
-- Table structure for table `blog_post_tags`
--

CREATE TABLE `blog_post_tags` (
  `post_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog_tags`
--

CREATE TABLE `blog_tags` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`id`, `name`, `user_id`, `full_name`, `email`, `phone`, `password`, `address`, `created_at`) VALUES
(1, NULL, 8, 'Indira Hamed', 'indira@gmail.com', '', '', NULL, '2025-07-14 18:19:19'),
(2, NULL, 5, 'Moussa', 'moussa@gmail.com', NULL, '', NULL, '2025-07-15 03:20:54'),
(3, NULL, 12, 'Okacha', 'okacha@gmail.com', NULL, '', NULL, '2025-07-15 05:01:58'),
(4, NULL, 10, 'Ami', 'ami@gmail.com', NULL, '', NULL, '2025-07-27 22:20:41');

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `instructor_id` int(11) DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `thumbnail_url` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `video_url` varchar(255) DEFAULT NULL,
  `file_url` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `status` enum('draft','published') DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `category` varchar(100) DEFAULT 'autre'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `instructor_id`, `title`, `thumbnail_url`, `description`, `video_url`, `file_url`, `price`, `status`, `created_at`, `is_active`, `category`) VALUES
(1, 2, 'Skelewu Tome 2', NULL, '6 Moth', NULL, NULL, 6500.00, 'published', '2025-07-02 03:23:50', 1, 'autre'),
(2, 2, 'Skelewu Tome 1', NULL, '12 Moth', NULL, NULL, 5000.00, 'published', '2025-07-02 03:54:47', 1, 'autre'),
(5, 6, 'Web design', NULL, '6 Moth', NULL, NULL, 1200.00, 'published', '2025-07-05 05:04:49', 1, 'autre'),
(7, 6, 'Databases', NULL, '6 Moth', NULL, NULL, 4000.00, 'published', '2025-07-05 16:29:53', 1, 'autre'),
(16, 2, 'Skelewu Tome 3', NULL, '6 Moth', NULL, NULL, 2000.00, 'published', '2025-07-15 05:48:14', 1, 'autre'),
(17, 2, 'Skelewu Tome 4', NULL, '9 Moth', NULL, NULL, 1700.00, 'published', '2025-07-15 07:50:16', 1, 'autre'),
(18, 2, 'Skelewu Tome 5', NULL, '12 Moth', NULL, NULL, 1500.00, 'published', '2025-07-15 07:50:46', 1, 'autre'),
(20, 6, 'Oracle database', NULL, '8 Moth', NULL, NULL, 1600.00, 'published', '2025-07-17 02:24:54', 1, 'autre'),
(21, 6, 'Postgresql', NULL, '6 Moth', NULL, NULL, 800.00, 'published', '2025-07-17 02:28:48', 1, 'autre'),
(22, 6, 'MySQL Courses', NULL, '8 Moth', NULL, NULL, 400.00, 'published', '2025-07-17 19:05:32', 1, 'autre'),
(23, 6, 'Skelewu Tome 666', NULL, '3 Moths', NULL, NULL, 800.00, 'published', '2025-07-27 03:02:38', 1, 'autre'),
(24, 2, 'Tailoring', NULL, '6 Months', NULL, NULL, 700.00, 'published', '2025-07-29 17:14:09', 1, 'autre');

-- --------------------------------------------------------

--
-- Table structure for table `course_contents`
--

CREATE TABLE `course_contents` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `type` varchar(50) DEFAULT 'text',
  `content_type` enum('video','pdf','texte','lien') NOT NULL,
  `content_data` text DEFAULT NULL,
  `content_url` text NOT NULL,
  `is_free_preview` tinyint(1) DEFAULT 0,
  `position` int(11) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course_contents`
--

INSERT INTO `course_contents` (`id`, `course_id`, `title`, `type`, `content_type`, `content_data`, `content_url`, `is_free_preview`, `position`, `created_at`, `description`) VALUES
(1, 2, 'Web design tools', 'text', 'video', NULL, 'https://www.youtube.com/watch?v=4KGfnUEJaCQ', 1, 1, '2025-07-02 03:57:47', NULL),
(5, 7, 'Introduction', 'text', 'video', 'https://youtu.be/8_W5JT7Jz2Y', '', 0, 1, '2025-07-07 12:53:16', NULL),
(7, 7, 'Database architecture', 'text', 'video', 'https://youtu.be/8_W5JT7Jz2Y', '', 0, 1, '2025-07-07 13:16:01', NULL),
(8, 7, 'Oracle database', 'text', 'pdf', 'The importance of database system architecture', '', 0, 1, '2025-07-07 13:17:59', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `course_submissions`
--

CREATE TABLE `course_submissions` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `content_id` int(11) NOT NULL,
  `submission_text` text DEFAULT NULL,
  `file_url` varchar(255) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('submitted','graded','returned') DEFAULT 'submitted',
  `grade` decimal(5,2) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `graded_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `earnings`
--

CREATE TABLE `earnings` (
  `id` int(11) NOT NULL,
  `instructor_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `course_id` int(11) DEFAULT NULL,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `progress` int(11) DEFAULT 0,
  `student_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` datetime NOT NULL,
  `instructor_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `title`, `description`, `event_date`, `instructor_name`, `created_at`) VALUES
(1, 'Taabie Lunch', 'Mario Hôtel', '2025-08-07 00:00:00', 'Ibou Sam', '2025-07-09 05:36:11'),
(3, 'Awards', 'Tres important', '2025-10-04 00:00:00', 'Mani Sam', '2025-07-09 05:44:37'),
(4, 'Embauché bovine (Vol 1)', 'A l\'hôtel Radisson', '2025-08-08 00:00:00', 'Faycal Sam', '2025-07-09 06:03:12'),
(5, 'Recruitment', 'A Palace mall', '2025-08-01 00:00:00', 'Landry Habib', '2025-07-09 21:20:52'),
(6, 'Embauché bovine (Vol 2)', 'Africa Hall', '2025-10-01 00:00:00', 'Faycal Sam', '2025-07-13 23:20:07');

-- --------------------------------------------------------

--
-- Table structure for table `event_participants`
--

CREATE TABLE `event_participants` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `registered_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_registrations`
--

CREATE TABLE `event_registrations` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `registered_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_registrations`
--

INSERT INTO `event_registrations` (`id`, `event_id`, `name`, `email`, `registered_at`) VALUES
(1, 4, 'Issa', 'issa@gmail.com', '2025-07-09 06:33:00'),
(2, 4, 'Issa', 'issa@gmail.com', '2025-07-09 06:33:24'),
(3, 1, 'Ami', 'ami@gmail.com', '2025-07-09 06:34:18'),
(4, 5, 'Okacha', 'okacha@gmai.com', '2025-07-12 02:25:28'),
(5, 5, 'Ilyassou', 'ali@gmail.com', '2025-07-13 23:13:20'),
(6, 5, 'Ilyassou', 'ali@gmail.com', '2025-07-13 23:14:08'),
(7, 5, 'Ilyassou', 'ali@gmail.com', '2025-07-13 23:14:23');

-- --------------------------------------------------------

--
-- Table structure for table `instructor_earnings`
--

CREATE TABLE `instructor_earnings` (
  `id` int(11) NOT NULL,
  `instructor_id` int(11) NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `order_id` int(11) DEFAULT NULL,
  `earning_amount` decimal(10,2) DEFAULT NULL,
  `platform_commission` decimal(10,2) DEFAULT NULL,
  `status` enum('pending','available','paid') DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lessons`
--

CREATE TABLE `lessons` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text DEFAULT NULL,
  `video_url` varchar(255) DEFAULT NULL,
  `lesson_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `file_url` varchar(255) DEFAULT NULL,
  `duration` int(11) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lessons`
--

INSERT INTO `lessons` (`id`, `course_id`, `title`, `content`, `video_url`, `lesson_order`, `created_at`, `file_url`, `duration`, `description`) VALUES
(1, 2, 'Introduction', 'Intro to skelewu', '', 0, '2025-07-16 01:41:57', '1752780373_pg1.pdf', 45, 'Proposal'),
(2, 21, 'Introduction', '', NULL, 0, '2025-07-17 03:14:32', '1752875458_01- La formation Laravel 10 est en ligne !.mp4', 180, 'Week 1'),
(4, 21, 'Week 1', 'Architecturé ', '', 0, '2025-07-17 06:01:30', '1752778873_pg1.pdf', 54, 'The fondamental'),
(5, 2, 'Week 1', 'Abstract ', '', 0, '2025-07-17 19:24:26', NULL, 0, NULL),
(6, 2, 'Week 2', 'très importante ', '', 0, '2025-07-17 19:25:19', NULL, 0, NULL),
(7, 23, 'Week 1', 'Introduction ', '', 0, '2025-07-27 03:04:30', '1753585548_Untitled_Project(1).mp4', 45, 'introduction'),
(8, 24, 'Week 1', '', '', 0, '2025-07-29 17:15:13', NULL, 0, NULL),
(9, 23, 'Week 1', 'The student side now provides a comprehensive, professional learning platform that enhances user engagement', 'https://youtu.be/bw-NvGvLHtM', 0, '2025-07-31 12:39:32', NULL, 0, NULL),
(10, 23, 'Week 1', '', '', 0, '2025-07-31 12:41:30', NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `message` text NOT NULL,
  `sent_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `subject`, `content`, `message`, `sent_at`) VALUES
(1, 6, 8, 'exo', NULL, 'lundi soirée', '2025-07-08 02:22:53'),
(2, 8, 6, 'exo', 'ok', '', '2025-07-08 03:09:51'),
(3, 10, 6, 'Test', 'Bien reçu', '', '2025-07-10 17:11:05'),
(4, 6, 10, 'Test', NULL, 'Bien Reçu', '2025-07-10 17:12:58'),
(5, 10, 6, 'bien recu', 'mais le message ne s\'affiche pas', '', '2025-07-10 17:16:07'),
(6, 8, 6, 'Submiton', 'Exo réponse', '', '2025-07-13 22:25:45'),
(7, 2, 8, 'Test', NULL, 'cc', '2025-07-15 05:52:10'),
(8, 8, 2, 'test reçu', 'mais pas avec le texte', '', '2025-07-15 05:59:01'),
(9, 10, 6, 'Test A2', 'ok', '', '2025-08-05 18:25:26');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `buyer_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `status` enum('pending','paid','refunded','cancelled') DEFAULT 'pending',
  `payment_method` varchar(100) DEFAULT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'GHS',
  `ordered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `client_id` int(11) DEFAULT NULL,
  `order_date` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `buyer_id`, `total_amount`, `status`, `payment_method`, `transaction_id`, `currency`, `ordered_at`, `client_id`, `order_date`, `created_at`, `updated_at`) VALUES
(6, 8, 0.00, 'pending', NULL, NULL, 'GHS', '2025-07-14 18:36:07', NULL, '2025-07-14 18:36:07', '2025-07-14 18:36:07', '2025-07-24 21:27:33'),
(7, 8, 0.00, 'pending', NULL, NULL, 'GHS', '2025-07-14 18:36:09', NULL, '2025-07-14 18:36:09', '2025-07-14 18:36:09', '2025-07-24 21:27:33'),
(8, 8, 0.00, 'pending', NULL, NULL, 'GHS', '2025-07-14 18:36:15', NULL, '2025-07-14 18:36:15', '2025-07-14 18:36:15', '2025-07-24 21:27:33'),
(9, 8, 0.00, 'pending', NULL, NULL, 'GHS', '2025-07-14 18:43:31', NULL, '2025-07-14 18:43:31', '2025-07-14 18:43:31', '2025-07-24 21:27:33'),
(10, 8, 0.00, 'pending', NULL, NULL, 'GHS', '2025-07-14 18:44:53', NULL, '2025-07-14 18:44:53', '2025-07-14 18:44:53', '2025-07-24 21:27:33'),
(11, 8, 0.00, 'pending', NULL, NULL, 'GHS', '2025-07-14 18:44:53', NULL, '2025-07-14 18:44:53', '2025-07-14 18:44:53', '2025-07-24 21:27:33'),
(12, 8, 0.00, 'pending', NULL, NULL, 'GHS', '2025-07-14 18:49:59', NULL, '2025-07-14 18:49:59', '2025-07-14 18:49:59', '2025-07-24 21:27:33'),
(13, 8, 0.00, 'pending', NULL, NULL, 'GHS', '2025-07-14 18:49:59', NULL, '2025-07-14 18:49:59', '2025-07-14 18:49:59', '2025-07-24 21:27:33'),
(14, 8, 0.00, 'pending', NULL, NULL, 'GHS', '2025-07-14 18:51:13', NULL, '2025-07-14 18:51:13', '2025-07-14 18:51:13', '2025-07-24 21:27:33'),
(15, 8, 0.00, 'pending', NULL, NULL, 'GHS', '2025-07-14 18:51:13', NULL, '2025-07-14 18:51:13', '2025-07-14 18:51:13', '2025-07-24 21:27:33'),
(16, 8, 0.00, 'pending', NULL, NULL, 'GHS', '2025-07-14 18:53:13', NULL, '2025-07-14 18:53:13', '2025-07-14 18:53:13', '2025-07-24 21:27:33'),
(17, 8, 0.00, 'pending', NULL, NULL, 'GHS', '2025-07-14 18:53:13', NULL, '2025-07-14 18:53:13', '2025-07-14 18:53:13', '2025-07-24 21:27:33'),
(18, 8, 0.00, 'pending', NULL, NULL, 'GHS', '2025-07-14 19:01:11', NULL, '2025-07-14 19:01:11', '2025-07-14 19:01:11', '2025-07-24 21:27:33'),
(19, 8, 0.00, 'pending', NULL, NULL, 'GHS', '2025-07-14 19:01:11', NULL, '2025-07-14 19:01:11', '2025-07-14 19:01:11', '2025-07-24 21:27:33'),
(20, 8, 0.00, 'pending', NULL, NULL, 'GHS', '2025-07-14 19:01:52', NULL, '2025-07-14 19:01:52', '2025-07-14 19:01:52', '2025-07-24 21:27:33'),
(21, 8, 0.00, 'pending', NULL, NULL, 'GHS', '2025-07-14 19:01:52', NULL, '2025-07-14 19:01:52', '2025-07-14 19:01:52', '2025-07-24 21:27:33'),
(22, 8, NULL, '', NULL, NULL, 'GHS', '2025-07-14 19:08:05', 1, NULL, '2025-07-14 19:08:05', '2025-07-24 21:27:33'),
(23, 5, NULL, '', NULL, NULL, 'GHS', '2025-07-15 03:20:54', 2, NULL, '2025-07-15 03:20:54', '2025-07-24 21:27:33'),
(24, 5, NULL, '', NULL, NULL, 'GHS', '2025-07-15 03:23:52', 2, NULL, '2025-07-15 03:23:52', '2025-07-24 21:27:33'),
(25, 5, NULL, '', NULL, NULL, 'GHS', '2025-07-15 03:35:44', 2, NULL, '2025-07-15 03:35:44', '2025-07-24 21:27:33'),
(26, 5, NULL, '', NULL, NULL, 'GHS', '2025-07-15 03:36:24', 2, NULL, '2025-07-15 03:36:24', '2025-07-24 21:27:33'),
(27, 5, NULL, '', NULL, NULL, 'GHS', '2025-07-15 03:45:48', 2, NULL, '2025-07-15 03:45:48', '2025-07-24 21:27:33'),
(28, 12, NULL, '', NULL, NULL, 'GHS', '2025-07-15 05:01:58', 3, NULL, '2025-07-15 05:01:58', '2025-07-24 21:27:33'),
(29, 12, NULL, '', NULL, NULL, 'GHS', '2025-07-15 05:03:24', 3, NULL, '2025-07-15 05:03:24', '2025-07-24 21:27:33'),
(30, 12, NULL, '', NULL, NULL, 'GHS', '2025-07-15 05:04:46', 3, NULL, '2025-07-15 05:04:46', '2025-07-24 21:27:33'),
(31, 10, NULL, '', NULL, NULL, 'GHS', '2025-07-27 22:20:41', 4, NULL, '2025-07-27 22:20:41', '2025-07-27 22:20:41'),
(32, 10, NULL, '', NULL, NULL, 'GHS', '2025-07-27 22:23:17', 4, NULL, '2025-07-27 22:23:17', '2025-07-27 22:23:17'),
(33, 10, NULL, '', NULL, NULL, 'GHS', '2025-07-27 22:34:11', 4, NULL, '2025-07-27 22:34:11', '2025-07-27 22:34:11'),
(34, 10, NULL, '', NULL, NULL, 'GHS', '2025-07-27 22:35:06', 4, NULL, '2025-07-27 22:35:06', '2025-07-27 22:35:06'),
(35, 10, NULL, '', NULL, NULL, 'GHS', '2025-07-27 22:36:01', 4, NULL, '2025-07-27 22:36:01', '2025-07-27 22:36:01'),
(36, 10, NULL, '', NULL, NULL, 'GHS', '2025-07-27 22:37:50', 4, NULL, '2025-07-27 22:37:50', '2025-07-27 22:37:50'),
(37, 10, NULL, '', NULL, NULL, 'GHS', '2025-07-27 22:38:20', 4, NULL, '2025-07-27 22:38:20', '2025-07-27 22:38:20'),
(38, 10, NULL, '', NULL, NULL, 'GHS', '2025-07-27 22:39:52', 4, NULL, '2025-07-27 22:39:52', '2025-07-27 22:39:52'),
(39, 10, NULL, '', NULL, NULL, 'GHS', '2025-07-27 22:44:48', 4, NULL, '2025-07-27 22:44:48', '2025-07-27 22:44:48'),
(40, 10, NULL, '', NULL, NULL, 'GHS', '2025-07-27 22:47:55', 4, NULL, '2025-07-27 22:47:55', '2025-07-27 22:47:55'),
(41, 10, NULL, '', NULL, NULL, 'GHS', '2025-07-27 22:52:18', 4, NULL, '2025-07-27 22:52:18', '2025-07-27 22:52:18'),
(42, 10, NULL, '', NULL, NULL, 'GHS', '2025-07-27 22:54:40', 4, NULL, '2025-07-27 22:54:40', '2025-07-27 22:54:40'),
(43, 10, NULL, '', NULL, NULL, 'GHS', '2025-07-27 22:56:24', 4, NULL, '2025-07-27 22:56:24', '2025-07-27 22:56:24'),
(44, 10, NULL, '', NULL, NULL, 'GHS', '2025-07-27 22:56:55', 4, NULL, '2025-07-27 22:56:55', '2025-07-27 22:56:55'),
(45, 10, NULL, '', NULL, NULL, 'GHS', '2025-07-27 23:03:43', 4, NULL, '2025-07-27 23:03:43', '2025-07-27 23:03:43'),
(46, 10, NULL, '', NULL, NULL, 'GHS', '2025-07-27 23:11:40', 4, NULL, '2025-07-27 23:11:40', '2025-07-27 23:11:40'),
(47, 10, NULL, '', NULL, NULL, 'GHS', '2025-07-27 23:18:23', 4, NULL, '2025-07-27 23:18:23', '2025-07-27 23:18:23'),
(48, 10, NULL, '', NULL, NULL, 'GHS', '2025-07-27 23:19:14', 4, NULL, '2025-07-27 23:19:14', '2025-07-27 23:19:14'),
(49, 10, NULL, '', NULL, NULL, 'GHS', '2025-07-27 23:19:59', 4, NULL, '2025-07-27 23:19:59', '2025-07-27 23:19:59'),
(50, 10, NULL, '', NULL, NULL, 'GHS', '2025-07-27 23:21:02', 4, NULL, '2025-07-27 23:21:02', '2025-07-27 23:21:02');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `instructor_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `commission_percent` decimal(5,2) DEFAULT 20.00,
  `earning_amount` decimal(10,2) DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp(),
  `product_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `instructor_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `status` enum('pending','paid') DEFAULT 'pending',
  `payment_date` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_settings`
--

CREATE TABLE `payment_settings` (
  `id` int(11) NOT NULL,
  `gateway_name` varchar(50) DEFAULT NULL,
  `public_key` text DEFAULT NULL,
  `secret_key` text DEFAULT NULL,
  `commission_rate` decimal(5,2) DEFAULT 10.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payouts`
--

CREATE TABLE `payouts` (
  `id` int(11) NOT NULL,
  `instructor_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `method` varchar(100) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `transaction_ref` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payout_requests`
--

CREATE TABLE `payout_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('instructor','vendor') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','approved','rejected','paid') DEFAULT 'pending',
  `requested_at` datetime DEFAULT current_timestamp(),
  `paid_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `name` varchar(150) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `vendor_id`, `name`, `description`, `price`, `image_url`, `stock`, `status`, `created_at`, `is_active`) VALUES
(2, NULL, 'Site Dynamic', 'Platform', 6000.00, NULL, 0, 'active', '2025-07-02 05:25:32', 1),
(3, NULL, 'Pro e-commerce', 'super cool', 120.00, NULL, 0, 'active', '2025-07-02 05:31:16', 1),
(4, NULL, 'E-book', 'Databases', 120.00, '1752045752_dashboard.png', 0, 'active', '2025-07-09 07:22:32', 1),
(5, NULL, 'TaaBia E-book', 'Digital Bussinesss', 70.00, '1752096305_15.jpeg', 0, 'active', '2025-07-09 21:25:05', 1),
(6, NULL, 'ProsgreSQL E-book', 'Beginning crash', 80.00, NULL, 0, 'active', '2025-07-17 05:21:37', 1);

-- --------------------------------------------------------

--
-- Table structure for table `student_courses`
--

CREATE TABLE `student_courses` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `progress` int(11) DEFAULT 0,
  `enrolled_at` datetime DEFAULT current_timestamp(),
  `progress_percent` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_courses`
--

INSERT INTO `student_courses` (`id`, `student_id`, `course_id`, `progress`, `enrolled_at`, `progress_percent`) VALUES
(2, 8, 7, 0, '2025-07-07 23:56:44', 10),
(5, 8, 2, 0, '2025-07-08 01:24:22', 10),
(7, 11, 2, 0, '2025-07-13 02:57:47', 0),
(8, 8, 1, 0, '2025-07-13 22:20:44', 0),
(9, 5, 2, 0, '2025-07-14 21:32:46', 0),
(10, 5, 1, 0, '2025-07-14 21:33:06', 0),
(13, 8, 18, 0, '2025-07-17 22:33:43', 0),
(14, 8, 21, 0, '2025-07-18 22:46:52', 0),
(15, 13, 21, 0, '2025-07-18 23:36:38', 0),
(16, 14, 7, 0, '2025-07-27 03:24:06', 0),
(17, 10, 24, 0, '2025-07-29 17:18:57', 0),
(18, 10, 23, 0, '2025-07-29 23:08:10', 0),
(19, 8, 24, 0, '2025-07-31 04:51:43', 0),
(20, 8, 23, 0, '2025-07-31 04:52:00', 0),
(21, 8, 22, 0, '2025-07-31 04:52:26', 0);

-- --------------------------------------------------------

--
-- Table structure for table `submissions`
--

CREATE TABLE `submissions` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `status` enum('en_attente','validé','rejeté') DEFAULT 'en_attente',
  `submitted_at` datetime DEFAULT current_timestamp(),
  `validated_at` datetime DEFAULT NULL,
  `feedback` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES
(1, 'platform_name', 'TaaBia Skills & Market', 'Platform name', '2025-08-03 02:35:25'),
(2, 'platform_description', 'Integrated learning and e-commerce platform', 'Platform description', '2025-08-03 02:35:25'),
(3, 'currency', 'GHS', 'Default currency', '2025-08-03 02:35:25'),
(4, 'commission_rate', '10', 'Platform commission rate in percentage', '2025-08-03 02:35:25'),
(5, 'min_payout_amount', '50', 'Minimum payout amount', '2025-08-03 02:35:25'),
(6, 'max_file_size', '10485760', 'Maximum file upload size in bytes', '2025-08-03 02:35:25');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `instructor_id` int(11) DEFAULT NULL,
  `student_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` varchar(50) DEFAULT 'completed',
  `date_created` datetime DEFAULT current_timestamp(),
  `currency` varchar(10) DEFAULT 'GHS',
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_status` enum('pending','success','failed') DEFAULT 'pending',
  `transaction_id` varchar(100) DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `type` enum('course','product','subscription') DEFAULT 'course',
  `created_at` datetime DEFAULT current_timestamp(),
  `course_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `fullname` varchar(100) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','instructor','student','vendor') NOT NULL DEFAULT 'student',
  `profile_img` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_image` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `fullname`, `full_name`, `email`, `password`, `role`, `profile_img`, `is_active`, `created_at`, `profile_image`, `phone`) VALUES
(1, NULL, 'Faycal sam', 'Sam@gmail.con', '$2y$10$oxJQBjiDC9ZxPrlaIY4y1egg/YMUW7stYdhVySE/jVqv8Ge8/XOz6', 'admin', NULL, 1, '2025-06-29 05:58:34', NULL, NULL),
(2, NULL, 'Ibou Sam', 'ibou@gmail.com', '$2y$10$a1BsIzrKm2LWZbVkUoiJHu0DVA4oZ/yGRK0fGwhewMEvFwtolHlgO', 'instructor', NULL, 1, '2025-06-29 06:44:52', NULL, NULL),
(5, NULL, 'Moussa', 'moussa@gmail.com', '$2y$10$LGPghMmvyQsUJHhGnUIlluiiUc01b0H4iNpjFNKMMQTv9jnuJcq42', 'student', NULL, 1, '2025-07-02 07:57:38', NULL, NULL),
(6, NULL, 'Djo Sam', 'djo@gmail.com', '$2y$10$BiXKlJu5IveWSSfbpl0UlOGq8JTUft5p.RoWBCzhghVtPKC8EP11C', 'instructor', NULL, 1, '2025-07-05 03:18:48', NULL, NULL),
(7, NULL, 'Landry', 'landry@gmail.com', '$2y$10$aH9dzKTV.mFhnTCZ7hqQceGADoDlCXa9RgeY3ZtWB8iSqfRss/Ll.', 'instructor', NULL, 1, '2025-07-05 06:01:59', NULL, NULL),
(8, NULL, 'Indira Hamed', 'indira@gmail.com', '$2y$10$TtU8YEZyaVKVoVkxpT9kNejHjtEfvxtAXiyrxdvSofGld6s6Cv7/W', 'student', NULL, 1, '2025-07-07 20:21:07', 'student_8.jpeg', NULL),
(9, NULL, 'Hamed', 'hamed@gmail.con', '$2y$10$GSSx1VsGL9BDPKW01pRM.e3rhieSzEXSeQdg0ep2kzKpqgp.ghjZu', 'vendor', NULL, 1, '2025-07-09 06:13:29', NULL, NULL),
(10, NULL, 'Ami', 'ami@gmail.com', '$2y$10$6PCdXTARTsJTnztHMa2wK.41sem.pzCB50j9ZIANVf8.Ytc/6fT8u', 'student', NULL, 1, '2025-07-09 06:35:46', 'student_10_1754417428.jpeg', NULL),
(11, NULL, 'Omar Sam', 'omar@gmail.com', '$2y$10$uhFy5GWG9gsHa1st4eb2He2Yg6XylgYugOAvyOKqVhBvyDmPlqzVi', 'vendor', NULL, 1, '2025-07-13 02:56:21', 'student_11.webp', NULL),
(12, NULL, 'Okacha', 'okacha@gmail.com', '$2y$10$5ChxfKR9FaAai1.pDmy9v.mHx/ekyZ7Rk/uk7XO.MFtGQRH6ZhogW', 'vendor', NULL, 1, '2025-07-15 04:56:50', NULL, NULL),
(13, NULL, 'Issa', 'issa@gmail.com', '$2y$10$qQr8S3/1PijTM5ySdZVSXusEyWXgV6Xmx6RqPAYHWdvmfLQ.CS5ne', 'student', NULL, 1, '2025-07-18 23:36:07', 'student_13.jpeg', NULL),
(14, NULL, 'Hayat Sam', 'hayat@gmail.com', '$2y$10$5KG7uJMxOySQbZIHTYibeuI2ky0O0vH40HlJErw3IjEgRa5Oqg.5e', 'student', NULL, 1, '2025-07-27 03:23:08', 'student_14.jpg', NULL),
(15, NULL, 'Moctar Chaoulani', 'moctar@gmail.com', '$2y$10$7dHL4wMwL.niM3HT7Lfpn.XIiY750tD.QV5KD5o29tRQoTfbvjo5i', 'student', NULL, 1, '2025-07-28 03:54:57', NULL, NULL),
(16, NULL, 'Mahadi', 'mahadibusinessglobal227@gmail.com', '$2y$10$wf6KunFoUoxninmV1p.9YOOl0ICq3nL/cpZXTliCggqLize0sskda', 'admin', NULL, 1, '2025-07-29 17:33:19', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vendor_earnings`
--

CREATE TABLE `vendor_earnings` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `order_id` int(11) DEFAULT NULL,
  `earning_amount` decimal(10,2) DEFAULT NULL,
  `platform_commission` decimal(10,2) DEFAULT NULL,
  `status` enum('pending','available','paid') DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `blog_categories`
--
ALTER TABLE `blog_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `blog_posts`
--
ALTER TABLE `blog_posts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `author_id` (`author_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `blog_post_tags`
--
ALTER TABLE `blog_post_tags`
  ADD PRIMARY KEY (`post_id`,`tag_id`),
  ADD KEY `tag_id` (`tag_id`);

--
-- Indexes for table `blog_tags`
--
ALTER TABLE `blog_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_client_user` (`user_id`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `instructor_id` (`instructor_id`);

--
-- Indexes for table `course_contents`
--
ALTER TABLE `course_contents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `course_submissions`
--
ALTER TABLE `course_submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `content_id` (`content_id`);

--
-- Indexes for table `earnings`
--
ALTER TABLE `earnings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `instructor_id` (`instructor_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `event_participants`
--
ALTER TABLE `event_participants`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `event_registrations`
--
ALTER TABLE `event_registrations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `instructor_earnings`
--
ALTER TABLE `instructor_earnings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `lessons`
--
ALTER TABLE `lessons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `buyer_id` (`buyer_id`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payment_settings`
--
ALTER TABLE `payment_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payouts`
--
ALTER TABLE `payouts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `instructor_id` (`instructor_id`);

--
-- Indexes for table `payout_requests`
--
ALTER TABLE `payout_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendor_id` (`vendor_id`);

--
-- Indexes for table `student_courses`
--
ALTER TABLE `student_courses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_student` (`student_id`),
  ADD KEY `fk_course` (`course_id`);

--
-- Indexes for table `submissions`
--
ALTER TABLE `submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `vendor_earnings`
--
ALTER TABLE `vendor_earnings`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `blog_categories`
--
ALTER TABLE `blog_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `blog_posts`
--
ALTER TABLE `blog_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `blog_tags`
--
ALTER TABLE `blog_tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `course_contents`
--
ALTER TABLE `course_contents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `course_submissions`
--
ALTER TABLE `course_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `earnings`
--
ALTER TABLE `earnings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `event_participants`
--
ALTER TABLE `event_participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_registrations`
--
ALTER TABLE `event_registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `instructor_earnings`
--
ALTER TABLE `instructor_earnings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lessons`
--
ALTER TABLE `lessons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_settings`
--
ALTER TABLE `payment_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payouts`
--
ALTER TABLE `payouts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payout_requests`
--
ALTER TABLE `payout_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `student_courses`
--
ALTER TABLE `student_courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `submissions`
--
ALTER TABLE `submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `vendor_earnings`
--
ALTER TABLE `vendor_earnings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `blog_posts`
--
ALTER TABLE `blog_posts`
  ADD CONSTRAINT `blog_posts_ibfk_1` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `blog_posts_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `blog_categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `blog_post_tags`
--
ALTER TABLE `blog_post_tags`
  ADD CONSTRAINT `blog_post_tags_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `blog_posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `blog_post_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `blog_tags` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `clients`
--
ALTER TABLE `clients`
  ADD CONSTRAINT `fk_client_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `course_contents`
--
ALTER TABLE `course_contents`
  ADD CONSTRAINT `course_contents_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `course_submissions`
--
ALTER TABLE `course_submissions`
  ADD CONSTRAINT `course_submissions_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `course_submissions_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `course_submissions_ibfk_3` FOREIGN KEY (`content_id`) REFERENCES `course_contents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `earnings`
--
ALTER TABLE `earnings`
  ADD CONSTRAINT `earnings_ibfk_1` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `earnings_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`);

--
-- Constraints for table `event_registrations`
--
ALTER TABLE `event_registrations`
  ADD CONSTRAINT `event_registrations_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lessons`
--
ALTER TABLE `lessons`
  ADD CONSTRAINT `lessons_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `payouts`
--
ALTER TABLE `payouts`
  ADD CONSTRAINT `payouts_ibfk_1` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `student_courses`
--
ALTER TABLE `student_courses`
  ADD CONSTRAINT `fk_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_courses_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `student_courses_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`);

--
-- Constraints for table `submissions`
--
ALTER TABLE `submissions`
  ADD CONSTRAINT `submissions_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `submissions_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
