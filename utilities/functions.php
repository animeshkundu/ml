<?php

function ML_Transpose ( $rows )
{
	$columns = array ();
	for ( $i = 0; $i < count( $rows ); $i ++ ) {
		for ( $k = 0; $k < count( $rows[$i] ); $k ++ ) {
			$columns[$k][$i] = $rows[$i][$k];
		}
	}
	return $columns;
}

function ML_Mean ( $array )
{
	return array_sum( $array ) / count( $array );
}

function ML_Variance ( $array )
{
	$mean = ML_Mean( $array );

	$sum_difference = 0;
	$n = count( $array );

	for ( $i = 0; $i < $n; $i ++ ) {
		$sum_difference += pow( ($array[$i] - $mean), 2 );
	}

	$variance = $sum_difference / $n;
	return $variance;
}

function ML_Euclidian_Distance ( $a, $b )
{
	if ( count( $a ) != count( $b ) ) return false;

	$distance = 0;
	for ( $i = 0; $i < count( $a ); $i ++ ) {
		$distance += pow( $a[$i] - $b[$i], 2 );
	}

	return sqrt( $distance );
}