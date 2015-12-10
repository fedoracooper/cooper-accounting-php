-- MySQL dump 10.15  Distrib 10.0.21-MariaDB, for Linux (x86_64)
--
-- Host: localhost    Database: accounting
-- ------------------------------------------------------
-- Server version	10.0.21-MariaDB

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
-- Table structure for table `AccountAudits`
--

DROP TABLE IF EXISTS `AccountAudits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `AccountAudits` (
  `audit_id` int(11) NOT NULL AUTO_INCREMENT,
  `ledger_id` int(11) NOT NULL DEFAULT '0',
  `audit_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `account_balance` decimal(9,2)/*old*/ NOT NULL DEFAULT '0.00',
  `audit_comment` text COLLATE latin1_general_ci NOT NULL,
  `updated_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`audit_id`),
  KEY `ledger_id` (`ledger_id`),
  CONSTRAINT `AccountAudits_ibfk_1` FOREIGN KEY (`ledger_id`) REFERENCES `LedgerEntries` (`ledger_id`)
) ENGINE=InnoDB AUTO_INCREMENT=630 DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Accounts`
--

DROP TABLE IF EXISTS `Accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Accounts` (
  `account_id` smallint(6) NOT NULL AUTO_INCREMENT,
  `login_id` tinyint(4) NOT NULL DEFAULT '0',
  `account_parent_id` smallint(6) DEFAULT NULL,
  `account_name` varchar(25) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL DEFAULT '',
  `account_descr` varchar(50) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL DEFAULT '',
  `account_debit` tinyint(4) NOT NULL DEFAULT '1',
  `equation_side` char(1) NOT NULL DEFAULT 'L',
  `monthly_budget_default` decimal(8,2) NOT NULL DEFAULT '0.00',
  `active` tinyint(4) NOT NULL DEFAULT '1',
  `updated_time` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `savings_account_id` smallint(6) DEFAULT NULL,
  `is_savings` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`account_id`),
  KEY `login_id` (`login_id`,`account_parent_id`),
  KEY `inactive` (`active`),
  KEY `fk_savings_account_id` (`savings_account_id`),
  CONSTRAINT `Accounts_ibfk_1` FOREIGN KEY (`login_id`) REFERENCES `Logins` (`login_id`),
  CONSTRAINT `Accounts_ibfk_2` FOREIGN KEY (`login_id`) REFERENCES `Logins` (`login_id`),
  CONSTRAINT `fk_savings_account_id` FOREIGN KEY (`savings_account_id`) REFERENCES `Accounts` (`account_id`)
) ENGINE=InnoDB AUTO_INCREMENT=238 DEFAULT CHARSET=latin1 COMMENT='Account_debit is 1 or -1 for math reasons.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Budget`
--

DROP TABLE IF EXISTS `Budget`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Budget` (
  `budget_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` smallint(6) NOT NULL,
  `budget_month` date NOT NULL,
  `budget_amount` decimal(8,2) NOT NULL,
  `updated_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `budget_comment` varchar(500) COLLATE latin1_general_ci DEFAULT NULL,
  PRIMARY KEY (`budget_id`),
  UNIQUE KEY `budget_uk` (`account_id`,`budget_month`)
) ENGINE=InnoDB AUTO_INCREMENT=1663 DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `LedgerEntries`
--

