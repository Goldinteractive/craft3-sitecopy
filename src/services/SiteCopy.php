<?php
/**
 * @link      https://www.goldinteractive.ch
 * @copyright Copyright (c) 2018 Gold Interactive
 */

namespace goldinteractive\sitecopy\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\base\Model;
use craft\elements\Entry;
use craft\events\ElementEvent;
use craft\helpers\ElementHelper;
use craft\models\Site;
use Exception;
use goldinteractive\sitecopy\jobs\SyncElementContent;
use Throwable;
use yii\base\Event;

/**
 * Class SiteCopy
 *
 * @package goldinteractive\sitecopy\services
 */
class SiteCopy extends Component
{
    /**
     * @var Model|null
     */
    private $settings = null;

    public function init()
    {
        parent::init();

        $this->settings = \goldinteractive\sitecopy\SiteCopy::getInstance()->getSettings();
    }

    public static function getCriteriaFields()
    {
        return [
            [
                'value' => 'id',
                'label' => Craft::t('sitecopy', 'Entry id'),
            ],
            [
                'value' => 'type',
                'label' => Craft::t('sitecopy', 'Entry type (handle)'),
            ],
            [
                'value' => 'section',
                'label' => Craft::t('sitecopy', 'Section (handle)'),
            ],
            [
                'value' => 'site',
                'label' => Craft::t('sitecopy', 'Site (handle)'),
            ],
        ];
    }

    public static function getOperators()
    {
        return [
            [
                'value' => 'eq',
                'label' => Craft::t('sitecopy', 'Equals'),
            ],
            [
                'value' => 'neq',
                'label' => Craft::t('sitecopy', 'Does not equal'),
            ],
        ];
    }

    /**
     * Indicates if we are already syncing
     *
     * @var bool
     */
    private static $syncing = false;

    /**
     * Get list of sites to sync to.
     *
     * @param array $sites
     * @param array $exclude
     * @return array
     */
    public function getSiteInputOptions(array $sites = [], $exclude = [])
    {
        $sites = $sites ?: Craft::$app->getSites()->getAllSites();

        $sites = array_map(
            function ($site) use ($exclude) {
                if (!$site instanceof Site) {
                    $siteId = $site['siteId'] ?? $site ?? null;
                    if ($siteId !== null) {
                        $site = Craft::$app->sites->getSiteById($siteId);
                    }
                }

                if ($site instanceof Site && !in_array($site->id, $exclude)) {
                    $site = [
                        'label' => $site->name,
                        'value' => $site->id,
                    ];
                } else {
                    $site = null;
                }

                return $site;
            },
            $sites
        );

        return array_filter($sites);
    }

    /**
     * Get list of attributes to sync.
     *
     * @return array
     */
    public function getAttributesToCopyOptions()
    {
        return [
            [
                'value' => 'fields',
                'label' => Craft::t('sitecopy', 'Fields (Content)'),
            ],
            [
                'value' => 'title',
                'label' => Craft::t('sitecopy', 'Title'),
            ],
            [
                'value' => 'slug',
                'label' => Craft::t('sitecopy', 'Slug'),
            ],
        ];
    }

