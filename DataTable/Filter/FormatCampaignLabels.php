<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\MarketingCampaignsReporting\DataTable\Filter;

use Piwik\DataTable;
use Piwik\DataTable\BaseFilter;
use Piwik\Plugins\MarketingCampaignsReporting\MarketingCampaignsReporting;

class FormatCampaignLabels extends BaseFilter
{
    public function filter($table)
    {
        foreach ($table->getRows() as $row) {
            $label = $row->getColumn('label');
            if ($label !== false) {
                $row->setColumn('label', MarketingCampaignsReporting::formatCombinedCampaignValue($label));
            }

            $subtable = $row->getSubtable();
            if (!empty($subtable)) {
                $subtable->filter(static::class);
            }

            $comparisons = $row->getComparisons();
            if (!empty($comparisons)) {
                $comparisons->filter(static::class);
            }
        }

        $table->setLabelsHaveChanged();
    }
}
