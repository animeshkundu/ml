<?php

/**
 * Density-Based Clustering
 */

function ML_DBScan ( $data, $e, $minimumPoints = 10 )
{
	$clusters = array ();
	$visited = array ();

	foreach ( $data as $index => $datum ) {
		if ( in_array( $index, $visited ) ) continue;

		$visited[] = $index;

		$regionPoints = _ML_Points_In_Region( array ( $index => $datum ), $data, $e );

		if ( count( $regionPoints ) >= $minimumPoints ) $clusters[] = _ML_Expand_Cluster( array (
			$index => $datum ), $regionPoints, $e, $minimumPoints, &$visited );
	}
}

function _ML_Points_In_Region ( $point, $data, $epsilon )
{
	$region = array ();

	foreach ( $data as $index => $datum )
		if ( ML_Euclidian_Distance( $point, $datum ) < $epsilon )
			$region[$index] = $datum;

	return $region;
}

function _ML_Expand_Cluster ( $point, $data, $epsilon, $minimumPoints, &$visited )
{
	$cluster[] = $point;

	foreach ( $data as $index => $datum ) {

		if ( ! in_array( $index, $visited ) ) {
			$visited[] = $index;
			$regionPoints = _ML_Points_In_Region( array ( $index => $datum ), $data, $epsilon );

			if ( count( $regionPoints ) > $minimumPoints )
				$cluster = _ML_Join_Clusters( $regionPoints, $cluster );
		}

		$cluster[] = array ( $index => $datum );
	}
}

function _ML_Join_Clusters ( $one, $two )
{
	return array_merge( $one, $two );
}
