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
use Piwik\Container\StaticContainer;
use Piwik\DataAccess\LogAggregator;
use Piwik\DataTable;
use Piwik\Metrics;
use Piwik\Plugins\MarketingCampaignsReporting\Archiver;
use Piwik\Plugins\MarketingCampaignsReporting\SystemSettings;
use Piwik\RankingQuery;

class CampaignReporting extends RecordBuilder
{
    /**
     * Subtables, and records built without a ranking query, are capped at this multiple of their archived
     * size while they are built, so that the truncation in insertBlobRecord() still picks the top rows from
     * a wider set of candidates.
     */
    private const BUILD_ROWS_FACTOR = 10;

    private const GOAL_PARTITION_COLUMN = 'idgoal_partition';

    private const LABEL_ALIAS_PREFIX = 'label_';

    /**
     * @var array[][] per top-level label, the columns of the subtables pass's rows that have no subtable
     *                label: the subtable misses them without their having been ranked past the limit
     */
    private array $unlabelledColumns = [];

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
        $rankingQueryLimit = $this->getRankingQueryLimit();
        $idGoals = $rankingQueryLimit > 0 ? $this->getConvertedGoalIds($logAggregator) : [];

        $reports = [];
        foreach ($this->getRecordToDimensions() as $recordName => $dimensionsForRecord) {
            $record = new DataTable();
            // with a ranking query, each query's limit bounds the record instead, and a cap here could
            // fold a ranked label into "Others" just because it arrived late
            if ($rankingQueryLimit === 0) {
                $record->setMaximumAllowedRows(self::BUILD_ROWS_FACTOR * (int) $this->maxRowsInTable);
            }

            $passes = [[$dimensionsForRecord, false]];
            if ($rankingQueryLimit > 0 && isset($dimensionsForRecord[1])) {
                // the top level is ranked on its own label, since ranking label combinations would leave a
                // campaign spread over many small ones in "Others"; the combinations then fill subtables
                $passes = [[[$dimensionsForRecord[0]], false], [$dimensionsForRecord, true]];
            }

            $subtablesMissRows = false;
            $this->unlabelledColumns = [];
            foreach ($passes as [$dimensions, $subtablesOnly]) {
                foreach (['log_visit', 'log_conversion'] as $table) {
                    $rankedPastLimit = $this->aggregateFromLogs($logAggregator, $record, $dimensions, $table, $rankingQueryLimit, $idGoals, $subtablesOnly);
                    $subtablesMissRows = $subtablesMissRows || ($subtablesOnly && $rankedPastLimit);
                }
            }
            if ($subtablesMissRows) {
                $this->addSubtableRemainders($record);
            }

            $record->filter(DataTable\Filter\EnrichRecordWithGoalMetricSums::class);
            $reports[$recordName] = $record;
        }

