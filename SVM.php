<?php

/**
 * SVM using Platt's SMO algorithm.
 */

class SVM {

	const OUTPUT = false;
	const NO_OUTPUT = true;

	const GAMMA = 0.5;
	const CACHE_LRU = 1;
	const EPSILON = 0.001;
	const CACHE_INMEM = 0;
	const UPPER_BOUND = 100;
	const TOLERANCE = 0.001;
	const CACHE_EXTERNAL = 2;

	protected $quiet;
	protected $bias = 0;
	protected $externalFh;
	protected $recordCount;
	protected $useExternal;
	protected $entryLength;
	protected $dotCachePath;

	protected $data = array ();
	protected $targets = array ();
	protected $dotCache = array ();
	protected $errorCache = array ();
	protected $lagrangeMults = array ();

	public function __construct ( $quiet = false, $dotCache = 0, $dotCachePath = '' )
	{
		$this->quiet = $quiet;
		switch ( $dotCache ) {
		case 1 :
			$this->dotCache = new LRU_Array();
			break;
		case 2 :
			$this->useExternal = $dotCache;
			$this->dotCachePath = $dotCachePath;
			$this->entryLength = strlen( pack( 'f', 0.001 ) );
			break;
		}
	}

	public function Train ( $dataFile, $modelFile, $testDataFile = null )
	{
		$this->recordCount = $this->Load_Data( $dataFile );

		if ( $this->useExternal ) $this->Pre_Calculate_Dots();

		for ( $i = 0; $i < $this->recordCount; $i ++ )
			$this->lagrangeMults[$i] = 0;

		$numChanged = 0;
		$examined = 0;

		while ( $numChanged > 0 || $examined == 0 ) {
			$numChanged = 0;

			if ( $examined == 0 ) {
				for ( $i = 0; $i < $this->recordCount; $i ++ )
					$numChanged += $this->Examine_Example( $i );
			} else {
				foreach ( $this->lagrangeMults as $id => $val )
					if ( $val != 0 && $val != self::UPPER_BOUND ) $numChanged += $this->Examine_Example( $id );
			}

			if ( $examined == 0 )
				$examined = 1;
			else if ( $numChanged == 0 ) $examined = 0;
		}

		if ( isset( $modelFile ) && is_string( $modelFile ) ) $this->Write_SVM( $modelFile );

		if ( isset( $this->fh ) ) {
			fclose( $this->fh );
			unset( $this->fh );
		}

		if ( isset( $testDataFile ) && is_string( $testDataFile ) ) $this->Test( $testDataFile, null, null );
	}

	public function Test ( $dataFile, $modelFile = null, $outputFile = null )
	{
		$right = $wrong = 0;

		if ( $modelFile ) {
			$vectorCount = $this->Read_SVM( $modelFile );
		} else if ( ! count( $this->lagrangeMults ) ) {
			if ( ! $this->quiet ) echo 'No model supplied';
			return false;
		} else {
			$vectorCount = $this->recordCount;
		}

		$this->recordCount = $this->Load_Data( $dataFile, $vectorCount );

		if ( $outputFile ) $fh = fopen( $outputFile, 'w' );

		if ( $this->useExternal ) $this->Pre_Calculate_Dots();

		for ( $i = $vectorCount; $i < $this->recordCount; $i ++ ) {
			$classification = $this->Classify( $i );

			if ( $outputFile ) fwrite( $fh, $classification . "\n" );

			if ( isset( $this->targets[$i] ) && $this->targets[$i] != 0 ) {
				if ( ($this->targets[$i] > 0) == ($classification > 0) )
					$right ++;
				else
					$wrong ++;
			}
		}

		if ( $outputFile ) fclose( $fh );

		if ( isset( $this->fh ) ) {
			fclose( $this->fh );
			unset( $this->fh );
		}

		if ( ($right + $wrong) > 0 && ! $this->quiet ) echo "\nAccuracy: " . ($right / ($right + $wrong)) . " over " . ($right + $wrong) . " examples.\n";
	}

