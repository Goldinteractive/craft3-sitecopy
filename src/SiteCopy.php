<?php
/**
 * @link      https://www.goldinteractive.ch
 * @copyright Copyright (c) 2018 Gold Interactive
 */

namespace goldinteractive\sitecopy;

use craft\base\Plugin;

use Craft;
use craft\events\ElementEvent;
use craft\services\Elements;
use craft\web\twig\variables\CraftVariable;
use yii\base\Event;

/**
 * @author    Gold Interactive
 * @package   Gold SiteCopy
 * @since     0.2.0
 *
 */
class SiteCopy extends Plugin
{
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
                    $isNew = $element->id === null;
                    $sites = $element->getSupportedSites();

                    if ($isNew || count($sites) < 2) {
                        return;
                    }

                    return Craft::$app->view->renderTemplate(
                        'sitecopy/_cp/entriesEditRightPane',
                        [
                            'siteId'         => $element->siteId,
                            'supportedSites' => $sites,
                        ]
                    );
                }
            );

            Event::on(
                Elements::class,
                Elements::EVENT_BEFORE_SAVE_ELEMENT,
                function (ElementEvent $event) {
                    $this->sitecopy->syncElementContent($event, Craft::$app->request->post('sitecopy', []));
                }
            );
        }
    }
}