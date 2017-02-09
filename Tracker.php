<?php
/**
 * Piwik PRO -  Premium functionality and enterprise-level support for Piwik Analytics
 *
 * @link http://piwik.pro
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\AdvancedCampaignReporting;

use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Plugins\AdvancedCampaignReporting\Campaign\CampaignDetectorInterface;

class Tracker
{
    /**
     * @var \Piwik\Tracker\Request
     */
    private $request;

    /**
     * @var CampaignDetectorInterface
     */
    private $campaignDetector;

    const CAMPAIGN_NAME_FIELD = 'campaign_name';
    const CAMPAIGN_KEYWORD_FIELD = 'campaign_keyword';
    const CAMPAIGN_SOURCE_FIELD = 'campaign_source';
    const CAMPAIGN_MEDIUM_FIELD = 'campaign_medium';
    const CAMPAIGN_CONTENT_FIELD = 'campaign_content';
    const CAMPAIGN_ID_FIELD = 'campaign_id';

    const CAMPAIGN_NAME_FIELD_DEFAULT_URL_PARAMS    = array('pk_campaign', 'piwik_campaign', 'pk_cpn', 'utm_campaign');
    const CAMPAIGN_KEYWORD_FIELD_DEFAULT_URL_PARAMS = array('pk_keyword', 'piwik_kwd', 'pk_kwd', 'utm_term');
    const CAMPAIGN_SOURCE_FIELD_DEFAULT_URL_PARAMS  = array('pk_source', 'utm_source');
    const CAMPAIGN_MEDIUM_FIELD_DEFAULT_URL_PARAMS  = array('pk_medium', 'utm_medium');
    const CAMPAIGN_CONTENT_FIELD_DEFAULT_URL_PARAMS = array('pk_content', 'utm_content');
    const CAMPAIGN_ID_FIELD_DEFAULT_URL_PARAMS      = array('pk_cid', 'utm_id');

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
        $this->campaignDetector = StaticContainer::get('advanced_campaign_reporting.campaign_detector');
    }

    /**
     * @return array
     */
    public static function getCampaignParameters()
    {
        return array_merge(
            StaticContainer::get('advanced_campaign_reporting.uri_parameters.campaign_name'),
            StaticContainer::get('advanced_campaign_reporting.uri_parameters.campaign_keyword'),
            StaticContainer::get('advanced_campaign_reporting.uri_parameters.campaign_source'),
            StaticContainer::get('advanced_campaign_reporting.uri_parameters.campaign_medium'),
            StaticContainer::get('advanced_campaign_reporting.uri_parameters.campaign_content'),
            StaticContainer::get('advanced_campaign_reporting.uri_parameters.campaign_id')
        );
    }

    public function updateNewConversionWithCampaign(&$conversionToInsert, $visitorInfo)
    {
        $campaignParameters = self::getCampaignParameters();

        $campaignDimensions = $this->campaignDetector->detectCampaignFromVisit(
            $visitorInfo,
            $campaignParameters
        );
        if(empty($campaignDimensions)) {
            $campaignDimensions = $this->campaignDetector->detectCampaignFromRequest(
                $this->request,
                $campaignParameters
            );
        }

        $this->addDimensionsToRow($conversionToInsert, $campaignDimensions);
    }

    public function updateNewVisitWithCampaign(&$visitToInsert)
    {
        $campaignParameters = self::getCampaignParameters();

        $campaignDimensions = $this->campaignDetector->detectCampaignFromRequest(
            $this->request,
            $campaignParameters
        );

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
            $rowToInsert['referer_name'] = substr($rowToInsert[self::CAMPAIGN_NAME_FIELD], 0, 70);
        }
        if (isset($rowToInsert[self::CAMPAIGN_KEYWORD_FIELD])) {
            $rowToInsert['referer_keyword'] = substr($rowToInsert[self::CAMPAIGN_KEYWORD_FIELD], 0, 255);
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
