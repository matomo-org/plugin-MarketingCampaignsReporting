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
use Piwik\Plugin\Dimension\VisitDimension;
use Piwik\Plugins\MarketingCampaignsReporting\MarketingCampaignsReporting;
use Piwik\Tracker\Action;
use Piwik\Tracker\Request;
use Piwik\Tracker\Visitor;

abstract class Base extends VisitDimension
{
    protected $category = 'Referrers_Referrers';

    public function getRequiredVisitFields()
    {
        return array(
            'referer_type',
            'referer_name',
            'referer_keyword'
        );
    }

    /**
     * @param Request     $request
     * @param Visitor     $visitor
     * @param Action|null $action
     * @return mixed
     */
    public function onNewVisit(Request $request, Visitor $visitor, $action)
    {
        $campaignDetector   = StaticContainer::get('advanced_campaign_reporting.campaign_detector');
        $campaignParameters = MarketingCampaignsReporting::getCampaignParameters();

        $visitProperties = $visitor->visitProperties->getProperties();

        $campaignDimensions = $campaignDetector->detectCampaignFromRequest(
            $request,
            $campaignParameters
        );

        if (empty($campaignDimensions)) {
            // If for some reason a campaign was detected in Core Tracker
            // but not here, copy that campaign to the Advanced Campaign
            if ($visitProperties['referer_type'] == Common::REFERRER_TYPE_CAMPAIGN) {

                $campaignDimensions = array(
                    (new CampaignName())->getColumnName() => $visitProperties['referer_name']
                );
                if (!empty($visitProperties['referer_keyword'])) {
                    $campaignDimensions[(new CampaignKeyword())->getColumnName()] = $visitProperties['referer_keyword'];
                }
            }
        }

        if (!empty($campaignDimensions) && array_key_exists($this->getColumnName(), $campaignDimensions)) {
            return substr($campaignDimensions[$this->getColumnName()], 0, $this->getColumnName() == 'campaign_id' ? 100 : 255);
        }

        return null;
    }

    public function onExistingVisit(Request $request, Visitor $visitor, $action)
    {
        $campaignDetector   = StaticContainer::get('advanced_campaign_reporting.campaign_detector');
        $campaignParameters = MarketingCampaignsReporting::getCampaignParameters();

        $campaignDimensions = $campaignDetector->detectCampaignFromRequest(
            $request,
            $campaignParameters
        );

        if ($this->isCurrentReferrerDirectEntry($visitor)
            && !empty($campaignDimensions)
            && array_key_exists($this->getColumnName(), $campaignDimensions)
        ) {
            return substr($campaignDimensions[$this->getColumnName()], 0, $this->getColumnName() == 'campaign_id' ? 100 : 255);
        }

        return false;
    }

    protected function isCurrentReferrerDirectEntry(Visitor $visitor)
    {
        $referrerType = $visitor->getVisitorColumn('referer_type');
        return $referrerType == Common::REFERRER_TYPE_DIRECT_ENTRY;
    }

    /**
     * @param Request     $request
     * @param Visitor     $visitor
     * @param Action|null $action
     * @return mixed
     */
    public function onAnyGoalConversion(Request $request, Visitor $visitor, $action)
    {
        $campaignDetector   = StaticContainer::get('advanced_campaign_reporting.campaign_detector');
        $campaignParameters = MarketingCampaignsReporting::getCampaignParameters();

        $visitProperties = $visitor->visitProperties->getProperties();

        $campaignDimensions = $campaignDetector->detectCampaignFromVisit(
            $visitProperties,
            $campaignParameters
        );

        if (empty($campaignDimensions)) {
            $campaignDimensions = $campaignDetector->detectCampaignFromRequest(
                $request,
                $campaignParameters
            );
        }

        if (!empty($campaignDimensions) && array_key_exists($this->getColumnName(), $campaignDimensions)) {
            return substr($campaignDimensions[$this->getColumnName()], 0, $this->getColumnName() == 'campaign_id' ? 100 : 255);
        }

        return null;
    }
}