DROP TABLE IF EXISTS `LedgerEntries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `LedgerEntries` (
  `ledger_id` int(11) NOT NULL AUTO_INCREMENT,
  `trans_id` int(11) NOT NULL DEFAULT '0',
  `account_id` smallint(6) NOT NULL DEFAULT '0',
  `ledger_amount` decimal(9,2)/*old*/ NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`ledger_id`),
  KEY `trans_id` (`trans_id`,`account_id`),
  KEY `account_id` (`account_id`),
  CONSTRAINT `LedgerEntries_ibfk_1` FOREIGN KEY (`trans_id`) REFERENCES `Transactions` (`trans_id`),
  CONSTRAINT `LedgerEntries_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `Accounts` (`account_id`)
) ENGINE=InnoDB AUTO_INCREMENT=24325 DEFAULT CHARSET=latin1 COMMENT='Adjustment to a single account';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Logins`
--

DROP TABLE IF EXISTS `Logins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Logins` (
  `login_id` tinyint(4) NOT NULL AUTO_INCREMENT,
  `login_user` varchar(25) NOT NULL DEFAULT '',
  `login_password` varchar(50) NOT NULL DEFAULT '',
  `default_account_id` smallint(6) DEFAULT NULL,
  `default_summary1` smallint(6) DEFAULT NULL,
  `default_summary2` smallint(6) DEFAULT NULL,
  `car_account_id` smallint(6) DEFAULT NULL,
  `login_admin` tinyint(4) NOT NULL DEFAULT '0',
  `display_name` varchar(50) NOT NULL DEFAULT '',
  `bad_login_count` tinyint(4) NOT NULL DEFAULT '0',
  `locked` tinyint(1) NOT NULL DEFAULT '0',
  `active` tinyint(4) NOT NULL DEFAULT '1',
  `primary_checking_account_id` smallint(6) DEFAULT NULL,
  PRIMARY KEY (`login_id`),
  UNIQUE KEY `login_user` (`login_user`),
  KEY `default_account_id` (`default_account_id`),
  KEY `primary_checking_account_id` (`primary_checking_account_id`),
  CONSTRAINT `Logins_ibfk_1` FOREIGN KEY (`default_account_id`) REFERENCES `Accounts` (`account_id`),
  CONSTRAINT `Logins_ibfk_2` FOREIGN KEY (`primary_checking_account_id`) REFERENCES `Accounts` (`account_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1 COMMENT='Each login has its own set of accounts';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ProductAccounts`
--

DROP TABLE IF EXISTS `ProductAccounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ProductAccounts` (
  `prod_account_id` smallint(6) NOT NULL AUTO_INCREMENT,
  `prod_id` smallint(6) NOT NULL DEFAULT '0',
  `user_name` varchar(50) NOT NULL DEFAULT '',
  `password` varchar(50) NOT NULL DEFAULT '',
  `email` varchar(100) NOT NULL DEFAULT '',
  `serial_num` varchar(100) NOT NULL DEFAULT '',
  `account_comment` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`prod_account_id`),
  KEY `prod_id` (`prod_id`),
  CONSTRAINT `ProductAccounts_ibfk_1` FOREIGN KEY (`prod_id`) REFERENCES `Products` (`prod_id`)
) ENGINE=InnoDB AUTO_INCREMENT=127 DEFAULT CHARSET=latin1 COMMENT='One or more accounts per product';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ProductCategories`
--

