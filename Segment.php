<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\MarketingCampaignsReporting;

class Segment extends \Piwik\Plugin\Segment
{
    protected  function init()
    {
        $this->setCategory('MarketingCampaignsReporting_Title');
    }
}
