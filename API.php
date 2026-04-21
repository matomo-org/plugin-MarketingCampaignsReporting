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
 * Exposes reporting API endpoints for marketing campaign dimensions and drill-down reports.
 * Includes campaign IDs, names, keywords, source and medium dimensions, and hierarchical subtables.
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
     * Returns campaign ID rows with standard marketing campaign metrics.
     *
     * @param int|string|int[] $idSite Website ID(s) to query.
     *                         - Single site ID (e.g. 1)
     *                         - Multiple site IDs (e.g. [1, 4, 5])
     *                         - Comma-separated list ("1,4,5") or "all"
     * @param 'day'|'week'|'month'|'year'|'range' $period The period to process, processes data
     *                                                   for the period containing the specified
     *                                                   date.
     * @param string $date The date or date range to process.
     *                     'YYYY-MM-DD', magic keywords (today, yesterday, lastWeek, lastMonth,
     *                     lastYear), or date range (ie, 'YYYY-MM-DD,YYYY-MM-DD', lastX,
     *                     previousX).
     * @param string|null|false $segment Custom segment to filter the report.
     *                                   Example: "referrerName==example.com"
     *                                   Supports AND (;) and OR (,) operators.
     * @return DataTable|DataTable\Map Campaign ID report rows with visit, conversion, and revenue
     *                                 metrics.
     */
    public function getId($idSite, $period, $date, $segment = false)
    {
        $dataTable = $this->getDataTable(Archiver::CAMPAIGN_ID_RECORD_NAME, $idSite, $period, $date, $segment);
        $dataTable->filter('AddSegmentValue');
        return $dataTable;
    }

    /**
     * Returns campaign name rows with standard marketing campaign metrics.
     * Falls back to the Referrers campaigns report when no archived data is available.
     *
     * @param int|string|int[] $idSite Website ID(s) to query.
     *                         - Single site ID (e.g. 1)
     *                         - Multiple site IDs (e.g. [1, 4, 5])
     *                         - Comma-separated list ("1,4,5") or "all"
     * @param 'day'|'week'|'month'|'year'|'range' $period The period to process, processes data
     *                                                   for the period containing the specified
     *                                                   date.
     * @param string $date The date or date range to process.
     *                     'YYYY-MM-DD', magic keywords (today, yesterday, lastWeek, lastMonth,
     *                     lastYear), or date range (ie, 'YYYY-MM-DD,YYYY-MM-DD', lastX,
     *                     previousX).
     * @param string|null|false $segment Custom segment to filter the report.
     *                                   Example: "referrerName==example.com"
     *                                   Supports AND (;) and OR (,) operators.
     * @param bool $expanded Whether to expand keyword/content subtables for each campaign name.
     * @param bool $flat Whether to flatten expanded subtables into a single table.
     * @return DataTable|DataTable\Map Campaign name report rows, optionally including drill-down
     *                                 subtables.
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
     * Returns keyword and content rows for a campaign name subtable.
     * Falls back to Referrers campaign subtables and campaign label lookup when needed.
     *
     * @param int|string|int[] $idSite Website ID(s) to query.
     *                         - Single site ID (e.g. 1)
     *                         - Multiple site IDs (e.g. [1, 4, 5])
     *                         - Comma-separated list ("1,4,5") or "all"
     * @param 'day'|'week'|'month'|'year'|'range' $period The period to process, processes data
     *                                                   for the period containing the specified
     *                                                   date.
     * @param string $date The date or date range to process.
     *                     'YYYY-MM-DD', magic keywords (today, yesterday, lastWeek, lastMonth,
     *                     lastYear), or date range (ie, 'YYYY-MM-DD,YYYY-MM-DD', lastX,
     *                     previousX).
     * @param int $idSubtable The subtable ID from a `getName` campaign row.
     * @param string|null|false $segment Custom segment to filter the report.
     *                                   Example: "referrerName==example.com"
     *                                   Supports AND (;) and OR (,) operators.
     * @return DataTable|DataTable\Map Keyword and content rows for the selected campaign name.
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
     * Returns campaign keyword rows with standard marketing campaign metrics.
     * Falls back to merged Referrers campaign subtables when no archived keyword data is available.
     *
     * @param int|string|int[] $idSite Website ID(s) to query.
     *                         - Single site ID (e.g. 1)
     *                         - Multiple site IDs (e.g. [1, 4, 5])
     *                         - Comma-separated list ("1,4,5") or "all"
     * @param 'day'|'week'|'month'|'year'|'range' $period The period to process, processes data
     *                                                   for the period containing the specified
     *                                                   date.
     * @param string $date The date or date range to process.
     *                     'YYYY-MM-DD', magic keywords (today, yesterday, lastWeek, lastMonth,
     *                     lastYear), or date range (ie, 'YYYY-MM-DD,YYYY-MM-DD', lastX,
     *                     previousX).
     * @param string|null|false $segment Custom segment to filter the report.
     *                                   Example: "referrerName==example.com"
     *                                   Supports AND (;) and OR (,) operators.
     * @return DataTable|DataTable\Map Campaign keyword report rows with visit, conversion, and
     *                                 revenue metrics.
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
     * Returns campaign source rows with standard marketing campaign metrics.
     *
     * @param int|string|int[] $idSite Website ID(s) to query.
     *                         - Single site ID (e.g. 1)
     *                         - Multiple site IDs (e.g. [1, 4, 5])
     *                         - Comma-separated list ("1,4,5") or "all"
     * @param 'day'|'week'|'month'|'year'|'range' $period The period to process, processes data
     *                                                   for the period containing the specified
     *                                                   date.
     * @param string $date The date or date range to process.
     *                     'YYYY-MM-DD', magic keywords (today, yesterday, lastWeek, lastMonth,
     *                     lastYear), or date range (ie, 'YYYY-MM-DD,YYYY-MM-DD', lastX,
     *                     previousX).
     * @param string|null|false $segment Custom segment to filter the report.
     *                                   Example: "referrerName==example.com"
     *                                   Supports AND (;) and OR (,) operators.
     * @return DataTable|DataTable\Map Campaign source report rows with visit, conversion, and
     *                                 revenue metrics.
     */
    public function getSource($idSite, $period, $date, $segment = false)
    {
        $dataTable = $this->getDataTable(Archiver::CAMPAIGN_SOURCE_RECORD_NAME, $idSite, $period, $date, $segment);
        $dataTable->filter('AddSegmentValue');
        return $dataTable;
    }

    /**
     * Returns campaign medium rows with standard marketing campaign metrics.
     *
     * @param int|string|int[] $idSite Website ID(s) to query.
     *                         - Single site ID (e.g. 1)
     *                         - Multiple site IDs (e.g. [1, 4, 5])
     *                         - Comma-separated list ("1,4,5") or "all"
     * @param 'day'|'week'|'month'|'year'|'range' $period The period to process, processes data
     *                                                   for the period containing the specified
     *                                                   date.
     * @param string $date The date or date range to process.
     *                     'YYYY-MM-DD', magic keywords (today, yesterday, lastWeek, lastMonth,
     *                     lastYear), or date range (ie, 'YYYY-MM-DD,YYYY-MM-DD', lastX,
     *                     previousX).
     * @param string|null|false $segment Custom segment to filter the report.
     *                                   Example: "referrerName==example.com"
     *                                   Supports AND (;) and OR (,) operators.
     * @return DataTable|DataTable\Map Campaign medium report rows with visit, conversion, and
     *                                 revenue metrics.
     */
    public function getMedium($idSite, $period, $date, $segment = false)
    {
        $dataTable = $this->getDataTable(Archiver::CAMPAIGN_MEDIUM_RECORD_NAME, $idSite, $period, $date, $segment);
        $dataTable->filter('AddSegmentValue');
        return $dataTable;
    }

    /**
     * Returns campaign content rows with standard marketing campaign metrics.
     *
     * @param int|string|int[] $idSite Website ID(s) to query.
     *                         - Single site ID (e.g. 1)
     *                         - Multiple site IDs (e.g. [1, 4, 5])
     *                         - Comma-separated list ("1,4,5") or "all"
     * @param 'day'|'week'|'month'|'year'|'range' $period The period to process, processes data
     *                                                   for the period containing the specified
     *                                                   date.
     * @param string $date The date or date range to process.
     *                     'YYYY-MM-DD', magic keywords (today, yesterday, lastWeek, lastMonth,
     *                     lastYear), or date range (ie, 'YYYY-MM-DD,YYYY-MM-DD', lastX,
     *                     previousX).
     * @param string|null|false $segment Custom segment to filter the report.
     *                                   Example: "referrerName==example.com"
     *                                   Supports AND (;) and OR (,) operators.
     * @return DataTable|DataTable\Map Campaign content report rows with visit, conversion, and
     *                                 revenue metrics.
     */
    public function getContent($idSite, $period, $date, $segment = false)
    {
        $dataTable = $this->getDataTable(Archiver::CAMPAIGN_CONTENT_RECORD_NAME, $idSite, $period, $date, $segment);
        $dataTable->filter('AddSegmentValue');
        return $dataTable;
    }

    /**
     * Returns campaign group rows with standard marketing campaign metrics.
     *
     * @param int|string|int[] $idSite Website ID(s) to query.
     *                         - Single site ID (e.g. 1)
     *                         - Multiple site IDs (e.g. [1, 4, 5])
     *                         - Comma-separated list ("1,4,5") or "all"
     * @param 'day'|'week'|'month'|'year'|'range' $period The period to process, processes data
     *                                                   for the period containing the specified
     *                                                   date.
     * @param string $date The date or date range to process.
     *                     'YYYY-MM-DD', magic keywords (today, yesterday, lastWeek, lastMonth,
     *                     lastYear), or date range (ie, 'YYYY-MM-DD,YYYY-MM-DD', lastX,
     *                     previousX).
     * @param string|null|false $segment Custom segment to filter the report.
     *                                   Example: "referrerName==example.com"
     *                                   Supports AND (;) and OR (,) operators.
     * @return DataTable|DataTable\Map Campaign group report rows with visit, conversion, and
     *                                 revenue metrics.
     */
    public function getGroup($idSite, $period, $date, $segment = false)
    {
        $dataTable = $this->getDataTable(Archiver::CAMPAIGN_GROUP_RECORD_NAME, $idSite, $period, $date, $segment);
        $dataTable->filter('AddSegmentValue');
        return $dataTable;
    }

    /**
     * Returns campaign placement rows with standard marketing campaign metrics.
     *
     * @param int|string|int[] $idSite Website ID(s) to query.
     *                         - Single site ID (e.g. 1)
     *                         - Multiple site IDs (e.g. [1, 4, 5])
     *                         - Comma-separated list ("1,4,5") or "all"
     * @param 'day'|'week'|'month'|'year'|'range' $period The period to process, processes data
     *                                                   for the period containing the specified
     *                                                   date.
     * @param string $date The date or date range to process.
     *                     'YYYY-MM-DD', magic keywords (today, yesterday, lastWeek, lastMonth,
     *                     lastYear), or date range (ie, 'YYYY-MM-DD,YYYY-MM-DD', lastX,
     *                     previousX).
     * @param string|null|false $segment Custom segment to filter the report.
     *                                   Example: "referrerName==example.com"
     *                                   Supports AND (;) and OR (,) operators.
     * @return DataTable|DataTable\Map Campaign placement report rows with visit, conversion, and
     *                                 revenue metrics.
     */
    public function getPlacement($idSite, $period, $date, $segment = false)
    {
        $dataTable = $this->getDataTable(Archiver::CAMPAIGN_PLACEMENT_RECORD_NAME, $idSite, $period, $date, $segment);
        $dataTable->filter('AddSegmentValue');
        return $dataTable;
    }

    /**
     * Returns hierarchical source and medium report rows.
     *
     * @param int|string|int[] $idSite Website ID(s) to query.
     *                         - Single site ID (e.g. 1)
     *                         - Multiple site IDs (e.g. [1, 4, 5])
     *                         - Comma-separated list ("1,4,5") or "all"
     * @param 'day'|'week'|'month'|'year'|'range' $period The period to process, processes data
     *                                                   for the period containing the specified
     *                                                   date.
     * @param string $date The date or date range to process.
     *                     'YYYY-MM-DD', magic keywords (today, yesterday, lastWeek, lastMonth,
     *                     lastYear), or date range (ie, 'YYYY-MM-DD,YYYY-MM-DD', lastX,
     *                     previousX).
     * @param string|null|false $segment Custom segment to filter the report.
     *                                   Example: "referrerName==example.com"
     *                                   Supports AND (;) and OR (,) operators.
     * @param bool $expanded Whether to expand campaign-name subtables under each source/medium row.
     * @param bool $flat Whether to flatten expanded subtables into a single table.
     * @return DataTable|DataTable\Map Hierarchical source/medium rows, optionally including
     *                                 nested campaign-name subtables.
     */
    public function getSourceMedium($idSite, $period, $date, $segment = false, $expanded = false, $flat = false)
    {
        $dataTable = $this->getDataTable(Archiver::HIERARCHICAL_SOURCE_MEDIUM_RECORD_NAME, $idSite, $period, $date, $segment, $expanded, $flat);
        return $dataTable;
    }

    /**
     * Returns campaign names for a source and medium subtable.
     *
     * @param int|string|int[] $idSite Website ID(s) to query.
     *                         - Single site ID (e.g. 1)
     *                         - Multiple site IDs (e.g. [1, 4, 5])
     *                         - Comma-separated list ("1,4,5") or "all"
     * @param 'day'|'week'|'month'|'year'|'range' $period The period to process, processes data
     *                                                   for the period containing the specified
     *                                                   date.
     * @param string $date The date or date range to process.
     *                     'YYYY-MM-DD', magic keywords (today, yesterday, lastWeek, lastMonth,
     *                     lastYear), or date range (ie, 'YYYY-MM-DD,YYYY-MM-DD', lastX,
     *                     previousX).
     * @param int $idSubtable The subtable ID from a `getSourceMedium` source/medium row.
     * @param string|null|false $segment Custom segment to filter the report.
     *                                   Example: "referrerName==example.com"
     *                                   Supports AND (;) and OR (,) operators.
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
