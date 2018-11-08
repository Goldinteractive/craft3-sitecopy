<?php
/**
 * @link      https://www.goldinteractive.ch
 * @copyright Copyright (c) 2018 Gold Interactive
 */

namespace goldinteractive\sitecopy\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\elements\Entry;
use craft\events\ElementEvent;
use craft\models\Site;
use yii\base\Event;

/**
 * Class SiteCopy
 *
 * @package goldinteractive\sitecopy\services
 */
class SiteCopy extends Component
{
    /**
     * Indicates if we are already syncing
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
                    $siteId = $site['siteId'] ?? null;
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
     * @param ElementEvent $event
     * @param array        $elementSettings
     * @throws \Throwable
     */
    public function syncElementContent(ElementEvent $event, array $elementSettings)
    {
        /** @var Entry $entry */
        $entry = $event->element;

        if (self::$syncing) {
            return;
        }

        self::$syncing = true;

        // elementSettings will be null in HUD, where we want to continue with defaults
        if ($elementSettings !== null && ($event->isNew || empty($elementSettings['enabled']))) {
            return;
        }

        $supportedSites = $entry->getSupportedSites();

        $targets = $elementSettings['targets'];
        if (!is_array($targets)) {
            $targets = [$targets];
        }

        foreach ($supportedSites as $supportedSite) {
            $siteId = $supportedSite['siteId'];
            $siteElement = Craft::$app->elements->getElementById(
                $entry->id,
                null,
                $siteId
            );
            $matchingTarget = $targets === '*' || in_array($siteId, $targets);

            if ($siteElement && $matchingTarget && $entry->siteId !== $siteId) {
                $fieldsLocation = Craft::$app->getRequest()->getParam('fieldsLocation', 'fields');
                $siteElement->setFieldValuesFromRequest($fieldsLocation);

                Craft::$app->elements->saveElement($siteElement);
            }
        }

        self::$syncing = false;
    }
}