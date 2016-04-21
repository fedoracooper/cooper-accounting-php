-- MySQL dump 10.15  Distrib 10.0.21-MariaDB, for Linux (x86_64)
--
-- Host: localhost    Database: accounting
-- ------------------------------------------------------
-- Server version	10.0.21-MariaDB


--
-- Table structure for table AccountAudits
--

CREATE TABLE acct.login_audit (
  login_audit_id serial NOT NULL constraint pk_login_audit PRIMARY KEY,
  login_user varchar(25) NOT NULL,
  ip_address varchar(100) NOT NULL,
  event_time timestamp NOT NULL DEFAULT current_timestamp,
  login_success char(1) NOT NULL),
  account_locked char(1) NOT NULL);

CREATE TABLE acct.account_audits (
  audit_id SERIAL NOT NULL constraint pk_account_audit PRIMARY KEY,
  ledger_id int NOT NULL constraint fk_audit_ledger REFERENCES acct.ledger_entries(ledger_id),
  audit_date timestamp NOT NULL,
  account_balance decimal(9,2) NOT NULL,
  audit_comment varchar(500) NOT NULL,
  updated_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP);

  CREATE INDEX ix_audit_ledger ON acct.account_audits(ledger_id);

--
-- Table structure for table Accounts
--

CREATE TABLE acct.accounts (
  account_id SMALLSERIAL NOT NULL constraint pk_account PRIMARY KEY,
  login_id smallint NOT NULL constraint fk_account_login REFERENCES acct.logins(login_id), 
  account_parent_id smallint constraint fk_account_parent REFERENCES acct.accounts(account_id),
  account_name varchar(25) NOT NULL,
  account_descr varchar(50) NOT NULL,
  account_debit smallint NOT NULL DEFAULT '1',
  equation_side char(1) NOT NULL DEFAULT 'L',
  monthly_budget_default decimal(8,2) NOT NULL DEFAULT '0.00',
  active smallint NOT NULL DEFAULT '1',
  updated_time timestamp NOT NULL DEFAULT current_timestamp, 
  savings_account_id smallint DEFAULT NULL constraint fk_account_savings REFERENCES acct.accounts(account_id),
  is_savings smallint NOT NULL DEFAULT '0',
  is_paycheck_sink smallint NOT NULL DEFAULT '0');

  create index ix_account_login ON acct.accounts(login_id,account_parent_id);
  create index ix_account_parent ON acct.accounts(account_parent_id);
  create index ix_account_savings ON acct.accounts(savings_account_id);
); 
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table Budget
--

CREATE TABLE acct.budget (
  budget_id SERIAL NOT NULL constraint pk_budget PRIMARY KEY,
  account_id smallint NOT NULL constraint fk_budget_account REFERENCES acct.accounts(account_id),
  budget_month date NOT NULL,
  budget_amount decimal(8,2) NOT NULL,
  updated_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  budget_comment varchar(500) DEFAULT NULL);

  create UNIQUE INDEX uk_budget ON acct.budget (account_id,budget_month);

--
-- Table structure for table LedgerEntries
--

CREATE TABLE acct.ledger_entries (
  ledger_id SERIAL NOT NULL constraint pk_ledger_entries PRIMARY KEY,
  trans_id int NOT NULL constraint fk_ledger_trans REFERENCES acct.transactions(trans_id),
  account_id smallint NOT NULL constraint fk_ledger_acccount REFERENCES acct.accounts(account_id),
  ledger_amount decimal(9,2) NOT NULL,
  memo varchar(100) );

  create INDEX ix_ledger_trans ON acct.ledger_entries(trans_id);
  create INDEX ix_ledger_account ON acct.ledger_entries(account_id);

--
-- Table structure for table Logins
--


CREATE TABLE acct.logins (
  login_id SMALLSERIAL NOT NULL constraint pk_logins PRIMARY KEY,
  login_user varchar(25) NOT NULL ,
  login_password varchar(50) NOT NULL,
  default_account_id smallint DEFAULT NULL,
  default_summary1 smallint DEFAULT NULL,
  default_summary2 smallint DEFAULT NULL,
  car_account_id smallint DEFAULT NULL,
  login_admin smallint NOT NULL DEFAULT '0',
  display_name varchar(50) NOT NULL DEFAULT '',
  bad_login_count smallint NOT NULL DEFAULT '0',
  locked smallint NOT NULL DEFAULT '0',
  active smallint NOT NULL DEFAULT '1',
  primary_checking_account_id smallint DEFAULT NULL,
  last_login timestamp,
  last_login_ip varchar(100),
  last_login_failure timestamp,
  last_failure_ip varchar(100) );

  CREATE UNIQUE INDEX ix_login_user ON acct.logins(login_user);

  -- TODO:  create constraints
  CONSTRAINT Logins_ibfk_1 FOREIGN KEY (default_account_id) REFERENCES Accounts (account_id),
  CONSTRAINT Logins_ibfk_2 FOREIGN KEY (primary_checking_account_id) REFERENCES Accounts (account_id)

--
-- Table structure for table Transactions
--