DROP TABLE IF EXISTS `ProductCategories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ProductCategories` (
  `category_id` tinyint(4) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(50) NOT NULL DEFAULT '',
  `category_comment` varchar(100) NOT NULL DEFAULT '',
  `active` tinyint(4) NOT NULL DEFAULT '1',
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=latin1 COMMENT='Categories of product information';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Products`
--

DROP TABLE IF EXISTS `Products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Products` (
  `prod_id` smallint(6) NOT NULL AUTO_INCREMENT,
  `login_id` tinyint(4) NOT NULL DEFAULT '0',
  `category_id` tinyint(4) NOT NULL DEFAULT '0',
  `prod_name` varchar(50) NOT NULL DEFAULT '',
  `prod_comment` text NOT NULL,
  `modified_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_time` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `active` tinyint(4) NOT NULL DEFAULT '1',
  PRIMARY KEY (`prod_id`),
  KEY `category_id` (`category_id`),
  KEY `login_id` (`login_id`),
  CONSTRAINT `Products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `ProductCategories` (`category_id`),
  CONSTRAINT `Products_ibfk_2` FOREIGN KEY (`login_id`) REFERENCES `Logins` (`login_id`)
) ENGINE=InnoDB AUTO_INCREMENT=127 DEFAULT CHARSET=latin1 COMMENT='Product information parent table';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Transactions`
--

DROP TABLE IF EXISTS `Transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Transactions` (
  `trans_id` int(11) NOT NULL AUTO_INCREMENT,
  `login_id` tinyint(4) NOT NULL DEFAULT '0',
  `trans_descr` varchar(100) NOT NULL DEFAULT '',
  `trans_date` date NOT NULL DEFAULT '0000-00-00',
  `accounting_date` date NOT NULL DEFAULT '0000-00-00',
  `trans_vendor` varchar(50) NOT NULL DEFAULT '',
  `trans_comment` text,
  `check_number` smallint(6) DEFAULT NULL,
  `gas_miles` decimal(7,1) DEFAULT NULL,
  `gas_gallons` decimal(4,2)/*old*/ DEFAULT NULL,
  `import_id` smallint(6) DEFAULT NULL,
  `updated_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `trans_status` tinyint(4) NOT NULL DEFAULT '1',
  `budget_date` date DEFAULT NULL,
  `exclude_from_budget` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`trans_id`),
  KEY `login_id` (`login_id`),
  KEY `import_id` (`import_id`),
  KEY `trans_status` (`trans_status`),
  CONSTRAINT `Transactions_ibfk_1` FOREIGN KEY (`login_id`) REFERENCES `Logins` (`login_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10496 DEFAULT CHARSET=latin1 COMMENT='Transaction can have many ledger entries.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `spreadsheet_import`
--

DROP TABLE IF EXISTS `spreadsheet_import`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `spreadsheet_import` (
  `trans_date` date NOT NULL DEFAULT '0000-00-00',
  `trans_descr` varchar(100) NOT NULL DEFAULT '',
  `amount` decimal(9,2)/*old*/ DEFAULT NULL,
  `trans_vendor` varchar(100) NOT NULL DEFAULT '',
  `trans_comment` text NOT NULL,
  `checking` decimal(9,2)/*old*/ DEFAULT NULL,
  `brokerage` decimal(9,2)/*old*/ DEFAULT NULL,
  `stock` decimal(9,2)/*old*/ DEFAULT NULL,
  `insurance` decimal(9,2)/*old*/ DEFAULT NULL,
  `ascent_expense` decimal(9,2)/*old*/ DEFAULT NULL,
  `paypal` decimal(9,2)/*old*/ DEFAULT NULL,
  `chase` decimal(9,2)/*old*/ DEFAULT NULL,
  `discover` decimal(9,2)/*old*/ DEFAULT NULL,
  `best_buy` decimal(9,2)/*old*/ DEFAULT NULL,
  `tax_owed` decimal(9,2)/*old*/ DEFAULT NULL,
  `paycheck` decimal(9,2)/*old*/ DEFAULT NULL,
  `other_income` decimal(9,2)/*old*/ DEFAULT NULL,
  `tax` decimal(9,2)/*old*/ DEFAULT NULL,
  `bills` decimal(9,2)/*old*/ DEFAULT NULL,
  `cars` decimal(9,2)/*old*/ DEFAULT NULL,
  `groceries` decimal(9,2)/*old*/ DEFAULT NULL,
  `food` decimal(9,2)/*old*/ DEFAULT NULL,
  `home` decimal(9,2)/*old*/ DEFAULT NULL,
  `electronics` decimal(9,2)/*old*/ DEFAULT NULL,
  `software` decimal(9,2)/*old*/ DEFAULT NULL,
  `misc` decimal(9,2)/*old*/ DEFAULT NULL,
  `import_id` smallint(6) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`import_id`)
) ENGINE=InnoDB AUTO_INCREMENT=679 DEFAULT CHARSET=latin1 PACK_KEYS=0 COMMENT='importing from accounting spreadsheets';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2015-12-09 23:59:57
