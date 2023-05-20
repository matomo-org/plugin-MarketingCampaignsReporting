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

namespace Piwik\Plugins\MarketingCampaignsReporting\RecordBuilders;

use Piwik\ArchiveProcessor;
use Piwik\ArchiveProcessor\Record;
use Piwik\ArchiveProcessor\RecordBuilder;
use Piwik\Common;
use Piwik\Config;
use Piwik\DataAccess\LogAggregator;
use Piwik\DataTable;
use Piwik\Metrics;
use Piwik\Plugins\MarketingCampaignsReporting\Archiver;

class CampaignReporting extends RecordBuilder
{
    public function __construct()
    {
        parent::__construct();
        $this->columnToSortByBeforeTruncation = Metrics::INDEX_NB_VISITS;
        $this->maxRowsInTable = Config::getInstance()->General['datatable_archiving_maximum_rows_referrers'];
        $this->maxRowsInSubtable = Config::getInstance()->General['datatable_archiving_maximum_rows_subtable_referrers'];
    }

    public function getRecordMetadata(ArchiveProcessor $archiveProcessor): array
    {
        return [
            Record::make(Record::TYPE_BLOB, Archiver::CAMPAIGN_CONTENT_RECORD_NAME),
            Record::make(Record::TYPE_BLOB, Archiver::CAMPAIGN_GROUP_RECORD_NAME),
            Record::make(Record::TYPE_BLOB, Archiver::CAMPAIGN_ID_RECORD_NAME),
            Record::make(Record::TYPE_BLOB, Archiver::CAMPAIGN_KEYWORD_RECORD_NAME),
            Record::make(Record::TYPE_BLOB, Archiver::CAMPAIGN_MEDIUM_RECORD_NAME),
            Record::make(Record::TYPE_BLOB, Archiver::CAMPAIGN_NAME_RECORD_NAME),
            Record::make(Record::TYPE_BLOB, Archiver::CAMPAIGN_PLACEMENT_RECORD_NAME),
            Record::make(Record::TYPE_BLOB, Archiver::CAMPAIGN_SOURCE_RECORD_NAME),
            Record::make(Record::TYPE_BLOB, Archiver::HIERARCHICAL_SOURCE_MEDIUM_RECORD_NAME),
        ];
    }

    protected function aggregate(ArchiveProcessor $archiveProcessor): array
    {
        $logAggregator = $archiveProcessor->getLogAggregator();

        $reports = [];
        foreach ($this->getRecordToDimensions() as $recordName => $ignore) {
            $reports[$recordName] = new DataTable();
        }

        // list of all dimensions to query for
        $dimensions = array_merge(...array_values($this->getRecordToDimensions()));
        $dimensions = array_merge(...$dimensions);
        $dimensions = array_unique($dimensions);

        $this->aggregateFromLogs($logAggregator, $reports, $dimensions, 'log_visit', 'queryVisitsByDimension');
        $this->aggregateFromLogs($logAggregator, $reports, $dimensions, 'log_conversion', 'queryConversionsByDimension');

        foreach ($reports as $report) {
            $report->filter(DataTable\Filter\EnrichRecordWithGoalMetricSums::class);
        }

        return $reports;
    }

