<?php
	$current_page = 'car_stats';
	require('include.php');
	if (!isset ($_SESSION['login_id']))
	{
		// redirect to login if they have not logged in
		header ("Location: login.php");
		exit();
	}

	$fuel_list = array();
	// Get fuel consumption data
	$error = Account::Get_gas_totals ($fuel_list);

?>

</head>


<body onload="bodyLoad()">
<?= $navbar ?>

<h3><?= $title ?></h3>

<table class="summary-list">

	<tr>
		<th>Year</th>
		<th>Account</th>
		<th>Tanks</th>
		<th>Total miles</th>
		<th>Total galls</th>
		<th>Total $</th>
		<th>Avg MPG</th>
		<th>Avg $ / gallon</th>
		<th>Avg miles / tank</th>
	</tr>

<?php
		//	array (account_name, accounting_year, num_records, total_miles, 
		//		total_gallons, total_dollars)
	foreach ($fuel_list as $fuel_data)
	{
		$total_miles	= number_format ($fuel_data[3]);
		$total_gallons	= number_format ($fuel_data[4], 1);
		$total_dollars	= number_format ($fuel_data[5], 2);
		$avg_mpg = '0.0';
		$cost_per_gall = '0.00';

		if ($fuel_data[4] != 0.0)
		{
			$avg_mpg = sprintf ("%0.1f", $fuel_data[3] / $fuel_data[4]);
			$cost_per_gall = number_format ($fuel_data[5] / $fuel_data[4], 2);
		}
		$avg_miles = '0.0';
		if ($fuel_data[1] != 0)
		{
			$avg_miles = number_format ($fuel_data[3] / $fuel_data[2], 1);
		}

		echo "	<tr> \n".
			"		<td>$fuel_data[1]</td> \n".
			"		<td>$fuel_data[0]</td> \n".
			"		<td style=\"text-align: right;\">$fuel_data[2]</td> \n".
			"		<td style=\"text-align: right;\">$total_miles</td> \n".
			"		<td style=\"text-align: right;\">$total_gallons</td> \n".
			"		<td style=\"text-align: right;\">\$$total_dollars</td> \n".
			"		<td style=\"text-align: right;\">$avg_mpg</td> \n".
			"		<td style=\"text-align: right;\">\$$cost_per_gall</td> \n".
			"		<td style=\"text-align: right;\">$avg_miles</td> \n".
			"	</tr> \n\n" ;
	}
?>
</table>

</body>
</html>
