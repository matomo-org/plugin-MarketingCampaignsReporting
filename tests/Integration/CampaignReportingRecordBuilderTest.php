<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\MarketingCampaignsReporting\tests\Integration;

use Piwik\ArchiveProcessor;
use Piwik\Config;
use Piwik\DataAccess\ArchiveWriter;
use Piwik\DataAccess\LogAggregator;
use Piwik\DataTable;
use Piwik\Date;
use Piwik\Metrics;
use Piwik\Period\Factory as PeriodFactory;
use Piwik\Plugins\Goals\API as GoalsApi;
use Piwik\Plugins\MarketingCampaignsReporting\Archiver;
use Piwik\Plugins\MarketingCampaignsReporting\RecordBuilders\CampaignReporting;
use Piwik\Segment;
use Piwik\Site;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;
use Piwik\Tracker\GoalManager;

/**
 * @group MarketingCampaignsReporting
 * @group CampaignReportingRecordBuilderTest
 * @group Plugins
 */
class CampaignReportingRecordBuilderTest extends IntegrationTestCase
{
    private const DATE = '2024-03-01';
    private const VISITS_PER_GROUP = 30;
    private const TOTAL_VISITS = 3 * self::VISITS_PER_GROUP;

    // the other records skip the visits, which have no campaign id, content, group or placement
    private const RECORDS_SET_BY_EVERY_VISIT = [
        Archiver::CAMPAIGN_NAME_RECORD_NAME,
        Archiver::CAMPAIGN_SOURCE_RECORD_NAME,
        Archiver::CAMPAIGN_MEDIUM_RECORD_NAME,
        Archiver::HIERARCHICAL_SOURCE_MEDIUM_RECORD_NAME,
    ];

    private $idSite;
    private $idGoal;

    public function setUp(): void
    {
        parent::setUp();

        Fixture::createSuperUser();
        $this->idSite = Fixture::createWebsite('2024-01-01 00:00:00', $ecommerce = 1, 'TestSite', 'https://example.com');
        $this->idGoal = GoalsApi::getInstance()->addGoal($this->idSite, 'Goal', 'manually', '', 'contains');

        // 40 label combinations from each ranking query, and while building 10 rows per subtable, and 20 per
        // table when there is no ranking query
        Config::getInstance()->General['datatable_archiving_maximum_rows_referrers'] = 2;
        Config::getInstance()->General['datatable_archiving_maximum_rows_subtable_referrers'] = 1;
        Config::getInstance()->General['archiving_ranking_query_row_limit'] = 40;
    }

    public function testAggregateBoundsTablesWhileBuildingAndKeepsAllVisitsAndConversions(): void
    {
        $this->trackVisits();
        $lastTableId = DataTable\Manager::getInstance()->getMostRecentTableId();
        $records = $this->aggregate();

        // the ranked labels, or label combinations, plus "Others"
        $this->assertBoundedAndComplete($records, 41);
        foreach (self::RECORDS_SET_BY_EVERY_VISIT as $recordName) {
            $this->assertSame(self::VISITS_PER_GROUP, $this->sumConversions($records[$recordName], GoalManager::IDGOAL_CART), $recordName);
        }

        // the 31 campaign names fit in the ranking limit, though their 60 combinations don't
        $names = $records[Archiver::CAMPAIGN_NAME_RECORD_NAME];
        $this->assertEmpty($names->getSummaryRow());
        $big = $names->getRowFromLabel('big');
        $this->assertSame(2 * self::VISITS_PER_GROUP, (int) $big->getColumn(Metrics::INDEX_NB_VISITS));
        $bigSubtable = $big->getSubtable();
        $this->assertInstanceOf(DataTable::class, $bigSubtable);
        $this->assertLessThanOrEqual(10, $bigSubtable->getRowsCountWithoutSummaryRow() + 1);
        foreach ($bigSubtable->getRowsWithoutSummaryRow() as $keywordRow) {
            $this->assertSame(2, (int) $keywordRow->getColumn(Metrics::INDEX_NB_VISITS), $keywordRow->getColumn('label'));
        }

        // every subtable created is attached to a row kept in its record, none to rows summed into "Others"
        $subtableCount = 0;
        foreach ($records as $record) {
            foreach ($record->getRows() as $row) {
                $subtableCount += $row->getSubtable() ? 1 : 0;
            }
        }
        $this->assertSame(count($records) + $subtableCount, DataTable\Manager::getInstance()->getMostRecentTableId() - $lastTableId);

        // a report with few labels is ranked on its own labels, so it stays exact
        $sourceMedium = $records[Archiver::HIERARCHICAL_SOURCE_MEDIUM_RECORD_NAME];
        $this->assertEmpty($sourceMedium->getSummaryRow());
        $this->assertSame(self::TOTAL_VISITS, (int) $sourceMedium->getRowFromLabel('src - med')->getColumn(Metrics::INDEX_NB_VISITS));
    }