    /**
     * @param ElementEvent $event
     * @param array        $elementSettings
     * @throws Throwable
     */
    public function syncElementContent(ElementEvent $event, array $elementSettings)
    {
        /** @var Entry $entry */
        // This is not necessarily our localized entry
        // the EVENT_AFTER_SAVE_ELEMENT gets called multiple times during the save, for each localized entry and draft / revision
        $entry = $event->element;
        $isDraftOrRevision = ElementHelper::isDraftOrRevision($entry);

        if (!$entry instanceof Entry && !$entry instanceof craft\commerce\elements\Product || $isDraftOrRevision) {
            return;
        }

        // we cannot know where to copy the content from
        if (empty($elementSettings['sourceSite'])) {
            return;
        }

        // make sure we are in the correct localized entry
        if ($entry->siteId != $elementSettings['sourceSite']) {
            return;
        }

        if (self::$syncing) {
            return;
        }

        // we only want to add our task to the queue once
        self::$syncing = true;

        // elementSettings will be null in HUD, where we want to continue with defaults
        if ($elementSettings !== null && ($event->isNew || empty($elementSettings['enabled']))) {
            return;
        }

        $attributesToCopy = $this->getAttributesToCopy();

        if (empty($attributesToCopy)) {
            return;
        }

        $supportedSites = $entry->getSupportedSites();

        $targets = $elementSettings['targets'] ?? [];

        if (!is_array($targets)) {
            $targets = [$targets];
        }

        $matchingSites = [];

        foreach ($supportedSites as $supportedSite) {
            $siteId = $supportedSite['siteId'];

            if (!$siteId) {
                $siteId = $supportedSite; // For Products as no siteId key exists
            }

            $siteElement = Craft::$app->elements->getElementById(
                $entry->id,
                null,
                $siteId
            );

            $matchingTarget = in_array($siteId, $targets);

            if ($siteElement && $matchingTarget) {
                $matchingSites[] = (int)$siteId;
            }
        }

        if (!empty($matchingSites)) {
            foreach ($attributesToCopy as $attribute) {
                $tmp = Craft::$app->getRequest()->getBodyParam($attribute);

                // special case, we need to get the data from the model
                if ($attribute == 'fields') {
                    $tmp = $entry->getSerializedFieldValues();
                }

                if (empty($tmp)) {
                    continue;
                }

                $data[$attribute] = $tmp;
            }

            if (empty($data)) {
                return;
            }

            Craft::$app->getQueue()->push(new SyncElementContent([
                'elementId' => (int)$entry->id,
                'sites'     => $matchingSites,
                'data'      => $data,
            ]));
        }
    }

    /**
     * @param Entry|craft\commerce\elements\Product $element
     * @return array
     * @throws Exception
     */
    public function handleSiteCopyActiveState($element)
    {
        if (!is_object($element)) {
            throw new Exception('Given value must be an object!');
        }

        $siteCopyEnabled = false;
        $selectedSites = [];

        $settings = $this->getCombinedSettings();

        foreach ($settings['settings'] as $setting) {
            $criteriaField = $setting[0] ?? null;
            $operator = $setting[1] ?? null;
            $value = $setting[2] ?? null;
            $sourceId = $setting[3] ?? null;
            $targetId = $setting[4] ?? null;

            if (!empty($criteriaField) && !empty($operator) && !empty($value) && !empty($sourceId) && !empty($targetId)) {
                if (($sourceId != '*' && (int)$sourceId != $element->siteId) || ($criteriaField !== 'typeHandle' && !$element->hasProperty($criteriaField))) {
                    continue;
                }

                $checkFrom = false;

                if ($criteriaField === 'id') {
                    $checkFrom = $element->id;
                } elseif (isset($element[$criteriaField]['handle'])) {
                    $checkFrom = $element[$criteriaField]['handle'];
                }

                $check = false;

                if ($operator === 'eq') {
                    $check = $checkFrom == $value;
                } elseif ($operator === 'neq') {
                    $check = $checkFrom != $value;
                }

                if ($check && (int)$targetId !== $element->siteId) {
                    $siteCopyEnabled = true;
                    $selectedSites[] = (int)$targetId;

                    if ($settings['method'] == 'or') {
                        break;
                    }
                } elseif ($settings['method'] == 'and' && (int)$targetId !== $element->siteId) {
                    // check failed, revert values to default
                    $siteCopyEnabled = false;
                    $selectedSites = [];

                    break;
                }
            }
        }

        return [
            'siteCopyEnabled' => $siteCopyEnabled,
            'selectedSites'   => $selectedSites,
        ];
    }

    /**
     * @return array
     */
    public function getAttributesToCopy()
    {
        if ($this->settings && isset($this->settings->attributesToCopy) && is_array($this->settings->attributesToCopy)) {
            return $this->settings->attributesToCopy;
        }

        return [];
    }

    /**
     * @return array
     */
    public function getCombinedSettings()
    {
        $combinedSettings = [];

        // default set to or for backwards compatibility
        $combinedSettingsCheckMethod = 'or';

        if ($this->settings && isset($this->settings->combinedSettings) && is_array($this->settings->combinedSettings)) {
            $combinedSettings = $this->settings->combinedSettings;
        }

        if ($this->settings && isset($this->settings->combinedSettingsCheckMethod) && is_string($this->settings->combinedSettingsCheckMethod)) {
            $combinedSettingsCheckMethod = $this->settings->combinedSettingsCheckMethod;
        }

        return ['settings' => $combinedSettings, 'method' => $combinedSettingsCheckMethod];
    }
}
