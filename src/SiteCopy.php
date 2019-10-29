<?php
/**
 * @link      https://www.goldinteractive.ch
 * @copyright Copyright (c) 2018 Gold Interactive
 */

namespace goldinteractive\sitecopy;

use craft\base\Plugin;

use Craft;
use craft\elements\Entry;
use craft\events\ElementEvent;
use craft\services\Elements;
use craft\web\twig\variables\CraftVariable;
use goldinteractive\sitecopy\models\SettingsModel;
use yii\base\Event;

/**
 * @author    Gold Interactive
 * @package   Gold SiteCopy
 * @since     0.2.0
 *
 */
class SiteCopy extends Plugin
{
    public $hasCpSettings = true;

    public function init()
    {
        parent::init();

        $this->setComponents(
            [
                'sitecopy' => \goldinteractive\sitecopy\services\SiteCopy::class,
            ]
        );

        if (Craft::$app->getRequest()->getIsCpRequest()) {
            Event::on(
                CraftVariable::class,
                CraftVariable::EVENT_INIT,
                function (Event $event) {
                    $variable = $event->sender;
                    $variable->set('sitecopy', \goldinteractive\sitecopy\services\SiteCopy::class);
                }
            );

            Craft::$app->view->hook(
                'cp.entries.edit.details',
                function (array &$context) {
                    /** @var $element craft\elements\Entry */
                    $element = $context['entry'];

                    return $this->editDetailsHook($element);
                }
            );

            Craft::$app->view->hook(
                'cp.commerce.product.edit.details',
                function (array &$context) {
                    /** @var $element craft\commerce\elements\Product */
                    $element = $context['product'];

                    return $this->editDetailsHook($element);
                }
            );

            Event::on(
                Elements::class,
                Elements::EVENT_AFTER_SAVE_ELEMENT,
                function (ElementEvent $event) {
                    $this->sitecopy->syncElementContent($event, Craft::$app->request->post('sitecopy', []));
                }
            );
        }
    }

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): SettingsModel
    {
        return new SettingsModel();
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('sitecopy/_cp/settings', [
            'settings'                => $this->getSettings(),
            'criteriaFieldOptions'    => \goldinteractive\sitecopy\services\SiteCopy::getCriteriaFields(),
            'criteriaOperatorOptions' => \goldinteractive\sitecopy\services\SiteCopy::getOperators(),
        ]);
    }

    /**
     * @param Entry|craft\commerce\elements\Product|object $element
     * @return string|void
     */
    private function editDetailsHook(object $element)
    {
        $isNew = $element->id === null;
        $sites = $element->getSupportedSites();

        if ($isNew || count($sites) < 2) {
            return;
        }

        $scas = $this->sitecopy->handleSiteCopyActiveState($element);

        $siteCopyEnabled = $scas['siteCopyEnabled'];
        $selectedSites = $scas['selectedSites'];

        return Craft::$app->view->renderTemplate(
            'sitecopy/_cp/entriesEditRightPane',
            [
                'siteId'          => $element->siteId,
                'supportedSites'  => $sites,
                'siteCopyEnabled' => $siteCopyEnabled,
                'selectedSites'   => $selectedSites,
            ]
        );
    }
}
