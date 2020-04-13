/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `loans`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ils_loan_id` varchar(100) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ils_user_id` varchar(255) NOT NULL,
  `bib_id` varchar(80) NOT NULL,
  `title` varchar(1000) DEFAULT NULL,
  `author` varchar(255) DEFAULT NULL,
  `publication_year` varchar(150) DEFAULT NULL,
  `description` varchar(1500) DEFAULT NULL,
  `loan_date` datetime DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `return_date`datetime DEFAULT NULL,
  `library_code` varchar(255) DEFAULT NULL,
  `location_code` varchar(50) DEFAULT NULL,
  `borrowing_location_code` varchar(255) DEFAULT NULL,
  `call_no` varchar(100) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ils_loan_id` (`ils_loan_id`),
  KEY `user_id` (`user_id`),
  KEY `ils_user_id` (`ils_user_id`),
  CONSTRAINT `loans_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `loans_ibfk_2` FOREIGN KEY (`ils_user_id`) REFERENCES `user` (`cat_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Add column `save_loans` to table `user`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
ALTER TABLE `user`
  ADD COLUMN `save_loans` TINYINT(1) NOT NULL DEFAULT 0;
/*!40101 SET character_set_client = @saved_cs_client */;
