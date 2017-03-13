<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\AdvancedCampaignReporting\Tracker;

use Piwik\Plugins\AdvancedCampaignReporting\Columns\CampaignContent;
use Piwik\Plugins\AdvancedCampaignReporting\Columns\CampaignId;
use Piwik\Plugins\AdvancedCampaignReporting\Columns\CampaignKeyword;
use Piwik\Plugins\AdvancedCampaignReporting\Columns\CampaignMedium;
use Piwik\Plugins\AdvancedCampaignReporting\Columns\CampaignName;
use Piwik\Plugins\AdvancedCampaignReporting\Columns\CampaignSource;
use Piwik\Tracker;
use Piwik\Common;

class RequestProcessor extends Tracker\RequestProcessor
{
    public function onNewVisit(Tracker\Visit\VisitProperties $visitProperties, Tracker\Request $request)
    {
        $campaignName = $visitProperties->getProperty((new CampaignName())->getColumnName());
        $campaignKeyword = $visitProperties->getProperty((new CampaignKeyword())->getColumnName());
        $campaignMedium = $visitProperties->getProperty((new CampaignMedium())->getColumnName());
        $campaignContent = $visitProperties->getProperty((new CampaignContent())->getColumnName());
        $campaignSource = $visitProperties->getProperty((new CampaignSource())->getColumnName());
        $campaignId = $visitProperties->getProperty((new CampaignId())->getColumnName());

        if (!empty($campaignContent) || !empty($campaignId) || !empty($campaignKeyword) ||
            !empty($campaignMedium) || !empty($campaignName) || !empty($campaignSource)) {
            $visitProperties->setProperty('referer_type', Common::REFERRER_TYPE_CAMPAIGN);
        }

        if ($campaignName) {
            $visitProperties->setProperty('referer_name', substr($campaignName, 0, 70));
        }

        if ($visitProperties->getProperty($campaignKeyword)) {
            $visitProperties->setProperty('referer_keyword', substr($campaignKeyword, 0, 70));
        }
    }
}
