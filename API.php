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

namespace Piwik\Plugins\MarketingCampaignsReporting;

use Piwik\Archive;
use Piwik\DataTable;
use Piwik\Metrics;
use Piwik\Piwik;
use Piwik\Plugins\Referrers\API as ReferrersAPI;

/**
 * The MarketingCampaignsReporting API provides campaign reports grouped by campaign dimensions
 * such as name, keyword, source, medium, content, group, placement, and source/medium hierarchy.
 *
 * @package MarketingCampaignsReporting
 * @method static \Piwik\Plugins\MarketingCampaignsReporting\API getInstance()
 */
class API extends \Piwik\Plugin\API
{
    protected function getDataTable($name, $idSite, $period, $date, $segment, $expanded = false, $flat = false, $idSubtable = null)
    {
        Piwik::checkUserHasViewAccess($idSite);
        $dataTable = Archive::createDataTableFromArchive($name, $idSite, $period, $date, $segment, $expanded, $flat, $idSubtable);
        $dataTable->filter('Sort', array(Metrics::INDEX_NB_VISITS));
        return $dataTable;
    }

    /**
     * Returns campaign IDs with standard campaign metrics.
     *
     * @param int|string|int[] $idSite Website ID(s) to query.
     *                         - Single site ID (e.g. 1)
     *                         - Multiple site IDs (e.g. [1, 4, 5])
     *                         - Comma-separated list ("1,4,5") or "all"
     * @param string $period The period to process, processes data for the period containing the
     *                       specified date. Allowed values: "day", "week", "month", "year",
     *                       "range".
     * @param string $date The date or date range to process.
     *                     'YYYY-MM-DD', magic keywords (today, yesterday, lastWeek, lastMonth,
     *                     lastYear), or date range (ie, 'YYYY-MM-DD,YYYY-MM-DD', lastX,
     *                     previousX).
     * @param string|false $segment (Optional) Custom segment to filter the report.
     *                              Example: "referrerName==twitter.com"
     *                              Supports AND (;) and OR (,) operators.
     *                              [See documentation:](https://developer.matomo.org/api-reference/reporting-api-segmentation)
     * @return DataTable|DataTable\Map Campaign IDs report.
     */
    public function getId($idSite, $period, $date, $segment = false)
    {
        $dataTable = $this->getDataTable(Archiver::CAMPAIGN_ID_RECORD_NAME, $idSite, $period, $date, $segment);
        $dataTable->filter('AddSegmentValue');
        return $dataTable;
    }

    /**
     * Returns campaign names with standard campaign metrics.
     *
     * Falls back to the Referrers campaigns report when no archived data is available.
     *
     * @param int|string|int[] $idSite Website ID(s) to query.
     *                         - Single site ID (e.g. 1)
     *                         - Multiple site IDs (e.g. [1, 4, 5])
     *                         - Comma-separated list ("1,4,5") or "all"
     * @param string $period The period to process, processes data for the period containing the
     *                       specified date. Allowed values: "day", "week", "month", "year",
     *                       "range".
     * @param string $date The date or date range to process.
     *                     'YYYY-MM-DD', magic keywords (today, yesterday, lastWeek, lastMonth,
     *                     lastYear), or date range (ie, 'YYYY-MM-DD,YYYY-MM-DD', lastX,
     *                     previousX).
     * @param string|false $segment (Optional) Custom segment to filter the report.
     *                              Example: "referrerName==twitter.com"
     *                              Supports AND (;) and OR (,) operators.
     *                              [See documentation:](https://developer.matomo.org/api-reference/reporting-api-segmentation)
     * @param bool $expanded Whether to return subtables as nested data.
     * @param bool $flat Whether to flatten subtables into a single table.
     * @return DataTable|DataTable\Map Campaign names report.
     */
    public function getName($idSite, $period, $date, $segment = false, $expanded = false, $flat = false)
    {
        $dataTable = $this->getDataTable(Archiver::CAMPAIGN_NAME_RECORD_NAME, $idSite, $period, $date, $segment, $expanded, $flat);
        $dataTable->filter('AddSegmentValue');

        if ($this->isTableEmpty($dataTable)) {
            $referrersDataTable = ReferrersAPI::getInstance()->getCampaigns($idSite, $period, $date, $segment, $expanded);
            $dataTable          = $this->mergeDataTableMaps($dataTable, $referrersDataTable);
        }

        return $dataTable;
    }

