<?php

namespace goldinteractive\sitecopy\migrations;

use Craft;
use craft\db\Migration;
use goldinteractive\sitecopy\SiteCopy;

/**
 * m200811_123415_OrXorChange migration.
 */
class m200811_123415_OrXorChange extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $plugin = SiteCopy::getInstance();

        $settings = $plugin->getSettings();

        if (isset($settings['combinedSettingsCheckMethod']) && $settings['combinedSettingsCheckMethod'] === 'or') {
            Craft::$app->plugins->savePluginSettings($plugin,['combinedSettingsCheckMethod' => 'xor']);
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200811_123415_OrXorChange cannot be reverted.\n";
        return false;
    }
}
