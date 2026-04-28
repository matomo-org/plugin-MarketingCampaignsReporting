<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\MarketingCampaignsReporting\Columns;

use Piwik\Columns\Dimension;
use Piwik\Metrics\Formatter;
use Piwik\Piwik;
use Piwik\Plugins\MarketingCampaignsReporting\Archiver;
use Piwik\Plugins\MarketingCampaignsReporting\MarketingCampaignsReporting;

class CampaignSourceMedium extends Dimension
{
    public function getName()
    {
        return Piwik::translate('MarketingCampaignsReporting_CombinedSourceMedium');
    }

    public function formatValue($value, $idSite, Formatter $formatter)
    {
        $parts = explode(Archiver::SEPARATOR_COMBINED_DIMENSIONS, (string) $value);
        $parts = array_map([MarketingCampaignsReporting::class, 'formatCampaignValue'], $parts);

        return implode(Archiver::SEPARATOR_COMBINED_DIMENSIONS, $parts);
    }
}
