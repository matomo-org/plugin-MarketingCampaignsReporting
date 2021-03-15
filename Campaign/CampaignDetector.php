<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * Based on code from AdvancedCampaignReporting plugin by Piwik PRO released under GPL v3 or later:
 * https://github.com/PiwikPRO/plugin-AdvancedCampaignReporting
 */

namespace Piwik\Plugins\MarketingCampaignsReporting\Campaign;

use Piwik\Common;
use Piwik\Plugins\MarketingCampaignsReporting\MarketingCampaignsReporting;
use Piwik\Tracker\PageUrl;
use Piwik\Tracker\Request;
use Piwik\UrlHelper;

class CampaignDetector implements CampaignDetectorInterface
{
    /**
     * @param Request $request
     * @return boolean|array
     */
    public function detectCampaignFromRequest(Request $request, $campaignParameters)
    {
        $landingUrl       = $request->getParam('url');
        $landingUrl       = PageUrl::cleanupUrl($landingUrl);
        $landingUrlParsed = parse_url($landingUrl);

        if (!isset($landingUrlParsed['query'])
            && !isset($landingUrlParsed['fragment'])
        ) {
            return false;
        }

        $campaignDimensions = [];

        // 1) Detect from fragment #hash
        if (isset($landingUrlParsed['fragment'])) {
            $queryString        = $this->extractQueryString($landingUrlParsed['fragment']);
            $campaignDimensions = $this->detectCampaignFromString(
                $queryString,
                $campaignParameters
            );
        }

        // 2) Detect campaign from query string
        if (empty($campaignDimensions) && isset($landingUrlParsed['query'])) {
            $campaignDimensions = $this->detectCampaignFromString(
                $landingUrlParsed['query'],
                $campaignParameters
            );
        }
        return $campaignDimensions;
    }

    /**
     * @param $queryString
     * @return boolean|array
     */
    public function detectCampaignFromString($queryString, $campaignParameters)
    {
        $campaignDimensions = [];
        foreach ($campaignParameters as $sqlField => $requestParams) {
            foreach ($requestParams as $campaignDimensionParam) {
                $value = $this->getValueFromQueryString($campaignDimensionParam, $queryString);
                if (!empty($value)) {
                    $campaignDimensions[$sqlField] = $value;
                    break 1;
                }
            }
        }
        return $campaignDimensions;
    }

    /**
     * @param $param
     * @param $queryString
     * @return bool|null|string
     */
    protected function getValueFromQueryString($param, $queryString)
    {
        $valueFromRequest = UrlHelper::getParameterFromQueryString($queryString, $param);
        $valueFromRequest = trim(urldecode($valueFromRequest));
        if ($param != "mtm_clid") {
	        $valueFromRequest = Common::mb_strtolower($valueFromRequest);
        }
        $valueFromRequest = substr($valueFromRequest, 0, 250);
        if (!empty($valueFromRequest)) {
            return $valueFromRequest;
        }
        return false;
    }

    /**
     * @param $visitorInfo
     * @param $campaignParameters
     * @return array|bool
     */
    public function detectCampaignFromVisit($visitorInfo, $campaignParameters)
    {
        $campaignFields = MarketingCampaignsReporting::getAdvancedCampaignFields();

        $campaignDimensions = array_intersect_key($visitorInfo, array_flip($campaignFields));

        foreach ($campaignDimensions as $key => $value) {
            if (is_null($value) || $value == '') {
                unset($campaignDimensions[$key]);
            }
        }
        return $campaignDimensions;
    }

    protected function extractQueryString($fragment)
    {
        if (strpos($fragment, '/') === 0) {
            $parsed = parse_url($fragment);
            if (isset($parsed['query'])) {
                $fragment = $parsed['query'];
            }
        }

        return $fragment;
    }
}
