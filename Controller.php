<?php
/**
 * Piwik PRO -  Premium functionality and enterprise-level support for Piwik Analytics
 *
 * @link http://piwik.pro
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\AdvancedCampaignReporting;

use Piwik\View;

/**
 *
 * @package AdvancedCampaignReporting
 */
class Controller extends \Piwik\Plugin\Controller
{

    public function indexCampaigns()
    {
        $view = new View('@AdvancedCampaignReporting/index');
        $view->name = $this->getName(true);
        $view->source = $this->getSource(true);
        $view->medium = $this->getMedium(true);
        $view->keyword = $this->getKeyword(true);
        $view->content = $this->getContent(true);
        $view->combinedSourceMedium = $this->getSourceMedium(true);
        return $view->render();
    }

    public function getKeywordContentFromNameId()
    {
        return $this->renderReport(__FUNCTION__);
    }

    public function getNameFromSourceMediumId()
    {
        return $this->renderReport(__FUNCTION__);
    }

    public function getName()
    {
        return $this->renderReport(__FUNCTION__);
    }

    public function getSource()
    {
        return $this->renderReport(__FUNCTION__);
    }

    public function getMedium()
    {
        return $this->renderReport(__FUNCTION__);
    }

    public function getKeyword()
    {
        return $this->renderReport(__FUNCTION__);
    }

    public function getContent()
    {
        return $this->renderReport(__FUNCTION__);
    }

    public function getSourceMedium()
    {
        return $this->renderReport(__FUNCTION__);
    }
}
