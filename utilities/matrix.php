<?php

class ML_Matrix {

	private $_data;

	function __construct ( $array )
	{
		$this->Set_Data( $array );
	}

	function ML_Matrix ( $array )
	{
		return $this->__construct( $array );
	}

	public static function Identity ( $size = 3 )
	{
		$result = array ();

		for ( $i = 0; $i < $size; $i ++ )
			for ( $j = 0; $j < $size; $j ++ )
				$result[$i][$j] = ($j == $i);


		return new ML_Matrix( $result );
	}

	public function Invert ()
	{
		$return = array ();

		for ( $i = 0; $i < $this->Rows(); $i ++ ) {
			for ( $j = 0; $j < $this->Columns(); $j ++ ) {
				$cofactor = $this->Get_Cofactor_Matrix( $i, $j );
				$return[$i][$j] = (pow( - 1, $i + $j ) * $cofactor->Determinant());
			}
		}

		$return = new ML_Matrix( $return );
		$det = $return->Determinant();

		if ( $det == 0 ) return false;

		$return = $return->Scalar_Multiply( 1 / $return->Determinant() );
		$return->Transpose();
		return $return;
	}

	public function Transpose ()
	{
		$return = array ();

		for ( $i = 0; $i < $this->Rows(); $i ++ )
			for ( $j = 0; $j < $this->Columns(); $j ++ )
				$return[$j][$i] = $this->Get( $i, $j );

		return new ML_Matrix( $return );
	}

	public function Determinant ()
	{
		$return = 0;
		if ( $this->Columns() == 1 ) return $this->Get( 0, 0 );

		for ( $i = 0; $i < $this->Columns(); $i ++ ) {
			$cofactor = $this->Get_Cofactor_Matrix( 0, $i );
			$multipland = (pow( (- 1), $i ) * $this->Get( 0, $i ));
			$return += $cofactor->Determinant() * $multipland;
		}

		return $return;
	}

	public function Subtract ( ML_Matrix $matrix )
	{
		if ( is_array( $matrix ) ) $matrix = new ML_Matrix( $matrix );

		if ( $this->Rows() != $matrix->Rows() || $this->Columns() != $matrix->Columns() ) return false;

		$return = array ();

		for ( $i = 0; $i < $this->Rows(); $i ++ )
			for ( $j = 0; $j < $this->Columns(); $j ++ )
				$return[$i][$j] = $this->Get( $i, $j ) - $matrix->Get( $i, $j );

		return new ML_Matrix( $return );
	}

	public function Add ( ML_Matrix $matrix )
	{
		if ( is_array( $matrix ) ) $matrix = new ML_Matrix( $matrix );

		if ( $this->Rows() != $matrix->Rows() || $this->Columns() != $matrix->Columns() ) return false;

		$return = array ();

		for ( $i = 0; $i < $this->Rows(); $i ++ )
			for ( $j = 0; $j < $this->Columns(); $j ++ )
				$return[$i][$j] = $this->Get( $i, $j ) + $matrix->Get( $i, $j );

		return new ML_Matrix( $return );
	}

	public function Scalar_Multiply ( $value )
	{
		$return = array ();

		for ( $i = 0; $i < $this->Rows(); $i ++ )
			for ( $j = 0; $j < $this->Columns(); $j ++ )
				$return[$i][$j] = $this->Get( $i, $j ) * $value;

		return new ML_Matrix( $return );
	}

	private function Rebuild ( $array )
	{
		$return = array ();

		$tiles_width = count( $array );
		$tiles_height = count( $array[0] );

		for ( $n = 0; $n < $tiles_width; $n ++ ) {
			for ( $m = 0; $m < $tiles_height; $m ++ ) {

				if ( is_array( $array[$n][$m] ) ) {
					$division_width = count( $array[$n][$m] );
					$division_height = count( $array[$n][$m][$i] );

					for ( $i = 0; $i < $division_width; $i ++ ) {
						for ( $j = 0; $j < $division_height; $j ++ ) {
							$destination_n = $n * $division_width + $i;
							$destination_m = $m * $division_height + $j;

							$return[$destination_n][$destination_m] = $array[$n][$m][$i][$j];
						}
					}
				} else if ( is_a( $array[$n][$m], "ML_Matrix" ) ) {
					$division_width = $array[$n][$m]->Columns();
					$division_height = $array[$n][$m]->Rows();

					for ( $i = 0; $i < $division_width; $i ++ ) {
						for ( $j = 0; $j < $division_height; $j ++ ) {
							$destination_n = $n * $division_width + $i;
							$destination_m = $m * $division_height + $j;

							$return[$destination_n][$destination_m] = $array[$n][$m]->Get( $i, $j );
						}
					}
				}
			}
		}

		return new ML_Matrix( $return );
	}

	private function Sub_Divide ( ML_Matrix $matrix, $n_wide, $m_tall )
	{
		$per_width = ($this->Columns() / $n_wide);
		$per_height = ($this->Rows() / $m_tall);

		if ( ( float ) $per_width != ( float ) round( $per_width ) || ( float ) $per_height != ( float ) round( $per_height ) ) return false;

		$return = array ();

		for ( $n = 0; $n < $n_wide; $n ++ )
			for ( $m = 0; $m < $m_tall; $m ++ )
				for ( $i = 0; $i < $per_width; $i ++ )
					for ( $j = 0; $j < $per_height; $j ++ )
						$return[$n][$m][$i][$j] = $matrix->Get( $i + ($n * $per_width), $j + ($m * $per_height) );


		return $return;
	}

