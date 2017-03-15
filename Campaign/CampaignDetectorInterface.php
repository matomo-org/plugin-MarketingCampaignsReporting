<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * Based on code from AdvancedCampaignReporting plugin by Piwik PRO released under GPL v3 or later: https://github.com/PiwikPRO/plugin-AdvancedCampaignReporting
 */
namespace Piwik\Plugins\MarketingCampaignsReporting\Campaign;

use Piwik\Tracker\Request;

interface CampaignDetectorInterface
{
    /**
     * @param Request $request
     * @param $campaignParameters
     * @return array|bool
     */
    public function detectCampaignFromRequest(Request $request, $campaignParameters);

    /**
     * @param $queryString
     * @param $campaignParameters
     * @return array|bool
     */
    public function detectCampaignFromString($queryString, $campaignParameters);

    /**
     * @param $visitorInfo
     * @param $campaignParameters
     * @return array|bool
     */
    public function detectCampaignFromVisit($visitorInfo, $campaignParameters);
}