    public function testAggregateKeepsCampaignSpreadOverManyKeywordsWithRankingQuery(): void
    {
        $tracker = $this->getTracker();
        $visitNumber = 0;
        // enough bigger combinations that some of "spread"'s fall past the ranking limit
        for ($i = 1; $i <= 30; $i++) {
            for ($j = 0; $j < 2; $j++) {
                $this->addVisit($tracker, ++$visitNumber, "mtm_campaign=camp$i&mtm_kwd=kwd&mtm_source=src&mtm_medium=med", false);
            }
        }
        for ($i = 1; $i <= 15; $i++) {
            $this->addVisit($tracker, ++$visitNumber, "mtm_campaign=spread&mtm_kwd=kwd$i&mtm_source=src&mtm_medium=med", false);
        }
        $records = $this->aggregate();

        $spread = $records[Archiver::CAMPAIGN_NAME_RECORD_NAME]->getRowFromLabel('spread');
        $this->assertNotEmpty($spread);
        $this->assertSame(15, (int) $spread->getColumn(Metrics::INDEX_NB_VISITS));

        // the keywords past the limit are summed into the subtable's "Others" row
        $keywords = $spread->getSubtable();
        $this->assertNotEmpty($keywords->getSummaryRow());
        $this->assertSame(15, $this->sumColumn($keywords, Metrics::INDEX_NB_VISITS));
        $this->assertSame(15, $this->sumConversions($keywords, $this->idGoal));
    }

    public function testAggregateKeepsAllVisitsWithoutRankingQueryLimit(): void
    {
        Config::getInstance()->General['archiving_ranking_query_row_limit'] = 0;

        $this->trackVisits();
        $records = $this->aggregate();

        $this->assertBoundedAndComplete($records, 20);
    }

    public function testAggregateKeepsBiggestLabelsWithoutRankingQueryLimit(): void
    {
        Config::getInstance()->General['archiving_ranking_query_row_limit'] = 0;

        $this->trackVisits();
        // bigger than every campaign but "big", and named and tracked to reach the record last unless
        // visits arrive biggest-first
        $tracker = $this->getTracker();
        for ($i = 1; $i <= 3; $i++) {
            $this->addVisit($tracker, 3 * self::VISITS_PER_GROUP + $i, 'mtm_campaign=zlate&mtm_source=src&mtm_medium=med', false);
        }
        $records = $this->aggregate();

        $late = $records[Archiver::CAMPAIGN_NAME_RECORD_NAME]->getRowFromLabel('zlate');
        $this->assertNotEmpty($late);
        $this->assertSame(3, (int) $late->getColumn(Metrics::INDEX_NB_VISITS));
    }

    public function testAggregateAttachesNoSubtableToOthersForCampaignNamedMinusOne(): void
    {
        Config::getInstance()->General['archiving_ranking_query_row_limit'] = 20;

        $tracker = $this->getTracker();
        $visitNumber = 0;
        // 25 campaigns bigger than "-1" fill the top level, so it is ranked into "Others", while its one
        // combination is bigger than theirs, so the combinations pass keeps it; '-1' then resolves to
        // the summary row
        for ($i = 1; $i <= 25; $i++) {
            for ($j = 1; $j <= 4; $j++) {
                $this->addVisit($tracker, ++$visitNumber, "mtm_campaign=camp$i&mtm_kwd=kwd$j&mtm_source=src&mtm_medium=med", false);
            }
        }
        for ($j = 1; $j <= 2; $j++) {
            $this->addVisit($tracker, ++$visitNumber, 'mtm_campaign=-1&mtm_kwd=kwd&mtm_source=src&mtm_medium=med', false);
        }
        $records = $this->aggregate();

        $summaryRow = $records[Archiver::CAMPAIGN_NAME_RECORD_NAME]->getSummaryRow();
        $this->assertNotEmpty($summaryRow);
        $this->assertEmpty($summaryRow->getSubtable());
    }

