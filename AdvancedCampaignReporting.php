<?php
/**
 * Piwik PRO -  Premium functionality and enterprise-level support for Piwik Analytics
 *
 * @link    http://piwik.pro
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\AdvancedCampaignReporting;

use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugins\AdvancedCampaignReporting\Columns\Base;
use Piwik\Plugins\Referrers\Reports\GetCampaigns;

/**
 * @package AdvancedCampaignReporting
 */
class AdvancedCampaignReporting extends \Piwik\Plugin
{
    public static $CAMPAIGN_NAME_FIELD_DEFAULT_URL_PARAMS    = array('pk_campaign', 'piwik_campaign', 'pk_cpn', 'utm_campaign');
    public static $CAMPAIGN_KEYWORD_FIELD_DEFAULT_URL_PARAMS = array('pk_keyword', 'piwik_kwd', 'pk_kwd', 'utm_term');
    public static $CAMPAIGN_SOURCE_FIELD_DEFAULT_URL_PARAMS  = array('pk_source', 'utm_source');
    public static $CAMPAIGN_MEDIUM_FIELD_DEFAULT_URL_PARAMS  = array('pk_medium', 'utm_medium');
    public static $CAMPAIGN_CONTENT_FIELD_DEFAULT_URL_PARAMS = array('pk_content', 'utm_content');
    public static $CAMPAIGN_ID_FIELD_DEFAULT_URL_PARAMS      = array('pk_cid', 'utm_id');

    public function getListHooksRegistered()
    {
        return array(
            'Tracker.PageUrl.getQueryParametersToExclude' => 'getQueryParametersToExclude',
            'Report.filterReports'                        => 'filterReports',
        );
    }

    /**
     * @todo    Implement a way to overwrite the campaign parameters set in core if an campaign was detected by plugin
     *
     * Overwrite core referer_ fields when an advanced campaign was detected
     * $rowToInsert['referer_type'] = Common::REFERRER_TYPE_CAMPAIGN;
     *
     * if (isset($rowToInsert[self::CAMPAIGN_NAME_FIELD])) {
     *    $rowToInsert['referer_name'] = substr($rowToInsert[self::CAMPAIGN_NAME_FIELD], 0, 70);
     * }
     * if (isset($rowToInsert[self::CAMPAIGN_KEYWORD_FIELD])) {
     *    $rowToInsert['referer_keyword'] = substr($rowToInsert[self::CAMPAIGN_KEYWORD_FIELD], 0, 255);
     * }
     *
     */

    public function getQueryParametersToExclude(&$excludedParameters)
    {
        $advancedCampaignParameters = self::getCampaignParameters();

        foreach ($advancedCampaignParameters as $advancedCampaignParameter) {
            $excludedParameters = array_merge($excludedParameters, $advancedCampaignParameter);
        }
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

    /**
     * @param Report[] $reports
     */
    public function filterReports(&$reports)
    {
        foreach ($reports as $index => $report) {
            if ($report instanceof GetCampaigns) {
                unset($reports[$index]); // remove original campaign report
            }
        }
    }

    public static function getAdvancedCampaignFields()
    {
        $dimensions     = Base::getDimensions(new self());
        $campaignFields = array();

        foreach ($dimensions as $dimension) {
            $campaignFields[] = $dimension->getColumnName();
        }

        return $campaignFields;
    }
}