    /**
     * Returns campaign keyword/content rows for a campaign name subtable ID.
     *
     * Falls back to Referrers campaign subtables and then campaign label lookup when needed.
     *
     * @param int|string|int[] $idSite Website ID(s) to query.
     *                         - Single site ID (e.g. 1)
     *                         - Multiple site IDs (e.g. [1, 4, 5])
     *                         - Comma-separated list ("1,4,5") or "all"
     * @param string $period The period to process, processes data for the period containing the
     *                       specified date. Allowed values: "day", "week", "month", "year",
     *                       "range".
     * @param string $date The date or date range to process.
     *                     'YYYY-MM-DD', magic keywords (today, yesterday, lastWeek, lastMonth,
     *                     lastYear), or date range (ie, 'YYYY-MM-DD,YYYY-MM-DD', lastX,
     *                     previousX).
     * @param int $idSubtable Subtable ID to load.
     * @param string|false $segment (Optional) Custom segment to filter the report.
     *                              Example: "referrerName==twitter.com"
     *                              Supports AND (;) and OR (,) operators.
     *                              [See documentation:](https://developer.matomo.org/api-reference/reporting-api-segmentation)
     * @return DataTable|DataTable\Map Campaign keyword/content rows for the selected campaign name.
     */
    public function getKeywordContentFromNameId($idSite, $period, $date, $idSubtable, $segment = false)
    {
        $dataTable = $this->getDataTable(Archiver::CAMPAIGN_NAME_RECORD_NAME, $idSite, $period, $date, $segment, $expanded = false, $flat = false, $idSubtable);

        if (!$this->isTableEmpty($dataTable)) {
            return $dataTable;
        }

        // try to load sub table from referrers api. That might work, if the report leading to this subtable was loaded using the referrers api fallback
        $referrersDataTable = ReferrersAPI::getInstance()->getKeywordsFromCampaignId($idSite, $period, $date, $idSubtable, $segment);

        if (!$this->isTableEmpty($referrersDataTable)) {
            return $this->mergeDataTableMaps($dataTable, $referrersDataTable);
        }

        // if we can't find a subtable report using the id, try fetching the label to search for a subtable
        $campaignNames = $this->getDataTable(Archiver::CAMPAIGN_NAME_RECORD_NAME, $idSite, $period, $date, $segment, $expanded = false);
        $row           = $campaignNames->getRowFromIdSubDataTable($idSubtable);

        if (!$row) {
            return $dataTable;
        }

        $campaignName = $row->getColumn('label');

        $campaignsDataTable = ReferrersAPI::getInstance()->getCampaigns($idSite, $period, $date, $segment, false);
        $campaignRow        = $campaignsDataTable->getRowFromLabel($campaignName);

        if ($campaignRow && $idSubtable = $campaignRow->getIdSubDataTable()) {
            $referrersDataTable = ReferrersAPI::getInstance()->getKeywordsFromCampaignId($idSite, $period, $date, $idSubtable, $segment);
            return $this->mergeDataTableMaps($dataTable, $referrersDataTable);
        }

        return $dataTable;
    }

