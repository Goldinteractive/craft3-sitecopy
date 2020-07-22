<?php
/**
 * @link      https://www.goldinteractive.ch
 * @copyright Copyright (c) 2019 Gold Interactive
 */

namespace goldinteractive\sitecopy\jobs;

use Craft;
use craft\base\Element;
use craft\base\Field;
use craft\queue\BaseJob;

/**
 * Class SyncMatrixContent
 *
 * @package goldinteractive\sitecopy\jobs
 */
class SyncSuperTableContent extends BaseJob {
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
    public $elementSiteId;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function execute($queue)
    {
        $elementsService = Craft::$app->getElements();

        $sourceEntry = $elementsService->getElementById($this->elementId, null, $this->elementSiteId);

        if (!$sourceEntry) {
            return;
        }

        $totalSites = count($this->sites);
        $currentSite = 0;

        foreach ($this->sites as $siteId) {
            $this->setProgress($queue, $currentSite / $totalSites, Craft::t('app', '{step} of {total}', [
                'step'  => $currentSite + 1,
                'total' => $totalSites,
            ]));

            /** @var Element $targetSite */
			$targetSite = $elementsService->getElementById($sourceEntry->id, get_class($sourceEntry), $siteId);
			$fields = $this->getTransatableFields($sourceEntry);
        	foreach($fields as $field) {
				if($field  instanceof \verbb\supertable\fields\SuperTableField) {
					\verbb\supertable\SuperTable::$plugin
                        ->getService()
                        ->duplicateBlocks($field, $sourceEntry, $targetSite);
				}
			}
			
            $targetSite->setScenario(Element::SCENARIO_ESSENTIALS);
            $elementsService->saveElement($targetSite);

            $currentSite++;
        }
	}
	
	private function getTransatableFields(Element $sourceEntry) {
        return $sourceEntry->getFieldLayout()->getFields();
        return array_filter($sourceEntry->getFieldLayout()->getFields(), function(Field $field) {
            return $field->translationMethod === $field::TRANSLATION_METHOD_SITE;
        });
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        return Craft::t('app', 'Syncing SuperTable contents');
    }
}