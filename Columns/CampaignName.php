<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\MarketingCampaignsReporting\Columns;

use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Piwik;
use Piwik\Plugins\MarketingCampaignsReporting\MarketingCampaignsReporting;
use Piwik\Plugins\MarketingCampaignsReporting\Segment;
use Piwik\Tracker\Action;
use Piwik\Tracker\Request;
use Piwik\Tracker\TrackerConfig;
use Piwik\Tracker\Visitor;

class CampaignName extends Base
{
    protected $columnName = 'campaign_name';
    protected $columnType = 'VARCHAR(255) NULL';

    protected function configureSegments()
    {
        $segment = new Segment();
        $segment->setSegment('campaignName');
        $segment->setName('MarketingCampaignsReporting_Name');
        $this->addSegment($segment);
    }

    public function getName()
    {
        return Piwik::translate('MarketingCampaignsReporting_Name');
    }

    /**
     * Force a new visit if any new campaign parameters are detected
     *
     * @param Request     $request
     * @param Visitor     $visitor
     * @param Action|null $action
     * @return bool
     */
    public function shouldForceNewVisit(Request $request, Visitor $visitor, Action $action = null)
    {
        if (TrackerConfig::getConfigValue('create_new_visit_when_campaign_changes') != 1) {
            return false;
        }

        $campaignDetector   = StaticContainer::get('advanced_campaign_reporting.campaign_detector');
        $campaignParameters = MarketingCampaignsReporting::getCampaignParameters();

        $campaignDimensions = $campaignDetector->detectCampaignFromRequest(
            $request,
            $campaignParameters
        );

        if (empty($campaignDimensions)) {
            return false;
        }

        foreach ($campaignDimensions as $dimension => $value) {
            if (Common::mb_strtolower($visitor->getVisitorColumn($dimension)) != Common::mb_strtolower($value)) {
                return true;
            }
        }

        return false;
    }
}
