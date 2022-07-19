<?php
/**
 * @link      https://www.goldinteractive.ch
 * @copyright Copyright (c) 2019 Gold Interactive
 */

namespace goldinteractive\sitecopy\jobs;

use Craft;
use craft\base\Element;
use craft\queue\BaseJob;
use goldinteractive\sitecopy\SiteCopy;

/**
 * Class SyncElementContent
 *
 * @package goldinteractive\sitecopy\jobs
 */
class SyncElementContent extends BaseJob
{
    // Properties
    // =========================================================================

    /**
     * @var int The element ID where we want to perform the syncing on
     */
    public $elementId;

    /**
     * @var int the site ID where we want to copy the content from
     */
    public $sourceSiteId;

    /**
     * @var int[] The sites IDs where we want to overwrite the content
     */
    public $sites;

    /**
     * @var array
     */
    public $attributesToCopy;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function execute($queue)
    {
        $elementsService = Craft::$app->getElements();

        if (empty($this->sites)) {
            return;
        }

        $sourceElement = $elementsService->getElementById($this->elementId, null, $this->sourceSiteId);

        if (!$sourceElement) {
            return;
        }

        $data = [];

        foreach ($this->attributesToCopy as $attribute) {
            if ($attribute == 'fields') {
                $tmp = SiteCopy::getInstance()->sitecopy->getSerializedFieldValues($sourceElement);

                if (empty($tmp)) {
                    continue;
                }
            }else {
                $tmp = $sourceElement->{$attribute};
            }

            $data[$attribute] = $tmp;
        }

        $totalSites = count($this->sites);
        $currentSite = 0;

        foreach ($this->sites as $siteId) {
            $this->setProgress($queue, $currentSite / $totalSites, Craft::t('app', '{step} of {total}', [
                'step'  => $currentSite + 1,
                'total' => $totalSites,
            ]));

            /** @var Element $siteElement */
            $siteElement = $elementsService->getElementById($sourceElement->id, get_class($sourceElement), $siteId);

            foreach ($data as $key => $item) {
                if ($key == 'fields') {
                    $siteElement->setFieldValues($item);
                    continue;
                }

                // this is not possible for custom fields as of craft 3.4.0, make sure they dont reach this
                $siteElement->{$key} = $item;
            }

            $siteElement->setScenario(Element::SCENARIO_ESSENTIALS);
            $elementsService->saveElement($siteElement);

            $currentSite++;
        }
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        return Craft::t('app', 'Syncing element contents');
    }
}
