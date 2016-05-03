<?php
/*	Accounting Include file
	Created 9/22/2004 by Cooper Blake

	Modified 10/11/2004: moved class definitions to external files.
*/

$START_TIME = microtime(true);

if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == "off") {
	// Redirect HTTP to HTTPS
	$redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	header('HTTP/1.1 301 Moved Permanently');
	header('Location: ' . $redirect);

	exit();
}


// **************************  Session Initialization **************************

// Override cookie params to set Secure = true
session_set_cookie_params(
	0,     // Lifetime; 0 = until browser close
	'/',   // Path
	NULL,  // Domain
	true   // Secure:  only send cookies over HTTPS
);
session_start();

$SESSION_TIMEOUT_MINUTES = 240;     // After 4 hours of inactivity, logout the user
$SESSION_ID_TIMEOUT_MINUTES = 30;   // Every 30 minutes, get new session ID for better security.

if (isset($_SESSION['LAST_ACTIVITY']) && 
	(time() - $_SESSION['LAST_ACTIVITY'] > $SESSION_TIMEOUT_MINUTES * 60)) {
	// Session has expired
	session_unset();     // unset $_SESSION variable for the run-time 
	session_destroy();   // destroy session data in storage
}
$_SESSION['LAST_ACTIVITY'] = time(); // update last activity time stamp

// Get a fresh session ID every 30 minutes; this does not logout the user.
if (!isset($_SESSION['SESSION_CREATED'])) {
	$_SESSION['SESSION_CREATED'] = time();
} else if (time() - $_SESSION['SESSION_CREATED'] > $SESSION_ID_TIMEOUT_MINUTES * 60) {
	// session started more than 30 minutes ago
	session_regenerate_id(true);    // change session ID for the current session and invalidate old session ID
	$_SESSION['SESSION_CREATED'] = time();  // update creation time
}

$login_admin = 0;
$login_id = -1;
if (isset ($_SESSION['login_id']))
{
	$login_id = $_SESSION['login_id'];
	$login_admin = $_SESSION['login_admin'];
} else {
	// No session.  Only permit one page:  Login (also handles Logout)
	if ($current_page != 'login') {
		// no session & not on login page
		header ("Location: login.php");
		exit();	
	}
}

// **************************  End Session Initialization **************************



// Define constants here
define( 'DISPLAY_DATE',	'm/d/Y' );			// MM/DD/YYYY
define( 'SQL_DATE',		'Y-m-d' );			// YYYY-MM-DD
define( 'LONG_DATE',	'M j, Y g:i a' );	// Mon D, YYYY H:MM pm

// Setup timezone
date_default_timezone_set('America/New_York');

// Global variables for performance metrics
$connTime = 0.0;
$execTime = 0.0;
$readTime = 0.0;
$txTime = 0.0;
$sqlCount = 0;
$pdo = NULL;


require ('accountAuditClass.php');
require ('accountClass.php');
require ('accountSavingsClass.php');
require ('budgetClass.php');
require ('incomeEntryClass.php');
require ('ledgerEntryClass.php');
require ('loginClass.php');
require ('transactionClass.php');


// define top header
if (!isset ($current_page))
	$current_page = '';

$navbar = "<ul class='nav-list'> \n".
	"	<li><a id='index-link' href='index.php'>Ledger</a></li> \n".
	"	<li><a id='account_details-link' href='account_details.php'> Account Details </a></li> \n".
	"	<li id='reports-link' class='dropdown'><a href='#' class='drop-button'> Reports </a> \n".
	"		<div class='dropdown-content'> \n".
	"			<a id='account_summary-link' href='account_summary.php'> Monthly Comparisons </a> \n".
	"			<a id='account_breakdown-link' href='account_breakdown.php'> Period Breakdown </a> \n".
	"			<a id='car_stats-link' href='car_stats.php'> Car Statistics </a> \n".
	"			<a id='export-link' href='export.php'> Export to QIF </a> \n".
	"		</div> \n".
	"	</li> \n".
	"	<li><a id='edit_budgets-link' href='edit_budgets.php'> Budget </a></li> \n";

