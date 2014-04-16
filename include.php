<?
/*	Accounting Include file
	Created 9/22/2004 by Cooper Blake

	Modified 10/11/2004: moved class definitions to external files.
*/

header ("Content-type: text/html; charset=utf-8");

echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" '.
'"http://www.w3.org/TR/html4/loose.dtd">' . "\n\n";


session_start();

// Define constants here
define( 'DISPLAY_DATE',	'm/d/Y' );			// MM/DD/YYYY
define( 'SQL_DATE',		'Y-m-d' );			// YYYY-MM-DD
define( 'LONG_DATE',	'M j, Y g:i a' );	// Mon D, YYYY H:MM pm

// Setup timezone
date_default_timezone_set('America/New_York');


require ('accountClass.php');
require ('transactionClass.php');
require ('loginClass.php');
require ('productAccountClass.php');
require ('accountAuditClass.php');
require ('budgetClass.php');

$login_admin = 0;
$login_id = -1;
if (isset ($_SESSION['login_id']))
{
	$login_id = $_SESSION['login_id'];
	$login_admin = $_SESSION['login_admin'];
}

// define top header
if (!isset ($current_page))
	$current_page = '';

$navbar = "<table class=\"navbar\"> \n".
	"	<tr>\n".
	"		<td><a href=\"index.php\"";
if ($current_page == 'index')
	$navbar .= ' style="font-weight: bold;"';
$navbar .= ">Ledger</a></td> \n".
	"		<td><a href=\"account_summary.php\"";
if ($current_page == 'account_summary')
	$navbar .= ' style="font-weight: bold;"';
$navbar .=	">Monthly Comparisons</a></td> \n".
	"		<td><a href=\"account_breakdown.php\"";
if ($current_page == 'account_breakdown')
	$navbar .= ' style="font-weight: bold;"';
$navbar .= ">Period Breakdown</a></td> \n";

$navbar .= "		<td><a href=\"account_details.php\"";
if ($current_page == 'account_details')
	$navbar .= ' style="font-weight: bold;"';
$navbar .= ">Account Details</a></td> \n";

$navbar .= "		<td><a href=\"edit_budgets.php\"";
if ($current_page == 'edit_budgets')
	$navbar .= ' style="font-weight: bold;"';
$navbar .= ">Budget</a></td> \n";

if ($login_admin >= 1)
{
	$navbar .= "		<td><a href=\"accounts.php\"";
	if ($current_page == 'accounts')
		$navbar .= ' style="font-weight: bold;"';
	$navbar .= ">Accounts</a></td> \n";
}
if ($login_admin > 1)
{
	// top-level admin
	$navbar .= "		<td><a href=\"logins.php\"";
	if ($current_page == 'logins')
		$navbar .= ' style="font-weight: bold;"';
	$navbar .= ">Manage Logins</a></td> \n";
}
if ($login_id > -1)
	$navbar .= "		<td><a href=\"login.php?logout=1\">Logout ".
		$_SESSION['display_name']. "</a></td> \n";
$navbar .= "	</tr>\n".
	"</table> \n\n";


// UTILITY FUNCTIONS
//------------------------------------------------------------------------------
function db_connect()
{
	$link = mysql_connect('localhost', 'accounting_user', 'accounting');
	mysql_select_db('accounting');
	return $link;
}

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
	$host = 'localhost';
	$db = 'accounting';
	$user = 'accounting_user';
	$pass = 'accounting';
	try {
		$pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
		return $pdo;
	} catch (PDOException $ex) {
		return 'Error connecting to PDO database: ' . $ex->getMessage();
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
	$onChange = '')
{
	$html = "<select name=\"$dropdown_name\" id=\"$dropdown_name\"";
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

// Given an array & two indicies, do a lookup
function ArrVal ($data, $x, $y = -1)
{
	if (array_key_exists ($x, $data))
	{
		$subarr = $data[$x];
		if ($y == -1)
			return $subarr;
		elseif (array_key_exists ($y, $subarr))
		{
			// valid y index & value is found
			return $subarr[$y];
		}
	}
	return array (-1, -1, '');	//default empty array
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
function get_auto_increment($pdo)
{
	// Find out the auto_increment value that was created
	$sql = "SELECT last_insert_id() ";
	//$pdo = db_connect_pdo();
	$ps = $pdo->prepare($sql);
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
function convert_date ($dateStr, $mode)
{
	$newDate = '';
	$seperator = '/';
	if ($mode == 2)
		$seperator = '-';
	$dateArr = explode($seperator, $dateStr);
	if (count ($dateArr) < 3)
		return '';

	if ($mode == 1)
	{
		// convert to mysql date
		$newDate = $dateArr[2]. '-' .
			$dateArr[0]. '-'.
			$dateArr[1];
	}
	else
	{
		// convert to normal date
		$newDate = $dateArr[1]. '/'.
			$dateArr[2]. '/'.
			$dateArr[0];
	}

	return $newDate;
}

// Convert a DateTime object into a SQL String
function dateTimeToSQL(DateTime $dateTime) {
	return $dateTime->format('Y-m-d');
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
// one. Negatives are red, and a dollar sign will be shown
function format_currency ($amount)
{
	$txt = '';
	if (is_numeric ($amount))
	{
		$amount_str = number_format ($amount, 2);
		if ($amount < 0)
			$txt =  '<span style="color: red;">$'.
				$amount_str. '</span>';
		else
			$txt = '$'. $amount_str;
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

?>
