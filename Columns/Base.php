<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\MarketingCampaignsReporting\Columns;

use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Metrics\Formatter;
use Piwik\Plugin\Dimension\VisitDimension;
use Piwik\Plugins\CoreHome\Columns\Consent;
use Piwik\Plugins\MarketingCampaignsReporting\MarketingCampaignsReporting;
use Piwik\Plugins\MarketingCampaignsReporting\SystemSettings;
use Piwik\Tracker\Action;
use Piwik\Tracker\Request;
use Piwik\Tracker\Visit\EntryPage;
use Piwik\Tracker\Visitor;

abstract class Base extends VisitDimension
{
    protected $category = 'Referrers_Referrers';

    public function getRequiredVisitFields()
    {
        return array(
            'referer_type',
            'referer_name',
            'referer_keyword'
        );
    }

    /**
     * @param Request     $request
     * @param Visitor     $visitor
     * @param Action|null $action
     * @return mixed
     */
    public function onNewVisit(Request $request, Visitor $visitor, $action)
    {
        $campaignDetector   = StaticContainer::get('advanced_campaign_reporting.campaign_detector');
        $campaignParameters = MarketingCampaignsReporting::getCampaignParameters();

        $visitProperties = $visitor->visitProperties->getProperties();

        // @todo Not using Common::REFERRER_TYPE_AI_ASSISTANT for BC reasons. Can be changed with Matomo 6
        if ($visitProperties['referer_type'] === 8) {
            return null; // skip campaign detection when a AI assistant was detected as referrer by core
        }

        $campaignDimensions = $campaignDetector->detectCampaignFromRequest(
            $request,
            $campaignParameters
        );
        $campaignDimensions = $this->normalizeDetectedCampaignDimensions(
            $campaignDimensions,
            (int) $request->getIdSiteIfExists(),
            $this->hasVisitorConsented($request, $visitor)
        );

        if (empty($campaignDimensions)) {
            // If for some reason a campaign was detected in Core Tracker
            // but not here, copy that campaign to the Advanced Campaign
            if ($visitProperties['referer_type'] == Common::REFERRER_TYPE_CAMPAIGN) {
                $campaignDimensions = array(
                    (new CampaignName())->getColumnName() => $visitProperties['referer_name']
                );
                if (!empty($visitProperties['referer_keyword'])) {
                    $campaignDimensions[(new CampaignKeyword())->getColumnName()] = $visitProperties['referer_keyword'];
                }
            }
        }

        if (!empty($campaignDimensions) && array_key_exists($this->getColumnName(), $campaignDimensions)) {
            return $this->truncateCampaignValue($campaignDimensions[$this->getColumnName()]);
        }

        return null;
    }

    /**
     * @param Request     $request
     * @param Visitor     $visitor
     * @param Action|null $action
     * @return mixed
     */
    public function onAnyGoalConversion(Request $request, Visitor $visitor, $action)
    {
        $campaignDetector   = StaticContainer::get('advanced_campaign_reporting.campaign_detector');
        $campaignParameters = MarketingCampaignsReporting::getCampaignParameters();

        $visitProperties = $visitor->visitProperties->getProperties();

        // the attribution cookie this can draw on outlives the visit. Where the visit itself holds
        // a discarded campaign, surfacing one against the conversion would contradict it with
        // detail gathered before the visitor consented
        $hasConsent = $this->hasVisitorConsented($request, $visitor)
            && !$this->isDiscardedValue($visitor->getVisitorColumn((new CampaignName())->getColumnName()));

        // @todo Not using Common::REFERRER_TYPE_AI_ASSISTANT for BC reasons. Can be changed with Matomo 6
        if ($visitProperties['referer_type'] === 8) {
            $campaignDimensions = $this->getCampaignDimensionsFromReferrerAttributionCookie($request);
            $campaignDimensions = $this->normalizeDetectedCampaignDimensions(
                $campaignDimensions,
                (int) $request->getIdSiteIfExists(),
                $hasConsent
            );

            if (empty($campaignDimensions)) {
                return null; // skip campaign detection when a AI assistant was detected as referrer by core
            }
        } else {
            $campaignDimensions = $campaignDetector->detectCampaignFromVisit(
                $visitProperties,
                $campaignParameters
            );
            $campaignDimensions = $this->normalizeDetectedCampaignDimensions(
                $campaignDimensions,
                (int) $request->getIdSiteIfExists(),
                $hasConsent
            );
        }

        if (empty($campaignDimensions)) {
            $campaignDimensions = $campaignDetector->detectCampaignFromRequest(
                $request,
                $campaignParameters
            );
            $campaignDimensions = $this->normalizeDetectedCampaignDimensions(
                $campaignDimensions,
                (int) $request->getIdSiteIfExists(),
                $hasConsent
            );
        }

        $cookieCampaignDimensions = $this->getCampaignDimensionsFromReferrerAttributionCookie($request);
        $cookieCampaignDimensions = $this->normalizeDetectedCampaignDimensions(
            $cookieCampaignDimensions,
            (int) $request->getIdSiteIfExists(),
            $hasConsent
        );

        if (!is_array($campaignDimensions)) {
            $campaignDimensions = [];
        }

        if (!empty($cookieCampaignDimensions)) {
            $campaignDimensions = array_merge($campaignDimensions, $cookieCampaignDimensions);
        }

        if (empty($campaignDimensions) && $visitProperties['referer_type'] === 8) {
            return null; // skip campaign detection when a AI assistant was detected as referrer by core
        }

        if (!empty($campaignDimensions) && array_key_exists($this->getColumnName(), $campaignDimensions)) {
            return $this->truncateCampaignValue($campaignDimensions[$this->getColumnName()]);
        }

        return null;
    }

