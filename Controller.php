<?php
/**
 * Copyright (C) Piwik PRO - All rights reserved.
 *
 * Using this code requires that you first get a license from Piwik PRO.
 * Unauthorized copying of this file, via any medium is strictly prohibited.
 *
 * @link http://piwik.pro
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
