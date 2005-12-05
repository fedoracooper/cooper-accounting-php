-- phpMyAdmin SQL Dump
-- version 2.7.0
-- http://www.phpmyadmin.net
-- 
-- Host: localhost
-- Generation Time: Dec 04, 2005 at 08:01 PM
-- Server version: 4.1.15
-- PHP Version: 5.0.4
-- 
-- Database: `accounting`
-- 

-- --------------------------------------------------------

-- 
-- Table structure for table `Accounts`
-- 

CREATE TABLE `Accounts` (
  `account_id` smallint(6) NOT NULL auto_increment,
  `login_id` tinyint(4) NOT NULL default '0',
  `account_parent_id` smallint(6) default NULL,
  `account_name` varchar(25) character set latin1 collate latin1_general_ci NOT NULL default '',
  `account_descr` varchar(50) character set latin1 collate latin1_general_ci NOT NULL default '',
  `account_debit` tinyint(4) NOT NULL default '1',
  `equation_side` char(1) NOT NULL default 'L',
  `active` tinyint(4) NOT NULL default '1',
  PRIMARY KEY  (`account_id`),
  KEY `login_id` (`login_id`,`account_parent_id`),
  KEY `inactive` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Account_debit is 1 or -1 for math reasons.' AUTO_INCREMENT=119 ;

-- --------------------------------------------------------

-- 
-- Table structure for table `LedgerEntries`
-- 

CREATE TABLE `LedgerEntries` (
  `ledger_id` int(11) NOT NULL auto_increment,
  `trans_id` int(11) NOT NULL default '0',
  `account_id` smallint(6) NOT NULL default '0',
  `ledger_amount` decimal(9,2) NOT NULL default '0.00',
  PRIMARY KEY  (`ledger_id`),
  KEY `trans_id` (`trans_id`,`account_id`),
  KEY `account_id` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Adjustment to a single account' AUTO_INCREMENT=3034 ;

-- --------------------------------------------------------

-- 
-- Table structure for table `Logins`
-- 

CREATE TABLE `Logins` (
  `login_id` tinyint(4) NOT NULL auto_increment,
  `login_user` varchar(25) NOT NULL default '',
  `login_password` varchar(50) NOT NULL default '',
  `default_account_id` smallint(6) default NULL,
  `default_summary1` smallint(6) default NULL,
  `default_summary2` smallint(6) default NULL,
  `car_account_id` smallint(6) default NULL,
  `login_admin` tinyint(4) NOT NULL default '0',
  `display_name` varchar(50) NOT NULL default '',
  `active` tinyint(4) NOT NULL default '1',
  PRIMARY KEY  (`login_id`),
  KEY `default_account_id` (`default_account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Each login has its own set of accounts' AUTO_INCREMENT=3 ;

-- --------------------------------------------------------

-- 
-- Table structure for table `ProductAccounts`
-- 

CREATE TABLE `ProductAccounts` (
  `prod_account_id` smallint(6) NOT NULL auto_increment,
  `prod_id` smallint(6) NOT NULL default '0',
  `user_name` varchar(50) NOT NULL default '',
  `password` varchar(50) NOT NULL default '',
  `email` varchar(100) NOT NULL default '',
  `serial_num` varchar(100) NOT NULL default '',
  `account_comment` varchar(100) NOT NULL default '',
  PRIMARY KEY  (`prod_account_id`),
  KEY `prod_id` (`prod_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='One or more accounts per product' AUTO_INCREMENT=59 ;

-- --------------------------------------------------------

-- 
-- Table structure for table `ProductCategories`
-- 

CREATE TABLE `ProductCategories` (
  `category_id` tinyint(4) NOT NULL auto_increment,
  `category_name` varchar(50) NOT NULL default '',
  `category_comment` varchar(100) NOT NULL default '',
  `active` tinyint(4) NOT NULL default '1',
  PRIMARY KEY  (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Categories of product information' AUTO_INCREMENT=6 ;

-- --------------------------------------------------------

-- 
-- Table structure for table `Products`
-- 

CREATE TABLE `Products` (
  `prod_id` smallint(6) NOT NULL auto_increment,
  `login_id` tinyint(4) NOT NULL default '0',
  `category_id` tinyint(4) NOT NULL default '0',
  `prod_name` varchar(50) NOT NULL default '',
  `prod_comment` text NOT NULL,
  `modified_time` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  `created_time` timestamp NOT NULL default '0000-00-00 00:00:00',
  `active` tinyint(4) NOT NULL default '1',
  PRIMARY KEY  (`prod_id`),
  KEY `category_id` (`category_id`),
  KEY `login_id` (`login_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Product information parent table' AUTO_INCREMENT=59 ;

-- --------------------------------------------------------

-- 
-- Table structure for table `Transactions`
-- 

CREATE TABLE `Transactions` (
  `trans_id` int(11) NOT NULL auto_increment,
  `login_id` tinyint(4) NOT NULL default '0',
  `trans_descr` varchar(100) NOT NULL default '',
  `trans_date` date NOT NULL default '0000-00-00',
  `accounting_date` date NOT NULL default '0000-00-00',
  `trans_vendor` varchar(50) NOT NULL default '',
  `trans_comment` text,
  `check_number` smallint(6) default NULL,
  `gas_miles` decimal(6,0) default NULL,
  `gas_gallons` decimal(4,2) default NULL,
  `import_id` smallint(6) default NULL,
  `updated_time` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  `trans_status` tinyint(4) NOT NULL default '1',
  PRIMARY KEY  (`trans_id`),
  KEY `login_id` (`login_id`),
  KEY `import_id` (`import_id`),
  KEY `trans_status` (`trans_status`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Transaction can have many ledger entries.' AUTO_INCREMENT=1342 ;

-- --------------------------------------------------------

-- 
-- Table structure for table `spreadsheet_import`
-- 

CREATE TABLE `spreadsheet_import` (
  `trans_date` date NOT NULL default '0000-00-00',
  `trans_descr` varchar(100) NOT NULL default '',
  `amount` decimal(9,2) default NULL,
  `trans_vendor` varchar(100) NOT NULL default '',
  `trans_comment` text NOT NULL,
  `checking` decimal(9,2) default NULL,
  `brokerage` decimal(9,2) default NULL,
  `stock` decimal(9,2) default NULL,
  `insurance` decimal(9,2) default NULL,
  `ascent_expense` decimal(9,2) default NULL,
  `paypal` decimal(9,2) default NULL,
  `chase` decimal(9,2) default NULL,
  `discover` decimal(9,2) default NULL,
  `best_buy` decimal(9,2) default NULL,
  `tax_owed` decimal(9,2) default NULL,
  `paycheck` decimal(9,2) default NULL,
  `other_income` decimal(9,2) default NULL,
  `tax` decimal(9,2) default NULL,
  `bills` decimal(9,2) default NULL,
  `cars` decimal(9,2) default NULL,
  `groceries` decimal(9,2) default NULL,
  `food` decimal(9,2) default NULL,
  `home` decimal(9,2) default NULL,
  `electronics` decimal(9,2) default NULL,
  `software` decimal(9,2) default NULL,
  `misc` decimal(9,2) default NULL,
  `import_id` smallint(6) NOT NULL auto_increment,
  PRIMARY KEY  (`import_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 PACK_KEYS=0 COMMENT='importing from accounting spreadsheets' AUTO_INCREMENT=679 ;

-- 
-- Constraints for dumped tables
-- 

-- 
-- Constraints for table `Accounts`
-- 
ALTER TABLE `Accounts`
  ADD CONSTRAINT `Accounts_ibfk_1` FOREIGN KEY (`login_id`) REFERENCES `Logins` (`login_id`),
  ADD CONSTRAINT `Accounts_ibfk_2` FOREIGN KEY (`login_id`) REFERENCES `Logins` (`login_id`);

-- 
-- Constraints for table `LedgerEntries`
-- 
ALTER TABLE `LedgerEntries`
  ADD CONSTRAINT `LedgerEntries_ibfk_1` FOREIGN KEY (`trans_id`) REFERENCES `Transactions` (`trans_id`),
  ADD CONSTRAINT `LedgerEntries_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `Accounts` (`account_id`);

-- 
-- Constraints for table `Logins`
-- 
ALTER TABLE `Logins`
  ADD CONSTRAINT `Logins_ibfk_1` FOREIGN KEY (`default_account_id`) REFERENCES `Accounts` (`account_id`);

-- 
-- Constraints for table `ProductAccounts`
-- 
ALTER TABLE `ProductAccounts`
  ADD CONSTRAINT `ProductAccounts_ibfk_1` FOREIGN KEY (`prod_id`) REFERENCES `Products` (`prod_id`);

-- 
-- Constraints for table `Products`
-- 
ALTER TABLE `Products`
  ADD CONSTRAINT `Products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `ProductCategories` (`category_id`),
  ADD CONSTRAINT `Products_ibfk_2` FOREIGN KEY (`login_id`) REFERENCES `Logins` (`login_id`);

-- 
-- Constraints for table `Transactions`
-- 
ALTER TABLE `Transactions`
  ADD CONSTRAINT `Transactions_ibfk_1` FOREIGN KEY (`login_id`) REFERENCES `Logins` (`login_id`);