    /**
     * Returns campaign keywords with standard campaign metrics.
     *
     * Falls back to merged Referrers campaign subtables when no archived keyword data is available.
     *
     * @param int|string|int[] $idSite Website ID(s) to query.
     *                         - Single site ID (e.g. 1)
     *                         - Multiple site IDs (e.g. [1, 4, 5])
     *                         - Comma-separated list ("1,4,5") or "all"
     * @param string $period The period to process, processes data for the period containing the
     *                       specified date. Allowed values: "day", "week", "month", "year",
     *                       "range".
     * @param string $date The date or date range to process.
     *                     'YYYY-MM-DD', magic keywords (today, yesterday, lastWeek, lastMonth,
     *                     lastYear), or date range (ie, 'YYYY-MM-DD,YYYY-MM-DD', lastX,
     *                     previousX).
     * @param string|false $segment (Optional) Custom segment to filter the report.
     *                              Example: "referrerName==twitter.com"
     *                              Supports AND (;) and OR (,) operators.
     *                              [See documentation:](https://developer.matomo.org/api-reference/reporting-api-segmentation)
     * @return DataTable|DataTable\Map Campaign keywords report.
     */
    public function getKeyword($idSite, $period, $date, $segment = false)
    {
        $dataTable = $this->getDataTable(Archiver::CAMPAIGN_KEYWORD_RECORD_NAME, $idSite, $period, $date, $segment);
        $dataTable->filter('AddSegmentValue');

        if ($this->isTableEmpty($dataTable)) {
            $referrersDataTable = ReferrersAPI::getInstance()->getCampaigns($idSite, $period, $date, $segment, $expanded = true);
            $referrersDataTable->applyQueuedFilters();
            $referrersDataTable = $referrersDataTable->mergeSubtables();

            $dataTable = $this->mergeDataTableMaps($dataTable, $referrersDataTable);
        }

        return $dataTable;
    }

    /**
     * Returns campaign sources with standard campaign metrics.
     *
     * @param int|string|int[] $idSite Website ID(s) to query.
     *                         - Single site ID (e.g. 1)
     *                         - Multiple site IDs (e.g. [1, 4, 5])
     *                         - Comma-separated list ("1,4,5") or "all"
     * @param string $period The period to process, processes data for the period containing the
     *                       specified date. Allowed values: "day", "week", "month", "year",
     *                       "range".
     * @param string $date The date or date range to process.
     *                     'YYYY-MM-DD', magic keywords (today, yesterday, lastWeek, lastMonth,
     *                     lastYear), or date range (ie, 'YYYY-MM-DD,YYYY-MM-DD', lastX,
     *                     previousX).
     * @param string|false $segment (Optional) Custom segment to filter the report.
     *                              Example: "referrerName==twitter.com"
     *                              Supports AND (;) and OR (,) operators.
     *                              [See documentation:](https://developer.matomo.org/api-reference/reporting-api-segmentation)
     * @return DataTable|DataTable\Map Campaign sources report.
     */
    public function getSource($idSite, $period, $date, $segment = false)
    {
        $dataTable = $this->getDataTable(Archiver::CAMPAIGN_SOURCE_RECORD_NAME, $idSite, $period, $date, $segment);
        $dataTable->filter('AddSegmentValue');
        return $dataTable;
    }

    /**
     * Returns campaign media with standard campaign metrics.
     *
     * @param int|string|int[] $idSite Website ID(s) to query.
     *                         - Single site ID (e.g. 1)
     *                         - Multiple site IDs (e.g. [1, 4, 5])
     *                         - Comma-separated list ("1,4,5") or "all"
     * @param string $period The period to process, processes data for the period containing the
     *                       specified date. Allowed values: "day", "week", "month", "year",
     *                       "range".
     * @param string $date The date or date range to process.
     *                     'YYYY-MM-DD', magic keywords (today, yesterday, lastWeek, lastMonth,
     *                     lastYear), or date range (ie, 'YYYY-MM-DD,YYYY-MM-DD', lastX,
     *                     previousX).
     * @param string|false $segment (Optional) Custom segment to filter the report.
     *                              Example: "referrerName==twitter.com"
     *                              Supports AND (;) and OR (,) operators.
     *                              [See documentation:](https://developer.matomo.org/api-reference/reporting-api-segmentation)
     * @return DataTable|DataTable\Map Campaign media report.
     */
    public function getMedium($idSite, $period, $date, $segment = false)
    {
        $dataTable = $this->getDataTable(Archiver::CAMPAIGN_MEDIUM_RECORD_NAME, $idSite, $period, $date, $segment);
        $dataTable->filter('AddSegmentValue');
        return $dataTable;
    }