	protected function Classify ( $rowID )
	{
		$score = 0;

		foreach ( $this->lagrangeMults as $key => $value ) {
			if ( $value > 0 ) $score += $value * $this->targets[$key] * $this->Kernel( $rowID, $key );
		}

		return $score - $this->bias;
	}

	protected function Kernel ( $indexA, $indexB )
	{
		$score = 2 * $this->Dot( $indexA, $indexB );
		$xsquares = $this->Dot( $indexA, $indexA ) + $this->Dot( $indexB, $indexB );

		return exp( - self::GAMMA * ($xsquares - $score) );
	}

	protected function Dot ( $indexA, $indexB )
	{
		if ( $indexA > $indexB ) list ( $indexA, $indexB ) = array (
			$indexB,
			$indexA );

		if ( $this->useExternal && isset( $this->fh ) ) {
			$recordPos = (((($this->recordCount * $indexA) - ((1 + $indexA) * ($indexA / 2))) + $indexA) * $this->entryLength) + (($indexB - $indexA) * $this->entryLength);
			fseek( $this->fh, $recordPos, SEEK_SET );
			$var = fread( $this->fh, $this->entryLength );
			$p = unpack( 'fscore', $var );

			return $p['score'];
		} else {
			$key = $indexA . '.' . $indexB;

			if ( ! isset( $this->dotCache[$key] ) ) $this->dotCache[$key] = $this->Calc_Dot_Product( $indexA, $indexB );

			return $this->dotCache[$key];
		}
	}

	protected function Calc_Dot_Product ( $indexA, $indexB )
	{
		$score = 0;

		foreach ( $this->data[$indexA] as $id => $val ) {
			if ( isset( $this->data[$indexB][$id] ) ) $score += $this->data[$indexB][$id] * $val;
		}

		return $score;
	}

	protected function Pre_Calculate_Dots ()
	{
		$this->fh = fopen( $this->dotCachePath . 'dotcache.dot', 'wb' );

		for ( $i = 0; $i < $this->recordCount; $i ++ ) {
			for ( $j = $i; $j < $this->recordCount; $j ++ )
				fwrite( $this->fh, pack( 'f', $this->Calc_Dot_Product( $i, $j ) ) );

		}

		fclose( $this->fh );
		$this->fh = fopen( $this->dotCachePath . 'dotcache.dot', 'rb' );
	}

	protected function Write_SVM ( $outputFile )
	{
		$fh = fopen( $outputFile, 'w' );
		fwrite( $fh, $this->bias . "\n" );
		$vectorCount = 0;

		foreach ( $this->lagrangeMults as $val ) {
			if ( $val > 0 ) $vectorCount ++;
		}

		fwrite( $fh, $vectorCount . "\n" );

		foreach ( $this->lagrangeMults as $key => $val ) {
			if ( $val > 0 ) fwrite( $fh, $val . "\n" );
		}

		foreach ( $this->lagrangeMults as $key => $val ) {
			if ( $val > 0 ) {
				$target = $this->targets[$key] > 0 ? '+' : '';
				$target .= $this->targets[$key];
				fwrite( $fh, $target );

				foreach ( $this->data[$key] as $id => $value )
					fwrite( $fh, " " . $id . ":" . $value );

				fwrite( $fh, "\n" );
			}
		}
	}

	protected function Read_SVM ( $modelFile )
	{
		$fh = fopen( $modelFile, 'r' );
		$this->bias = ( float ) fgets( $fh );
		$vectorCount = ( int ) fgets( $fh );

		for ( $i = 0; $i < $vectorCount; $i ++ )
			$this->lagrangeMults[$i] = ( float ) fgets( $fh );

		$this->Read_Data( $fh );

		return $vectorCount;
	}

	protected function Load_Data ( $dataFile, $numLines = 0 )
	{
		$fh = fopen( $dataFile, 'r' );
		$numLines = $this->Read_Data( $fh, $numLines );
		fclose( $fh );

		return $numLines;
	}