CREATE TABLE acct.transactions (
  trans_id SERIAL NOT NULL constraint pk_transaction PRIMARY KEY,
  login_id smallint NOT NULL constraint fk_tx_login REFERENCES acct.logins(login_id),
  trans_descr varchar(100) NOT NULL DEFAULT '',
  trans_date date NOT NULL,
  accounting_date date NOT NULL,
  trans_vendor varchar(50) NOT NULL DEFAULT '',
  trans_comment varchar(500),
  check_number smallint DEFAULT NULL,
  gas_miles decimal(7,1) DEFAULT NULL,
  gas_gallons decimal(4,2) DEFAULT NULL,
  import_id smallint DEFAULT NULL,
  updated_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  trans_status smallint NOT NULL DEFAULT '1',
  budget_date date DEFAULT NULL,
  exclude_from_budget "char" NOT NULL DEFAULT '0',
  closing_transaction "char" NOT NULL DEFAULT '0' );

  -- create INDEX ix_tx_login ON acct.transactions(login_id);
  create INDEX ix_tx_accounting_date ON acct.transactions(accounting_date);


--
-- Table structure for table import_2002
--

DROP TABLE IF EXISTS import_2002;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE import_2002 (
  trans_id int(11) NOT NULL AUTO_INCREMENT,
  trans_date date NOT NULL,
  trans_type smallint NOT NULL,
  trans_descr varchar(100) NOT NULL,
  account_name varchar(100) DEFAULT NULL,
  amount decimal(8,2) NOT NULL,
  vendor varchar(100) DEFAULT NULL,
  credit_balance decimal(8,2) NOT NULL,
  trans_comment varchar(500) DEFAULT NULL,
  account_id1 int(11) DEFAULT NULL,
  account_id2 int(11) DEFAULT NULL,
  bank_balance decimal(8,2) DEFAULT NULL,
  PRIMARY KEY (trans_id)
) ENGINE=InnoDB AUTO_INCREMENT=511 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table import_account_names
--

DROP TABLE IF EXISTS import_account_names;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE import_account_names (
  account_id int(11) NOT NULL,
  account_name varchar(50) NOT NULL,
  id int(11) NOT NULL AUTO_INCREMENT,
  account_id2 int(11) DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table import_simple
--

DROP TABLE IF EXISTS import_simple;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE import_simple (
  trans_id int(11) NOT NULL AUTO_INCREMENT,
  trans_date date NOT NULL,
  trans_descr varchar(100) NOT NULL,
  checking_amount decimal(8,2) DEFAULT NULL,
  vendor varchar(100) DEFAULT NULL,
  trans_comment varchar(500) DEFAULT NULL,
  credit_amount decimal(8,2) DEFAULT NULL,
  credit_balance decimal(8,2) DEFAULT NULL,
  account_name varchar(50) DEFAULT NULL,
  account_id1 int(11) DEFAULT NULL,
  account_id2 int(11) DEFAULT NULL,
  PRIMARY KEY (trans_id)
) ENGINE=InnoDB AUTO_INCREMENT=1534 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table spreadsheet_import
--

DROP TABLE IF EXISTS spreadsheet_import;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE spreadsheet_import (
  trans_date date NOT NULL DEFAULT '0000-00-00',
  trans_descr varchar(100) NOT NULL DEFAULT '',
  amount decimal(9,2)/*old*/ DEFAULT NULL,
  trans_vendor varchar(100) NOT NULL DEFAULT '',
  trans_comment mediumtext NOT NULL,
  checking decimal(9,2)/*old*/ DEFAULT NULL,
  brokerage decimal(9,2)/*old*/ DEFAULT NULL,
  stock decimal(9,2)/*old*/ DEFAULT NULL,
  insurance decimal(9,2)/*old*/ DEFAULT NULL,
  ascent_expense decimal(9,2)/*old*/ DEFAULT NULL,
  paypal decimal(9,2)/*old*/ DEFAULT NULL,
  chase decimal(9,2)/*old*/ DEFAULT NULL,
  discover decimal(9,2)/*old*/ DEFAULT NULL,
  best_buy decimal(9,2)/*old*/ DEFAULT NULL,
  tax_owed decimal(9,2)/*old*/ DEFAULT NULL,
  paycheck decimal(9,2)/*old*/ DEFAULT NULL,
  other_income decimal(9,2)/*old*/ DEFAULT NULL,
  tax decimal(9,2)/*old*/ DEFAULT NULL,
  bills decimal(9,2)/*old*/ DEFAULT NULL,
  cars decimal(9,2)/*old*/ DEFAULT NULL,
  groceries decimal(9,2)/*old*/ DEFAULT NULL,
  food decimal(9,2)/*old*/ DEFAULT NULL,
  home decimal(9,2)/*old*/ DEFAULT NULL,
  electronics decimal(9,2)/*old*/ DEFAULT NULL,
  software decimal(9,2)/*old*/ DEFAULT NULL,
  misc decimal(9,2)/*old*/ DEFAULT NULL,
  import_id smallint NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (import_id)
) ENGINE=InnoDB AUTO_INCREMENT=679 DEFAULT CHARSET=utf8 PACK_KEYS=0 COMMENT='importing from accounting spreadsheets';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2015-12-21 23:00:47
