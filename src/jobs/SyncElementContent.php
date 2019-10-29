<?php
/**
 * @link      https://www.goldinteractive.ch
 * @copyright Copyright (c) 2019 Gold Interactive
 */

namespace goldinteractive\sitecopy\jobs;

use Craft;
use craft\base\Element;
use craft\queue\BaseJob;

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
     * @var int[] The sites IDs where we want to overwrite the content
     */
    public $sites;

    /**
     * @var array The content
     */
    public $data;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function execute($queue)
    {
        $elementsService = Craft::$app->getElements();

        // use data only from $this->data, never from this element
        // we cant be sure its the right localized element
        $element = $elementsService->getElementById($this->elementId);

        if (!$element) {
            return;
        }

        $totalSites = count($this->sites);
        $currentSite = 0;

        foreach ($this->sites as $siteId) {
            $this->setProgress($queue, $currentSite / $totalSites, Craft::t('app', '{step} of {total}', [
                'step'  => $currentSite + 1,
                'total' => $totalSites,
            ]));

            /** @var Element $siteElement */
            $siteElement = $elementsService->getElementById($element->id, get_class($element), $siteId);

            $siteElement->setFieldValues($this->data);

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
