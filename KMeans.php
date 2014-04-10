<?php

require_once 'utilities/functions.php';

function ML_KMeans ( $xs, $k )
{
	if ( $k > count( $xs ) ) return false;

	$centroids = _ML_Init_Centroids( $xs, $k );
	$belongs_to = array ();

	do {
		for ( $i = 0; $i < count( $xs ); $i ++ )
			$belongs_to[_ML_Closest_Centroid( $xs[$i], $centroids )][] = $i;

		$old_centroids = $centroids;
		$centroids = _ML_Reposition_Centroids( $centroids, $belongs_to, $xs );

		$continue = ($old_centroids == $centroids);
	} while ( $continue );

	return $belongs_to;
}

function _ML_Reposition_Centroids ( $centroids, $belongs_to, $xs )
{
	for ( $index = 0; $index < count( $centroids ); $index ++ ) {

		$my_observations = $belongs_to[$index];
		$my_obs_values = array ();

		foreach ( $my_observations as $obs )
			$my_obs_values[] = $xs[$obs];

		$my_obs_values = __ML_Flip( $my_obs_values );
		$new_position = array ();

		foreach ( $my_obs_values as $new_dimension )
			$new_position[] = array_sum( $new_dimension ) / count( $new_dimension );

		$centroids[$index] = $new_position;
	}

	return $centroids;
}

function __ML_Flip ( $rows )
{
	return ML_Transpose( $rows );
}

function _ML_Closest_Centroid ( $x, $centroids )
{
	$smallest = null;
	$smallest_distance = PHP_INT_MAX;

	foreach ( $centroids as $index => $centroid ) {
		$distance = __ML_Distance_To_Centroid( $x, $centroid );

		if ( $distance < $smallest_distance ) {
			$smallest = $index;
			$smallest_distance = $distance;
		}
	}

	return $smallest;
}

function __ML_Distance_To_Centroid ( $x, $centroid )
{
	return ML_Euclidian_Distance( $x, $centroid );
}

function _ML_Init_Centroids ( $xs, $k )
{
	if ( $k > count( $xs ) ) return false;

	$centroids = array ();

	for ( $i = 0; $i < $k; $i ++ ) {
		$temp_array = array ();
		$random = rand( 0, count( $xs ) - 1 );
		$temp_array = $xs[$random];
		unset( $xs[$random] );
		$xs = array_values( $xs );

		$centroids[] = $temp_array;
	}

	return $centroids;
}
