<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik_Plugins
 * @package AdvancedCampaignReporting
 */
namespace Piwik\Plugins\AdvancedCampaignReporting;
use Piwik\Common;
use Piwik\Db;
use Piwik\Piwik;

/**
 * @package AdvancedCampaignReporting
 */
class AdvancedCampaignReporting extends \Piwik\Plugin
{
    public function getListHooksRegistered()
    {
        return array(
            'Tracker.newVisitorInformation'     => 'enrichVisitWithAdvancedCampaign',
            'Tracker.newConversionInformation'  => 'enrichVisitWithAdvancedCampaign',
            'API.getReportMetadata'             => 'getReportMetadata',
        );
    }

    /**
     * New DB fields to track Campaign attributes
     */
    public function install()
    {
        $tables = array(
            Common::prefixTable("log_visit"),
            Common::prefixTable("log_conversion"),
        );

        foreach($tables as $table) {
            try {
                $query = "ALTER TABLE `" . $table . "`
                    ADD `campaign_name` VARCHAR(255) NULL DEFAULT NULL AFTER `referer_keyword` ,
                    ADD `campaign_keyword` VARCHAR(255) NULL DEFAULT NULL AFTER `campaign_name` ,
                    ADD `campaign_source` VARCHAR(255) NULL DEFAULT NULL AFTER  `campaign_keyword` ,
                    ADD `campaign_medium` VARCHAR(255) NULL DEFAULT NULL AFTER `campaign_source`,
                    ADD `campaign_content` VARCHAR(255) NULL DEFAULT NULL AFTER `campaign_medium`,
                    ADD `campaign_id` VARCHAR( 100 ) NULL DEFAULT NULL AFTER  `campaign_content`";
                Db::exec($query);
            } catch (\Exception $e) {
                if (!Db::get()->isErrNo($e, '1060')) {
                    throw $e;
                }
            }
        }
    }

    public function enrichVisitWithAdvancedCampaign(&$visitorInfo, \Piwik\Tracker\Request $request)
    {
        $campaignTracker = new Tracker($request);
        $campaignTracker->updateNewVisitWithCampaign($visitorInfo);
    }

    public function getReportMetadata(&$report)
    {
        $report[] = array(
            'category' => Piwik::translate('AdvancedCampaignReporting_Title'),
            'name' => Piwik::translate('AdvancedCampaignReporting_Names'),
            'module' => 'AdvancedCampaignReporting',
            'action' => 'getName',
            'dimension' => Piwik::translate('AdvancedCampaignReporting_Name'),
            'actionToLoadSubTables' => 'getKeywordContentFromNameId',
            'order' => 1,
        );
        $report[] = array(
            'category' => Piwik::translate('AdvancedCampaignReporting_Title'),
            'name' => Piwik::translate('AdvancedCampaignReporting_Keywords'),
            'module' => 'AdvancedCampaignReporting',
            'action' => 'getKeyword',
            'dimension' => Piwik::translate('AdvancedCampaignReporting_Keyword'),
            'order' => 2,
        );
        $report[] = array(
            'category' => Piwik::translate('AdvancedCampaignReporting_Title'),
            'name' => Piwik::translate('AdvancedCampaignReporting_Sources'),
            'module' => 'AdvancedCampaignReporting',
            'action' => 'getSource',
            'dimension' => Piwik::translate('AdvancedCampaignReporting_Source'),
            'order' => 3,
        );
        $report[] = array(
            'category' => Piwik::translate('AdvancedCampaignReporting_Title'),
            'name' => Piwik::translate('AdvancedCampaignReporting_Mediums'),
            'module' => 'AdvancedCampaignReporting',
            'action' => 'getMedium',
            'dimension' => Piwik::translate('AdvancedCampaignReporting_Medium'),
            'order' => 4,
        );
        $report[] = array(
            'category' => Piwik::translate('AdvancedCampaignReporting_Title'),
            'name' => Piwik::translate('AdvancedCampaignReporting_Contents'),
            'module' => 'AdvancedCampaignReporting',
            'action' => 'getContent',
            'dimension' => Piwik::translate('AdvancedCampaignReporting_Content'),
            'order' => 5,
        );
        $report[] = array(
            'category' => Piwik::translate('AdvancedCampaignReporting_Title'),
            'name' => Piwik::translate('AdvancedCampaignReporting_CombinedSourcesMediums'),
            'module' => 'AdvancedCampaignReporting',
            'action' => 'getSourceMedium',
            'dimension' => Piwik::translate('AdvancedCampaignReporting_CombinedSourceMedium'),
            'actionToLoadSubTables' => 'getNameFromSourceMediumId',
            'order' => 6,
        );
    }
}
