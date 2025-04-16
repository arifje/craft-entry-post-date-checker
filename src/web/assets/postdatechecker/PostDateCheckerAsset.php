<?php

namespace arjanbrinkman\craftentrypostdatechecker\web\assets\postdatechecker;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * Post Date Checker asset bundle
 */
class PostDateCheckerAsset extends AssetBundle
{
	public function init()
	{
		$this->sourcePath = __DIR__ . '/dist';
		$this->depends = [
			CpAsset::class,
		];
		$this->js = [
			'js/postdate-checker.js'
		];
		$this->css = [
			//'css/style.css'
		];
		
		parent::init();
	}
	
}
