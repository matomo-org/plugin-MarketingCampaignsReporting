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
use Piwik\Db;
use Piwik\Piwik;
use Piwik\Plugin\ViewDataTable;
use Piwik\Url;
use Piwik\View\ReportsByDimension;
use Piwik\WidgetsList;

/**
 * @package AdvancedCampaignReporting
 */
class AdvancedCampaignReporting extends \Piwik\Plugin
{
    public function getListHooksRegistered()
    {
        return array(
            'Tracker.newVisitorInformation'     => 'enrichVisitWithAdvancedCampaign',
            'Tracker.newConversionInformation'  => 'enrichConversionWithAdvancedCampaign',
            'Tracker.getVisitFieldsToPersist'   => 'getVisitFieldsToPersist',
            'API.getReportMetadata'             => 'getReportMetadata',
            'API.getSegmentDimensionMetadata'   => 'getSegmentDimensionMetadata',
            'Request.dispatch'                  => 'dispatchAdvancedCampaigns',
            'ViewDataTable.configure'           => 'configureViewDataTable',
            'View.ReportsByDimension.render'    => 'configureReportsByDimensionViews',
            'WidgetsList.addWidgets'            => 'addWidgets',
        );
    }

    /**
     * New DB fields to track Campaign attributes
     */
    public function install()
    {
        foreach($this->getTables() as $table) {
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

    public function uninstall()
    {
        $fields = array(
            'campaign_name',
            'campaign_keyword',
            'campaign_source',
            'campaign_medium',
            'campaign_content',
            'campaign_id',
        );
        foreach($this->getTables() as $table) {
            foreach($fields as $field) {
                Db::exec("ALTER TABLE `" . $table . "` DROP COLUMN `". $field ."` ");
            }
        }
    }

    public function enrichConversionWithAdvancedCampaign(&$goal, $visitorInfo, \Piwik\Tracker\Request $request)
    {
        $campaignTracker = new Tracker($request);
        $campaignTracker->updateNewConversionWithCampaign($goal, $visitorInfo);
    }

    public function enrichVisitWithAdvancedCampaign(&$visitorInfo, \Piwik\Tracker\Request $request)
    {
        $campaignTracker = new Tracker($request);
        $campaignTracker->updateNewVisitWithCampaign($visitorInfo);
    }

    public function getVisitFieldsToPersist(&$fields)
    {
        $fields = array_merge($fields, self::getAdvancedCampaignFields());
    }

    public static function getAdvancedCampaignFields()
    {
        return array(
            'campaign_name',
            'campaign_keyword',
            'campaign_source',
            'campaign_medium',
            'campaign_content',
            'campaign_id',
        );
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

    public function getSegmentDimensionMetadata(&$segments)
    {
        $segments[] = array(
            'type'           => 'dimension',
            'category'       => 'AdvancedCampaignReporting_Title',
            'name'           => 'AdvancedCampaignReporting_Name',
            'segment'        => 'campaignName',
            'sqlSegment'     => 'log_visit.' . Tracker::CAMPAIGN_NAME_FIELD,
        );
        $segments[] = array(
            'type'           => 'dimension',
            'category'       => 'AdvancedCampaignReporting_Title',
            'name'           => 'AdvancedCampaignReporting_Keyword',
            'segment'        => 'campaignKeyword',
            'sqlSegment'     => 'log_visit.' . Tracker::CAMPAIGN_KEYWORD_FIELD,
        );
        $segments[] = array(
            'type'           => 'dimension',
            'category'       => 'AdvancedCampaignReporting_Title',
            'name'           => 'AdvancedCampaignReporting_Source',
            'segment'        => 'campaignSource',
            'sqlSegment'     => 'log_visit.' . Tracker::CAMPAIGN_SOURCE_FIELD,
        );
        $segments[] = array(
            'type'           => 'dimension',
            'category'       => 'AdvancedCampaignReporting_Title',
            'name'           => 'AdvancedCampaignReporting_Medium',
            'segment'        => 'campaignMedium',
            'sqlSegment'     => 'log_visit.' . Tracker::CAMPAIGN_MEDIUM_FIELD,
        );
        $segments[] = array(
            'type'           => 'dimension',
            'category'       => 'AdvancedCampaignReporting_Title',
            'name'           => 'AdvancedCampaignReporting_Content',
            'segment'        => 'campaignContent',
            'sqlSegment'     => 'log_visit.' . Tracker::CAMPAIGN_CONTENT_FIELD,
        );
        $segments[] = array(
            'type'           => 'dimension',
            'category'       => 'AdvancedCampaignReporting_Title',
            'name'           => 'AdvancedCampaignReporting_CampaignId',
            'segment'        => 'campaignId',
            'sqlSegment'     => 'log_visit.' . Tracker::CAMPAIGN_ID_FIELD
        );
    }

    /**
     * Instead of dispatching the standard Referrers>Campaigns report,
     * dispatch our better campaign report.
     *
     * @param $module
     * @param $action
     * @param $parameters
     */
    public function dispatchAdvancedCampaigns(&$module, &$action, &$parameters)
    {
        if($module == 'Referrers'
            && $action == 'menuGetCampaigns') {
            $module = 'AdvancedCampaignReporting';
            $action = 'indexCampaigns';
        }
    }

    public function configureViewDataTable(ViewDataTable $view)
    {
        if($view->requestConfig->getApiModuleToRequest() == 'AdvancedCampaignReporting') {
            $view->config->show_goals = true;
            $action = $view->requestConfig->getApiMethodToRequest();
            $view->config->addTranslation('label', $this->getLabelFromMethod($action));

            switch($action) {
                case 'getSourceMedium':
                    $view->config->subtable_controller_action = 'getNameFromSourceMediumId';
                    break;
                case 'getName':
                    $view->config->subtable_controller_action = 'getKeywordContentFromNameId';
                break;
            }
        }
    }

    public function getLabelFromMethod($method)
    {
        $labels = array(
            'getName' => 'AdvancedCampaignReporting_Name',
            'getKeyword' => 'AdvancedCampaignReporting_Keyword',
            'getSource' => 'AdvancedCampaignReporting_Source',
            'getMedium' => 'AdvancedCampaignReporting_Medium',
            'getContent' => 'AdvancedCampaignReporting_Content',
            'getSourceMedium' => 'AdvancedCampaignReporting_CombinedSourceMedium',
            'getKeywordContentFromNameId' => 'AdvancedCampaignReporting_CombinedKeywordContent',
            'getNameFromSourceMediumId' => 'AdvancedCampaignReporting_Name',
        );
        if(!isset($labels[$method])) {
            throw new \Exception("Invalid requested label for $method");
        }
        return Piwik::translate($labels[$method]);
    }

    public function configureReportsByDimensionViews(ReportsByDimension $reportList)
    {
        if ($reportList->getId() == 'Referrers') {
            $this->addReportsByDimension($reportList, 'Referrers_ViewReferrersBy');
        } else if ($reportList->getId() == 'Goals') {
            $templateVars = $reportList->getTemplateVars();
            $viewCategories = $templateVars['dimensionCategories'];

            if (count($viewCategories) == 0
                || (count($viewCategories) == 1
                    && isset($viewCategories['Goals_EcommerceReports']))
            ) {
                return;
            }

            $customGoalsParams = array(
                'viewDataTable' => 'tableGoals',
                'documentationForGoalsPage' => '1'
            );

            if (Common::getRequestVar('idGoal', '') === '') { // code taken from Goals Controller
                $customGoalsParams['idGoal'] = '0';
            }

            // check whether filterEcommerce was used in a URL & make sure it's used in new URLs (see Goals Controller code)
            $firstCategory = reset($viewCategories);
            $firstCategoryReport = reset($firstCategory);
            $firstCategoryUrl = $firstCategoryReport['url'];

            $filterEcommerceParam = Common::getRequestVar('filterEcommerce', false, null, Url::getQueryStringFromUrl($firstCategoryUrl));
            if (!empty($filterEcommerceParam)) {
                $customGoalsParams['filterEcommerce'] = $filterEcommerceParam;
            }

            $this->addReportsByDimension($reportList, 'Goals_ViewGoalsBy', $customGoalsParams);
        }
    }

    private function addReportsByDimension(ReportsByDimension $reportList, $categoryTranslation, $defaultParams = array())
    {
        $metadatas = array();
        $this->getReportMetadata($metadatas);

        $byCampaign = Piwik::translate($categoryTranslation, Piwik::translate('Referrers_ColumnCampaign'));
        foreach($metadatas as $metadata) {
            $api = 'AdvancedCampaignReporting.' . $metadata['action'];
            $title = $this->getLabelFromMethod($metadata['action']);

            $customParams = array();
            foreach ($defaultParams as $key => $value) {
                $customParams[$key] = !empty($metadata[$key]) ? $metadata[$key] : $value;
            }

            $reportList->addReport($byCampaign, $title, $api, $customParams);
        }
    }

    function addWidgets()
    {
        $metadatas = array();
        $this->getReportMetadata($metadatas);
        foreach($metadatas as $metadata) {
            $title = $this->getLabelFromMethod($metadata['action']);
            WidgetsList::add('AdvancedCampaignReporting_Title', $title, 'AdvancedCampaignReporting', $metadata['action']);
        }
    }

    private function getTables()
    {
        $tables = array(
            Common::prefixTable("log_visit"),
            Common::prefixTable("log_conversion"),
        );
        return $tables;
    }
}