	protected function Read_Data ( $dataHandle, $numLines = 0 )
	{
		while ( $line = fgets( $dataHandle ) ) {
			if ( strlen( $line ) < 5 ) continue;

			$tokens = explode( " ", $line );

			if ( count( $tokens ) < 2 ) continue;

			unset( $line );

			$target = array_shift( $tokens );

			if ( $target == '#' ) continue;

			$vector = array ();

			foreach ( $tokens as $token ) {
				if ( strlen( $token ) < 3 ) continue;
				list ( $key, $value ) = explode( ':', $token );
				$vector[$key] = ( float ) $value;
			}

			unset( $tokens );
			$this->data[$numLines] = $vector;
			$this->targets[$numLines] = ( int ) $target;
			$numLines ++;
		}

		return $numLines;
	}

	protected function Examine_Example ( $rowID )
	{
		$target = $this->targets[$rowID];
		$alpha = $this->lagrangeMults[$rowID];

		if ( $alpha > 0 && $alpha < self::UPPER_BOUND )
			$score = $this->errorCache[$rowID];
		else
			$score = $this->Classify( $rowID ) - $target;

		$result = $target * $score;

		if ( ($result < - self::TOLERANCE && $alpha < self::UPPER_BOUND) || ($result > self::TOLERANCE && $alpha > 0) ) {
			$maxTemp = 0;
			$otherRowID = 0;

			foreach ( $this->lagrangeMults as $id => $value ) {
				if ( $value > 0 && $value < self::UPPER_BOUND ) {
					$result2 = $this->errorCache[$id];
					$temp = abs( $result - $result2 );

					if ( $temp > $maxTemp ) {
						$maxTemp = $temp;
						$otherRowID = $id;
					}
				}
			}

			if ( $otherRowID > 0 ) if ( $this->Take_Step( $rowID, $otherRowID ) == 1 ) return 1;

			$endPoint = array_rand( $this->lagrangeMults );

			for ( $k = $endPoint; $k < $this->recordCount + $endPoint; $k ++ ) {
				$otherRowID = $k % $this->recordCount;

				if ( $this->lagrangeMults[$otherRowID] > 0 && $this->lagrangeMults[$otherRowID] < self::UPPER_BOUND ) if ( $this->Take_Step( $rowID, $otherRowID ) == 1 ) return 1;
			}

			$endPoint = array_rand( $this->lagrangeMults );

			for ( $k = $endPoint; $k < $this->recordCount + $endPoint; $k ++ ) {
				$otherRowID = $k % $this->recordCount;

				if ( $this->Take_Step( $rowID, $otherRowID ) == 1 ) return 1;
			}

		}

		return 0;
	}

