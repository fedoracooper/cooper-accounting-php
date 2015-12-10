<?
	$current_page = 'products';
	require('include.php');
	if (!isset ($_SESSION['login_id']))
	{
		// redirect to login if they have not logged in
		header ("Location: login.php");
	}

	$error = '';
	$login_id = $_SESSION['login_id'];
	$sel_category_id = 1;	// default to ALL accounts
	$sel_active = 1;		// default to active products
	$edit_product = new Product();
	$edit_account = new ProductAccount();	

	$mode = '';

	if (isset ($_POST['sel_category_id']))
	{
		// any post should contain filter vars
		$sel_category_id	= $_POST['sel_category_id'];
		$sel_active			= $_POST['sel_active'];
	}

	if (isset ($_GET['edit']))
	{
		// Load a product for editing
		$error = $edit_product->Load_product ($_GET['edit']);
		$account_list = $edit_product->get_account_list();
		if ($error == '' && count($account_list) > 0)
		{
			// load the first account for this product
			$edit_account = $account_list[0];
		}

		// change filter category based on entry
		$sel_category_id = $edit_product->get_category_id();

		$mode = 'load';
	}

	if (isset ($_POST['save']))
	{
		$mode = 'save';
		// Grab data from the form
		/*
			Comments 1/21/2005
			A new product save will typically consist of the basic
			product data, plus one account.  The save button will
			clear the account edit but retain the product edit.
			If new data is entered into the account section, it will
			be saved as a new account.

			Account validation will occur in the following conditions:
				1. any account field is not blank
				2. account is being edited

			Clicking Cancel or Done will prepare for a new product.
			There must be separate delete buttons for the currently
			edited product & account.

			1/24/05
			Currently this page only operates with one product +
			one account.  Also, the Save button will save both and
			then clear the fields.

		*/
		$active = 0;	// checkboxes don't post when unchecked
		if (isset ($_POST['active']))
			$active = 1;
		$error = $edit_product->Init_product (
			$login_id,
			$_POST['category_id'],
			$_POST['prod_name'],
			$_POST['prod_comment'],
			-1, -1,		// modified time & created time are automatic
			$active,
			$_POST['prod_id']
		);

		if ($error == '')
		{
			// Init succeeded, so save
			// Save the product now; for new records, this will get
			// the new prod_id, which is required for the account save.

			if ($edit_product->get_prod_id() < 0)
			{
				// new record save; update category to list
				$sel_category_id = $edit_product->get_category_id();
			}

			$error = $edit_product->Save_product();
		}

		$save_account = false;	// default

		if ($_POST['prod_account_id'] > -1
			|| $_POST['user_name'] != ''
			|| $_POST['password'] != ''
			|| $_POST['email'] != ''
			|| $_POST['serial_num'] != ''
			|| $_POST['account_comment'] )
		{
			// Need to save the account fields
			$error2 = $edit_account->Init_product_account (
				$edit_product->get_prod_id(),
				$_POST['user_name'],
				$_POST['password'],
				$_POST['email'],
				$_POST['serial_num'],
				$_POST['account_comment'],
				$_POST['prod_account_id']
			);

			if ($error == '')
				$error = $error2;

			if ($error == '')
				// No errors initializing or saving product
				$save_account = true;
		}

		if ($save_account)
		{
			// no errors with product or account, so save them
			$error = $edit_account->Save_product_account();
		}

		// clear out fields
		$edit_product = new Product();
		$edit_account = new ProductAccount();
	}

	elseif (isset ($_POST['delete']))
	{
		$mode = 'delete';
		// already confirmed delete
		$error = Product::Delete_product ($_POST['prod_id']);
	}


	// By this point, all data has been initialized from forms,
	// if applicable.

	// Build category dropdowns
	$cat_list = ProductCategory::Get_product_category_list ();
	$cat_list = array ('-1' => '--All Categories--') + $cat_list;
	$cat_dropdown = Build_dropdown ($cat_list, 'sel_category_id',
		$sel_category_id);

	// category dropdown for product
	$cat_list = array ('-1' => '--Select category--') + $cat_list;
	$prod_cat_dropdown = Build_dropdown ($cat_list, 'category_id',
		$edit_product->get_category_id());

	$active_list = array (1=>'Active', 2=>'Inactive');
	$active_dropdown = Build_dropdown ($active_list, 'sel_active',
		$sel_active);


	// Get product list
	$prod_list = NULL;
	$error = Product::Get_product_list ($login_id, $prod_list,
		$sel_category_id, $sel_active);

	// Load category info
	$sel_category = new ProductCategory();
	$sel_category->Load_product_category ($sel_category_id);

?>


<html>
<head>
	<title>Product Numbers</title>
	<link href="style.css" rel="stylesheet" type="text/css">
	<script language="javascript" type="text/javascript">

		function confirmDelete()
		{
			return confirm('Are you sure you want to delete the '
				+ 'current product and ALL its accounts?');
		}

		function clickEdit()
		{
			document.forms[0].editClick = 1;
		}

		function bodyLoad()
		{
			if (document.forms[0].editClick.value == "1")
			{
				document.forms[0].trans_date.focus();
				document.forms[0].trans_date.select();
			}
		}

	</script>
