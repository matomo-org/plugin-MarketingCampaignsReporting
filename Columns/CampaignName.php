<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\MarketingCampaignsReporting\Columns;

use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Plugins\MarketingCampaignsReporting\Campaign\CampaignDetector;
use Piwik\Plugins\MarketingCampaignsReporting\Campaign\CampaignDetectorInterface;
use Piwik\Plugins\MarketingCampaignsReporting\MarketingCampaignsReporting;
use Piwik\Tracker\Action;
use Piwik\Tracker\Request;
use Piwik\Tracker\TrackerConfig;
use Piwik\Tracker\Visitor;

class CampaignName extends Base
{
    protected $columnName = 'campaign_name';
    protected $columnType = 'VARCHAR(255) NULL';
    protected $segmentName = 'campaignName';
    protected $nameSingular = 'MarketingCampaignsReporting_Name';

    /**
     * If we should create a new visit when the campaign changes, check if the campaign info changed and if so
     * force the tracker to create a new visit.i
     *
     * @param Request     $request
     * @param Visitor     $visitor
     * @param Action|null $action
     * @return bool
     */
    public function shouldForceNewVisit(Request $request, Visitor $visitor, Action $action = null)
    {
        if (TrackerConfig::getConfigValue('create_new_visit_when_campaign_changes', $request->getIdSiteIfExists()) != 1) {
            return false;
        }

        /** @var CampaignDetector|CampaignDetectorInterface $campaignDetector */
        $campaignDetector   = StaticContainer::get('advanced_campaign_reporting.campaign_detector');
        $campaignParameters = MarketingCampaignsReporting::getCampaignParameters();

        $campaignDimensions = $campaignDetector->detectCampaignFromRequest(
            $request,
            $campaignParameters
        );

        // we force a new visit if the referrer is a campaign and it's different than the currently recorded referrer.
        // if the current referrer is 'direct entry', however, we assume the referrer information was sent in a later request, and
        // we just update the existing referrer information instead of creating a visit.
        if (!empty($campaignDimensions)
            && $this->isCampaignInformationNew($visitor, $campaignDimensions)
        ) {
            Common::printDebug("Existing visit detected, but creating new visit because campaign information is different than last action.");

            return true;
        }

        return false;
    }

    protected function isCampaignInformationNew(Visitor $visitor, $campaignDimensions)
    {
        foreach (MarketingCampaignsReporting::getAdvancedCampaignFields() as $infoName) {
            if ($this->hasReferrerColumnChanged($visitor, $campaignDimensions, $infoName)) {
                return true;
            }
        }
        return false;
    }

    protected function hasReferrerColumnChanged(Visitor $visitor, $information, $infoName)
    {
        $existing = Common::mb_strtolower($visitor->getVisitorColumn($infoName));
        $new      = isset($information[$infoName]) ? Common::mb_strtolower($information[$infoName]) : false;

        $result = $existing != $new;
        if ($result) {
            Common::printDebug("Referrers\Base::isReferrerInformationNew: detected change in $infoName ('$existing' != '$new').");
        }

        return $result;
    }
}