    /**
     * Returns campaign contents with standard campaign metrics.
     *
     * @param int|string|int[] $idSite Website ID(s) to query.
     *                         - Single site ID (e.g. 1)
     *                         - Multiple site IDs (e.g. [1, 4, 5])
     *                         - Comma-separated list ("1,4,5") or "all"
     * @param string $period The period to process, processes data for the period containing the
     *                       specified date. Allowed values: "day", "week", "month", "year",
     *                       "range".
     * @param string $date The date or date range to process.
     *                     'YYYY-MM-DD', magic keywords (today, yesterday, lastWeek, lastMonth,
     *                     lastYear), or date range (ie, 'YYYY-MM-DD,YYYY-MM-DD', lastX,
     *                     previousX).
     * @param string|false $segment (Optional) Custom segment to filter the report.
     *                              Example: "referrerName==twitter.com"
     *                              Supports AND (;) and OR (,) operators.
     *                              [See documentation:](https://developer.matomo.org/api-reference/reporting-api-segmentation)
     * @return DataTable|DataTable\Map Campaign contents report.
     */
    public function getContent($idSite, $period, $date, $segment = false)
    {
        $dataTable = $this->getDataTable(Archiver::CAMPAIGN_CONTENT_RECORD_NAME, $idSite, $period, $date, $segment);
        $dataTable->filter('AddSegmentValue');
        return $dataTable;
    }

    /**
     * Returns campaign groups with standard campaign metrics.
     *
     * @param int|string|int[] $idSite Website ID(s) to query.
     *                         - Single site ID (e.g. 1)
     *                         - Multiple site IDs (e.g. [1, 4, 5])
     *                         - Comma-separated list ("1,4,5") or "all"
     * @param string $period The period to process, processes data for the period containing the
     *                       specified date. Allowed values: "day", "week", "month", "year",
     *                       "range".
     * @param string $date The date or date range to process.
     *                     'YYYY-MM-DD', magic keywords (today, yesterday, lastWeek, lastMonth,
     *                     lastYear), or date range (ie, 'YYYY-MM-DD,YYYY-MM-DD', lastX,
     *                     previousX).
     * @param string|false $segment (Optional) Custom segment to filter the report.
     *                              Example: "referrerName==twitter.com"
     *                              Supports AND (;) and OR (,) operators.
     *                              [See documentation:](https://developer.matomo.org/api-reference/reporting-api-segmentation)
     * @return DataTable|DataTable\Map Campaign groups report.
     */
    public function getGroup($idSite, $period, $date, $segment = false)
    {
        $dataTable = $this->getDataTable(Archiver::CAMPAIGN_GROUP_RECORD_NAME, $idSite, $period, $date, $segment);
        $dataTable->filter('AddSegmentValue');
        return $dataTable;
    }

    /**
     * Returns campaign placements with standard campaign metrics.
     *
     * @param int|string|int[] $idSite Website ID(s) to query.
     *                         - Single site ID (e.g. 1)
     *                         - Multiple site IDs (e.g. [1, 4, 5])
     *                         - Comma-separated list ("1,4,5") or "all"
     * @param string $period The period to process, processes data for the period containing the
     *                       specified date. Allowed values: "day", "week", "month", "year",
     *                       "range".
     * @param string $date The date or date range to process.
     *                     'YYYY-MM-DD', magic keywords (today, yesterday, lastWeek, lastMonth,
     *                     lastYear), or date range (ie, 'YYYY-MM-DD,YYYY-MM-DD', lastX,
     *                     previousX).
     * @param string|false $segment (Optional) Custom segment to filter the report.
     *                              Example: "referrerName==twitter.com"
     *                              Supports AND (;) and OR (,) operators.
     *                              [See documentation:](https://developer.matomo.org/api-reference/reporting-api-segmentation)
     * @return DataTable|DataTable\Map Campaign placements report.
     */
    public function getPlacement($idSite, $period, $date, $segment = false)
    {
        $dataTable = $this->getDataTable(Archiver::CAMPAIGN_PLACEMENT_RECORD_NAME, $idSite, $period, $date, $segment);
        $dataTable->filter('AddSegmentValue');
        return $dataTable;
    }

