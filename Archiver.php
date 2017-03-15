<?php
/**
 * Piwik PRO -  Premium functionality and enterprise-level support for Piwik Analytics
 *
 * @link http://piwik.pro
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\AdvancedCampaignReporting;

use Piwik\Common;
use Piwik\Config;
use Piwik\DataArray;
use Piwik\Db;
use Piwik\Metrics;

class Archiver extends \Piwik\Plugin\Archiver
{
    const CAMPAIGN_NAME_RECORD_NAME = 'AdvancedCampaignReporting_Name';
    const CAMPAIGN_KEYWORD_RECORD_NAME = 'AdvancedCampaignReporting_Keyword';
    const CAMPAIGN_SOURCE_RECORD_NAME = 'AdvancedCampaignReporting_Source';
    const CAMPAIGN_MEDIUM_RECORD_NAME = 'AdvancedCampaignReporting_Medium';
    const CAMPAIGN_CONTENT_RECORD_NAME = 'AdvancedCampaignReporting_Content';

    const HIERARCHICAL_SOURCE_MEDIUM_RECORD_NAME = 'AdvancedCampaignReporting_SourceMedium_Name';

    protected $columnToSortByBeforeTruncation;
    protected $maximumRowsInDataTable;
    protected $maximumRowsInSubDataTable;

    /**
     * @var DataArray[]
     */
    protected $arrays = array();

    const SEPARATOR_COMBINED_DIMENSIONS = " - ";

    function __construct($processor)
    {
        parent::__construct($processor);
        $this->columnToSortByBeforeTruncation = Metrics::INDEX_NB_VISITS;
        $this->maximumRowsInDataTable = Config::getInstance()->General['datatable_archiving_maximum_rows_referrers'];
        $this->maximumRowsInSubDataTable = Config::getInstance()->General['datatable_archiving_maximum_rows_subtable_referrers'];
    }

    protected function getRecordToDimensions()
    {
        return array(
            self::CAMPAIGN_NAME_RECORD_NAME => array(
                array("campaign_name"),
                array("campaign_keyword", "campaign_content"),
            ),
            self::CAMPAIGN_KEYWORD_RECORD_NAME => array(
                array("campaign_keyword")
            ),
            self::CAMPAIGN_SOURCE_RECORD_NAME => array(
                array("campaign_source"),
            ),
            self::CAMPAIGN_MEDIUM_RECORD_NAME => array(
                array("campaign_medium"),
            ),
            self::CAMPAIGN_CONTENT_RECORD_NAME => array(
                array("campaign_content"),
            ),
            self::HIERARCHICAL_SOURCE_MEDIUM_RECORD_NAME => array(
                array("campaign_source", "campaign_medium"),
                array("campaign_name")
            ),
        );
    }

    protected function getRecordNames()
    {
        $mapping = $this->getRecordToDimensions();
        return array_keys($mapping);
    }

    public function aggregateMultipleReports()
    {
        $dataTableToSum = $this->getRecordNames();
        $this->getProcessor()->aggregateDataTableRecords($dataTableToSum, $this->maximumRowsInDataTable, $this->maximumRowsInSubDataTable, $this->columnToSortByBeforeTruncation);
    }

    public function aggregateDayReport()
    {
        $this->initDataArrays();
        $dimensions = array("campaign_name",
                            "campaign_keyword",
                            "campaign_content",
                            "campaign_source",
                            "campaign_medium"
        );
        $this->aggregateFromLogs($dimensions, 'log_visit', 'queryVisitsByDimension', 'sumMetricsVisits', 'sumMetricsVisitsPivot');
        $this->aggregateFromLogs($dimensions, 'log_conversion', 'queryConversionsByDimension', 'sumMetricsGoals', 'sumMetricsGoalsPivot');

        foreach ($this->arrays as $dataArray) {
            $dataArray->enrichMetricsWithConversions();
        }
        $this->insertDayReports();
    }

    protected function initDataArrays()
    {
        foreach ($this->getRecordNames() as $record) {
            $this->arrays[$record] = new DataArray();
        }
    }

    /**
     * @param string $name
     * @return DataArray
     */
    protected function getDataArray($name)
    {
        return $this->arrays[$name];
    }

    protected function aggregateFromLogs($dimensions, $table, $aggregatorMethod, $dataArraySum, $dataArraySubtableSum)
    {
        $whereClause = $table . ".referer_type = " . Common::REFERRER_TYPE_CAMPAIGN;
        $query = $this->getLogAggregator()->$aggregatorMethod($dimensions, $whereClause);
        if ($query === false) {
            return;
        }
        $recordToDimensions = $this->getRecordToDimensions();

        while ($row = $query->fetch()) {
            foreach($recordToDimensions as $record => $dimensionsForRecord) {
                $dataArray = $this->getDataArray($record);

                $mainLabelDimensions = $dimensionsForRecord[0];
                $mainLabel = $this->getLabelFromRowDimensions($mainLabelDimensions, $row);
                if(empty($mainLabel)) {
                    continue 1;
                }
                $dataArray->$dataArraySum($mainLabel, $row);

                if(isset($dimensionsForRecord[1])) {
                    $subLabelDimensions = $dimensionsForRecord[1];
                    $subLabel = $this->getLabelFromRowDimensions($subLabelDimensions, $row);
                    if(empty($subLabel)) {
                        continue 1;
                    }
                    $dataArray->$dataArraySubtableSum($mainLabel, $subLabel, $row);
                }
            }
        }
    }

    /**
     * Records the daily datatables
     */
    protected function insertDayReports()
    {
        foreach ($this->arrays as $recordName => $dataArray) {
            $blob = $dataArray->asDataTable()->getSerialized($this->maximumRowsInDataTable, $this->maximumRowsInSubDataTable, $this->columnToSortByBeforeTruncation);
            $this->getProcessor()->insertBlobRecord($recordName, $blob);
        }
    }

    /**
     * @param $dimensionsAsLabel
     * @param $row
     * @return string
     */
    protected function getLabelFromRowDimensions($dimensionsAsLabel, $row)
    {
        $labels = array();
        foreach ($dimensionsAsLabel as $dimensionLabelPart) {
            if(isset($row[$dimensionLabelPart])
                && $row[$dimensionLabelPart] != '') {
                $labels[] = $row[$dimensionLabelPart];
            }
        }
        $label = implode(self::SEPARATOR_COMBINED_DIMENSIONS, $labels);
        return $label;
    }

}
