<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\MarketingCampaignsReporting;

use Piwik\Archive\ArchiveInvalidator;
use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Date;
use Piwik\Db;
use Piwik\Plugins\SitesManager\Model as SitesModel;
use Piwik\Updater;
use Piwik\Updater\Migration;
use Piwik\Updater\Migration\Factory as MigrationFactory;
use Piwik\Updates as PiwikUpdates;

class Updates_5_2_2 extends PiwikUpdates
{
    private const BACKFILL_START_DATE = '2026-05-26';
    /**
     * @var int[]
     */
    private $affectedSiteIds = [];

    /**
     * @var MigrationFactory
     */
    private $migration;

    public function __construct(MigrationFactory $factory)
    {
        $this->migration = $factory;
    }

    /**
     * @return Migration[]
     */
    public function getMigrations(Updater $updater)
    {
        [$startDatetime, $endDatetime] = $this->parseDateRange(self::BACKFILL_START_DATE . ',today');
        $affectedSiteIds = $this->getAffectedSiteIds($startDatetime, $endDatetime);
        $this->affectedSiteIds = $affectedSiteIds;

        if (empty($affectedSiteIds)) {
            return [];
        }

        $logConversion = Common::prefixTable('log_conversion');
        $logVisit = Common::prefixTable('log_visit');

        $backfillSql = "
            UPDATE `$logConversion` lc FORCE INDEX (index_idsite_datetime)
            INNER JOIN `$logVisit` lv ON lv.idvisit = lc.idvisit
            SET
                lc.campaign_source = CASE
                    WHEN (lc.campaign_source IS NULL OR lc.campaign_source = '')
                        AND lv.campaign_source IS NOT NULL AND lv.campaign_source <> ''
                    THEN lv.campaign_source
                    ELSE lc.campaign_source
                END,
                lc.campaign_medium = CASE
                    WHEN (lc.campaign_medium IS NULL OR lc.campaign_medium = '')
                        AND lv.campaign_medium IS NOT NULL AND lv.campaign_medium <> ''
                    THEN lv.campaign_medium
                    ELSE lc.campaign_medium
                END,
                lc.campaign_content = CASE
                    WHEN (lc.campaign_content IS NULL OR lc.campaign_content = '')
                        AND lv.campaign_content IS NOT NULL AND lv.campaign_content <> ''
                    THEN lv.campaign_content
                    ELSE lc.campaign_content
                END,
                lc.campaign_id = CASE
                    WHEN (lc.campaign_id IS NULL OR lc.campaign_id = '')
                        AND lv.campaign_id IS NOT NULL AND lv.campaign_id <> ''
                    THEN lv.campaign_id
                    ELSE lc.campaign_id
                END,
                lc.campaign_group = CASE
                    WHEN (lc.campaign_group IS NULL OR lc.campaign_group = '')
                        AND lv.campaign_group IS NOT NULL AND lv.campaign_group <> ''
                    THEN lv.campaign_group
                    ELSE lc.campaign_group
                END,
                lc.campaign_placement = CASE
                    WHEN (lc.campaign_placement IS NULL OR lc.campaign_placement = '')
                        AND lv.campaign_placement IS NOT NULL AND lv.campaign_placement <> ''
                    THEN lv.campaign_placement
                    ELSE lc.campaign_placement
                END
            WHERE lc.idsite = ?
              AND lc.referer_type = " . Common::REFERRER_TYPE_CAMPAIGN . "
              AND lc.server_time >= ?
              AND lc.server_time <= ?
              AND (
                  (lc.campaign_source IS NULL OR lc.campaign_source = '')
                  OR (lc.campaign_medium IS NULL OR lc.campaign_medium = '')
                  OR (lc.campaign_content IS NULL OR lc.campaign_content = '')
                  OR (lc.campaign_id IS NULL OR lc.campaign_id = '')
                  OR (lc.campaign_group IS NULL OR lc.campaign_group = '')
                  OR (lc.campaign_placement IS NULL OR lc.campaign_placement = '')
              )
        ";

        $migrations = [];
        foreach ($affectedSiteIds as $idSite) {
            $migrations[] = $this->migration->db->boundSql($backfillSql, [(int) $idSite, $startDatetime, $endDatetime]);
        }

        return $migrations;
    }

    public function doUpdate(Updater $updater)
    {
        $updater->executeMigrations(__FILE__, $this->getMigrations($updater));

        if (!empty($this->affectedSiteIds)) {
            $invalidator = StaticContainer::get(ArchiveInvalidator::class);
            $invalidator->scheduleReArchiving($this->affectedSiteIds, 'MarketingCampaignsReporting', null, Date::factory(self::BACKFILL_START_DATE));
        }
    }

    /**
     * @return string[]
     */
    private function parseDateRange(string $dates): array
    {
        $parts = array_map('trim', explode(',', $dates));
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException(sprintf('Invalid date range: %s', $dates));
        }

        $start = Date::factory($parts[0])->getDatetime();
        $end = Date::factory($parts[1])->getEndOfDay()->getDatetime();

        return [$start, $end];
    }

    /**
     * @return int[]
     */
    private function getAffectedSiteIds(string $startDatetime, string $endDatetime): array
    {
        $logConversion = Common::prefixTable('log_conversion');
        $siteIds = [];

        foreach ((new SitesModel())->getSitesId() as $siteId) {
            $match = Db::fetchOne(
                "SELECT 1
                   FROM `$logConversion` lc FORCE INDEX (index_idsite_datetime)
                  WHERE lc.idsite = ?
                    AND lc.referer_type = " . Common::REFERRER_TYPE_CAMPAIGN . "
                    AND lc.server_time >= ?
                    AND lc.server_time <= ?
                    AND (
                        (lc.campaign_source IS NULL OR lc.campaign_source = '')
                        OR (lc.campaign_medium IS NULL OR lc.campaign_medium = '')
                        OR (lc.campaign_content IS NULL OR lc.campaign_content = '')
                        OR (lc.campaign_id IS NULL OR lc.campaign_id = '')
                        OR (lc.campaign_group IS NULL OR lc.campaign_group = '')
                        OR (lc.campaign_placement IS NULL OR lc.campaign_placement = '')
                    )
                  LIMIT 1",
                [(int) $siteId, $startDatetime, $endDatetime]
            );

            if ($match) {
                $siteIds[] = (int) $siteId;
            }
        }

        return $siteIds;
    }
}