    /**
     * Returns hierarchical source/medium report rows.
     *
     * @param int|string|int[] $idSite Website ID(s) to query.
     *                         - Single site ID (e.g. 1)
     *                         - Multiple site IDs (e.g. [1, 4, 5])
     *                         - Comma-separated list ("1,4,5") or "all"
     * @param string $period The period to process, processes data for the period containing the
     *                       specified date. Allowed values: "day", "week", "month", "year",
     *                       "range".
     * @param string $date The date or date range to process.
     *                     'YYYY-MM-DD', magic keywords (today, yesterday, lastWeek, lastMonth,
     *                     lastYear), or date range (ie, 'YYYY-MM-DD,YYYY-MM-DD', lastX,
     *                     previousX).
     * @param string|false $segment (Optional) Custom segment to filter the report.
     *                              Example: "referrerName==twitter.com"
     *                              Supports AND (;) and OR (,) operators.
     *                              [See documentation:](https://developer.matomo.org/api-reference/reporting-api-segmentation)
     * @param bool $expanded Whether to return subtables as nested data.
     * @param bool $flat Whether to flatten subtables into a single table.
     * @return DataTable|DataTable\Map Hierarchical source/medium report.
     */
    public function getSourceMedium($idSite, $period, $date, $segment = false, $expanded = false, $flat = false)
    {
        $dataTable = $this->getDataTable(Archiver::HIERARCHICAL_SOURCE_MEDIUM_RECORD_NAME, $idSite, $period, $date, $segment, $expanded, $flat);
        return $dataTable;
    }

    /**
     * Returns campaign names for a hierarchical source/medium subtable ID.
     *
     * @param int|string|int[] $idSite Website ID(s) to query.
     *                         - Single site ID (e.g. 1)
     *                         - Multiple site IDs (e.g. [1, 4, 5])
     *                         - Comma-separated list ("1,4,5") or "all"
     * @param string $period The period to process, processes data for the period containing the
     *                       specified date. Allowed values: "day", "week", "month", "year",
     *                       "range".
     * @param string $date The date or date range to process.
     *                     'YYYY-MM-DD', magic keywords (today, yesterday, lastWeek, lastMonth,
     *                     lastYear), or date range (ie, 'YYYY-MM-DD,YYYY-MM-DD', lastX,
     *                     previousX).
     * @param int $idSubtable Subtable ID to load.
     * @param string|false $segment (Optional) Custom segment to filter the report.
     *                              Example: "referrerName==twitter.com"
     *                              Supports AND (;) and OR (,) operators.
     *                              [See documentation:](https://developer.matomo.org/api-reference/reporting-api-segmentation)
     * @return DataTable|DataTable\Map Campaign names for the selected source/medium row.
     */
    public function getNameFromSourceMediumId($idSite, $period, $date, $idSubtable, $segment = false)
    {
        $dataTable = $this->getDataTable(Archiver::HIERARCHICAL_SOURCE_MEDIUM_RECORD_NAME, $idSite, $period, $date, $segment, $expanded = false, $flat = false, $idSubtable);
        return $dataTable;
    }

    private function isTableEmpty(DataTable\DataTableInterface $dataTable)
    {
        if ($dataTable instanceof DataTable) {
            return $dataTable->getRowsCount() == 0;
        } elseif ($dataTable instanceof DataTable\Map) {
            foreach ($dataTable->getDataTables() as $label => $childTable) {
                if ($this->isTableEmpty($childTable)) {
                    return true;
                }
            }
            return false;
        } else {
            throw new \Exception("Sanity check: unknown datatable type '" . get_class($dataTable) . "'.");
        }
    }

    private function mergeDataTableMaps(
        DataTable\DataTableInterface $dataTable,
        DataTable\DataTableInterface $referrersDataTable
    ) {
        if ($dataTable instanceof DataTable) {
            if ($this->isTableEmpty($dataTable)) {
                $referrersDataTable->setAllTableMetadata($dataTable->getAllTableMetadata());
                return $referrersDataTable;
            } else {
                return $dataTable;
            }
        } elseif ($dataTable instanceof DataTable\Map) {
            foreach ($dataTable->getDataTables() as $label => $childTable) {
                $newTable = $this->mergeDataTableMaps($childTable, $referrersDataTable->getTable($label));
                $dataTable->addTable($newTable, $label);
            }
            return $dataTable;
        } else {
            throw new \Exception("Sanity check: unknown datatable type '" . get_class($dataTable) . "'.");
        }
    }
}
