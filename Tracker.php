<?php
/**
 * Piwik PRO - cloud hosting and enterprise analytics consultancy
 * from the creators of Piwik.org
 *
 * @link http://piwik.pro
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\AdvancedCampaignReporting;

use Piwik\Common;
use Piwik\Tracker\PageUrl;
use Piwik\UrlHelper;

class Tracker
{
    private $request;

    const CAMPAIGN_NAME_FIELD = 'campaign_name';
    const CAMPAIGN_KEYWORD_FIELD = 'campaign_keyword';
    const CAMPAIGN_SOURCE_FIELD = 'campaign_source';
    const CAMPAIGN_MEDIUM_FIELD = 'campaign_medium';
    const CAMPAIGN_CONTENT_FIELD = 'campaign_content';
    const CAMPAIGN_ID_FIELD = 'campaign_id';

    const CAMPAIGN_NAME_COLUMN_LENGTH = 255;
    const CAMPAIGN_KEYWORD_COLUMN_LENGTH = 255;
    const CAMPAIGN_SOURCE_COLUMN_LENGTH = 255;
    const CAMPAIGN_MEDIUM_COLUMN_LENGTH = 255;
    const CAMPAIGN_CONTENT_COLUMN_LENGTH = 255;
    const CAMPAIGN_ID_COLUMN_LENGTH = 100;

    public static $campaignFieldLengths = array(
        self::CAMPAIGN_NAME_FIELD => 255,
        self::CAMPAIGN_KEYWORD_FIELD => 255,
        self::CAMPAIGN_SOURCE_FIELD => 255,
        self::CAMPAIGN_MEDIUM_FIELD => 255,
        self::CAMPAIGN_CONTENT_FIELD => 255,
        self::CAMPAIGN_ID_FIELD => 100
    );

    public function __construct(\Piwik\Tracker\Request $request)
    {
        $this->request = $request;
    }

    /**
     * @return array
     */
    protected function getCampaignParameters()
    {
        return array(
            self::CAMPAIGN_NAME_FIELD    => array('pk_campaign', 'piwik_campaign', 'pk_cpn', 'utm_campaign'),
            self::CAMPAIGN_KEYWORD_FIELD => array('pk_keyword', 'piwik_kwd', 'pk_kwd', 'utm_term'),
            self::CAMPAIGN_SOURCE_FIELD  => array('pk_source', 'utm_source'),
            self::CAMPAIGN_MEDIUM_FIELD  => array('pk_medium', 'utm_medium'),
            self::CAMPAIGN_CONTENT_FIELD => array('pk_content', 'utm_content'),
            self::CAMPAIGN_ID_FIELD      => array('pk_cid', 'utm_id'),
        );
    }

    protected function detectCampaignFromVisit($visitorInfo)
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

    public function updateNewConversionWithCampaign(&$conversionToInsert, $visitorInfo)
    {
        $campaignDimensions = $this->detectCampaignFromVisit($visitorInfo);
        if(empty($campaignDimensions)) {
            $campaignDimensions = $this->detectCampaignFromRequest();
        }

        $this->addDimensionsToRow($conversionToInsert, $campaignDimensions);
    }

    public function updateNewVisitWithCampaign(&$visitToInsert)
    {
        $campaignDimensions = $this->detectCampaignFromRequest();

        if(empty($campaignDimensions)) {

            // If for some reason a campaign was detected in Core Tracker
            // but not here, copy that campaign to the Advanced Campaign
            if($visitToInsert['referer_type'] != Common::REFERRER_TYPE_CAMPAIGN) {
                return ;
            }
            $campaignDimensions = array(
                self::CAMPAIGN_NAME_FIELD => $visitToInsert['referer_name']
            );
            if(!empty($visitToInsert['referer_keyword'])) {
                $campaignDimensions[self::CAMPAIGN_KEYWORD_FIELD] = $visitToInsert['referer_keyword'];
            }
        }

        $this->addDimensionsToRow($visitToInsert, $campaignDimensions);
    }

    protected function detectCampaignFromRequest()
    {
        $landingUrl = $this->request->getParam('url');
        $landingUrl = PageUrl::cleanupUrl($landingUrl);
        $landingUrlParsed = @parse_url($landingUrl);

        if (!isset($landingUrlParsed['query'])
            && !isset($landingUrlParsed['fragment'])
        ) {
            return false;
        }

        $campaignDimensions = array();

        // 1) Detect from fragment #hash
        if (isset($landingUrlParsed['fragment'])) {
            $campaignDimensions = $this->detectCampaignFromString($landingUrlParsed['fragment']);
        }

        // 2) Detect campaign from query string
        if (empty($campaignDimensions) && isset($landingUrlParsed['query'])) {
            $campaignDimensions = $this->detectCampaignFromString($landingUrlParsed['query']);
        }
        return $campaignDimensions;
    }


    /**
     * @param string $queryString
     * @return array of campaign dimensions
     */
    protected function detectCampaignFromString($queryString)
    {
        $parameters = $this->getCampaignParameters();

        $campaignDimensions = array();
        foreach($parameters as $sqlField => $requestParams) {
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
     * @param $rowToInsert
     * @param $campaignDimensions
     * @return array
     */
    protected function addDimensionsToRow(&$rowToInsert, $campaignDimensions)
    {
        if(empty($campaignDimensions)) {
            return;
        }

        $this->truncateDimensions($campaignDimensions);

        Common::printDebug("Found Advanced Campaign (after truncation): ");
        Common::printDebug($campaignDimensions);

        // Set the new campaign fields on the visitor
        foreach($campaignDimensions as $field => $value) {
            $rowToInsert[$field] = $value;
        }

        // Overwrite core referer_ fields when an advanced campaign was detected
        $rowToInsert['referer_type'] = Common::REFERRER_TYPE_CAMPAIGN;

        if (isset($rowToInsert[self::CAMPAIGN_NAME_FIELD])) {
            $rowToInsert['referer_name'] = $rowToInsert[self::CAMPAIGN_NAME_FIELD];
        }
        if (isset($rowToInsert[self::CAMPAIGN_KEYWORD_FIELD])) {
            $rowToInsert['referer_keyword'] = $rowToInsert[self::CAMPAIGN_KEYWORD_FIELD];
        }
    }

    private function truncateDimensions(&$campaignDimensions)
    {
        foreach (self::$campaignFieldLengths as $name => $length) {
            if (!empty($campaignDimensions[$name])) {
                $campaignDimensions[$name] = substr($campaignDimensions[$name], 0, $length);
            }
        }
    }
}