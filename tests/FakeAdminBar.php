<?php

namespace WpApp\Tests;

class FakeAdminBar {
	public $nodes = [];

	public function add_node( $node ) {
		$this->nodes[ $node['id'] ] = $node;
	}

	public function remove_node( $id ) {
		unset( $this->nodes[ $id ] );
	}
}
