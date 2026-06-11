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
use Piwik\Plugins\SitesManager\Model;
use Piwik\Updater;
use Piwik\Updater\Migration;
use Piwik\Updater\Migration\Custom as CustomMigration;
use Piwik\Updater\Migration\Factory as MigrationFactory;
use Piwik\Updates as PiwikUpdates;

class Updates_5_2_2 extends PiwikUpdates
{
    private const BACKFILL_START_DATE = '2026-05-26';

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
        $logConversion = Common::prefixTable('log_conversion');
        $logVisit = Common::prefixTable('log_visit');
        $backfillStart = self::BACKFILL_START_DATE . ' 00:00:00';

        $backfillSql = "
            UPDATE `$logConversion` lc
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
              AND (
                  (lc.campaign_source IS NULL OR lc.campaign_source = '')
                  OR (lc.campaign_medium IS NULL OR lc.campaign_medium = '')
                  OR (lc.campaign_content IS NULL OR lc.campaign_content = '')
                  OR (lc.campaign_id IS NULL OR lc.campaign_id = '')
                  OR (lc.campaign_group IS NULL OR lc.campaign_group = '')
                  OR (lc.campaign_placement IS NULL OR lc.campaign_placement = '')
              )
        ";

        $invalidateDescription = sprintf(
            'Schedule rearchiving for MarketingCampaignsReporting reports from %s onward',
            self::BACKFILL_START_DATE
        );

        $migrations = [];

        $model = new Model();
        foreach ($model->getSitesId() as $idSite) {
            $migrations[] = $this->migration->db->boundSql($backfillSql, [(int) $idSite, $backfillStart]);
        }

        $migrations[] = new CustomMigration(function () {
            $invalidator = StaticContainer::get(ArchiveInvalidator::class);
            $invalidator->scheduleReArchiving('all', 'MarketingCampaignsReporting', null, Date::factory(self::BACKFILL_START_DATE));
        }, $invalidateDescription);

        return $migrations;
    }

    public function doUpdate(Updater $updater)
    {
        $updater->executeMigrations(__FILE__, $this->getMigrations($updater));
    }
}
