<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\MarketingCampaignsReporting\Columns;

class CampaignGroup extends Base
{
    protected $columnName = 'campaign_group';
    protected $columnType = 'VARCHAR(255) NULL';
    protected $segmentName = 'campaignGroup';
    protected $nameSingular = 'MarketingCampaignsReporting_Group';
}