if ($login_admin >= 1)
{
	$navbar .= "		<li><a id='accounts-link' href='accounts.php'> Accounts </a></li> \n";
}
if ($login_admin > 1)
{
	// top-level admin
	$navbar .= "		<li><a id='logins-link' href='logins.php'> Manage Logins </a></li> \n";
}
if ($login_id > -1) {
	$navbar .= "		<li><a href='login.php?logout=1'>Logout ".
		$_SESSION['display_name']. "</a></li> \n";
}
$navbar .= "	</ul> \n\n";

$title = '';
$activeClass = ' class="active-nav"';
$parentLink = '';
switch ($current_page) {
	case 'account_breakdown':
		$title = 'Period Breakdown';
		$parentLink = 'reports-link';
		break;
	case 'account_details':
		$title = 'Account Details';
		break;
	case 'account_summary':
		$title = 'Account Summary';
		$parentLink = 'reports-link';
		break;
	case 'accounts':
		$title = 'Accounts Management';
		break;
	case 'car_stats':
		$title = 'Car Fuel Usage Statistics';
		$parentLink = 'reports-link';
		break;
	case 'edit_budgets':
		$title = 'Monthly Budget';
		break;
	case 'export':
		$title = 'Export to QIF File';
		$parentLink = 'reports-link';
		break;
	case 'index':
		$title = 'Account Ledger';
		break;
	case 'login':	// Login page
		$title = 'Accounting Login';
		break;
	case 'logins':	// Manage Logins
		$title = 'Login Management';
		break;
}



// UTILITY FUNCTIONS
//------------------------------------------------------------------------------


// Build an error message from the PDO object if there is
// an error code.  Otherwise return an empty string.
function get_pdo_error(PDOStatement $pdo)
{
	$errorInfo = $pdo->errorInfo();
	if ($errorInfo != NULL && count($errorInfo) > 0 && $errorInfo[0] != '00000')
	{
		// Get error description
		// ErrorInfo:  SQLSTATE, driver error code, driver msg
		$error = $errorInfo[2];
		if ($error == '') {
			$error = 'SQL error code: ' . $errorInfo[0];
		}
		return $error;
	}
	return '';
}

// Return new PDO object, or a string error msg on failure.
function db_connect_pdo()
{
	// Re-use PDO connection for each request on the page.
	// Under high latency, new connections cost about 0.6 seconds each!
	global $pdo, $connTime, $sqlCount;
	$sqlCount++;
	if ($pdo != NULL) {
		return $pdo;
	}

	// Get DB connection string injected from Cloud Foundry or Apache
	$dbUrl = $_SERVER['DATABASE_URL'];
	// Postgres URL style:  postgres://user:password@host:port/database
	// First, split by colon
	$dbFields = explode(':', $dbUrl);
	// Further split 2 more strings
	$passHost = explode('@', $dbFields[2]);
	$portDb = explode('/', $dbFields[3]);
	if (count($dbFields) != 4) {
		die("Unable to parse DATABASE_URL: $dbUrl");
	}

	$protocol = $dbFields[0]; 
	$prefix = NULL;
	if ($protocol == 'postgres') {
		$prefix = 'pgsql';  // PDO driver name for Postgres
	}
	$host = $passHost[1];
	$port = $portDb[0];
	$db = $portDb[1];
	$user = substr($dbFields[1], 2); // Skip '//' before username
	$pass = $passHost[0];

	$pdoDsn = "$prefix:host=$host;port=$port;dbname=$db";
	//error_log("PDO DSN: '$pdoDsn'");
	
	$start = microtime(true);
	try {
		// Note:  ATTR_PERSISTENT = true improves performance under high latency,
		// but has frequent problems with stale connections.
		$pdo = new PDO($pdoDsn, $user, $pass,
		   array(PDO::ATTR_PERSISTENT => false));
		 
		$connTime += microtime(true) - $start;
		
		return $pdo;
	} catch (PDOException $ex) {
		die ('Error connecting to PDO database: ' . $ex->getMessage());
	}
}