	protected function Take_Step ( $rowID, $otherRowID )
	{
		if ( $rowID == $otherRowID ) return 0;

		$alpha1 = $this->lagrangeMults[$rowID];
		$target1 = $this->targets[$rowID];

		if ( $alpha1 > 0 && $alpha1 < self::UPPER_BOUND )
			$result1 = $this->errorCache[$rowID];
		else
			$result1 = $this->Classify( $rowID ) - $target1;

		$alpha2 = $this->lagrangeMults[$otherRowID];
		$target2 = $this->targets[$otherRowID];

		if ( $alpha2 > 0 && $alpha2 < self::UPPER_BOUND )
			$result2 = $this->errorCache[$otherRowID];
		else
			$result2 = $this->Classify( $otherRowID ) - $target2;

		$score = $target1 * $target2;

		$low = $high = 0;

		if ( $target1 == $target2 ) {
			$gamma = $alpha1 + $alpha2;
			if ( $gamma > self::UPPER_BOUND ) {
				$low = $gamma - self::UPPER_BOUND;
				$high = self::UPPER_BOUND;
			} else {
				$low = 0;
				$high = $gamma;
			}
		} else {
			$gamma = $alpha1 - $alpha2;
			if ( $gamma > 0 ) {
				$low = 0;
				$high = self::UPPER_BOUND - $gamma;
			} else {
				$low = - $gamma;
				$high = self::UPPER_BOUND;
			}
		}

		if ( $low == $high ) return 0;

		$k11 = $this->Kernel( $rowID, $rowID );
		$k12 = $this->Kernel( $rowID, $otherRowID );
		$k22 = $this->Kernel( $otherRowID, $otherRowID );
		$eta = 2 * $k12 - $k11 - $k22;

		if ( $eta < 0 ) {
			$a2 = $alpha2 + $target2 * ($result2 - $result1) / $eta;

			if ( $a2 < $low )
				$a2 = $low;
			else if ( $a2 > $high ) $a2 = $high;

		} else {
			$x1 = $eta / 2.0;
			$x2 = $target2 * ($result1 - $result2) - $eta * $alpha2;
			$lowObj = $x1 * $low * $low + $x2 * $low;
			$highObj = $x1 * $high * $high + $x2 * $high;

			if ( $lowObj > ($highObj + self::EPSILON) )
				$a2 = $low;
			else if ( $lowObj < ($highObj - self::EPSILON) )
				$a2 = $high;
			else
				$a2 = $alpha2;
		}

		if ( abs( $a2 - $alpha2 ) < self::EPSILON * ($a2 + $alpha2 + self::EPSILON) ) return 0;

		$a1 = $alpha1 - $score * ($a2 - $alpha2);

		if ( $a1 < 0 ) {
			$a2 += $score * $a1;
			$a1 = 0;
		} else if ( $a1 > self::UPPER_BOUND ) {
			$a2 += $score * ($a1 - self::UPPER_BOUND);
			$a1 = self::UPPER_BOUND;
		}

		$b1 = $this->bias + $result1 + $target1 * ($a1 - $alpha1) * $k11 + $target2 * ($a2 - $alpha2) * $k12;
		$b2 = $this->bias + $result2 + $target1 * ($a1 - $alpha1) * $k12 + $target2 * ($a2 - $alpha2) * $k22;

		if ( $a1 > 0 && $a1 < self::UPPER_BOUND )
			$newBias = $b1;
		else if ( $a2 > 0 && $a2 < self::UPPER_BOUND )
			$newBias = $b2;
		else
			$newBias = ($b1 + $b2) / 2;

		$deltaBias = $newBias - $this->bias;
		$this->bias = $newBias;

		$t1 = $target1 * ($a1 - $alpha1);
		$t2 = $target2 * ($a2 - $alpha2);

		foreach ( $this->lagrangeMults as $id => $value )
			if ( $value > 0 && $value < self::UPPER_BOUND ) $this->errorCache[$id] = (isset( $this->errorCache[$id] ) ? $this->errorCache[$id] : 0) + ($t1 * $this->Kernel( $rowID, $id ) + $t2 * $this->Kernel( $otherRowID, $id ) - $deltaBias);

		$this->errorCache[$rowID] = 0;
		$this->errorCache[$otherRowID] = 0;

		$this->lagrangeMults[$rowID] = $a1;
		$this->lagrangeMults[$otherRowID] = $a2;

		return 1;
	}

}


class LRU_Array implements ArrayAccess {

	const CLEAN_PERCENT = 5;

	private $size;
	private $count = 0;
	private $cleanAmount;
	private $data = array ();

	public function __construct ( $size = 400000 )
	{
		$this->size = $size;
		$this->cleanAmount = ceil( $size / self::CLEAN_PERCENT );
	}

	public function Offset_Exists ( $key )
	{
		return isset( $this->data[$key] );
	}

	public function Offset_Get ( $key )
	{
		$value = $this->data[$key];
		unset( $this->data[$key] );
		$this->data[$key] = $value;

		return $value;
	}

	public function Offset_Set ( $key, $value )
	{
		$this->count ++;

		if ( $this->count > $this->size ) $this->Resize();

		$this->data[$key] = $value;
	}

	public function Offset_Unset ( $key )
	{
		unset( $this->data[$key] );
	}

	protected function Resize ()
	{
		$i = 0;
		array_splice( $this->data, 0, $this->cleanAmount );
		$this->count -= $this->cleanAmount;
	}

}