</head>


<body onload="bodyLoad()">
<?= $navbar ?>

<table style="margin-top: 5px;">
	<tr>
		<td><h3>Product Numbers: <?= $sel_category->get_category_name() ?></h3></td>
		<td style="padding-left: 30px;"><?= 
			$sel_category->get_category_comment() ?></td>
	</tr>
</table>

<?
	if ($error != '')
		echo	"<p class=\"error\">$error</p> \n";
?>

<form method="post" action="product-numbers.php">
<table style="margin-left: 15px;">
	<tr>
		<td><?= $cat_dropdown ?></td>
		<td><?= $active_dropdown ?></td>
		<td><input type="submit" name="filter" value="Filter"></td>
	</tr>
</table>

<input type="hidden" name="editClick" value="<?= $editClick ?>">

<table class="summary-list" style="width: 880px;" cellspacing="0" cellpadding="0">
	<tr>
		<th>Product</th>
		<th>Comment</th>
		<th style="width: 90px;">Modified</th>
		<th>User</th>
		<th>Password</th>
		<th>Email</th>
		<th>Serial #</th>
	</tr>

	<?
		// loop through the product list
		foreach ($prod_list as $product)
		{
			echo "	<tr>\n".
				"		<td><a href=\"product-numbers.php?edit=".
					$product->get_prod_id(). "\">".
					$product->get_prod_name(). "</a></td>\n".
				"		<td>". $product->get_prod_comment(). "</td>\n".
				"		<td>". $product->get_modified_time(true). "</td>\n";

			$i = 0;
			foreach ($product->Get_account_list() as $prod_account)
			{
				if ($i > 0)
				{
					// repeat row
					echo "	</tr>\n".
						"	<tr>\n".
						"		<td></td> \n".
						"		<td></td> \n";
				}
				echo "		<td>". $prod_account->get_user_name(). "</td>\n".
					"		<td>". $prod_account->get_password(). "</td>\n".
					"		<td>". $prod_account->get_email(). "</td>\n".
					"		<td>". $prod_account->get_serial_num(). "</td>\n";
			
				$i++;
			}

			echo "	</tr> \n\n";
		}
	?>
</table>

<table class="summary-list" style="margin-top: 10px; width: 880px;">
	<!-- Product information -->
	<tr>
		<td><h4><?
				//if ($mode == 'save' || $mode == 'load')
				if ($edit_product->get_prod_id() >= 0)
					echo 'Edit ';
				else
					echo 'New ';
			?>Product Numbers</h4></td>
	</tr>

	<tr>
		<td><?= $prod_cat_dropdown ?></td>
		<td colspan="2">Product name: 
			<input type="hidden" name="prod_id"
				value="<?= $edit_product->get_prod_id() ?>">
			<input type="text" name="prod_name" 
				value="<?= $edit_product->get_prod_name() ?>"
				size="30" maxlength="50"></td>
		<td>created <?= $edit_product->get_created_time (true) ?></td>
	</tr>

	<tr>
		<td colspan="2">Comments: <input type="text" size="50" maxlength="100"
			name="prod_comment" 
			value="<?= $edit_product->get_prod_comment() ?>"></td>
		<td>Active: <input type="checkbox" name="active" value="1"
			<?= $edit_product->get_active (true) ?>></td>
		<td>modified <?= $edit_product->get_modified_time (true) ?></td>

	<!-- Product action buttons -->	
	<tr>
		<td><input type="submit" value="Save" name="save"></td>
		<td><?
			if ($edit_product->get_prod_id() >= 0)
			{
				// show the Cancel & delete buttons
				//echo '<input type="submit" value="Done" name="done"></td>';
				echo '<input type="submit" value="Cancel" name="cancel"></td>'.
				'<td><input type="submit" onclick="return confirmDelete()" '.
					'value="Delete" name="delete">';
			} ?></td>
	</tr>

	<!-- Product account editing -->
	<tr>
		<td style="fixed-width">User name:
			<input type="hidden" name="prod_account_id"
				value="<?= $edit_account->get_prod_account_id() ?>">
			<input type="text" name="user_name" size="30" maxlength="50"
				value="<?= $edit_account->get_user_name() ?>"></td>
		<td style="fixed-width">Password: <input type="text" name="password" size="30" maxlength="50"
			value="<?= $edit_account->get_password() ?>"></td>
		<td>Email: <input type="text" name="email" size="30" maxlength="100"
			value="<?= $edit_account->get_email() ?>"></td>
		<td style="fixed-width">Serial #: <input type="text" name="serial_num" 
			size="30" maxlength="100"
			value="<?= $edit_account->get_serial_num() ?>"></td>
	</tr>
	<tr>
		<td colspan="2">Comments: <input type="text" name="account_comment" 
			size="50" maxlength="100"
			value="<?= $edit_account->get_account_comment() ?>"></td>
		<td><input type="submit" value="Save" name="save"></td>
		<td><input type="submit" value="Delete account" 
			name="delete_account"></td>
	</tr>

	<!-- List of existing rows, each with an edit button -->
</table>

</form>
</body>
</html>