	public function Multiply ( ML_Matrix $matrix )
	{
		return $this->Strassen_Multiply( $matrix );
	}

	public function Strassen_Multiply ( ML_Matrix $matrix )
	{
		if ( $this->Columns() != $matrix->Rows() ) return false;

		if ( $this->Columns() < 32 ) return $this->Naive_Multiply( $matrix );

		$subdivisions = $this->Sub_Divide( $this, 2, 2 );

		if ( $subdivisions === false ) return $this->Naive_Multiply( $matrix );

		$a11 = new ML_Matrix( $subdivisions[0][0] );
		$a12 = new ML_Matrix( $subdivisions[0][1] );
		$a21 = new ML_Matrix( $subdivisions[1][0] );
		$a22 = new ML_Matrix( $subdivisions[1][1] );

		$subdivisions = $this->Sub_Divide( $matrix, 2, 2 );

		if ( $subdivisions === false ) return $this->Naive_Multiply( $matrix );

		$b11 = new ML_Matrix( $subdivisions[0][0] );
		$b12 = new ML_Matrix( $subdivisions[0][1] );
		$b21 = new ML_Matrix( $subdivisions[1][0] );
		$b22 = new ML_Matrix( $subdivisions[1][1] );

		$m1_1 = ($a11->Add( $a22 ));
		$m1_2 = ($b11->Add( $b22 ));
		$m1 = $m1_1->Strassen_Multiply( $m1_2 );
		unset( $m1_1 );
		unset( $m1_2 );

		$m2_1 = $a21->Add( $a22 );
		$m2 = $m2_1->Strassen_Multiply( $b11 );
		unset( $m2_1 );

		$m3_1 = $b12->Subtract( $b22 );
		$m3 = $a11->Strassen_Multiply( $m3_1 );
		unset( $m3_1 );

		$m4_1 = $b21->Subtract( $b11 );
		$m4 = $a22->Strassen_Multiply( $m4_1 );

		$m5_1 = $a11->Add( $a12 );
		$m5 = $m5_1->Strassen_Multiply( $b22 );

		$m6_1 = $a21->Subtract( $a11 );
		$m6_2 = $b11->Add( $b12 );
		$m6 = $m6_1->Strassen_Multiply( $m6_2 );

		$m7_1 = $a12->Add( $a22 );
		$m7_2 = $b21->Add( $b22 );
		$m7 = $m7_1->Strassen_Multiply( $m7_2 );

		$c11 = $m1->Add( $m1 );
		$c11 = $c11->Add( $m4 );
		$c11 = $c11->Subtract( $m5 );
		$c11 = $c11->Add( $m7 );

		$c12 = $m3->Add( $m5 );
		$c21 = $m2->Add( $m4 );

		$c22 = $m1->Subtract( $m2 );
		$c22 = $c22->Add( $m3 );
		$c22 = $c22->Add( $m6 );

		$result = array ( array ( $c11, $c12 ), array ( $c21, $c22 ) );
		return $this->Rebuild( $result );
	}

	public function Naive_Multiply ( ML_Matrix $matrix )
	{
		if ( is_array( $matrix ) ) $matrix = new ML_Matrix( $matrix );

		if ( $this->Columns() != $matrix->Rows() ) return false;

		$result = array ();
		for ( $a = 0; $a < $this->Rows(); $a ++ ) {
			for ( $b = 0; $b < $matrix->Columns(); $b ++ ) {
				$result[$a][$b] = 0;
				for ( $i = 0; $i < $this->Columns(); $i ++ )
					$result[$a][$b] += ($this->Get( $a, $i ) * $matrix->Get( $i, $b ));
			}
		}

		return new ML_Matrix( $result );
	}

	public function Get_Cofactor_Matrix ( $cofactorRow, $cofactorColumn )
	{
		$return = array ();
		for ( $i = 0, $a = 0; $i < $this->Rows(); $i ++ ) {
			$b = 0;
			if ( $i != $cofactorRow ) {
				for ( $j = 0; $j < $this->Columns(); $j ++ ) {
					if ( $j != $cofactorColumn ) {
						$return[$a][$b ++] = $this->Get( $i, $j );
					}
				}
				$a ++;
			}

		}
		return new ML_Matrix( $return );
	}

	public function Get ( $row, $column )
	{
		return $this->_data[$row][$column];
	}

	public function Set ( $row, $column, $value )
	{
		return ($this->_data[$row][$column] = $value);
	}

	public function Columns ()
	{
		return count( $this->_data[0] );
	}

	public function Rows ()
	{
		return count( $this->_data );
	}

	private function Set_Data ( $array )
	{
		if ( ! is_array( $array ) ) {
			var_dump( $array );
			throw Exception( "Degenerate matrix." );
		}

		foreach ( $array as $row => $vector ) {
			if ( ! is_array( $vector ) ) {
				$this->_data[$row][0] = $vector;
			} else {
				foreach ( $vector as $col => $cell ) {
					$this->_data[$row][$col] = $cell;
				}
			}
		}

		return $this->_data;
	}

	public function __toString ()
	{
		$string = "";

		for ( $i = 0; $i < $this->Rows(); $i ++ ) {
			for ( $j = 0; $j < $this->Columns(); $j ++ )
				$string .= $this->Get( $i, $j ) . " ";

			$string .= "\n";
		}

		return $string;
	}

}
