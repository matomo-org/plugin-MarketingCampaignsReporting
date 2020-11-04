<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\MarketingCampaignsReporting\Tracker;

use Piwik\Common;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignContent;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignGroup;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignId;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignKeyword;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignMedium;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignName;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignPlacement;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignSource;
use Piwik\Tracker;

class RequestProcessor extends Tracker\RequestProcessor
{
    public function onNewVisit(Tracker\Visit\VisitProperties $visitProperties, Tracker\Request $request)
    {
        $campaignName      = $visitProperties->getProperty((new CampaignName())->getColumnName());
        $campaignKeyword   = $visitProperties->getProperty((new CampaignKeyword())->getColumnName());
        $campaignMedium    = $visitProperties->getProperty((new CampaignMedium())->getColumnName());
        $campaignContent   = $visitProperties->getProperty((new CampaignContent())->getColumnName());
        $campaignSource    = $visitProperties->getProperty((new CampaignSource())->getColumnName());
        $campaignId        = $visitProperties->getProperty((new CampaignId())->getColumnName());
        $campaignGroup     = $visitProperties->getProperty((new CampaignGroup())->getColumnName());
        $campaignPlacement = $visitProperties->getProperty((new CampaignPlacement())->getColumnName());

        if (!empty($campaignContent) || !empty($campaignId) || !empty($campaignKeyword) ||
            !empty($campaignMedium) || !empty($campaignName) || !empty($campaignSource) ||
            !empty($campaignGroup) || !empty($campaignPlacement)) {
            $visitProperties->setProperty('referer_type', Common::REFERRER_TYPE_CAMPAIGN);
        }

        if ($campaignName) {
            $visitProperties->setProperty('referer_name', substr($campaignName, 0, 255));
        }

        if ($campaignKeyword) {
            $visitProperties->setProperty('referer_keyword', substr($campaignKeyword, 0, 255));
        }
    }
}