        return $reports;
    }

    /**
     * @param string[][] $dimensionsForRecord the columns of the record's label, then of its subtable's label
     * @param int[] $idGoals
     * @param bool $subtablesOnly add rows only to the subtables of rows already in the record
     * @return bool whether the ranking query summed rows past its limit into "Others"
     */
    protected function aggregateFromLogs(
        LogAggregator $logAggregator,
        DataTable $record,
        array $dimensionsForRecord,
        string $table,
        int $rankingQueryLimit,
        array $idGoals,
        bool $subtablesOnly = false
    ): bool {
        $isConversions = $table === 'log_conversion';
        if ($isConversions && $rankingQueryLimit > 0 && empty($idGoals)) {
            return false;
        }
        $rankedPastLimit = false;

        $labelColumns = array_values(array_unique(array_merge(...$dimensionsForRecord)));
        $dimensions = $this->getSelectedDimensions($labelColumns, $table);
        $whereClause = $table . ".referer_type = " . Common::REFERRER_TYPE_CAMPAIGN;

        $rankingQuery = false;
        if ($rankingQueryLimit > 0) {
            $rankingQuery = new RankingQuery($rankingQueryLimit);
            $rankingQuery->addLabelColumn(array_keys($dimensions));
        }

        if ($isConversions) {
            $additionalSelects = [];
            if ($rankingQuery) {
                // one limit and one "Others" row per goal, so conversions past the limit keep their goal;
                // shifted by one because the abandoned cart goal is -1, which cannot name a counter
                $rankingQuery->partitionResultIntoMultipleGroups(self::GOAL_PARTITION_COLUMN, array_map(function ($idGoal) {
                    return $idGoal + 1;
                }, $idGoals));
                $additionalSelects[] = 'log_conversion.idgoal + 1 AS ' . self::GOAL_PARTITION_COLUMN;
            }
            $query = $logAggregator->queryConversionsByDimension($dimensions, $whereClause, $additionalSelects, [], $rankingQuery, true, true);
        } else {
            // core orders by visits only for a ranking query; without one, the capped record must still
            // see the biggest labels first
            $orderBy = $rankingQuery ? false : '`' . Metrics::INDEX_NB_VISITS . '` DESC';
            $query = $logAggregator->queryVisitsByDimension($dimensions, $whereClause, [], false, $rankingQuery, $orderBy, -1, true);
        }

        while ($row = $query->fetch()) {
            foreach ($labelColumns as $column) {
                $row[$column] = $row[self::LABEL_ALIAS_PREFIX . $column];
            }
            if ($isConversions) {
                $idGoal = $rankingQuery ? (int) $row[self::GOAL_PARTITION_COLUMN] - 1 : (int) $row['idgoal'];
                if ($rankingQuery && !in_array($idGoal, $idGoals, true)) {
                    // a goal first converted after getConvertedGoalIds() ran has no partition, so its rows
                    // are merged under an arbitrary label; leave it to the next archiving run
                    continue;
                }
                $columns = [
                    Metrics::INDEX_GOALS => [
                        $idGoal => Metrics::makeGoalColumnsRow($idGoal, $row),
                    ],
                ];
            } else {
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
            }

            if ($rankingQuery && $row[$labelColumns[0]] === RankingQuery::LABEL_SUMMARY_ROW) {
                $rankedPastLimit = true;
                // in the subtables pass these visits are already counted in the top level
                if (!$subtablesOnly) {
                    $record->sumRowWithLabel(RankingQuery::LABEL_SUMMARY_ROW, $columns);
                }
                continue;
            }

            $mainLabel = $this->getLabelFromRowDimensions($dimensionsForRecord[0], $row);
            if (empty($mainLabel)) {
                continue;
            }

            $topLevelRow = $subtablesOnly
                ? $record->getRowFromLabel($mainLabel)
                : $record->sumRowWithLabel($mainLabel, $columns);

            // the top level ranks visits and each goal's conversions separately, so a kept row can have
            // had some of them summed into "Others", and its subtable must not count them a second time
            if ($subtablesOnly && !empty($topLevelRow)) {
                $parentGoals = $topLevelRow->getColumn(Metrics::INDEX_GOALS) ?: [];
                $parentHasMetric = $isConversions
                    ? isset($parentGoals[$idGoal])
                    : !empty($topLevelRow->getColumn(Metrics::INDEX_NB_VISITS));
                if (!$parentHasMetric) {
                    continue;
                }
            }

            if (!isset($dimensionsForRecord[1])) {
                continue;
            }

            $subLabel = $this->getLabelFromRowDimensions($dimensionsForRecord[1], $row);
            // once the record is full, sumRowWithLabel() sums into the summary row and returns a row
            // that is not in the record, and a subtable created on it would never be freed; the label
            // '-1' also resolves to the summary row, which must not get a subtable
            if (
                empty($topLevelRow)
                || $record->getRowFromLabel($mainLabel) !== $topLevelRow
                || $topLevelRow === $record->getSummaryRow()
            ) {
                continue;
            }
            if (empty($subLabel)) {
                if ($subtablesOnly) {
                    $this->unlabelledColumns[$mainLabel][] = $columns;
                }
                continue;
            }

            $subtable = $topLevelRow->getSubtable();
            if (empty($subtable)) {
                $subtable = new DataTable();
                $subtable->setMaximumAllowedRows(self::BUILD_ROWS_FACTOR * (int) $this->maxRowsInSubtable);
                $topLevelRow->setSubtable($subtable);
            }
            $subtable->sumRowWithLabel($subLabel, $columns);
        }

        return $rankedPastLimit;
    }

    /**
     * Sums into each subtable's "Others" row what its row has but the subtable is missing, since the
     * combinations ranked past the limit cannot be told apart by row.
     */
    private function addSubtableRemainders(DataTable $record): void
    {
        foreach ($record->getRowsWithoutSummaryRow() as $row) {
            $remainder = $row->getColumns();
            // distinct counts and maximums cannot be split by subtraction
            unset(
                $remainder['label'],
                $remainder[Metrics::INDEX_MAX_ACTIONS],
                $remainder[Metrics::INDEX_NB_UNIQ_VISITORS],
                $remainder[Metrics::INDEX_NB_USERS]
            );

            $subtable = $row->getSubtable();
            foreach ($subtable ? $subtable->getRows() : [] as $subRow) {
                $remainder = $this->subtractColumns($remainder, $subRow->getColumns());
            }
            foreach ($this->unlabelledColumns[$row->getColumn('label')] ?? [] as $columns) {
                $remainder = $this->subtractColumns($remainder, $columns);
            }

            $remainder = $this->keepPositiveColumns($remainder);
            $goals = [];
            foreach ($remainder[Metrics::INDEX_GOALS] ?? [] as $idGoal => $goal) {
                if (!empty($goal[Metrics::INDEX_GOAL_NB_CONVERSIONS])) {
                    // core's goal metric sums read every column, so the ones dropped as zero are put back
                    $goals[$idGoal] = $goal + array_fill_keys(array_keys(Metrics::makeGoalColumnsRow((int) $idGoal, [])), 0);
                }
            }
            unset($remainder[Metrics::INDEX_GOALS]);
            if (!empty($goals)) {
                $remainder[Metrics::INDEX_GOALS] = $goals;
            }
            if (empty($remainder)) {
                continue;
            }
            if (empty($subtable)) {
                $subtable = new DataTable();
                $subtable->setMaximumAllowedRows(self::BUILD_ROWS_FACTOR * (int) $this->maxRowsInSubtable);
                $row->setSubtable($subtable);
            }
            $subtable->sumRowWithLabel(RankingQuery::LABEL_SUMMARY_ROW, $remainder);
        }
    }

    private function subtractColumns(array $columns, array $subtrahend): array
    {
        foreach ($columns as $name => $value) {
            if (!isset($subtrahend[$name])) {
                continue;
            }
            if (is_array($value) && is_array($subtrahend[$name])) {
                $columns[$name] = $this->subtractColumns($value, $subtrahend[$name]);
            } elseif (is_numeric($value) && is_numeric($subtrahend[$name])) {
                $columns[$name] = $value - $subtrahend[$name];
            }
        }
        return $columns;
    }

    private function keepPositiveColumns(array $columns): array
    {
        foreach ($columns as $name => $value) {
            if (is_array($value)) {
                $value = $this->keepPositiveColumns($value);
            } elseif (is_numeric($value)) {
                // revenue is a float, so a fully covered row can leave a rounding error
                $value = round((float) $value, 2) > 0 ? $value : null;
            } else {
                $value = null;
            }
            if (empty($value)) {
                unset($columns[$name]);
            } else {
                $columns[$name] = $value;
            }
        }
        return $columns;
    }

    /**
     * @param string[] $labelColumns
     */
    private function getSelectedDimensions(array $labelColumns, string $table): array
    {
        $systemSettings = StaticContainer::get(SystemSettings::class);
        // grouped by exact bytes: the log tables' collation would otherwise merge "Spring" and "spring", or "café" and
        // "cafe", under either spelling, and each query, the top level's and its subtables', can pick a different one.
        // LOWER() goes inside the CAST, as it leaves a binary string unchanged
        $expression = $systemSettings->doNotChangeCaseOfUtmParameters->getValue() ? 'CAST(%s AS BINARY)' : 'CAST(LOWER(%s) AS BINARY)';

        $dimensions = [];
        foreach ($labelColumns as $column) {
            // not aliased as the column itself, which MySQL's GROUP BY would resolve to the table's column
            $dimensions[self::LABEL_ALIAS_PREFIX . $column] = sprintf($expression, "$table.$column");
        }
        return $dimensions;
    }

    /**
     * @return int[]
     */
    private function getConvertedGoalIds(LogAggregator $logAggregator): array
    {
        $whereClause = "log_conversion.referer_type = " . Common::REFERRER_TYPE_CAMPAIGN;
        $query = $logAggregator->queryConversionsByDimension([], $whereClause, [], [], false, false, true);

        $idGoals = [];
        while ($row = $query->fetch()) {
            $idGoals[] = (int) $row['idgoal'];
        }
        return $idGoals;
    }

    private function getRankingQueryLimit(): int
    {
        $configLimit = (int) Config::getInstance()->General['archiving_ranking_query_row_limit'];
        if ($configLimit === 0) {
            return 0;
        }

        return max($configLimit, self::BUILD_ROWS_FACTOR * (int) $this->maxRowsInTable);
    }

    protected function getLabelFromRowDimensions(array $dimensionsAsLabel, array $row): string
    {
        $labels = [];
        foreach ($dimensionsAsLabel as $dimensionLabelPart) {
            if (
                isset($row[$dimensionLabelPart])
                && $row[$dimensionLabelPart] != ''
            ) {
                $labels[] = $row[$dimensionLabelPart];
            }
        }

        return implode(Archiver::SEPARATOR_COMBINED_DIMENSIONS, $labels);
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