// return an html-formatted error string for the database, if there
// is an error.
function db_error ($rs, $sql)
{
	$str = '';
	if (!$rs)
	{
		$str = "<br>Error getting transaction list;<br>SQL: $sql\n".
				"<br><span class=\"error\">". mysql_error(). "</span><br>";
	}
	return $str;
}

// Given a list of key => value pairs, build an HTML dropdown menu.
// If a selected value is supplied, an item with its value will be preselected.
// If onchange is non-empty, the javascript event handler is embedded.
function Build_dropdown ($data_list, $dropdown_name, $selected_value = '',
	$onChange = '', $setId = true)
{
	$html = "<select name=\"$dropdown_name\"";
	if ($setId) {
		$html .= " id=\"$dropdown_name\"";
	}
	if ($onChange != '')
		$html .= " onChange=\"$onChange\"";
	$html .= "> \n";
	foreach ($data_list as $value => $text)
	{
		// echo "key => val: $value => $text<br>";
		$html .= "		<option value=\"$value\"";
		if ($selected_value <> '' && $value == $selected_value)
		{
			$html .= " SELECTED";
		}
		$html .= ">$text</option> \n";
	}
	$html .= "</select>";

	return $html;
}

// Given an array of LedgerEntry objects,
// check to see if the given index exists.  If so,
// return it; otherwise, return a default LedgerEntry.
function GetLedger ($ledgerList, $x)
{
	if (array_key_exists ($x, $ledgerList))
	{
		return $ledgerList[$x];
	}
	
	return new LedgerEntry();
}

// Parse date in SQL style:  YYYY-MM-DDDD and return UNIX timestamp.
function parse_sql_date($date_str) {
	$dateObj = DateTime::createFromFormat(SQL_DATE, $date_str);
	if ($dateObj == FALSE) {
		return -1;
	}

	return $dateObj->getTimestamp();
	
}

// Expects a date string of the format mm/dd/yy
// Returns the timestamp value or -1 when invalid
function parse_date ($date_str)
{
	$dateArr = explode('/', $date_str);
	if (count ($dateArr) != 3)
	{
		return -1;
	}
	foreach ($dateArr as $val)
	{
		if (!is_numeric ($val))
		{
			//echo "non-numeric date part";
			return -1;	// non-integer value
		}
	}
	if (!checkdate ($dateArr[0], $dateArr[1], $dateArr[2]))
	{
		//echo "failed checkdate";
		return -1;	// invalid numbers
	}
	else
		return mktime (0, 0, 0, $dateArr[0], $dateArr[1], $dateArr[2]);
}

// Returns the last auto_increment value from the current connection.
// If no value is found, it returns -1.
// Note: this assumes that a db connection is already open
function get_auto_increment($pdo, $seq)
{
	// Find out the auto_increment value that was created
	$sql = "SELECT currval(:seq) ";
	$ps = $pdo->prepare($sql);
	$ps->bindParam(':seq', $seq);
	$ps->execute();
	$row = $ps->fetch();
	if ($row != NULL)
	{
		// Successful query
		if ($row[0] > 0)
			return $row[0];
	}

	return -1;
}

// Converts date strings between formats:
// mm/dd/yy (normal) and yyyy-mm-dd (mySQL)
// mode = 1  - normal -> mysql
// mode = 2  - mysql -> normal
// Returns empty string on failure
// Updated to accept date in *any* format
function convert_date ($dateStr, $mode)
{
	$dateObj = new DateTime($dateStr);
	if ($mode == 1) {
		return $dateObj->format(SQL_DATE);
	} elseif ($mode == 2) {
		return $dateObj->format(DISPLAY_DATE);
	}
}

