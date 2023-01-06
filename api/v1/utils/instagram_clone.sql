-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Anamakine: 127.0.0.1
-- Üretim Zamanı: 06 Oca 2023, 21:12:34
-- Sunucu sürümü: 10.4.25-MariaDB
-- PHP Sürümü: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Veritabanı: `instagram_clone`
--

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `comments`
--

CREATE TABLE `comments` (
  `comment_id` int(11) NOT NULL,
  `comment_text` varchar(250) COLLATE utf8_turkish_ci NOT NULL,
  `comment_post` int(11) NOT NULL,
  `comment_owner` int(11) NOT NULL,
  `created_at` varchar(24) COLLATE utf8_turkish_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

--
-- Tetikleyiciler `comments`
--
DELIMITER $$
CREATE TRIGGER `after_comment_insert` AFTER INSERT ON `comments` FOR EACH ROW BEGIN
	SET @sender_name = (SELECT user_name FROM users WHERE user_id = new.comment_owner);
    SET @notification_text = CONCAT(@sender_name, ' has commented on your post.');
    SET @notification_resource = new.comment_post;
    SET @notification_type = 1;
    SET @notification_receiver = (SELECT DISTINCT posts.post_owner FROM posts INNER JOIN comments ON comments.comment_post = posts.post_id WHERE posts.post_id = new.comment_post);
    SET @received_at = (SELECT DATE_FORMAT(NOW(),'%b %e, %Y - %h:%i %p'));
    
    IF new.comment_owner != @notification_receiver THEN
    	INSERT INTO notifications (notification_text, notification_resource, notification_type, notification_receiver, received_at) VALUES(@notification_text, @notification_resource, @notification_type, @notification_receiver, @received_at);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `follow`
--

CREATE TABLE `follow` (
  `follower_id` int(11) NOT NULL,
  `following_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

--
-- Tetikleyiciler `follow`
--
DELIMITER $$
CREATE TRIGGER `after_follow` AFTER INSERT ON `follow` FOR EACH ROW BEGIN
	SET @sender_name = (SELECT user_name FROM users WHERE user_id = new.follower_id);
    SET @notification_text = CONCAT(@sender_name, ' started following you.');
    SET @notification_resource = new.follower_id;
    SET @notification_type = 0;
    SET @notification_receiver = new.following_id;
    SET @received_at = (SELECT DATE_FORMAT(NOW(),'%b %e, %Y - %h:%i %p'));
    
    INSERT INTO notifications (notification_text, notification_resource, notification_type, notification_receiver, received_at) VALUES(@notification_text, @notification_resource, @notification_type, @notification_receiver, @received_at);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `notification_text` text COLLATE utf8_turkish_ci NOT NULL,
  `notification_resource` int(11) NOT NULL COMMENT 'user or post id',
  `notification_type` tinyint(1) NOT NULL COMMENT '0: user\r\n1: post',
  `notification_receiver` int(11) NOT NULL,
  `received_at` varchar(24) COLLATE utf8_turkish_ci NOT NULL,
  `is_seen` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `posts`
--

CREATE TABLE `posts` (
  `post_id` int(11) NOT NULL,
  `post_photo` varchar(15) COLLATE utf8_turkish_ci NOT NULL,
  `post_description` varchar(200) COLLATE utf8_turkish_ci NOT NULL,
  `post_owner` int(11) NOT NULL,
  `created_at` varchar(24) COLLATE utf8_turkish_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

--
-- Tetikleyiciler `posts`
--
DELIMITER $$
CREATE TRIGGER `after_post_delete` AFTER DELETE ON `posts` FOR EACH ROW BEGIN
    DELETE FROM comments WHERE comment_post = old.post_id;
    DELETE FROM notifications WHERE notification_type = 1 AND notification_resource = old.post_id;
    DELETE FROM post_likes WHERE post_id = old.post_id;
    DELETE FROM saved_posts WHERE post_id = old.post_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `post_likes`
--

CREATE TABLE `post_likes` (
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

--
-- Tetikleyiciler `post_likes`
--
DELIMITER $$
CREATE TRIGGER `after_post_like` AFTER INSERT ON `post_likes` FOR EACH ROW BEGIN
	SET @sender_name = (SELECT user_name FROM users WHERE user_id = new.user_id);
    SET @notification_text = CONCAT(@sender_name, ' liked your post.');
    SET @notification_resource = new.post_id;
    SET @notification_type = 1;
    SET @notification_receiver = (SELECT DISTINCT post_owner FROM posts WHERE post_id = new.post_id);
    SET @received_at = (SELECT DATE_FORMAT(NOW(),'%b %e, %Y - %h:%i %p'));
    
    IF new.user_id != @notification_receiver THEN
    	INSERT INTO notifications (notification_text, notification_resource, notification_type, notification_receiver, received_at) VALUES(@notification_text, @notification_resource, @notification_type, @notification_receiver, @received_at);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `saved_posts`
--

CREATE TABLE `saved_posts` (
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `user_email` varchar(50) COLLATE utf8_turkish_ci NOT NULL,
  `user_name` varchar(25) COLLATE utf8_turkish_ci NOT NULL,
  `user_password` varchar(50) COLLATE utf8_turkish_ci NOT NULL,
  `user_fullname` varchar(50) COLLATE utf8_turkish_ci DEFAULT NULL,
  `user_photo` varchar(15) COLLATE utf8_turkish_ci NOT NULL DEFAULT 'default.png',
  `user_bio` varchar(250) COLLATE utf8_turkish_ci DEFAULT NULL,
  `user_profile_private` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

--
-- Dökümü yapılmış tablolar için indeksler
--

--
-- Tablo için indeksler `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`comment_id`);

--
-- Tablo için indeksler `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`);

--
-- Tablo için indeksler `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`post_id`);

--
-- Tablo için indeksler `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`);

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `comments`
--
ALTER TABLE `comments`
  MODIFY `comment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `posts`
--
ALTER TABLE `posts`
  MODIFY `post_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
