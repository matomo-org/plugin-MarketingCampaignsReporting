<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * Based on code from AdvancedCampaignReporting plugin by Piwik PRO released under GPL v3 or later: https://github.com/PiwikPRO/plugin-AdvancedCampaignReporting
 */
namespace Piwik\Plugins\MarketingCampaignsReporting;

use Piwik\Container\StaticContainer;
use Piwik\Db;
use Piwik\Plugin;
use Piwik\Plugin\Report;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\Base;
use Piwik\Plugins\Referrers\Reports\GetCampaigns;

/**
 * @package MarketingCampaignsReporting
 */
class MarketingCampaignsReporting extends Plugin
{
    public static $CAMPAIGN_NAME_FIELD_DEFAULT_URL_PARAMS    = array('pk_campaign', 'piwik_campaign', 'pk_cpn', 'utm_campaign');
    public static $CAMPAIGN_KEYWORD_FIELD_DEFAULT_URL_PARAMS = array('pk_keyword', 'piwik_kwd', 'pk_kwd', 'utm_term');
    public static $CAMPAIGN_SOURCE_FIELD_DEFAULT_URL_PARAMS  = array('pk_source', 'utm_source');
    public static $CAMPAIGN_MEDIUM_FIELD_DEFAULT_URL_PARAMS  = array('pk_medium', 'utm_medium');
    public static $CAMPAIGN_CONTENT_FIELD_DEFAULT_URL_PARAMS = array('pk_content', 'utm_content');
    public static $CAMPAIGN_ID_FIELD_DEFAULT_URL_PARAMS      = array('pk_cid', 'utm_id');

    public function registerEvents()
    {
        return array(
            'Tracker.PageUrl.getQueryParametersToExclude' => 'getQueryParametersToExclude',
            'Report.filterReports'                        => 'removeOriginalCampaignReport',
            'Insights.addReportToOverview'                => 'addReportToInsightsOverview',
            'AssetManager.getStylesheetFiles'             => 'getStylesheetFiles',
        );
    }

    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = "plugins/MarketingCampaignsReporting/stylesheets/styles.less";
    }

    public function install()
    {
        $tables = \Piwik\DbHelper::getTablesInstalled();
        foreach ($tables as $tableName) {
            if (strpos($tableName, 'archive_') !== false) {
                Db::exec('UPDATE `' . $tableName . '` SET `name`=REPLACE(`name`, \'AdvancedCampaignReporting_\', \'MarketingCampaignsReporting_\') WHERE `name` LIKE \'AdvancedCampaignReporting_%\'');
            }
        }

        Plugin\Manager::getInstance()->deactivatePlugin('AdvancedCampaignReporting');
    }

    public function getQueryParametersToExclude(&$excludedParameters)
    {
        $advancedCampaignParameters = self::getCampaignParameters();

        foreach ($advancedCampaignParameters as $advancedCampaignParameter) {
            $excludedParameters = array_merge($excludedParameters, $advancedCampaignParameter);
        }
    }

    public function addReportToInsightsOverview(&$reports)
    {
        unset($reports['Referrers_getCampaigns']);
        $reports['MarketingCampaignsReporting_getName'] = array();
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
    public function removeOriginalCampaignReport(&$reports)
    {
        foreach ($reports as $index => $report) {
            if ($report instanceof GetCampaigns) {
                unset($reports[$index]);
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