// Convert a DateTime object into a SQL String
function dateTimeToSQL(DateTime $dateTime) {
	return $dateTime->format(SQL_DATE);
}

// Adds specified number of months to UNIX timestamp.
// The time parameter is passed by reference & modified.
function add_months (&$time, $num_months)
{
	if (!is_int ($time))
		return 'Invalid timestamp';
	if (!is_int ($num_months))
		return 'Invalid # of months';

	$dateArr = getdate ($time);

	$time = mktime (0, 0, 0,
		$dateArr['mon'] + $num_months, $dateArr['mday'], $dateArr['year']);

	return '';
}

// Given a number, this will return an HTML-formatted
// one. Negatives are red, and a dollar sign will be shown.
// If negativeStyle is NULL, we won't inject a span element
// with red color CSS style.
function format_currency ($amount, $negativeStyle = 'negative')
{
	$txt = '';
	if (is_numeric ($amount))
	{
		if (abs($amount) < 0.001) {
			// Fractional floating point error; set to 0
			// to avoid spurious negative signs with 0.00.
			$amount = 0.0;
		}
		$amount_str = '$' . number_format ($amount, 2);
		if ($amount < 0.0 && $negativeStyle != NULL) {
			$txt =  "<span class='$negativeStyle'> $amount_str </span>";
		} else {
			$txt = $amount_str;
		}
	}

	return $txt;
}

function format_percent($amount, $digits) {
	if (is_numeric($amount)) {
		return number_format ($amount, 0) . '%';
	} else {
		return '';
	}
}

// Given a number, format to 2 decimal places
function format_amount($amount)
{
	if ($amount == null) {
		return null;
	}
	return sprintf('%.2f', $amount);
}

// Get HTML "checked" value if a value is set
function get_checked($value) {
	if ($value != null && $value != 0) {
		return " CHECKED";
	} else {
		return "";
	}
}


if (!isset($buildHtmlHeaders)) {
	// default to on
	$buildHtmlHeaders = true;
}

if ($buildHtmlHeaders) {
	// Start HTML document

	header ("Content-Type: text/html; charset=utf-8");

?>
<!DOCTYPE HTML>

<html>
<head>
	<title><?= $title ?></title>
	<meta charset="UTF-8">
	<link href="style.css" rel="stylesheet" type="text/css">
	<script src="https://code.jquery.com/jquery-2.2.3.js" ></script>
	<script>
		var currentPage = "<?= $current_page ?>";
		var parentLink = "<?= $parentLink ?>";
	
		$(document).ready(function() {
			// Make current nav link bold
			var activeLinkId = '#' + currentPage + '-link';
			$(activeLinkId).addClass('active-nav');
			
			if (parentLink != '') {
				$('#' + parentLink).addClass('active-reports');
			}
		});

		/* Format amount into a text string starting with '$' and with thousands separators.
		 */
		function formatCurrency(num) {
			if (Math.abs(num) < 0.001) {
				// Very small / rounding error; set to 0 to avoid "-0.00".
				num = 0.0;
			}
			var numStr = num.toFixed(2);
			return '$' + numStr.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
		}
		
		/* Helper method to add a class to a given jQuery element, or to
		   remove it when enable is false.
		 */
		function setCssClass(jQueryElem, cssClass, enable) {
			if (enable) {
				jQueryElem.addClass(cssClass);
			} else {
				jQueryElem.removeClass(cssClass);
			}
		}
		
		function getNumberOrZero(val) {
			if ($.isNumeric(val)) {
				return Number(val);
			} else {
				return 0.0;
			}
		}
		
		// Convert formatted currency amount to javascript numeric.
		// Invalid or empty string will be returned as 0.
		function currencyToNum(amount) {
			// Strip out $ sign and thousands separators
			var stripped = amount.replace(/[$,]/g, '');
			return getNumberOrZero(stripped);
		}
	</script>
<?php

} // end building HTML headers
?>
