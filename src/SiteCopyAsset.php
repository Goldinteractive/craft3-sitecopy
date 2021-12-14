<?php
/**
 * @link      https://www.goldinteractive.ch
 * @copyright Copyright (c) 2018 Gold Interactive
 */

namespace goldinteractive\sitecopy;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class SiteCopyAsset extends AssetBundle
{
	public function init()
	{
		// define the path that your publishable resources live
        $this->sourcePath = '@goldinteractive/sitecopy/resources';

        // define the dependencies
        $this->depends = [
            CpAsset::class,
        ];

        // define the relative path to CSS/JS files that should be registered with the page
        // when this asset bundle is registered
        $this->js = [
            'script.js',
        ];

        parent::init();
	}
}