    /**
     * @param DataTable[] $records
     */
    protected function aggregateFromLogs(LogAggregator $logAggregator, array $records, $dimensions, $table, $aggregatorMethod): void
    {
        $whereClause = $table . ".referer_type = " . Common::REFERRER_TYPE_CAMPAIGN;
        $query       = $logAggregator->$aggregatorMethod($dimensions, $whereClause);

        $recordToDimensions = $this->getRecordToDimensions();

        while ($row = $query->fetch()) {
            foreach ($recordToDimensions as $recordName => $dimensionsForRecord) {
                $record = $records[$recordName];

                $mainLabelDimensions = $dimensionsForRecord[0];
                $mainLabel = $this->getLabelFromRowDimensions($mainLabelDimensions, $row);
                if (empty($mainLabel)) {
                    continue;
                }

                if ($aggregatorMethod == 'queryVisitsByDimension') {
                    $columns = [
                        Metrics::INDEX_NB_UNIQ_VISITORS => $row[Metrics::INDEX_NB_UNIQ_VISITORS],
                        Metrics::INDEX_NB_VISITS => $row[Metrics::INDEX_NB_VISITS],
                        Metrics::INDEX_NB_ACTIONS => $row[Metrics::INDEX_NB_ACTIONS],
                        Metrics::INDEX_NB_USERS => $row[Metrics::INDEX_NB_USERS],
                        Metrics::INDEX_MAX_ACTIONS => $row[Metrics::INDEX_MAX_ACTIONS],
                        Metrics::INDEX_SUM_VISIT_LENGTH => $row[Metrics::INDEX_SUM_VISIT_LENGTH],
                        Metrics::INDEX_BOUNCE_COUNT => $row[Metrics::INDEX_BOUNCE_COUNT],
                        Metrics::INDEX_NB_VISITS_CONVERTED => $row[Metrics::INDEX_NB_VISITS_CONVERTED],
                    ];
                } else if ($aggregatorMethod == 'queryConversionsByDimension') {
                    $idGoal = $row['idgoal'];
                    $columns = [
                        Metrics::INDEX_GOALS => [
                            $idGoal => Metrics::makeGoalColumnsRow($idGoal, $row),
                        ],
                    ];
                } else {
                    throw new \Exception("unknown aggregator method $aggregatorMethod");
                }

                $topLevelRow = $record->sumRowWithLabel($mainLabel, $columns);

                if (isset($dimensionsForRecord[1])) {
                    $subLabelDimensions = $dimensionsForRecord[1];
                    $subLabel = $this->getLabelFromRowDimensions($subLabelDimensions, $row);
                    if (empty($subLabel)) {
                        continue;
                    }
                    $topLevelRow->sumRowWithLabelToSubtable($subLabel, $columns);
                }
            }
        }
    }

    protected function getLabelFromRowDimensions(array $dimensionsAsLabel, array $row): string
    {
        $labels = [];
        foreach ($dimensionsAsLabel as $dimensionLabelPart) {
            if (isset($row[$dimensionLabelPart])
                && $row[$dimensionLabelPart] != ''
            ) {
                $labels[] = $row[$dimensionLabelPart];
            }
        }
        $label = implode(Archiver::SEPARATOR_COMBINED_DIMENSIONS, $labels);
        return $label;
    }

    protected function getRecordToDimensions(): array
    {
        return [
            Archiver::CAMPAIGN_ID_RECORD_NAME => [
                ["campaign_id"]
            ],
            Archiver::CAMPAIGN_NAME_RECORD_NAME => [
                ["campaign_name"],
                ["campaign_keyword", "campaign_content"],
            ],
            Archiver::CAMPAIGN_KEYWORD_RECORD_NAME => [
                ["campaign_keyword"],
            ],
            Archiver::CAMPAIGN_SOURCE_RECORD_NAME => [
                ["campaign_source"],
            ],
            Archiver::CAMPAIGN_MEDIUM_RECORD_NAME => [
                ["campaign_medium"],
            ],
            Archiver::CAMPAIGN_CONTENT_RECORD_NAME => [
                ["campaign_content"],
            ],
            Archiver::HIERARCHICAL_SOURCE_MEDIUM_RECORD_NAME => [
                ["campaign_source", "campaign_medium"],
                ["campaign_name"],
            ],
            Archiver::CAMPAIGN_GROUP_RECORD_NAME => [
                ["campaign_group"],
            ],
            Archiver::CAMPAIGN_PLACEMENT_RECORD_NAME => [
                ["campaign_placement"],
            ],
        ];
    }
}
