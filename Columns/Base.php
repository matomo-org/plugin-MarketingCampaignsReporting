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
        $campaignDimensions = $this->normalizeDetectedCampaignDimensionsForConsent(
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

        // a visit holding a discarded campaign reports it as discarded whatever this says: its own
        // values are the placeholder, and the cookie is only reported where the visit holds it
        $hasConsent = $this->hasVisitorConsented($request, $visitor)
            && $this->mayRelaxForThisVisit($request, $visitor, $action);

        $cookieCampaignDimensions = $this->normalizeCookieCampaignDimensions(
            $this->getCampaignDimensionsFromReferrerAttributionCookie($request),
            $visitor,
            (int) $request->getIdSiteIfExists(),
            $hasConsent
        );

        // @todo Not using Common::REFERRER_TYPE_AI_ASSISTANT for BC reasons. Can be changed with Matomo 6
        if ($visitProperties['referer_type'] === 8) {
            $campaignDimensions = $cookieCampaignDimensions;

            if (empty($campaignDimensions)) {
                return null; // skip campaign detection when a AI assistant was detected as referrer by core
            }
        } else {
            $campaignDimensions = $campaignDetector->detectCampaignFromVisit(
                $visitProperties,
                $campaignParameters
            );
            $campaignDimensions = $this->normalizeDetectedCampaignDimensionsForConsent(
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
            $campaignDimensions = $this->normalizeDetectedCampaignDimensionsForConsent(
                $campaignDimensions,
                (int) $request->getIdSiteIfExists(),
                $hasConsent
            );
        }

        if (!is_array($campaignDimensions)) {
            $campaignDimensions = [];
        }

        if (!empty($cookieCampaignDimensions)) {
            $campaignDimensions = array_merge($campaignDimensions, $cookieCampaignDimensions);
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
     * A campaign the tracker withheld until consent left no marker at all, and is completed the
     * same way where consent reveals it (see consentRevealsArrival()).
     *
     * Either way the visit is completed only where core completed the campaign it records from
     * this same request (see coreCompletedCampaign()), so the two never disagree about it.
     *
     * @param Action|null $action
     * @return mixed
     */
    public function onExistingVisit(Request $request, Visitor $visitor, $action)
    {
        $isDiscarded = $this->isDiscardedValue($visitor->getVisitorColumn($this->getColumnName()));

        if ($isDiscarded) {
            if (
                !$this->hasVisitorConsented($request, $visitor)
                || true !== $this->isRequestOnEntryPage($visitor, $action, $request)
            ) {
                return false;
            }
        } elseif (!$this->consentRevealsArrival($request, $visitor, $action)) {
            return false;
        }

        $campaignDetector = StaticContainer::get('advanced_campaign_reporting.campaign_detector');

        $campaignDimensions = $campaignDetector->detectCampaignFromRequest(
            $request,
            MarketingCampaignsReporting::getCampaignParameters()
        );

        // without a campaign in this request there is nothing to complete the visit from, and
        // clearing the placeholder would claim the visitor arrived without one; and where core did
        // not complete its own campaign from it, completing these would contradict core
        if (empty($campaignDimensions) || !$this->coreCompletedCampaign($visitor)) {
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

    protected function normalizeDetectedCampaignDimensions($campaignDimensions, int $idSite)
    {
        return $this->normalizeDetectedCampaignDimensionsForConsent($campaignDimensions, $idSite, false);
    }

    /**
     * As normalizeDetectedCampaignDimensions(), lifting the masking for a visitor who has given
     * their consent.
     *
     * This is a method of its own so that normalizeDetectedCampaignDimensions() keeps the signature
     * a plugin extending these dimensions may already be overriding.
     */
    protected function normalizeDetectedCampaignDimensionsForConsent($campaignDimensions, int $idSite, bool $hasConsent)
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
        return MarketingCampaignsReporting::visitHasConsent($request, $visitor);
    }

    /**
     * Whether the consent this request reports may relax what is recorded against the visit it is
     * being written to, as core decides it in Referrers\Columns\Base::mayRelaxForThisVisit().
     *
     * A request can report consent and still land on a visit measured before any was given,
     * without being able to start one of its own. Its consent then belongs to a later visit, and
     * nothing it carries may be recorded in the clear against this one.
     *
     * @param Action|null $action
     */
    protected function mayRelaxForThisVisit(Request $request, Visitor $visitor, $action = null): bool
    {
        return !MarketingCampaignsReporting::requestHasConsent($request)
            || MarketingCampaignsReporting::consentAppliesToVisit($request, $visitor, $action);
    }

    /**
     * Whether consent reported now describes the arrival of this visit rather than a new one.
     *
     * Where the tracker withholds campaign parameters until consent, the request that opened the
     * visit carried none, so it was attributed to whatever was left - the referring site, or a
     * direct entry. Consent given while the visitor is still on the page they arrived on brings
     * the campaign parameters back with it, and they describe that same arrival, so they do not
     * start a new visit, as core does not in Referrers\Columns\Base::shouldCompleteWithheldCampaign().
     *
     * @param Action|null $action
     */
    protected function consentRevealsArrival(Request $request, Visitor $visitor, $action = null): bool
    {
        if (
            !MarketingCampaignsReporting::requestHasConsent($request)
            || MarketingCampaignsReporting::visitHasConsented($visitor)
        ) {
            return false;
        }

        // a visit already holding a campaign is completed through the placeholder rules instead,
        // and a second campaign over the first is a change rather than a completion. The visit as
        // stored is read, as the other campaign fields may already be completed by now
        foreach (MarketingCampaignsReporting::getAdvancedCampaignFields() as $field) {
            if ('' !== (string) $visitor->getImmutableVisitorColumn($field)) {
                return false;
            }
        }

        return true === $this->isRequestOnEntryPage($visitor, $action, $request);
    }

    /**
     * Whether core completed the campaign it records itself from this request.
     *
     * The campaign dimensions here complete a visit only where core does, so that the two never
     * tell different stories about how the visit began: core completes only a campaign it detects
     * as one - not an AI assistant, nor parameters it does not read as a campaign - in
     * Referrers\Columns\Base::shouldCompleteWithheldCampaign() and ::shouldCompleteDiscardedCampaign().
     * Core's referrer columns are required by these dimensions (getRequiredVisitFields()), so they
     * have run for this request by now, and what they recorded is compared with the visit as stored.
     *
     * A campaign carried only by parameters these dimensions read and core does not - a content or
     * placement on its own, say - is therefore never completed: core completes nothing from it, and
     * the visit keeps the placeholder it was measured with.
     */
    protected function coreCompletedCampaign(Visitor $visitor): bool
    {
        $name = (string) $visitor->getVisitorColumn('referer_name');

        if (
            Common::REFERRER_TYPE_CAMPAIGN != $visitor->getVisitorColumn('referer_type')
            || '' === $name
            || $this->isDiscardedValue($name)
        ) {
            return false;
        }

        return Common::REFERRER_TYPE_CAMPAIGN != $visitor->getImmutableVisitorColumn('referer_type')
            || $this->isDiscardedValue($visitor->getImmutableVisitorColumn('referer_name'));
    }

    /**
     * The campaign the attribution cookie carries, as a conversion may report it.
     *
     * The cookie outlives the visit and may name an older campaign than the one the visit holds,
     * or one the visit never had. Where the policy masks campaign values, a consenting visitor's
     * conversion therefore reports each value only where it is the one the visit holds in the
     * clear, and the placeholder otherwise - the rule core applies to the campaign it records
     * itself, in Referrers\Columns\Base::conversionMayReportCampaignValue(). Each value is judged
     * on its own, because a visit can hold its campaign name and still have had the keyword
     * withheld. For a visitor who has not consented the campaign is masked as a whole, as before.
     *
     * @param array<string, string> $cookieCampaignDimensions
     * @return array<string, string>
     */
    protected function normalizeCookieCampaignDimensions(
        array $cookieCampaignDimensions,
        Visitor $visitor,
        int $idSite,
        bool $hasConsent
    ): array {
        if (!$hasConsent) {
            return $this->normalizeDetectedCampaignDimensions($cookieCampaignDimensions, $idSite);
        }

        if (!MarketingCampaignsReporting::isCampaignValuesMaskingEnabled($idSite)) {
            return $cookieCampaignDimensions;
        }

        foreach ([(new CampaignName())->getColumnName(), (new CampaignKeyword())->getColumnName()] as $column) {
            if (isset($cookieCampaignDimensions[$column]) && !$this->visitHoldsCampaignValue($visitor, $column, $cookieCampaignDimensions[$column])) {
                $cookieCampaignDimensions[$column] = MarketingCampaignsReporting::getCampaignPlaceholderValue();
            }
        }

        return $cookieCampaignDimensions;
    }

    /**
     * Whether the visit holds this campaign value in the clear.
     *
     * Stored values are lowercased unless a setting keeps their case, which the value compared
     * with them may not have been sent in.
     */
    private function visitHoldsCampaignValue(Visitor $visitor, string $column, string $value): bool
    {
        $held = (string) $visitor->getVisitorColumn($column);

        if ('' === $held || $this->isDiscardedValue($held)) {
            return false;
        }

        return mb_strtolower($held) === mb_strtolower($value);
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
