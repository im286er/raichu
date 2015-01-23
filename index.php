<?php
require_once('src/config.php');
use \raichu\Raichu as raichu;

raichu::route()
	->add('GET', '/f', function ($matches, $req, $res) {
		$res->setContentType('txt');
		echo 1;
	})
	->get('/ff', function () {
		echo raichu::request_getQuery('a', null, 'escape');
		echo 2;
	})
	->all(function () {
		raichu::response_setContentType('txt');

		$tree = new \vakata\database\orm\Table(raichu::db(), 'tree_mixed');
		$chld = clone $tree;
		$tree->hasMany($chld, 'pid', 'children');
		$chld->hasMany(clone $tree, 'pid', 'children');
		$temp = $tree->filter("pid = 0")->get();

		/*
		foreach($temp as $node) {
			echo $node->id . ":" . $node->nm . "\n";
			foreach($node->children() as $child) {
				echo "  " . $child->id . ":" . $child->nm . "\n";
				foreach($child->children() as $grand) {
					echo "    " . $grand->id . ":" . $grand->nm . "\n";
					foreach($grand->children() as $ggrand) {
						echo "      " . $ggrand->id . ":" . $ggrand->nm . "\n";
						foreach($ggrand->children() as $gggrand) {
							echo "        " . $gggrand->id . ":" . $gggrand->nm . "\n";
							$gggrand->children()[] = ["nm" => "КОР"];
							foreach($gggrand->children() as $ggggrand) {
								echo "          " . $ggggrand->id . ":" . $ggggrand->nm . "\n";
							}
						}
					}
				}
			}
		}
		$temp->save();
		*/
		
		// var_dump($temp);

		// echo $temp[0]->children()[0]->children()[0]->nm;
	})
	->error(function () {
		echo 3;
	})
	->run(raichu::request(), raichu::response());

//raichu::response()->addFilter(function ($data, $mime) {
//	return $data;
//});
