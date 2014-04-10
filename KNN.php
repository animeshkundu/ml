<?php

require_once 'utilities/functions.php';

function ML_NN_Predict ( $xs, $ys, $row, $k )
{
	$distances = ML_Nearest_Neighbors( $xs, $row );
	$distances = array_slice( $distances, 0, $k );

	$predictions = array ();

	foreach ( $distances as $neighbor => $distance )
		$predictions[$ys[$neighbor]] ++;

	asort( $predictions );

	return $predictions;
}

function ML_Nearest_Neighbors ( $xs, $row )
{
	$testPoint = $xs[$row];

	$distances = _ML_Distances_To_Point( $xs, $testPoint );
	return $distances;
}

function _ML_Distances_To_Point ( $xs, $x )
{
	$distances = array ();

	foreach ( $xs as $index => $xi )
		$distances[$index] = ML_Euclidian_Distance( $xi, $x );

	asort( $distances );
	array_shift( $distances );

	return $distances;
}
