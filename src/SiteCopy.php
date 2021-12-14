<?php
/**
 * @link      https://www.goldinteractive.ch
 * @copyright Copyright (c) 2018 Gold Interactive
 */

namespace goldinteractive\sitecopy;

use craft\base\Plugin;

use Craft;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\elements\GlobalSet;
use craft\events\ElementEvent;
use craft\services\Elements;
use craft\web\twig\variables\CraftVariable;
use Exception;
use goldinteractive\sitecopy\models\SettingsModel;
use goldinteractive\sitecopy\SiteCopyAsset;
use yii\base\Event;
use craft\events\TemplateEvent;
use craft\web\View;

/**
 * @author    Gold Interactive
 * @package   Gold SiteCopy
 * @since     0.2.0
 *
 */
class SiteCopy extends Plugin
{
    public $schemaVersion = '1.0.2';
    public $hasCpSettings = true;

    public function init()
    {
        parent::init();

        $this->setComponents(
            [
                'sitecopy' => services\SiteCopy::class,
            ]
        );

        if (Craft::$app->getRequest()->getIsCpRequest()) {

            Event::on(
                View::class,
                View::EVENT_BEFORE_RENDER_TEMPLATE,
                function (TemplateEvent $event) {
                    
                    // Get view
                    $view = Craft::$app->getView();

                    // Load JS file
                    $view->registerAssetBundle(SiteCopyAsset::class);

                }
            );

            Event::on(
                CraftVariable::class,
                CraftVariable::EVENT_INIT,
                function (Event $event) {
                    $variable = $event->sender;
                    $variable->set('sitecopy', services\SiteCopy::class);
                }
            );

            Craft::$app->view->hook(
                'cp.globals.edit.content',
                function (array &$context) {
                    /** @var $element GlobalSet */
                    $element = $context['globalSet'];

                    return $this->editDetailsHookGlobals($element);
                }
            );

            Craft::$app->view->hook(
                'cp.assets.edit.meta',
                function (array &$context) {
                    /** @var $element Asset */
                    $element = $context['element'];

                    return $this->editDetailsHookAssets($element);
                }
            );

            Craft::$app->view->hook(
                'cp.entries.edit.details',
                function (array &$context) {
                    /** @var $element craft\elements\Entry */
                    $element = $context['entry'];

                    return $this->editDetailsHookEntries($element);
                }
            );

            Craft::$app->view->hook(
                'cp.commerce.product.edit.details',
                function (array &$context) {
                    /** @var $element craft\commerce\elements\Product */
                    $element = $context['product'];

                    return $this->editDetailsHookEntries($element);
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
            'settings'                    => $this->getSettings(),
            'criteriaFieldOptionsEntries' => services\SiteCopy::getCriteriaFieldsEntries(),
            'criteriaFieldOptionsGlobals' => services\SiteCopy::getCriteriaFieldsGlobals(),
            'criteriaFieldOptionsAssets'  => services\SiteCopy::getCriteriaFieldsAssets(),
            'criteriaOperatorOptions'     => services\SiteCopy::getOperators(),
        ]);
    }

    /**
     * @param Entry|craft\commerce\elements\Product|object $element
     * @param string                                       $template
     * @return string|void
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \yii\base\Exception
     */
    private function editDetailsHook($element, string $template)
    {
        if (!is_object($element)) {
            throw new Exception('Given value must be an object!');
        }

        $isNew = $element->id === null;
        $sites = $element->getSupportedSites();

        if ($isNew || count($sites) < 2) {
            return;
        }

        $scas = $this->sitecopy->handleSiteCopyActiveState($element);

        $siteCopyEnabled = $scas['siteCopyEnabled'];
        $selectedSites = $scas['selectedSites'];

        $currentSite = $element->siteId ?? null;

        return Craft::$app->view->renderTemplate(
            $template,
            [
                'siteId'          => $element->siteId,
                'supportedSites'  => $sites,
                'siteCopyEnabled' => $siteCopyEnabled,
                'selectedSites'   => $selectedSites,
                'currentSite'     => $currentSite,
            ]
        );
    }

    private function editDetailsHookEntries($element)
    {
        return $this->editDetailsHook($element, 'sitecopy/_cp/entriesEditRightPane');
    }

    private function editDetailsHookGlobals($element)
    {
        //todo own scas config
        return $this->editDetailsHook($element, 'sitecopy/_cp/globalsEdit');
    }

    private function editDetailsHookAssets($element)
    {
        return $this->editDetailsHook($element, 'sitecopy/_cp/entriesEditRightPane');
    }
}
