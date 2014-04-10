<?php

require_once 'utilities/functions.php';

/**
 * Online Anomaly Detector.
 *
 * Can Add_Observations instead of only training once.
 */

class ML_Online_Anomaly_Detection extends ML_Anomaly_Detection {

	private $m2s;
	private $sums;
	private $counts;

	protected $toSave = array ( "sums", "m2s", "counts" );

	public function Add_Observation ( $x )
	{
		if ( ! is_array( $x ) ) $x = array ( $x );

		for ( $i = 0; $i < count( $x ); $i ++ ) {
			$this->sums[$i] += $x[$i];
			$this->counts[$i] ++;
			$this->m2s[$i] += (pow( $x[$i] - $this->mean[$i], 2 ));

			$this->Update_Mean( $i );
			$this->Update_Variance( $i );
		}
	}

	protected function Compute_Parameters ( $xs )
	{
		if ( ! is_array( $xs[0] ) ) $xs = $this->Array_Non_Array( $xs );

		$xs_columns = ML_Transpose( $xs );

		foreach ( $xs_columns as $index => $column ) {
			$this->sums[$index] = $sum = array_sum( $column );
			$this->counts[$index] = $count = count( $column );

			$mean = ML_Mean( $column );
			$sum_difference = 0;
			$n = count( $column );

			for ( $i = 0; $i < $n; $i ++ )
				$sum_difference += pow( ($column[$i] - $mean), 2 );

			$this->m2s[$index] = $sum_difference;

			$this->Update_Mean( $index );
			$this->Update_Variance( $index );
		}
	}

	protected function Update_Mean ( $index )
	{
		$this->mean[$index] = $this->sums[$index] / $this->counts[$index];
	}

	protected function Update_Variance ( $index )
	{
		$this->variance[$index] = $this->m2s[$index] / $this->counts[$index];
	}

}

class ML_Anomaly_Detection {

	protected $mean = array ();
	protected $variance = array ();

	private $learned = false;

	public function Learn ( $xs )
	{
		$this->Compute_Parameters( $xs );
		$this->learned = true;
	}

	public function Is_Anomaly ( $data_point, $p = 0.01 )
	{
		if ( ! $this->learned ) return null;

		$probability = $this->Compute_Probability( $data_point );
		if ( $p === false ) return $probability;

		return ($probability < $p);
	}

	protected $toSave = array ( "mean", "variance" );

	public function Save ()
	{
		$saveString = "";

		foreach ( $this->toSave as $save )
			$saveString .= implode( ",", $this->$save ) . "|";

		$s_learned = intval( $this->learned );

		return $saveString . $s_learned;
	}

	public function Load ( $saveString )
	{
		$saveArray = explode( "|", $saveString );
		if ( count( $saveArray ) != count( $this->toSave ) - 1 ) return false;

		foreach ( $this->toSave as $key => $load )
			$this->$load = explode( ",", $saveArray[$key] );

		$this->learned = ( bool ) end( $saveArray );
	}

	protected function Compute_Probability ( $x )
	{
		if ( ! is_array( $x ) ) $x = array ( $x );

		$prod = 1;

		foreach ( $x as $index => $xi ) {
			$prob = $this->Normal_Pdf( $xi, $this->mean[$index], $this->variance[$index] );
			$prod *= $prob;
		}

		return $prod;
	}

	protected function Normal_Pdf ( $x, $mean, $variance )
	{
		return (1 / (sqrt( 2 * pi() ) * sqrt( $variance )) * (exp( (- 1) * pow( $x - $mean, 2 ) / (2 * $variance) )));
	}

	protected function Compute_Parameters ( $xs )
	{
		if ( ! is_array( $xs[0] ) ) $xs = $this->Array_Non_Array( $xs );

		$xs_columns = ML_Transpose( $xs );

		foreach ( $xs_columns as $index => $column ) {
			$this->mean[$index] = ML_Mean( $column );
			$this->variance[$index] = ML_Variance( $column );
		}
	}

	protected function Array_Non_Array ( $array )
	{
		$return = array ();

		foreach ( $array as $item )
			$return[] = array ( $item );

		return $return;
	}

}