    /**
     * A campaign the policy discarded is a marker for "not collected", not a campaign the visitor
     * arrived through. Once they consent, the request carrying the real values may record them in
     * its place - but only while the visit is still on the page it arrived on, which is the only
     * time those values are still in the URL to read.
     *
     * @param Action|null $action
     * @return mixed
     */
    public function onExistingVisit(Request $request, Visitor $visitor, $action)
    {
        if (
            !$this->isDiscardedValue($visitor->getVisitorColumn($this->getColumnName()))
            || !$this->hasVisitorConsented($request, $visitor)
            || true !== $this->isRequestOnEntryPage($visitor, $action, $request)
        ) {
            return false;
        }

        $campaignDetector = StaticContainer::get('advanced_campaign_reporting.campaign_detector');

        $campaignDimensions = $campaignDetector->detectCampaignFromRequest(
            $request,
            MarketingCampaignsReporting::getCampaignParameters()
        );

        // without a campaign in this request there is nothing to complete the visit from, and
        // clearing the placeholder would claim the visitor arrived without one
        if (empty($campaignDimensions)) {
            return false;
        }

        $value = $campaignDimensions[$this->getColumnName()] ?? '';

        // masking marks every campaign field as withheld, whether or not the campaign had one, so
        // nothing says which of them this request is missing because the campaign never had it.
        // The placeholder still describes that field correctly, where an empty value would claim
        // the visitor arrived without one
        if ('' === $value) {
            return false;
        }

        return $this->truncateCampaignValue($value);
    }

    private function getCampaignDimensionsFromReferrerAttributionCookie(Request $request): array
    {
        $campaignName = $this->getReferrerCampaignQueryParam($request, '_rcn');
        if ($campaignName === '') {
            return [];
        }

        $campaignDimensions = [
            (new CampaignName())->getColumnName() => $campaignName,
        ];

        $campaignKeyword = $this->getReferrerCampaignQueryParam($request, '_rck');
        if ($campaignKeyword !== '') {
            $campaignDimensions[(new CampaignKeyword())->getColumnName()] = $campaignKeyword;
        }

        return $campaignDimensions;
    }

    private function getReferrerCampaignQueryParam(Request $request, string $paramName): string
    {
        $value = trim(urldecode($request->getParam($paramName)));

        if ($value !== '' && $this->shouldLowerCampaignCase()) {
            $value = mb_strtolower($value);
        }

        return $value;
    }

    private function shouldLowerCampaignCase(): bool
    {
        $systemSettings = StaticContainer::get(SystemSettings::class);

        return !$systemSettings->doNotChangeCaseOfUtmParameters->getValue();
    }

    public function formatValue($value, $idSite, Formatter $formatter)
    {
        return MarketingCampaignsReporting::formatCampaignValue($value);
    }

    protected function normalizeDetectedCampaignDimensions($campaignDimensions, int $idSite, bool $hasConsent = false)
    {
        return $this->maskDetectedCampaignDimensions(
            $campaignDimensions,
            MarketingCampaignsReporting::isCampaignValuesMaskingEnabled($idSite) && !$hasConsent
        );
    }

    /**
     * Whether the visitor has given their consent, by this request or earlier in the visit.
     *
     * Masking is the treatment for a visitor who has not consented, so it is lifted for one who
     * has, in the same way core lifts it for the campaign it records itself.
     */
    protected function hasVisitorConsented(Request $request, Visitor $visitor): bool
    {
        // core only answers this where it measures consent-aware analytics. Until it does, every
        // visitor is one who has not consented, which is how this plugin has always treated them
        if (!method_exists($request, 'hasConsent')) {
            return false;
        }

        if ($request->hasConsent()) {
            return true;
        }

        // a visit being created now does not inherit the consent of the one before it: the
        // recognised visit's columns are still loaded at this point, and the consent recorded
        // against that visit says nothing about this one
        if ($request->getMetadata('CoreHome', 'isNewVisit')) {
            return false;
        }

        return method_exists(Consent::class, 'hasVisitConsented') && Consent::hasVisitConsented($visitor);
    }

    /**
     * Whether the value stored for a campaign field is the one a compliance policy leaves behind
     * instead of the campaign itself.
     *
     * @param mixed $value
     */
    protected function isDiscardedValue($value): bool
    {
        $placeholder = MarketingCampaignsReporting::getCampaignPlaceholderValue();

        return '' !== $placeholder && is_string($value) && $value === $placeholder;
    }

    /**
     * Whether the visit is still on the page it arrived on, where core can tell. Null is returned
     * where it cannot, and on a core that does not answer the question at all.
     *
     * @param Action|null $action
     */
    protected function isRequestOnEntryPage(Visitor $visitor, $action, ?Request $request = null): ?bool
    {
        if (!class_exists(EntryPage::class)) {
            return null;
        }

        return EntryPage::isRequestOnEntryPage($visitor, $action, $request);
    }

    private function truncateCampaignValue($value)
    {
        return substr($value, 0, $this->getColumnName() == 'campaign_id' ? 100 : 255);
    }

    private function maskDetectedCampaignDimensions($campaignDimensions, bool $campaignValuesMasked)
    {
        if (empty($campaignDimensions) || !$campaignValuesMasked) {
            return $campaignDimensions;
        }

        // Mask every campaign field once a campaign is detected so partial URLs cannot leak raw values.
        foreach (MarketingCampaignsReporting::getAdvancedCampaignFields() as $field) {
            $campaignDimensions[$field] = MarketingCampaignsReporting::getCampaignPlaceholderValue();
        }

        return $campaignDimensions;
    }
}
