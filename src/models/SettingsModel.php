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
    public $combinedSettings = [];

    /**
     * @var string
     */
    public $combinedSettingsCheckMethod = [];

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            [['combinedSettings'], 'checkCombinedSettings'],
            [['combinedSettingsCheckMethod'], 'in', 'range' => ['and', 'or']],
        ];
    }

    /**
     * Custom validation rule
     */
    public function checkCombinedSettings()
    {
        $criteriaFields = SiteCopy::getCriteriaFields();
        $operators = SiteCopy::getOperators();

        $exactValues = [
            array_map(function ($x) {
                return $x['value'];
            }, $criteriaFields),
            array_map(function ($x) {
                return $x['value'];
            }, $operators),
        ];

        if (!is_array($this->combinedSettings)) {
            $this->addError('combinedSettings', 'invalid array');

            return;
        }

        foreach ($exactValues as $key => $values) {
            foreach ($this->combinedSettings as $setting) {
                if (!in_array($setting[$key], $values)) {
                    $this->addError('combinedSettings', 'invalid value "' . $setting[$key] . '" for options "' . implode(',', $values) . '" given');

                    break 2;
                }
            }
        }

        foreach ($this->combinedSettings as $setting) {
            $setting = $setting[2] ?? null; // 2 = criteria value

            if (empty($setting)) {
                $this->addError('combinedSettings', 'Criteria can\'t be empty');

                break;
            }
        }
    }
}
