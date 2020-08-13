<?php

namespace goldinteractive\sitecopy\migrations;

use Craft;
use craft\db\Migration;
use goldinteractive\sitecopy\SiteCopy;

/**
 * m200813_090815_RenameCombinedSettings migration.
 */
class m200813_090815_RenameCombinedSettings extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
       $plugin = SiteCopy::getInstance();

        $settings = $plugin->getSettings();

        if (isset($settings['combinedSettings']) && !empty($settings['combinedSettings'])) {
            $oldSettings = $settings['combinedSettings'];

            Craft::$app->plugins->savePluginSettings($plugin, [
                'combinedSettings'        => [],
                'combinedSettingsEntries' => $oldSettings,
            ]);
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200813_090815_RenameCombinedSettings cannot be reverted.\n";
        return false;
    }
}
