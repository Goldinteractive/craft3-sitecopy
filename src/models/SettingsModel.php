<?php
/**
 * @link      https://www.goldinteractive.ch
 * @copyright Copyright (c) 2019 Gold Interactive
 */

namespace goldinteractive\sitecopy\models;

use craft\base\Model;
use goldinteractive\sitecopy\services\SiteCopy;

class SettingsModel extends Model
{
    /**
     * @var array
     */
    public $attributesToCopy = ['fields'];

    /**
     * @deprecated keep for migration
     * @var array
     */
    public $combinedSettings = [];

    /**
     * @var array
     */
    public $combinedSettingsEntries = [];

    /**
     * @var array
     */
    public $combinedSettingsGlobals = [];

    /**
     * @var array
     */
    public $combinedSettingsAssets = [];

    /**
     * @var string
     */
    public $combinedSettingsCheckMethod = '';

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            [['attributesToCopy'], 'checkAttributesToCopy'],
            [['combinedSettingsEntries'], 'checkCombinedSettingsEntries'],
            [['combinedSettingsGlobals'], 'checkCombinedSettingsGlobals'],
            [['combinedSettingsAssets'], 'checkCombinedSettingsAssets'],
            [['combinedSettingsCheckMethod'], 'in', 'range' => ['and', 'or', 'xor']],
        ];
    }

    /**
     * Custom validation rule
     */
    public function checkAttributesToCopy()
    {
        $attributesToCopy = \goldinteractive\sitecopy\SiteCopy::getInstance()->sitecopy->getAttributesToCopyOptions();

        $exactValues = [
            array_map(function ($x) {
                return $x['value'];
            }, $attributesToCopy),
        ];

        if (!is_array($this->attributesToCopy)) {
            $this->addError('attributesToCopy', 'invalid array');

            return;
        }

        foreach ($exactValues as $key => $values) {
            foreach ($this->attributesToCopy as $setting) {
                if (!in_array($setting, $values)) {
                    $this->addError('attributesToCopy', 'invalid value "' . $setting . '" for options "' . implode(',', $values) . '" given');

                    break 2;
                }
            }
        }
    }

    /**
     * Custom validation rule
     */
    public function checkCombinedSettingsEntries()
    {
        $this->checkCombinedSettings('combinedSettingsEntries', SiteCopy::getCriteriaFieldsEntries());
    }

    public function checkCombinedSettingsGlobals()
    {
        $this->checkCombinedSettings('combinedSettingsGlobals', SiteCopy::getCriteriaFieldsGlobals());
    }

    public function checkCombinedSettingsAssets()
    {
        $this->checkCombinedSettings('combinedSettingsAssets', SiteCopy::getCriteriaFieldsAssets());
    }

    public function checkCombinedSettings(string $attribute, array $criteriaFields)
    {
        $operators = SiteCopy::getOperators();

        $exactValues = [
            array_map(function ($x) {
                return $x['value'];
            }, $criteriaFields),
            array_map(function ($x) {
                return $x['value'];
            }, $operators),
        ];

        if (!is_array($this->{$attribute})) {
            $this->addError($attribute, 'invalid array');

            return;
        }

        foreach ($exactValues as $key => $values) {
            foreach ($this->{$attribute} as $setting) {
                if (!in_array($setting[$key], $values)) {
                    $this->addError($attribute, 'invalid value "' . $setting[$key] . '" for options "' . implode(',', $values) . '" given');

                    break 2;
                }
            }
        }

        foreach ($this->{$attribute} as $setting) {
            $setting = $setting[2] ?? null; // 2 = criteria value

            if (empty($setting)) {
                $this->addError($attribute, 'Criteria can\'t be empty');

                break;
            }
        }
    }
}
