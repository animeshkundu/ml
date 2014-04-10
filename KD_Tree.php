<?php

class KD_TreeNode {

	public $x;
	public $y;
	public $axis;
	public $left;
	public $right;

	function __construct ( $x, $y )
	{
		$this->x = $x;
		$this->y = $y;
		$this->axis = - 1;
		$this->left = null;
		$this->right = null;
	}

}

class KD_Tree {

	public $root;

	function __construct ( &$param )
	{
		if ( is_array( $param ) ) {
			$end = count( $param ) - 1;
			$this->root = KD_Tree::createTree( $param, 0, $end, 0 );
		} elseif ( is_object( $param ) ) {
			$this->root = $param;
		}
	}

	private static function partition_KD ( &$list, $begin, $end, $pivot, $axis )
	{
		$val = $list[$pivot];
		$tmp = $list[$end];
		$list[$end] = $val;
		$list[$pivot] = $tmp;

		$toSwap = $begin;
		for ( $i = $begin; $i <= $end - 1; $i ++ ) {
			$cmp1 = 0;
			$cmp2 = 0;

			if ( $axis == 0 ) {
				$cmp1 = $list[$i]->x;
				$cmp2 = $val->x;
			}

			if ( $axis == 1 ) {
				$cmp1 = $list[$i]->y;
				$cmp2 = $val->y;
			}

			if ( $cmp1 <= $cmp2 ) {
				$tmp = $list[$toSwap];
				$list[$toSwap] = $list[$i];
				$list[$i] = $tmp;
				$toSwap ++;
			}
		}

		$tmp = $list[$end];
		$list[$end] = $list[$toSwap];
		$list[$toSwap] = $tmp;

		return $toSwap;
	}

	private static function select_KD ( &$list, $begin, $end, $k, $axis )
	{
		$idx = 0;
		$target = 0;
		srand();
		while ( 1 ) {
			$pivot = rand( 0, $end - $begin ) + $begin;
			$idx = KD_Tree::partition_KD( $list, $begin, $end, $pivot, $axis );
			$target = $idx - $begin + 1;

			if ( $target == $k ) {
				return $idx;
			} else if ( $k < $target ) {
				$end = $idx - 1;
			} else {
				$k = $k - $target;
				$begin = $idx + 1;
			}
		}
	}

	/* Create a KD_Tree given a list of KD_Tree nodes. */
	private static function createTree ( &$list, $begin, $end, $depth )
	{
		$axis = $depth % 2;

		if ( $begin == $end ) {
			$list[$begin]->axis = $axis;
			return $list[$begin];
		}


		$k = floor( ($end - $begin) / 2 + $begin ) + 1;
		$selected = KD_Tree::select_KD( $list, $begin, $end, $k, $axis );

		$list[$selected]->axis = $axis;
		if ( $selected - 1 >= $begin ) {
			$list[$selected]->left = KD_Tree::createTree( $list, $begin, $selected - 1, $depth + 1 );
		}
		if ( $end >= $selected + 1 ) {
			$list[$selected]->right = KD_Tree::createTree( $list, $selected + 1, $end, $depth + 1 );
		}

		return $list[$selected];
	}

	public function queryTree ( $query_node )
	{
		$selected_reference_value = 0;
		$selected_coordinate = 0;
		$cur_distance = - 1;
		$cur_node = null;
		$ret = null;

		$stack = array ();
		$node = $this->root;
		while ( $node != null ) {
			array_push( $stack, $node );

			if ( $node->axis == 0 ) {
				$selected_reference_value = $node->x;
				$selected_coordinate = $query_node->x;
			} else {
				$selected_reference_value = $node->y;
				$selected_coordinate = $query_node->y;
			}
			if ( $selected_coordinate <= $selected_reference_value ) {
				if ( $node->left != null ) {
					$node = $node->left;
				} else {
					break;
				}
			} else {
				if ( $node->right != null ) {
					$node = $node->right;
				} else {
					break;
				}
			}
		}

		while ( count( $stack ) > 0 ) {
			$node = array_pop( $stack );
			$distance = ($node->x - $query_node->x) * ($node->x - $query_node->x) + ($node->y - $query_node->y) * ($node->y - $query_node->y);

			if ( $cur_distance < 0 ) {
				$cur_distance = $distance;
				$cur_node = $node;
			} else {
				if ( $cur_distance > $distance ) {
					$cur_distance = $distance;
					$cur_node = $node;
				}
			}

			if ( $node->left == null && $node->right == null ) continue;

			if ( $node->axis == 0 ) {
				$selected_reference_value = $node->x;
				$selected_coordinate = $query_node->x;
			} else {
				$selected_reference_value = $node->y;
				$selected_coordinate = $query_node->y;
			}

			$distance = ($selected_reference_value - $selected_coordinate) * ($selected_reference_value - $selected_coordinate) - $cur_distance;

			if ( $distance < 0 ) {
				if ( $selected_coordinate <= $selected_reference_value ) {
					if ( $node->right != null ) {
						$right_tree = new KD_Tree( $node->right );
						$ret = $right_tree->queryTree( $query_node );
					}
				} else {
					if ( $node->left != null ) {
						$left_tree = new KD_Tree( $node->left );
						$ret = $left_tree->queryTree( $query_node );
					}
				}

				if ( $ret != null && $cur_distance > $ret[0] ) {
					$cur_distance = $ret[0];
					$cur_node = $ret[1];
				}
			}
		}

		return array ( $cur_distance, $cur_node );
	}

}
