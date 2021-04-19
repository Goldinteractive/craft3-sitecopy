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
use craft\elements\Asset;
use craft\elements\db\ElementQuery;
use craft\elements\Entry;
use craft\elements\GlobalSet;
use craft\events\ElementEvent;
use craft\helpers\ElementHelper;
use craft\models\Site;
use Exception;
use goldinteractive\sitecopy\jobs\SyncElementContent;
use Throwable;

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

    public static function getCriteriaFieldsEntries()
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

    public static function getCriteriaFieldsGlobals()
    {
        return [
            [
                'value' => 'id',
                'label' => Craft::t('sitecopy', 'Global set id'),
            ],
            [
                'value' => 'handle',
                'label' => Craft::t('sitecopy', 'Global set handle'),
            ],
            [
                'value' => 'site',
                'label' => Craft::t('sitecopy', 'Site (handle)'),
            ],
        ];
    }

    public static function getCriteriaFieldsAssets()
    {
        return [
            [
                'value' => 'id',
                'label' => Craft::t('sitecopy', 'Asset id'),
            ],
            [
                'value' => 'volume',
                'label' => Craft::t('sitecopy', 'Volume (handle)'),
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
        /** @var Entry|GlobalSet $entry */
        // This is not necessarily our localized entry
        // the EVENT_AFTER_SAVE_ELEMENT gets called multiple times during the save, for each localized entry and draft / revision
        $entry = $event->element;
        $isDraftOrRevision = ElementHelper::isDraftOrRevision($entry);

        if ((!$entry instanceof Entry && !$entry instanceof craft\commerce\elements\Product && !$entry instanceof GlobalSet && !$entry instanceof Asset) || $isDraftOrRevision) {
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

        if ($entry instanceof GlobalSet) {
            $attributesToCopy = ['fields'];
        }elseif ($entry instanceof Asset) {
            $attributesToCopy = ['fields']; // todo maybe title too?
        }else {
            $attributesToCopy = $this->getAttributesToCopy();
        }

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
            $siteId = $supportedSite;  // For Products as no siteId key exists

            if (is_array($siteId) && isset($siteId['siteId'])) {
                $siteId = $siteId['siteId'];
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
                    $queryStart = Entry::find();

                    if ($entry instanceof GlobalSet) {
                        $queryStart = GlobalSet::find();
                    }

                    if ($entry instanceof craft\commerce\elements\Product) {
                        $queryStart = craft\commerce\elements\Product::find();
                    }

                    if ($entry instanceof Asset) {
                        $queryStart = Asset::find();
                    }

                    $refetchedEntry = $queryStart
                        ->id($entry->id)
                        ->siteId($entry->siteId)
                        ->anyStatus()
                        ->one();

                    $tmp = $this->getSerializedFieldValues($refetchedEntry);
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
     * @param Entry|craft\commerce\elements\Product|GlobalSet $element
     */
    public function getSerializedFieldValues($element)
    {
        $fields = Craft::$app->fields->getFieldsByLayoutId($element->getFieldLayout()->id);
        $serializedValues = [];

        // fix for https://github.com/spicywebau/craft-neo/issues/391
        // child elements of a disabled neo blocks will still be in the serialized response, but their parent not
        // solution: copy all disabled blocks too
        foreach ($fields as $field) {
            $value = $element->getFieldValue($field->handle);

            if ($value instanceof ElementQuery) {
                $serializedValues[$field->handle] = $field->serializeValue($value->status([Element::STATUS_ENABLED, Element::STATUS_DISABLED]), $element);
            } else {
                $serializedValues[$field->handle] = $field->serializeValue($value, $element);
            }
        }

        return $serializedValues;
    }

    /**
     * @param Entry|craft\commerce\elements\Product|GlobalSet|Asset $element
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

        $settings = $this->getCombinedSettings($element);

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

                if ($criteriaField === 'id' || $criteriaField === 'handle') {
                    $checkFrom = $element->{$criteriaField};
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

                    if ($settings['method'] == 'xor') {
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
     * @param Entry|craft\commerce\elements\Product|GlobalSet|Asset $element
     *
     * @return array
     */
    public function getCombinedSettings($element)
    {
        $combinedSettings = [];

        // default set to xor for backwards compatibility
        $combinedSettingsCheckMethod = 'xor';

        $attribute = 'combinedSettingsEntries';

        if ($element instanceof GlobalSet) {
            $attribute = 'combinedSettingsGlobals';
        }elseif ($element instanceof Asset) {
            $attribute = 'combinedSettingsAssets';
        }

        if ($this->settings && isset($this->settings->{$attribute}) && is_array($this->settings->{$attribute})) {
            $combinedSettings = $this->settings->{$attribute};
        }

        if ($this->settings && isset($this->settings->combinedSettingsCheckMethod) && is_string($this->settings->combinedSettingsCheckMethod)) {
            $combinedSettingsCheckMethod = $this->settings->combinedSettingsCheckMethod;
        }

        return ['settings' => $combinedSettings, 'method' => $combinedSettingsCheckMethod];
    }
}