    /**
     * @param DataTable[] $records
     */
    private function assertBoundedAndComplete(array $records, int $maxRows): void
    {
        foreach ($records as $recordName => $record) {
            $this->assertLessThanOrEqual($maxRows, $record->getRowsCountWithoutSummaryRow() + 1, $recordName);
        }
        foreach (self::RECORDS_SET_BY_EVERY_VISIT as $recordName) {
            $this->assertSame(self::TOTAL_VISITS, $this->sumColumn($records[$recordName], Metrics::INDEX_NB_VISITS), $recordName);
            $this->assertSame(self::TOTAL_VISITS, $this->sumConversions($records[$recordName], $this->idGoal), $recordName);
        }
    }

    private function getTracker(): \MatomoTracker
    {
        $tracker = Fixture::getTracker($this->idSite, self::DATE . ' 10:00:00', $defaultInit = true, $useLocal = true);
        $tracker->setTokenAuth(Fixture::getTokenAuth());
        return $tracker;
    }

    private function trackVisits(): void
    {
        $tracker = $this->getTracker();
        for ($i = 1; $i <= self::VISITS_PER_GROUP; $i++) {
            // two visits per "big" combination, so the ranking query returns them before the others
            $this->addVisit($tracker, $i, "mtm_campaign=big&mtm_kwd=kwd$i&mtm_source=src&mtm_medium=med", false);
            $this->addVisit($tracker, self::VISITS_PER_GROUP + $i, "mtm_campaign=big&mtm_kwd=kwd$i&mtm_source=src&mtm_medium=med", false);
            // a keyword, so the rows arriving after the Name table is full have a subtable label
            $this->addVisit($tracker, 2 * self::VISITS_PER_GROUP + $i, "mtm_campaign=name$i&mtm_kwd=other&mtm_source=src&mtm_medium=med", true);
        }
    }

    private function addVisit(\MatomoTracker $tracker, int $visitNumber, string $campaignQuery, bool $abandonCart): void
    {
        $tracker->setForceVisitDateTime(Date::factory(self::DATE . ' 10:00:00')->addPeriod($visitNumber, 'minute')->getDatetime());
        $tracker->setNewVisitorId();
        $tracker->setIp("10.0.0.$visitNumber");
        $tracker->setForceNewVisit();
        $tracker->setUrl('https://example.com/?' . $campaignQuery);
        Fixture::checkResponse($tracker->doTrackPageView('Landing page'));
        Fixture::checkResponse($tracker->doTrackGoal($this->idGoal, 1));
        if ($abandonCart) {
            $tracker->addEcommerceItem('SKU', 'Item', 'Category', 10, 1);
            Fixture::checkResponse($tracker->doTrackEcommerceCartUpdate(10));
        }
    }

    /**
     * @return DataTable[]
     */
    private function aggregate(): array
    {
        $period = PeriodFactory::build('day', self::DATE);
        $segment = new Segment('', [$this->idSite], $period->getDateTimeStart(), $period->getDateTimeEnd());
        $params = new ArchiveProcessor\Parameters(new Site($this->idSite), $period, $segment);
        $archiveProcessor = new ArchiveProcessor($params, new ArchiveWriter($params), new LogAggregator($params));

        $builder = new class extends CampaignReporting {
            public function aggregatePublic(ArchiveProcessor $archiveProcessor): array
            {
                return $this->aggregate($archiveProcessor);
            }
        };

        return $builder->aggregatePublic($archiveProcessor);
    }

    private function sumColumn(DataTable $table, int $column): int
    {
        $sum = 0;
        foreach ($table->getRows() as $row) {
            $sum += (int) $row->getColumn($column);
        }
        return $sum;
    }

    private function sumConversions(DataTable $table, int $idGoal): int
    {
        $sum = 0;
        foreach ($table->getRows() as $row) {
            $goals = $row->getColumn(Metrics::INDEX_GOALS) ?: [];
            $sum += (int) ($goals[$idGoal][Metrics::INDEX_GOAL_NB_CONVERSIONS] ?? 0);
        }
        return $sum;
    }
}
