<?php
/**
 * Piwik PRO -  Premium functionality and enterprise-level support for Piwik Analytics
 *
 * @link http://piwik.pro
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AdvancedCampaignReporting\Campaign;

use Piwik\Common;
use Piwik\Plugins\AdvancedCampaignReporting\AdvancedCampaignReporting;
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
        $landingUrl = $request->getParam('url');
        $landingUrl = PageUrl::cleanupUrl($landingUrl);
        $landingUrlParsed = parse_url($landingUrl);

        if (!isset($landingUrlParsed['query'])
            && !isset($landingUrlParsed['fragment'])
        ) {
            return false;
        }

        $campaignDimensions = array();

        // 1) Detect from fragment #hash
        if (isset($landingUrlParsed['fragment'])) {
            $queryString = $this->extractQueryString($landingUrlParsed['fragment']);
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
        $campaignDimensions = array();
        foreach($campaignParameters as $sqlField => $requestParams) {
            foreach($requestParams as $campaignDimensionParam) {
                $value = $this->getValueFromQueryString($campaignDimensionParam, $queryString);
                if(!empty($value)) {
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
        $valueFromRequest = Common::mb_strtolower($valueFromRequest);
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
        $campaignFields = AdvancedCampaignReporting::getAdvancedCampaignFields();

        $campaignDimensions = array_intersect_key($visitorInfo, array_flip($campaignFields));

        foreach($campaignDimensions as $key => $value) {
            if(is_null($value) || $value == '') {
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
