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
 * API for plugin MarketingCampaignsReporting
 *
 * @package MarketingCampaignsReporting
 * @method static \Piwik\Plugins\MarketingCampaignsReporting\API getInstance()
 */
class API extends \Piwik\Plugin\API
{
    protected function getDataTable($name, $idSite, $period, $date, $segment, $expanded = false, $idSubtable = null)
    {
        Piwik::checkUserHasViewAccess($idSite);
        $dataTable = Archive::createDataTableFromArchive($name, $idSite, $period, $date, $segment, $expanded, false, $idSubtable);
        $dataTable->filter('Sort', array(Metrics::INDEX_NB_VISITS));
        return $dataTable;
    }

    public function getId($idSite, $period, $date, $segment = false)
    {
        $dataTable = $this->getDataTable(Archiver::CAMPAIGN_ID_RECORD_NAME, $idSite, $period, $date, $segment);
        $dataTable->filter('AddSegmentValue');
        return $dataTable;
    }

    public function getName($idSite, $period, $date, $segment = false, $expanded = false)
    {
        $dataTable = $this->getDataTable(Archiver::CAMPAIGN_NAME_RECORD_NAME, $idSite, $period, $date, $segment, $expanded);
        $dataTable->filter('AddSegmentValue');

        if ($this->isTableEmpty($dataTable)) {
            $referrersDataTable = ReferrersAPI::getInstance()->getCampaigns($idSite, $period, $date, $segment, $expanded);
            $dataTable          = $this->mergeDataTableMaps($dataTable, $referrersDataTable);
        }

        return $dataTable;
    }

    public function getKeywordContentFromNameId($idSite, $period, $date, $idSubtable, $segment = false)
    {
        $dataTable = $this->getDataTable(Archiver::CAMPAIGN_NAME_RECORD_NAME, $idSite, $period, $date, $segment, $expanded = false, $idSubtable);

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

    public function getSource($idSite, $period, $date, $segment = false)
    {
        $dataTable = $this->getDataTable(Archiver::CAMPAIGN_SOURCE_RECORD_NAME, $idSite, $period, $date, $segment);
        $dataTable->filter('AddSegmentValue');
        return $dataTable;
    }

    public function getMedium($idSite, $period, $date, $segment = false)
    {
        $dataTable = $this->getDataTable(Archiver::CAMPAIGN_MEDIUM_RECORD_NAME, $idSite, $period, $date, $segment);
        $dataTable->filter('AddSegmentValue');
        return $dataTable;
    }

    public function getContent($idSite, $period, $date, $segment = false)
    {
        $dataTable = $this->getDataTable(Archiver::CAMPAIGN_CONTENT_RECORD_NAME, $idSite, $period, $date, $segment);
        $dataTable->filter('AddSegmentValue');
        return $dataTable;
    }

    public function getGroup($idSite, $period, $date, $segment = false)
    {
        $dataTable = $this->getDataTable(Archiver::CAMPAIGN_GROUP_RECORD_NAME, $idSite, $period, $date, $segment);
        $dataTable->filter('AddSegmentValue');
        return $dataTable;
    }

    public function getPlacement($idSite, $period, $date, $segment = false)
    {
        $dataTable = $this->getDataTable(Archiver::CAMPAIGN_PLACEMENT_RECORD_NAME, $idSite, $period, $date, $segment);
        $dataTable->filter('AddSegmentValue');
        return $dataTable;
    }

    public function getSourceMedium($idSite, $period, $date, $segment = false, $expanded = false)
    {
        $dataTable = $this->getDataTable(Archiver::HIERARCHICAL_SOURCE_MEDIUM_RECORD_NAME, $idSite, $period, $date, $segment, $expanded);
        return $dataTable;
    }

    public function getNameFromSourceMediumId($idSite, $period, $date, $idSubtable, $segment = false)
    {
        $dataTable = $this->getDataTable(Archiver::HIERARCHICAL_SOURCE_MEDIUM_RECORD_NAME, $idSite, $period, $date, $segment, $expanded = false, $idSubtable);
        return $dataTable;
    }

    private function isTableEmpty(DataTable\DataTableInterface $dataTable)
    {
        if ($dataTable instanceof DataTable) {
            return $dataTable->getRowsCount() == 0;
        } else if ($dataTable instanceof DataTable\Map) {
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

    private function mergeDataTableMaps(DataTable\DataTableInterface $dataTable,
                                        DataTable\DataTableInterface $referrersDataTable)
    {
        if ($dataTable instanceof DataTable) {
            if ($this->isTableEmpty($dataTable)) {
                $referrersDataTable->setAllTableMetadata($dataTable->getAllTableMetadata());
                return $referrersDataTable;
            } else {
                return $dataTable;
            }
        } else if ($dataTable instanceof DataTable\Map) {
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
