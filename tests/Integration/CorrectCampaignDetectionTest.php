<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\MarketingCampaignsReporting\tests\Integration;

use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\DataTable;
use Piwik\DataTable\Row;
use Piwik\Db;
use Piwik\Metrics\Formatter;
use Piwik\Piwik;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignName;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignSourceMedium;
use Piwik\Plugins\MarketingCampaignsReporting\DataTable\Filter\FormatCampaignLabels;
use Piwik\Plugins\MarketingCampaignsReporting\MarketingCampaignsReporting;
use Piwik\Plugins\MarketingCampaignsReporting\SystemSettings;
use Piwik\Plugins\MarketingCampaignsReporting\VisitorDetails;
use Piwik\Plugins\PrivacyManager\Settings\ConsentAwareAnalytics;
use Piwik\Plugins\Referrers\Columns\ReferrerName;
use Piwik\Policy\CnilPolicy;
use Piwik\Policy\PolicyManager;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;
use Piwik\Tests\Framework\TestingEnvironmentVariables;
use Piwik\Tracker\Cache;
use Piwik\Version;

/**
 * @group MarketingCampaignsReporting
 * @group Plugins
 */
class CorrectCampaignDetectionTest extends IntegrationTestCase
{
    protected $idSite = null;

    /**
     * @var Date
     */
    protected $testDate = null;


    public function setUp(): void
    {
        parent::setUp();

        $this->idSite = Fixture::createWebsite('2016-01-01 00:00:01', 0, 'TestSite', 'https://example.com');
        $this->setDoNotChangeCaseOfUtmParameters(true);
    }

    /**
     * @dataProvider getTrackingTestData
     */
    public function testTracking(string $url, string $referrerUrl, array $expectedAttributes)
    {
        Db::query('TRUNCATE TABLE ' . Common::prefixTable('log_visit'));

        $t = Fixture::getTracker(
            $this->idSite,
            date('Y-m-d H:i:s'),
            $defaultInit = true,
            $useLocal = false
        );

        $t->setUrl($url);
        $t->setUrlReferrer($referrerUrl);

        Fixture::checkResponse($t->doTrackPageView('Some page title'));


        $data = Db::fetchRow('SELECT * FROM ' . Common::prefixTable('log_visit') . ' LIMIT 1');

        foreach ($expectedAttributes as $name => $value) {
            self::assertEquals($value, $data[$name] ?? '');
        }
    }

    public function getTrackingTestData(): iterable
    {

        yield 'provided compaign parameters should be detected correctly' => [
            'https://www.example.com/?utm_campaign=November_Offer&utm_term=Mot_clé_PÉPÈRE&utm_source=newsletter_7&utm_content=contains personalized campaigns for client&utm_medium=email&utm_id=CAMPAIGN_ID_KABOOM&mtm_group=Audience%20Group%201&mtm_placement=Google%20Search',
            '', // no referer
            [
                'referer_type'       => Common::REFERRER_TYPE_CAMPAIGN,
                'referer_name'       => 'November_Offer',
                'referer_url'        => '',
                'referer_keyword'    => 'Mot_clé_PÉPÈRE',
                'campaign_content'   => 'contains personalized campaigns for client',
                'campaign_group'     => 'Audience Group 1',
                'campaign_id'        => 'CAMPAIGN_ID_KABOOM',
                'campaign_keyword'   => 'Mot_clé_PÉPÈRE',
                'campaign_medium'    => 'email',
                'campaign_name'      => 'November_Offer',
                'campaign_placement' => 'Google Search',
                'campaign_source'    => 'newsletter_7',
            ],
        ];

        if (version_compare(Version::VERSION, '5.5.0-b1', '>=')) {
            yield 'utm_source param provided by ChatGPT is detected as AI assistant' => [
                'https://www.example.com/?utm_source=chatgpt.com',
                '', // not referer
                [
                    'referer_type'       => Common::REFERRER_TYPE_AI_ASSISTANT,
                    'referer_name'       => 'ChatGPT',
                    'referer_url'        => '',
                    'referer_keyword'    => '',
                    'campaign_content'   => '',
                    'campaign_group'     => '',
                    'campaign_id'        => '',
                    'campaign_keyword'   => '',
                    'campaign_medium'    => '',
                    'campaign_name'      => '',
                    'campaign_placement' => '',
                    'campaign_source'    => '',
                ],
            ];

            yield 'campaign parameters are ignored for AI referrers' => [
                'https://www.example.com/?utm_source=random&utm_campaign=random',
                'https://chatgpt.com/',
                [
                    'referer_type'       => Common::REFERRER_TYPE_AI_ASSISTANT,
                    'referer_name'       => 'ChatGPT',
                    'referer_url'        => 'https://chatgpt.com/',
                    'referer_keyword'    => '',
                    'campaign_content'   => '',
                    'campaign_group'     => '',
                    'campaign_id'        => '',
                    'campaign_keyword'   => '',
                    'campaign_medium'    => '',
                    'campaign_name'      => '',
                    'campaign_placement' => '',
                    'campaign_source'    => '',
                ],
            ];

            yield 'ChatGPT referrer with utm_source param is detected as AI assistant' => [
                'https://www.example.com/?utm_source=chatgpt.com',
                'https://chatgpt.com/',
                [
                    'referer_type'       => Common::REFERRER_TYPE_AI_ASSISTANT,
                    'referer_name'       => 'ChatGPT',
                    'referer_url'        => 'https://chatgpt.com/',
                    'referer_keyword'    => '',
                    'campaign_content'   => '',
                    'campaign_group'     => '',
                    'campaign_id'        => '',
                    'campaign_keyword'   => '',
                    'campaign_medium'    => '',
                    'campaign_name'      => '',
                    'campaign_placement' => '',
                    'campaign_source'    => '',
                ],
            ];

            yield 'utm_source param provided by Perplexity is detected as AI assistant' => [
                'https://www.example.com/?utm_source=perplexity.ai',
                '', // no referer
                [
                    'referer_type'       => Common::REFERRER_TYPE_AI_ASSISTANT,
                    'referer_name'       => 'Perplexity',
                    'referer_url'        => '',
                    'referer_keyword'    => '',
                    'campaign_content'   => '',
                    'campaign_group'     => '',
                    'campaign_id'        => '',
                    'campaign_keyword'   => '',
                    'campaign_medium'    => '',
                    'campaign_name'      => '',
                    'campaign_placement' => '',
                    'campaign_source'    => '',
                ],
            ];
        }
    }

    public function testTrackingMasksCampaignDimensionsWhenCnilPolicyIsEnabled()
    {
        $placeholder = MarketingCampaignsReporting::getCampaignPlaceholderValue();
        if ($placeholder === '') {
            $this->markTestSkipped('CampaignParameterValuesMasked is not available in this core version.');
        }

        PolicyManager::setPolicyActiveStatus(CnilPolicy::class, true, $this->idSite);
        try {
            Db::query('TRUNCATE TABLE ' . Common::prefixTable('log_visit'));

            $tracker = Fixture::getTracker(
                $this->idSite,
                date('Y-m-d H:i:s'),
                $defaultInit = true,
                $useLocal = false
            );

            $tracker->setUrl('https://www.example.com/?utm_source=newsletter_7&utm_medium=email&utm_id=CAMPAIGN_ID_KABOOM&utm_content=hero-banner&mtm_group=Audience%20Group%201&mtm_placement=Google%20Search');

            Fixture::checkResponse($tracker->doTrackPageView('Some page title'));

            $data = Db::fetchRow('SELECT * FROM ' . Common::prefixTable('log_visit') . ' LIMIT 1');

            self::assertEquals(Common::REFERRER_TYPE_CAMPAIGN, $data['referer_type']);
            self::assertEquals($placeholder, $data['referer_name']);
            self::assertEquals($placeholder, $data['referer_keyword']);
            self::assertEquals($placeholder, $data['campaign_source']);
            self::assertEquals($placeholder, $data['campaign_medium']);
            self::assertEquals($placeholder, $data['campaign_content']);
            self::assertEquals($placeholder, $data['campaign_id']);
            self::assertEquals($placeholder, $data['campaign_group']);
            self::assertEquals($placeholder, $data['campaign_placement']);
            self::assertEquals($placeholder, $data['campaign_name']);
            self::assertEquals($placeholder, $data['campaign_keyword']);
        } finally {
            PolicyManager::setPolicyActiveStatus(CnilPolicy::class, false, $this->idSite);
        }
    }

    public function testTrackingKeepsCampaignDimensionsForAVisitorWhoHasConsented()
    {
        $placeholder = $this->requireConsentAwareAnalytics();

        PolicyManager::setPolicyActiveStatus(CnilPolicy::class, true, $this->idSite);
        try {
            Db::query('TRUNCATE TABLE ' . Common::prefixTable('log_visit'));

            $tracker = Fixture::getTracker(
                $this->idSite,
                date('Y-m-d H:i:s'),
                $defaultInit = true,
                $useLocal = false
            );

            $tracker->setCustomTrackingParameter('consent', '1');
            $tracker->setUrl('https://www.example.com/?utm_source=newsletter_7&utm_medium=email&utm_id=CAMPAIGN_ID_KABOOM&utm_content=hero-banner&mtm_group=Audience%20Group%201&mtm_placement=Google%20Search');

            Fixture::checkResponse($tracker->doTrackPageView('Some page title'));

            $data = Db::fetchRow('SELECT * FROM ' . Common::prefixTable('log_visit') . ' LIMIT 1');

            // the case these are stored in depends on a setting of its own, which is not what
            // this test is about
            self::assertEquals(Common::REFERRER_TYPE_CAMPAIGN, $data['referer_type']);
            self::assertEqualsIgnoringCase('newsletter_7', $data['campaign_source']);
            self::assertEqualsIgnoringCase('email', $data['campaign_medium']);
            self::assertEqualsIgnoringCase('hero-banner', $data['campaign_content']);
            self::assertEqualsIgnoringCase('CAMPAIGN_ID_KABOOM', $data['campaign_id']);
            self::assertEqualsIgnoringCase('Audience Group 1', $data['campaign_group']);
            self::assertEqualsIgnoringCase('Google Search', $data['campaign_placement']);
            self::assertNotEquals($placeholder, $data['campaign_name']);
        } finally {
            PolicyManager::setPolicyActiveStatus(CnilPolicy::class, false, $this->idSite);
        }
    }

    /**
     * The visitor is measured before consenting, so the arrival page view is already recorded with
     * the campaign discarded. Consent then reaches them while they are still on that page, so the
     * request carries the campaign parameters again and the visit is completed with them.
     */
    public function testTrackingCompletesDiscardedCampaignDimensionsWhenConsentArrivesOnTheArrivalPage()
    {
        $placeholder = $this->requireConsentAwareAnalytics();

        PolicyManager::setPolicyActiveStatus(CnilPolicy::class, true, $this->idSite);
        try {
            Db::query('TRUNCATE TABLE ' . Common::prefixTable('log_visit'));

            $tracker = $this->getCampaignTracker();

            Fixture::checkResponse($tracker->doTrackPageView('Some page title'));

            $data = Db::fetchRow('SELECT * FROM ' . Common::prefixTable('log_visit') . ' LIMIT 1');
            self::assertEquals($placeholder, $data['campaign_source'], 'discarded until the visitor consents');

            $tracker->setCustomTrackingParameter('consent', '1');
            Fixture::checkResponse($tracker->doTrackPageView('Some page title'));

            $data = Db::fetchRow('SELECT * FROM ' . Common::prefixTable('log_visit') . ' LIMIT 1');

            self::assertEquals(1, $this->countVisits(), 'consent must not start a second visit here');
            self::assertEqualsIgnoringCase('newsletter_7', $data['campaign_source']);
            self::assertEqualsIgnoringCase('email', $data['campaign_medium']);
            self::assertEqualsIgnoringCase('hero-banner', $data['campaign_content']);
            self::assertEqualsIgnoringCase('CAMPAIGN_ID_KABOOM', $data['campaign_id']);
            self::assertEqualsIgnoringCase('Audience Group 1', $data['campaign_group']);
            self::assertEqualsIgnoringCase('Google Search', $data['campaign_placement']);
            // this campaign had neither, but masking marks every field as withheld and nothing
            // was kept to tell the two apart, so they stay as they are - the same answer core
            // gives for a campaign keyword it cannot complete
            self::assertEquals($placeholder, $data['campaign_name']);
            self::assertEquals($placeholder, $data['campaign_keyword']);
        } finally {
            PolicyManager::setPolicyActiveStatus(CnilPolicy::class, false, $this->idSite);
        }
    }

    /**
     * Once the visitor has moved on, the campaign parameters are gone from the request and nothing
     * was kept of the discarded ones. Consent starts a visit of its own, and the visit measured
     * without it keeps the treatment it was measured under.
     */
    public function testTrackingKeepsDiscardedCampaignDimensionsWhenConsentArrivesAfterNavigating()
    {
        $placeholder = $this->requireConsentAwareAnalytics();

        PolicyManager::setPolicyActiveStatus(CnilPolicy::class, true, $this->idSite);
        try {
            Db::query('TRUNCATE TABLE ' . Common::prefixTable('log_visit'));

            $tracker = $this->getCampaignTracker();

            Fixture::checkResponse($tracker->doTrackPageView('Some page title'));

            $tracker->setCustomTrackingParameter('consent', '1');
            $tracker->setUrl('https://www.example.com/another-page');
            Fixture::checkResponse($tracker->doTrackPageView('Another page title'));

            $visits = Db::fetchAll(
                'SELECT * FROM ' . Common::prefixTable('log_visit') . ' ORDER BY idvisit ASC'
            );

            self::assertCount(2, $visits, 'consent away from the arrival page starts its own visit');
            self::assertEquals($placeholder, $visits[0]['campaign_source'], 'the first visit keeps its treatment');
            self::assertEquals($placeholder, $visits[0]['campaign_name']);
            self::assertNull($visits[1]['campaign_source'], 'the campaign was not in the request by then');
        } finally {
            PolicyManager::setPolicyActiveStatus(CnilPolicy::class, false, $this->idSite);
        }
    }

    /**
     * Consent recorded against a visit stops counting once the site stops measuring consent-aware
     * analytics. Turning the setting off has to take effect for the visits that already consented,
     * not only for the ones that start afterwards.
     */
    public function testConsentStopsCountingOnceTheSiteStopsMeasuringIt()
    {
        $placeholder = $this->requireConsentAwareAnalytics();

        PolicyManager::setPolicyActiveStatus(CnilPolicy::class, true, $this->idSite);
        try {
            Db::query('TRUNCATE TABLE ' . Common::prefixTable('log_visit'));

            $tracker = $this->getCampaignTracker();
            $tracker->setCustomTrackingParameter('consent', '1');
            Fixture::checkResponse($tracker->doTrackPageView('Some page title'));

            $data = Db::fetchRow('SELECT * FROM ' . Common::prefixTable('log_visit') . ' LIMIT 1');
            self::assertEqualsIgnoringCase('newsletter_7', $data['campaign_source'], 'the visit consented');

            $measurable = ConsentAwareAnalytics::getMeasurableSetting($this->idSite);
            $measurable->setValue(false);
            $measurable->save();
            Cache::deleteTrackerCache();

            $tracker->clearCustomTrackingParameters();
            $tracker->setUrl('https://www.example.com/?utm_source=winter_clearance');
            Fixture::checkResponse($tracker->doTrackPageView('Another page title'));

            // the incoming campaign is masked again, so it reads as the stored one rather than
            // as a different campaign and does not split the visit. Were the visit still counted
            // as consented, it would be compared in the clear and a second visit would start
            self::assertEquals(1, $this->countVisits(), 'the visit kept its consent after the site stopped measuring it');

            foreach (Db::fetchAll('SELECT campaign_source FROM ' . Common::prefixTable('log_visit')) as $row) {
                self::assertNotEquals('winter_clearance', $row['campaign_source']);
            }
        } finally {
            PolicyManager::setPolicyActiveStatus(CnilPolicy::class, false, $this->idSite);
        }
    }

    /**
     * Consent-aware measurement is opt-in per site and needs a core that supports it, so the tests
     * that rely on it opt their site in and skip where it is not available.
     *
     * @return string the placeholder a discarded campaign is stored as
     */
    private function requireConsentAwareAnalytics(): string
    {
        $placeholder = MarketingCampaignsReporting::getCampaignPlaceholderValue();
        if ($placeholder === '' || !class_exists(ConsentAwareAnalytics::class)) {
            $this->markTestSkipped('Consent-aware analytics is not available in this core version.');
        }

        $measurable = ConsentAwareAnalytics::getMeasurableSetting($this->idSite);
        $measurable->setValue(true);
        $measurable->save();

        Cache::deleteTrackerCache();

        return $placeholder;
    }

    private function getCampaignTracker()
    {
        $tracker = Fixture::getTracker(
            $this->idSite,
            date('Y-m-d H:i:s'),
            $defaultInit = true,
            $useLocal = false
        );

        $tracker->setUrl('https://www.example.com/?utm_source=newsletter_7&utm_medium=email&utm_id=CAMPAIGN_ID_KABOOM&utm_content=hero-banner&mtm_group=Audience%20Group%201&mtm_placement=Google%20Search');

        return $tracker;
    }

    private function countVisits(): int
    {
        return (int) Db::fetchOne('SELECT COUNT(*) FROM ' . Common::prefixTable('log_visit'));
    }

    public function testGoalConversionUsesReferrerAttributionCampaignCookie()
    {
        $this->setDoNotChangeCaseOfUtmParameters(false);

        Db::query('TRUNCATE TABLE ' . Common::prefixTable('log_visit'));
        Db::query('TRUNCATE TABLE ' . Common::prefixTable('log_conversion'));

        $idGoal = \Piwik\Plugins\Goals\API::getInstance()->addGoal(
            $this->idSite,
            'manual goal',
            'manually',
            '',
            'contains'
        );

        $tracker = Fixture::getTracker(
            $this->idSite,
            date('Y-m-d H:i:s'),
            $defaultInit = true,
            $useLocal = false
        );

        $tracker->setUrl('https://www.example.com/landing-page?utm_campaign=Campaign%20Name&utm_term=Campaign%20Keyword&utm_source=campaign-source&utm_medium=campaign-medium');
        Fixture::checkResponse($tracker->doTrackPageView('Some page title'));

        $tracker->setUrl('https://www.example.com/conversion-page');
        $tracker->setCustomTrackingParameter('_rcn', 'Campaign Name');
        $tracker->setCustomTrackingParameter('_rck', 'Campaign Keyword');
        Fixture::checkResponse($tracker->doTrackGoal($idGoal, 42));

        $conversion = Db::fetchRow(
            'SELECT referer_type, referer_name, referer_keyword, campaign_name, campaign_keyword, campaign_source, campaign_medium FROM '
            . Common::prefixTable('log_conversion')
            . ' LIMIT 1'
        );

        self::assertSame(Common::REFERRER_TYPE_CAMPAIGN, (int) $conversion['referer_type']);
        self::assertSame('campaign name', $conversion['referer_name']);
        self::assertSame('campaign keyword', $conversion['referer_keyword']);
        self::assertSame('campaign name', $conversion['campaign_name']);
        self::assertSame('campaign keyword', $conversion['campaign_keyword']);
        self::assertSame('campaign-source', $conversion['campaign_source']);
        self::assertSame('campaign-medium', $conversion['campaign_medium']);
    }

    public function testGoalConversionUsesReferrerAttributionCampaignCookieForAIAssistantVisit()
    {
        if (version_compare(Version::VERSION, '5.5.0-b1', '<')) {
            $this->markTestSkipped('AI assistant referrer type is not available in this core version.');
        }

        $this->setDoNotChangeCaseOfUtmParameters(false);

        Db::query('TRUNCATE TABLE ' . Common::prefixTable('log_visit'));
        Db::query('TRUNCATE TABLE ' . Common::prefixTable('log_conversion'));

        $idGoal = \Piwik\Plugins\Goals\API::getInstance()->addGoal(
            $this->idSite,
            'manual goal',
            'manually',
            '',
            'contains'
        );

        $tracker = Fixture::getTracker(
            $this->idSite,
            date('Y-m-d H:i:s'),
            $defaultInit = true,
            $useLocal = false
        );

        $tracker->setUrl('https://www.example.com/landing-page');
        $tracker->setUrlReferrer('https://chatgpt.com/');
        Fixture::checkResponse($tracker->doTrackPageView('Some page title'));

        $visit = Db::fetchRow(
            'SELECT referer_type FROM '
            . Common::prefixTable('log_visit')
            . ' LIMIT 1'
        );
        self::assertSame(Common::REFERRER_TYPE_AI_ASSISTANT, (int) $visit['referer_type']);

        $tracker->setUrl('https://www.example.com/conversion-page');
        $tracker->setCustomTrackingParameter('_rcn', 'Campaign Name');
        $tracker->setCustomTrackingParameter('_rck', 'Campaign Keyword');
        Fixture::checkResponse($tracker->doTrackGoal($idGoal, 42));

        $conversion = Db::fetchRow(
            'SELECT referer_type, referer_name, referer_keyword, campaign_name, campaign_keyword FROM '
            . Common::prefixTable('log_conversion')
            . ' LIMIT 1'
        );

        self::assertSame(Common::REFERRER_TYPE_CAMPAIGN, (int) $conversion['referer_type']);
        self::assertSame('campaign name', $conversion['referer_name']);
        self::assertSame('campaign keyword', $conversion['referer_keyword']);
        self::assertSame('campaign name', $conversion['campaign_name']);
        self::assertSame('campaign keyword', $conversion['campaign_keyword']);
    }

    /**
     * The attribution cookie outlives the visit, so a consented conversion can carry a campaign
     * the visit never held, or an older one than the visit holds. Where the policy masks campaign
     * values, the conversion reports each value the cookie carries only where it is the one the
     * visit holds in the clear - the rule core applies to the campaign it records itself, which
     * every case also checks on the same conversion.
     *
     * Expected values: 'P' is the placeholder, null is no value.
     *
     * @dataProvider getConsentedConversionTestData
     */
    public function testAConsentedConversionReportsOnlyTheCampaignTheVisitHolds(
        string $arrival,
        string $cookieName,
        string $cookieKeyword,
        bool $doNotChangeCase,
        array $expected,
        array $expectedInCore,
        bool $conversionRepeatsConsent = true
    ) {
        if ('ai' === $arrival && version_compare(Version::VERSION, '5.5.0-b1', '<')) {
            $this->markTestSkipped('AI assistant referrer type is not available in this core version.');
        }

        $placeholder = $this->requireConsentAwareAnalytics();
        $this->setDoNotChangeCaseOfUtmParameters($doNotChangeCase);

        if ('P' === $cookieName) {
            $cookieName = $placeholder;
        }

        PolicyManager::setPolicyActiveStatus(CnilPolicy::class, true, $this->idSite);
        try {
            $conversion = $this->trackConversionWithAttributionCookie(
                $arrival,
                $cookieName,
                $cookieKeyword,
                '1',
                false,
                [],
                $conversionRepeatsConsent
            );
        } finally {
            PolicyManager::setPolicyActiveStatus(CnilPolicy::class, false, $this->idSite);
        }

        [$name, $keyword, $source] = $expected;
        $this->assertCampaignValue($name, $conversion['campaign_name'], 'campaign_name');
        $this->assertCampaignValue($keyword, $conversion['campaign_keyword'], 'campaign_keyword');
        $this->assertCampaignValue($source, $conversion['campaign_source'], 'campaign_source');

        [$coreName, $coreKeyword] = $expectedInCore;
        $this->assertCampaignValue($coreName, $conversion['referer_name'], 'referer_name');
        $this->assertCampaignValue($coreKeyword, $conversion['referer_keyword'], 'referer_keyword');
    }

    public function getConsentedConversionTestData(): iterable
    {
        $held = ['Campaign Name', 'Campaign Keyword', 'campaign-source'];
        $heldInCore = ['Campaign Name', 'Campaign Keyword'];

        // arrival, cookie name, cookie keyword, keep the case of campaign values, expected name, keyword and
        // source, expected core name and keyword, and whether the conversion repeats the visit's consent
        yield 'visit arrived directly' => ['direct', 'Campaign Name', 'Campaign Keyword', false, ['P', 'P', null], ['P', 'P']];
        yield 'visit arrived from an AI assistant' => ['ai', 'Campaign Name', 'Campaign Keyword', false, ['P', 'P', null], ['P', 'P']];
        yield 'visit holds another campaign' => ['campaign', 'Autumn Sale', 'Campaign Keyword', false, ['P', 'Campaign Keyword', 'campaign-source'], ['P', 'Campaign Keyword']];
        yield 'visit holds the campaign under another keyword' => ['campaign', 'Campaign Name', 'Other Keyword', false, ['Campaign Name', 'P', 'campaign-source'], ['Campaign Name', 'P']];
        yield 'visit holds the campaign without a keyword' => ['campaign without keyword', 'Campaign Name', 'Campaign Keyword', false, ['Campaign Name', 'P', 'campaign-source'], ['Campaign Name', 'P']];
        yield 'cookie carries the placeholder' => ['campaign', 'P', '', false, ['P', 'Campaign Keyword', 'campaign-source'], ['P', 'P']];
        yield 'cookie is longer than the stored campaign' => ['long', str_repeat('a', 300), '', false, ['P', null, 'campaign-source'], ['P', 'P']];
        yield 'visit holds the campaign' => ['campaign', 'Campaign Name', 'Campaign Keyword', false, $held, $heldInCore];
        yield 'visit holds the campaign in another case' => ['campaign', 'CAMPAIGN NAME', 'CAMPAIGN KEYWORD', false, $held, $heldInCore];
        yield 'visit holds the campaign, case kept, cookie in another case' => ['campaign', 'campaign name', 'campaign keyword', true, $held, $heldInCore];
        yield 'visit holds the campaign, case kept' => ['campaign', 'Campaign Name', 'Campaign Keyword', true, $held, $heldInCore];
        // core reports the keyword the cookie carries, none here, against the one the visit holds;
        // this plugin has always kept the visit's keyword where the cookie carries none
        yield 'cookie carries no keyword' => ['campaign', 'Campaign Name', '', false, $held, ['Campaign Name', 'P']];
        yield 'no cookie' => ['campaign', '', '', false, $held, $heldInCore];
        yield 'visit arrived directly, consent not repeated' => ['direct', 'Campaign Name', 'Campaign Keyword', false, ['P', 'P', null], ['P', 'P'], false];
        yield 'visit holds the campaign, consent not repeated' => ['campaign', 'Campaign Name', 'Campaign Keyword', false, $held, $heldInCore, false];
    }

    /**
     * The conversion is masked as before wherever consent does not count for the visit.
     *
     * @dataProvider getUnconsentedConversionTestData
     */
    public function testAConversionReportsThePlaceholderWhereConsentDoesNotCount(
        ?string $consent,
        bool $optedIn,
        bool $optOutMidVisit,
        bool $conversionRepeatsConsent = true
    ) {
        $placeholder = $this->requireConsentAwareAnalytics();
        $this->setDoNotChangeCaseOfUtmParameters(false);

        if (!$optedIn) {
            $this->setConsentAwareAnalytics(false);
        }

        PolicyManager::setPolicyActiveStatus(CnilPolicy::class, true, $this->idSite);
        try {
            $conversion = $this->trackConversionWithAttributionCookie(
                'campaign',
                'Campaign Name',
                'Campaign Keyword',
                $consent,
                $optOutMidVisit,
                [],
                $conversionRepeatsConsent
            );
        } finally {
            PolicyManager::setPolicyActiveStatus(CnilPolicy::class, false, $this->idSite);
        }

        foreach (MarketingCampaignsReporting::getAdvancedCampaignFields() as $field) {
            self::assertSame($placeholder, $conversion[$field], $field);
        }
    }

    public function getUnconsentedConversionTestData(): iterable
    {
        // consent, site opted in, site stops measuring consent before the conversion, conversion repeats consent
        yield 'no consent' => [null, true, false];
        yield 'consent refused' => ['0', true, false];
        yield 'site not opted in' => ['1', false, false];
        yield 'site stops measuring consent mid visit' => ['1', true, true];
        yield 'site stops measuring consent mid visit, consent not repeated' => ['1', true, true, false];
    }

    /**
     * A visit that starts now does not inherit the consent of the one before it, even though the
     * earlier visit's columns are still loaded while the new one is being created.
     */
    public function testANewVisitDoesNotInheritTheConsentOfTheVisitBefore()
    {
        $placeholder = $this->requireConsentAwareAnalytics();

        PolicyManager::setPolicyActiveStatus(CnilPolicy::class, true, $this->idSite);
        try {
            Db::query('TRUNCATE TABLE ' . Common::prefixTable('log_visit'));

            $tracker = $this->getCampaignTracker();
            $tracker->setCustomTrackingParameter('consent', '1');
            Fixture::checkResponse($tracker->doTrackPageView('Some page title'));

            $tracker->setUrl('https://www.example.com/?utm_campaign=winter_clearance&utm_source=other_source');
            Fixture::checkResponse($tracker->doTrackPageView('Another page title'));

            $visits = Db::fetchAll('SELECT * FROM ' . Common::prefixTable('log_visit') . ' ORDER BY idvisit ASC');

            self::assertCount(2, $visits, 'a different campaign starts a visit of its own');
            self::assertEqualsIgnoringCase('newsletter_7', $visits[0]['campaign_source'], 'the first visit consented');
            self::assertEquals($placeholder, $visits[1]['campaign_source'], 'the new visit did not report consent');
            self::assertEquals($placeholder, $visits[1]['campaign_name']);
            self::assertEquals($placeholder, $visits[1]['referer_name'], 'core masks the new visit too');
        } finally {
            PolicyManager::setPolicyActiveStatus(CnilPolicy::class, false, $this->idSite);
        }
    }

    /**
     * A visit measured before consent holds the discarded campaign. A consenting conversion on that
     * visit does not report a campaign against it, whether the campaign is in its URL or its cookie.
     */
    public function testAConsentedConversionDoesNotReportACampaignTheVisitDiscarded()
    {
        $placeholder = $this->requireConsentAwareAnalytics();
        $this->setDoNotChangeCaseOfUtmParameters(false);

        PolicyManager::setPolicyActiveStatus(CnilPolicy::class, true, $this->idSite);
        try {
            Db::query('TRUNCATE TABLE ' . Common::prefixTable('log_visit'));
            Db::query('TRUNCATE TABLE ' . Common::prefixTable('log_conversion'));

            $idGoal = \Piwik\Plugins\Goals\API::getInstance()->addGoal($this->idSite, 'manual goal', 'manually', '', 'contains');

            $tracker = $this->getCampaignTracker();
            Fixture::checkResponse($tracker->doTrackPageView('Some page title'));

            $tracker->setUrl('https://www.example.com/another-page');
            Fixture::checkResponse($tracker->doTrackPageView('Another page title'));

            $tracker->setCustomTrackingParameter('consent', '1');
            $tracker->setCustomTrackingParameter('_rcn', 'newsletter_7');
            $tracker->setUrl('https://www.example.com/another-page?utm_source=newsletter_7&utm_medium=email');
            Fixture::checkResponse($tracker->doTrackGoal($idGoal, 42));

            $conversion = Db::fetchRow('SELECT * FROM ' . Common::prefixTable('log_conversion') . ' LIMIT 1');
            $visit = Db::fetchRow(
                'SELECT * FROM ' . Common::prefixTable('log_visit') . ' WHERE idvisit = ?',
                [$conversion['idvisit']]
            );

            foreach (MarketingCampaignsReporting::getAdvancedCampaignFields() as $field) {
                if ($placeholder === $visit[$field]) {
                    self::assertSame($placeholder, $conversion[$field], $field . ' contradicts the visit it was recorded against');
                }
            }

            if ($placeholder === $visit['referer_name']) {
                self::assertSame($placeholder, $conversion['referer_name'], 'core contradicts the visit too');
            }

            self::assertSame($placeholder, $visit['campaign_source'], 'the conversion was recorded against the discarded visit');
        } finally {
            PolicyManager::setPolicyActiveStatus(CnilPolicy::class, false, $this->idSite);
        }
    }

    /**
     * Where the tracker withholds campaign parameters until consent, the visit is first recorded
     * against whatever was left - the referring site, or a direct entry. Consent given while the
     * visitor is still on the page they arrived on brings the campaign back, and it describes that
     * same arrival, so the visit is completed with it rather than split - as core completes the
     * campaign it records itself.
     *
     * @dataProvider getWithheldCampaignTestData
     */
    public function testConsentOnTheArrivalPageCompletesAWithheldCampaign(string $referrer, string $carrier, bool $policy = true)
    {
        $this->requireConsentAwareAnalytics();

        // Whether the visit is split is core's decision alone, and a core that does not expose the
        // rule by name does not yet keep the arrival together where no policy masks the campaign.
        if (
            !$policy
            && !method_exists('Piwik\\Tracker\\Visit\\WithheldCampaign', 'revealsArrival')
        ) {
            $this->markTestSkipped('Completing a withheld campaign without a policy is not available in this core version.');
        }

        PolicyManager::setPolicyActiveStatus(CnilPolicy::class, $policy, $this->idSite);
        try {
            Db::query('TRUNCATE TABLE ' . Common::prefixTable('log_visit'));

            $tracker = Fixture::getTracker($this->idSite, date('Y-m-d H:i:s'), $defaultInit = true, $useLocal = false);
            $tracker->setUrl('https://www.example.com/');
            $tracker->setUrlReferrer($referrer);
            Fixture::checkResponse($tracker->doTrackPageView('Some page title'));

            $tracker->setUrlReferrer('');
            $tracker->setUrl('https://www.example.com/?utm_campaign=autumn_sale&utm_source=newsletter_7&utm_medium=email');
            $tracker->setCustomTrackingParameter('consent', '1');

            if ('ping' === $carrier) {
                Fixture::checkResponse($tracker->doPing());
            } elseif ('event' === $carrier) {
                Fixture::checkResponse($tracker->doTrackEvent('Consent', 'Given'));
            } else {
                Fixture::checkResponse($tracker->doTrackPageView('Some page title'));
            }

            $visit = Db::fetchRow('SELECT * FROM ' . Common::prefixTable('log_visit') . ' LIMIT 1');

            self::assertEquals(1, $this->countVisits(), 'consent on the arrival page should not have split the visit');
            self::assertEquals(1, $visit['consent'], 'the consent never reached the visit');
            self::assertEqualsIgnoringCase('autumn_sale', $visit['referer_name'], 'core completes the visit');
            self::assertEqualsIgnoringCase('autumn_sale', $visit['campaign_name']);
            self::assertEqualsIgnoringCase('newsletter_7', $visit['campaign_source']);
            self::assertEqualsIgnoringCase('email', $visit['campaign_medium']);
        } finally {
            PolicyManager::setPolicyActiveStatus(CnilPolicy::class, false, $this->idSite);
        }
    }

    public function getWithheldCampaignTestData(): iterable
    {
        foreach (['ping', 'event', 'pageview'] as $carrier) {
            yield "arrived from another site, consent by $carrier" => ['https://www.facebook.com/', $carrier];
            yield "arrived directly, consent by $carrier" => ['', $carrier];
        }

        // nothing is withheld by a policy here, but the tracker still withholds the campaign until consent
        yield 'arrived from another site, no policy enforced' => ['https://www.facebook.com/', 'pageview', false];
        // core records the campaign over the AI assistant the visit was first attributed to, as the
        // consenting request names no assistant
        yield 'arrived from an AI assistant, consent names none' => ['https://chatgpt.com/', 'pageview'];
    }

    /**
     * Where the visit already holds a campaign, a different one arriving with consent is a change
     * rather than a completion, and starts a visit of its own as before.
     */
    public function testConsentWithADifferentCampaignOnTheArrivalPageStillStartsANewVisit()
    {
        $this->requireConsentAwareAnalytics();

        Db::query('TRUNCATE TABLE ' . Common::prefixTable('log_visit'));

        // no policy: the visit holds its campaign in the clear before consent
        $tracker = Fixture::getTracker($this->idSite, date('Y-m-d H:i:s'), $defaultInit = true, $useLocal = false);
        $tracker->setUrl('https://www.example.com/?utm_campaign=spring_sale&utm_source=newsletter_7');
        Fixture::checkResponse($tracker->doTrackPageView('Some page title'));

        $tracker->setUrl('https://www.example.com/?utm_campaign=autumn_sale&utm_source=newsletter_7');
        $tracker->setCustomTrackingParameter('consent', '1');
        Fixture::checkResponse($tracker->doTrackPageView('Some page title'));

        $visits = Db::fetchAll('SELECT * FROM ' . Common::prefixTable('log_visit') . ' ORDER BY idvisit ASC');

        self::assertCount(2, $visits);
        self::assertEqualsIgnoringCase('spring_sale', $visits[0]['campaign_name']);
        self::assertEqualsIgnoringCase('autumn_sale', $visits[1]['campaign_name']);
    }

    /**
     * Without consent nothing is completed, and a site that does not measure consent-aware
     * analytics behaves as it always has, whatever the request reports.
     *
     * @dataProvider getWithheldCampaignBaselineTestData
     */
    public function testAWithheldCampaignIsNotCompletedWhereConsentDoesNotCount(?string $consent, bool $optedIn, bool $splitOnCampaignChange)
    {
        $placeholder = $this->requireConsentAwareAnalytics();
        if (!$optedIn) {
            $this->setConsentAwareAnalytics(false);
        }

        $this->setCreateNewVisitWhenCampaignChanges($splitOnCampaignChange);

        PolicyManager::setPolicyActiveStatus(CnilPolicy::class, true, $this->idSite);
        try {
            Db::query('TRUNCATE TABLE ' . Common::prefixTable('log_visit'));

            $tracker = Fixture::getTracker($this->idSite, date('Y-m-d H:i:s'), $defaultInit = true, $useLocal = false);
            $tracker->setUrl('https://www.example.com/');
            $tracker->setUrlReferrer('https://www.facebook.com/');
            Fixture::checkResponse($tracker->doTrackPageView('Some page title'));

            $tracker->setUrlReferrer('');
            $tracker->setUrl('https://www.example.com/?utm_campaign=autumn_sale&utm_source=newsletter_7&utm_medium=email');
            if (null !== $consent) {
                $tracker->setCustomTrackingParameter('consent', $consent);
            }
            Fixture::checkResponse($tracker->doTrackPageView('Some page title'));

            $visits = Db::fetchAll('SELECT * FROM ' . Common::prefixTable('log_visit') . ' ORDER BY idvisit ASC');

            // a campaign arriving on a visit that holds none starts a visit of its own where the
            // site splits visits on a campaign change, and is left out of it where it does not.
            // Consent reported on the arrival page never splits it: core reads that campaign as the
            // one the visitor arrived through, even where the server does not honour the consent
            $expectsSplit = $splitOnCampaignChange && '1' !== $consent;
            self::assertCount($expectsSplit ? 2 : 1, $visits);
            self::assertNull($visits[0]['campaign_name'], 'the first visit was completed');
            self::assertNull($visits[0]['campaign_source']);
            self::assertEquals('Facebook', $visits[0]['referer_name']);

            if ($expectsSplit) {
                self::assertEquals($placeholder, $visits[1]['campaign_source'], 'the new visit did not consent');
            }
        } finally {
            PolicyManager::setPolicyActiveStatus(CnilPolicy::class, false, $this->idSite);
            $this->setCreateNewVisitWhenCampaignChanges(true);
        }
    }

    public function getWithheldCampaignBaselineTestData(): iterable
    {
        foreach ([true, false] as $split) {
            $suffix = $split ? '' : ', visits not split on a campaign change';
            yield 'no consent' . $suffix => [null, true, $split];
            yield 'consent refused' . $suffix => ['0', true, $split];
            yield 'site not opted in' . $suffix => ['1', false, $split];
        }
    }

    /**
     * A visit whose arrival already left part of a campaign behind holds that campaign, and consent
     * bringing a fuller one does not overwrite it as though nothing had been recorded.
     */
    public function testConsentDoesNotOverwriteACampaignTheVisitAlreadyHolds()
    {
        $this->requireConsentAwareAnalytics();
        $this->setCreateNewVisitWhenCampaignChanges(false);

        try {
            Db::query('TRUNCATE TABLE ' . Common::prefixTable('log_visit'));

            $tracker = Fixture::getTracker($this->idSite, date('Y-m-d H:i:s'), $defaultInit = true, $useLocal = false);
            $tracker->setUrl('https://www.example.com/?utm_source=newsletter_7');
            $tracker->setUrlReferrer('https://www.facebook.com/');
            Fixture::checkResponse($tracker->doTrackPageView('Some page title'));

            $tracker->setUrlReferrer('');
            $tracker->setUrl('https://www.example.com/?utm_campaign=autumn_sale&utm_source=other_source');
            $tracker->setCustomTrackingParameter('consent', '1');
            Fixture::checkResponse($tracker->doTrackPageView('Some page title'));

            $visits = Db::fetchAll('SELECT * FROM ' . Common::prefixTable('log_visit') . ' ORDER BY idvisit ASC');

            self::assertCount(1, $visits);
            self::assertEqualsIgnoringCase('newsletter_7', $visits[0]['campaign_source']);
            self::assertNull($visits[0]['campaign_name']);
        } finally {
            $this->setCreateNewVisitWhenCampaignChanges(true);
        }
    }

    /**
     * Once the visitor has moved on, a campaign arriving with consent no longer describes how the
     * visit began, so it does not complete the visit even where nothing splits it.
     */
    public function testConsentAfterNavigatingDoesNotCompleteAWithheldCampaign()
    {
        $this->requireConsentAwareAnalytics();
        $this->setCreateNewVisitWhenCampaignChanges(false);

        try {
            Db::query('TRUNCATE TABLE ' . Common::prefixTable('log_visit'));

            $tracker = Fixture::getTracker($this->idSite, date('Y-m-d H:i:s'), $defaultInit = true, $useLocal = false);
            $tracker->setUrl('https://www.example.com/');
            $tracker->setUrlReferrer('https://www.facebook.com/');
            Fixture::checkResponse($tracker->doTrackPageView('Some page title'));

            $tracker->setUrlReferrer('');
            $tracker->setUrl('https://www.example.com/another-page');
            Fixture::checkResponse($tracker->doTrackPageView('Another page title'));

            $tracker->setUrl('https://www.example.com/another-page?utm_campaign=autumn_sale&utm_source=newsletter_7');
            $tracker->setCustomTrackingParameter('consent', '1');
            Fixture::checkResponse($tracker->doTrackPageView('Another page title'));

            $visits = Db::fetchAll('SELECT * FROM ' . Common::prefixTable('log_visit') . ' ORDER BY idvisit ASC');

            self::assertCount(1, $visits);
            self::assertNull($visits[0]['campaign_name'], 'the visit was completed from a later page');
            self::assertNull($visits[0]['campaign_source']);
            self::assertEquals('Facebook', $visits[0]['referer_name'], 'core does not complete it either');
        } finally {
            $this->setCreateNewVisitWhenCampaignChanges(true);
        }
    }

    /**
     * Consent on the arrival page completes only a campaign core records as one. A visit core
     * attributed to an AI assistant stays one, as it does where the campaign parameters were there
     * from the start, and parameters without a campaign name are not a campaign to core.
     *
     * @dataProvider getConsentRevealingNoCampaignTestData
     */
    public function testConsentOnTheArrivalPageDoesNotCompleteWhatCoreDoesNotRecordAsACampaign(
        string $referrer,
        string $query,
        string $carrier,
        int $expectedType,
        string $consentReferrer = ''
    ) {
        $this->requireConsentAwareAnalytics();

        PolicyManager::setPolicyActiveStatus(CnilPolicy::class, true, $this->idSite);
        try {
            Db::query('TRUNCATE TABLE ' . Common::prefixTable('log_visit'));

            $tracker = Fixture::getTracker($this->idSite, date('Y-m-d H:i:s'), $defaultInit = true, $useLocal = false);
            $tracker->setUrl('https://www.example.com/');
            $tracker->setUrlReferrer($referrer);
            Fixture::checkResponse($tracker->doTrackPageView('Some page title'));

            $tracker->setUrl('https://www.example.com/?' . $query);
            $tracker->setUrlReferrer($consentReferrer);
            $tracker->setCustomTrackingParameter('consent', '1');

            if ('ping' === $carrier) {
                Fixture::checkResponse($tracker->doPing());
            } else {
                Fixture::checkResponse($tracker->doTrackPageView('Some page title'));
            }

            $visit = Db::fetchRow('SELECT * FROM ' . Common::prefixTable('log_visit') . ' LIMIT 1');

            self::assertEquals(1, $this->countVisits(), 'consent on the arrival page should not have split the visit');
            self::assertEquals(1, $visit['consent'], 'the consent never reached the visit');
            self::assertEquals($expectedType, (int) $visit['referer_type'], 'core attributes the visit differently');

            foreach (MarketingCampaignsReporting::getAdvancedCampaignFields() as $field) {
                self::assertNull($visit[$field], $field . ' was completed');
            }
        } finally {
            PolicyManager::setPolicyActiveStatus(CnilPolicy::class, false, $this->idSite);
        }
    }

    public function getConsentRevealingNoCampaignTestData(): iterable
    {
        foreach (['ping', 'pageview'] as $carrier) {
            yield "AI assistant, source only, consent by $carrier" => ['https://chatgpt.com/', 'utm_source=chatgpt.com', $carrier, Common::REFERRER_TYPE_AI_ASSISTANT];
            yield "AI assistant, campaign, consent by $carrier" => ['https://chatgpt.com/', 'utm_campaign=autumn_sale&utm_source=chatgpt.com', $carrier, Common::REFERRER_TYPE_AI_ASSISTANT];
            yield "direct, source only, consent by $carrier" => ['', 'mtm_source=newsletter_7', $carrier, Common::REFERRER_TYPE_DIRECT_ENTRY];
        }

        // core attributes a direct entry to the AI assistant the consenting request names
        yield 'direct, AI assistant named with the consent' => ['', 'utm_campaign=autumn_sale', 'pageview', Common::REFERRER_TYPE_AI_ASSISTANT, 'https://chatgpt.com/'];
    }

    /**
     * A discarded campaign is completed only where core completes its own: a consenting request
     * that core attributes to an AI assistant, or whose parameters core does not read as a
     * campaign, leaves the visit as it was measured.
     *
     * @dataProvider getDiscardedCampaignNotCompletedTestData
     */
    public function testADiscardedCampaignIsCompletedOnlyWhereCoreCompletesIt(
        string $consentQuery,
        string $consentReferrer,
        string $arrivalQuery = 'utm_campaign=autumn_sale&utm_source=newsletter_7'
    ) {
        $placeholder = $this->requireConsentAwareAnalytics();

        PolicyManager::setPolicyActiveStatus(CnilPolicy::class, true, $this->idSite);
        try {
            Db::query('TRUNCATE TABLE ' . Common::prefixTable('log_visit'));

            $tracker = Fixture::getTracker($this->idSite, date('Y-m-d H:i:s'), $defaultInit = true, $useLocal = false);
            $tracker->setUrl('https://www.example.com/?' . $arrivalQuery);
            Fixture::checkResponse($tracker->doTrackPageView('Some page title'));

            $tracker->setUrl('https://www.example.com/?' . $consentQuery);
            $tracker->setUrlReferrer($consentReferrer);
            $tracker->setCustomTrackingParameter('consent', '1');
            Fixture::checkResponse($tracker->doTrackPageView('Some page title'));

            $visit = Db::fetchRow('SELECT * FROM ' . Common::prefixTable('log_visit') . ' LIMIT 1');

            self::assertEquals(1, $this->countVisits());
            self::assertSame($placeholder, $visit['referer_name'], 'core completed the visit');

            foreach (MarketingCampaignsReporting::getAdvancedCampaignFields() as $field) {
                self::assertSame($placeholder, $visit[$field], $field . ' was completed');
            }
        } finally {
            PolicyManager::setPolicyActiveStatus(CnilPolicy::class, false, $this->idSite);
        }
    }

    public function getDiscardedCampaignNotCompletedTestData(): iterable
    {
        yield 'consent names an AI assistant' => ['utm_campaign=autumn_sale&utm_source=newsletter_7', 'https://chatgpt.com/'];
        yield 'consent carries no campaign core reads' => ['mtm_source=other_source', ''];
        // core reads nothing of this campaign, so completes nothing, and it stays withheld here too
        yield 'campaign only these dimensions read' => ['utm_content=hero', '', 'utm_content=hero'];
    }

    /**
     * Consent reported by a conversion that cannot be placed on the arrival page does not apply to
     * the visit it is recorded against, so the campaign its URL carries is not recorded in the
     * clear there, as core records none.
     */
    public function testAConsentingConversionAfterNavigatingDoesNotRelaxTheVisitBefore()
    {
        $placeholder = $this->requireConsentAwareAnalytics();
        $this->setCreateNewVisitWhenCampaignChanges(false);

        PolicyManager::setPolicyActiveStatus(CnilPolicy::class, true, $this->idSite);
        try {
            Db::query('TRUNCATE TABLE ' . Common::prefixTable('log_visit'));
            Db::query('TRUNCATE TABLE ' . Common::prefixTable('log_conversion'));

            $idGoal = \Piwik\Plugins\Goals\API::getInstance()->addGoal($this->idSite, 'manual goal', 'manually', '', 'contains');

            $tracker = Fixture::getTracker($this->idSite, date('Y-m-d H:i:s'), $defaultInit = true, $useLocal = false);
            $tracker->setUrl('https://www.example.com/landing-page');
            Fixture::checkResponse($tracker->doTrackPageView('Some page title'));

            $tracker->setUrl('https://www.example.com/another-page');
            Fixture::checkResponse($tracker->doTrackPageView('Another page title'));

            $tracker->setCustomTrackingParameter('consent', '1');
            $tracker->setUrl('https://www.example.com/another-page?utm_campaign=autumn_sale&utm_source=newsletter_7');
            Fixture::checkResponse($tracker->doTrackGoal($idGoal, 42));

            $visit = Db::fetchRow('SELECT * FROM ' . Common::prefixTable('log_visit') . ' LIMIT 1');
            $conversion = Db::fetchRow('SELECT * FROM ' . Common::prefixTable('log_conversion') . ' LIMIT 1');

            self::assertEquals(1, $this->countVisits());
            self::assertEmpty($visit['consent'], 'the consent applied to the visit');
            self::assertEquals(Common::REFERRER_TYPE_DIRECT_ENTRY, (int) $conversion['referer_type'], 'core records no campaign');
            self::assertSame($placeholder, $conversion['campaign_name']);
            self::assertSame($placeholder, $conversion['campaign_source']);
        } finally {
            PolicyManager::setPolicyActiveStatus(CnilPolicy::class, false, $this->idSite);
            $this->setCreateNewVisitWhenCampaignChanges(true);
        }
    }

    private function setCreateNewVisitWhenCampaignChanges(bool $enabled): void
    {
        $env = new TestingEnvironmentVariables();
        $env->overrideConfig('Tracker', 'create_new_visit_when_campaign_changes', (int) $enabled);
        $env->save();
    }

    /**
     * A ping sent from the arrival page places the visitor there, so consent it reports completes
     * a discarded campaign from it, as core completes its own.
     */
    public function testAConsentingPingOnTheArrivalPageCompletesTheDiscardedCampaign()
    {
        $placeholder = $this->requireConsentAwareAnalytics();

        PolicyManager::setPolicyActiveStatus(CnilPolicy::class, true, $this->idSite);
        try {
            Db::query('TRUNCATE TABLE ' . Common::prefixTable('log_visit'));

            $tracker = Fixture::getTracker($this->idSite, date('Y-m-d H:i:s'), $defaultInit = true, $useLocal = false);
            $tracker->setUrl('https://www.example.com/?utm_campaign=autumn_sale&utm_source=newsletter_7');
            Fixture::checkResponse($tracker->doTrackPageView('Some page title'));

            $data = Db::fetchRow('SELECT * FROM ' . Common::prefixTable('log_visit') . ' LIMIT 1');
            self::assertEquals($placeholder, $data['campaign_name'], 'discarded until the visitor consents');

            $tracker->setCustomTrackingParameter('consent', '1');
            Fixture::checkResponse($tracker->doPing());

            $data = Db::fetchRow('SELECT * FROM ' . Common::prefixTable('log_visit') . ' LIMIT 1');

            self::assertEquals(1, $this->countVisits(), 'a ping cannot start a visit');
            self::assertEqualsIgnoringCase('autumn_sale', $data['referer_name'], 'core completes the visit');
            self::assertEqualsIgnoringCase('autumn_sale', $data['campaign_name']);
            self::assertEqualsIgnoringCase('newsletter_7', $data['campaign_source']);
        } finally {
            PolicyManager::setPolicyActiveStatus(CnilPolicy::class, false, $this->idSite);
        }
    }

    /**
     * Once the visitor has moved on, a ping cannot be placed on the arrival page, so consent it
     * reports completes nothing - even where its URL carries the campaign parameters again.
     */
    public function testAConsentingPingAfterNavigatingDoesNotCompleteTheDiscardedCampaign()
    {
        $placeholder = $this->requireConsentAwareAnalytics();

        PolicyManager::setPolicyActiveStatus(CnilPolicy::class, true, $this->idSite);
        try {
            Db::query('TRUNCATE TABLE ' . Common::prefixTable('log_visit'));

            $tracker = Fixture::getTracker($this->idSite, date('Y-m-d H:i:s'), $defaultInit = true, $useLocal = false);
            $tracker->setUrl('https://www.example.com/?utm_campaign=autumn_sale&utm_source=newsletter_7');
            Fixture::checkResponse($tracker->doTrackPageView('Some page title'));

            $tracker->setUrl('https://www.example.com/another-page');
            Fixture::checkResponse($tracker->doTrackPageView('Another page title'));

            $tracker->setUrl('https://www.example.com/another-page?utm_campaign=autumn_sale&utm_source=newsletter_7');
            $tracker->setCustomTrackingParameter('consent', '1');
            Fixture::checkResponse($tracker->doPing());

            $data = Db::fetchRow('SELECT * FROM ' . Common::prefixTable('log_visit') . ' LIMIT 1');

            self::assertEquals(1, $this->countVisits(), 'a ping cannot start a visit');
            self::assertEquals($placeholder, $data['campaign_name'], 'the visit keeps the treatment it was measured under');
            self::assertEquals($placeholder, $data['campaign_source']);
            self::assertEquals($placeholder, $data['referer_name'], 'core keeps it too');
        } finally {
            PolicyManager::setPolicyActiveStatus(CnilPolicy::class, false, $this->idSite);
        }
    }

    /**
     * Consent reported away from the arrival page starts a visit of its own, even where that page's
     * URL carries campaign parameters, and the discarded visit is not completed from it.
     */
    public function testConsentAwayFromTheArrivalPageDoesNotCompleteTheDiscardedCampaign()
    {
        $placeholder = $this->requireConsentAwareAnalytics();

        PolicyManager::setPolicyActiveStatus(CnilPolicy::class, true, $this->idSite);
        try {
            Db::query('TRUNCATE TABLE ' . Common::prefixTable('log_visit'));

            $tracker = $this->getCampaignTracker();
            Fixture::checkResponse($tracker->doTrackPageView('Some page title'));

            $tracker->setCustomTrackingParameter('consent', '1');
            $tracker->setUrl('https://www.example.com/another-page?utm_source=newsletter_7&utm_medium=email');
            Fixture::checkResponse($tracker->doTrackPageView('Another page title'));

            $visits = Db::fetchAll('SELECT * FROM ' . Common::prefixTable('log_visit') . ' ORDER BY idvisit ASC');

            self::assertCount(2, $visits);
            self::assertEquals($placeholder, $visits[0]['campaign_source'], 'the first visit keeps its treatment');
            self::assertEquals($placeholder, $visits[0]['campaign_medium']);
            self::assertEqualsIgnoringCase('newsletter_7', $visits[1]['campaign_source']);
        } finally {
            PolicyManager::setPolicyActiveStatus(CnilPolicy::class, false, $this->idSite);
        }
    }

    /**
     * @param string|null $expected 'P' for the placeholder, null for no value
     * @param mixed $actual
     */
    private function assertCampaignValue(?string $expected, $actual, string $column): void
    {
        if (null === $expected) {
            self::assertEmpty($actual, $column);
        } elseif ('P' === $expected) {
            self::assertSame(MarketingCampaignsReporting::getCampaignPlaceholderValue(), $actual, $column);
        } else {
            self::assertEqualsIgnoringCase($expected, $actual, $column);
        }
    }

    /**
     * An attribution cookie sent as an array carries no campaign, and must not fail the request.
     */
    public function testAConsentedConversionIgnoresAnArrayValuedAttributionCookie()
    {
        $this->requireConsentAwareAnalytics();
        $this->setDoNotChangeCaseOfUtmParameters(false);

        PolicyManager::setPolicyActiveStatus(CnilPolicy::class, true, $this->idSite);
        try {
            $conversion = $this->trackConversionWithAttributionCookie('direct', '', '', '1', false, ['_rcn[]' => 'Campaign Name']);
        } finally {
            PolicyManager::setPolicyActiveStatus(CnilPolicy::class, false, $this->idSite);
        }

        self::assertNull($conversion['campaign_name']);
    }

    /**
     * A campaign carried by the conversion request itself is this request's own and is consented
     * with it, so a consenting visitor's conversion records it as a site without the policy does.
     */
    public function testAConsentedConversionRecordsItsOwnCampaignAsASiteWithoutThePolicy()
    {
        $this->requireConsentAwareAnalytics();
        $this->setDoNotChangeCaseOfUtmParameters(false);

        PolicyManager::setPolicyActiveStatus(CnilPolicy::class, true, $this->idSite);
        try {
            $withPolicy = $this->trackConversionWithCampaignInItsUrl();
        } finally {
            PolicyManager::setPolicyActiveStatus(CnilPolicy::class, false, $this->idSite);
        }

        $withoutPolicy = $this->trackConversionWithCampaignInItsUrl();

        self::assertNotEmpty($withoutPolicy['campaign_name']);
        self::assertSame($withoutPolicy, $withPolicy);
    }

    /**
     * @param array<string, string> $extraParameters
     * @return array<string, mixed> the conversion row
     */
    private function trackConversionWithAttributionCookie(
        string $arrival,
        string $cookieName,
        string $cookieKeyword,
        ?string $consent,
        bool $optOutMidVisit = false,
        array $extraParameters = [],
        bool $conversionRepeatsConsent = true
    ): array {
        Db::query('TRUNCATE TABLE ' . Common::prefixTable('log_visit'));
        Db::query('TRUNCATE TABLE ' . Common::prefixTable('log_conversion'));

        $idGoal = \Piwik\Plugins\Goals\API::getInstance()->addGoal($this->idSite, 'manual goal', 'manually', '', 'contains');

        $tracker = Fixture::getTracker($this->idSite, date('Y-m-d H:i:s'), $defaultInit = true, $useLocal = false);

        if (null !== $consent) {
            $tracker->setCustomTrackingParameter('consent', $consent);
        }

        if ('ai' === $arrival) {
            $tracker->setUrl('https://www.example.com/landing-page');
            $tracker->setUrlReferrer('https://chatgpt.com/');
        } elseif ('direct' === $arrival) {
            $tracker->setUrl('https://www.example.com/landing-page');
        } elseif ('campaign without keyword' === $arrival) {
            $tracker->setUrl('https://www.example.com/landing-page?utm_campaign=Campaign%20Name&utm_source=campaign-source&utm_medium=campaign-medium');
        } elseif ('long' === $arrival) {
            $tracker->setUrl('https://www.example.com/landing-page?utm_campaign=' . str_repeat('a', 300) . '&utm_source=campaign-source');
        } else {
            $tracker->setUrl('https://www.example.com/landing-page?utm_campaign=Campaign%20Name&utm_term=Campaign%20Keyword&utm_source=campaign-source&utm_medium=campaign-medium');
        }

        Fixture::checkResponse($tracker->doTrackPageView('Some page title'));

        if ('ai' === $arrival) {
            $visit = Db::fetchRow('SELECT referer_type FROM ' . Common::prefixTable('log_visit') . ' LIMIT 1');
            self::assertSame(Common::REFERRER_TYPE_AI_ASSISTANT, (int) $visit['referer_type']);
        }

        if ($optOutMidVisit) {
            $this->setConsentAwareAnalytics(false);
        }

        // the tracker sends custom parameters with one request only
        if (null !== $consent && $conversionRepeatsConsent) {
            $tracker->setCustomTrackingParameter('consent', $consent);
        }

        $tracker->setUrl('https://www.example.com/conversion-page');
        if ('' !== $cookieName) {
            $tracker->setCustomTrackingParameter('_rcn', $cookieName);
        }
        if ('' !== $cookieKeyword) {
            $tracker->setCustomTrackingParameter('_rck', $cookieKeyword);
        }
        foreach ($extraParameters as $name => $value) {
            $tracker->setCustomTrackingParameter($name, $value);
        }
        Fixture::checkResponse($tracker->doTrackGoal($idGoal, 42));

        self::assertEquals(1, $this->countVisits(), 'the conversion should stay in the visit it was tracked in');

        return Db::fetchRow('SELECT * FROM ' . Common::prefixTable('log_conversion') . ' LIMIT 1');
    }

    /**
     * @return array<string, mixed> the campaign columns of the conversion
     */
    private function trackConversionWithCampaignInItsUrl(): array
    {
        Db::query('TRUNCATE TABLE ' . Common::prefixTable('log_visit'));
        Db::query('TRUNCATE TABLE ' . Common::prefixTable('log_conversion'));

        $idGoal = \Piwik\Plugins\Goals\API::getInstance()->addGoal($this->idSite, 'manual goal', 'manually', '', 'contains');

        $tracker = Fixture::getTracker($this->idSite, date('Y-m-d H:i:s'), $defaultInit = true, $useLocal = false);
        $tracker->setCustomTrackingParameter('consent', '1');
        $tracker->setUrl('https://www.example.com/landing-page');
        Fixture::checkResponse($tracker->doTrackPageView('Some page title'));

        $tracker->setCustomTrackingParameter('consent', '1');
        $tracker->setUrl('https://www.example.com/conversion-page?utm_campaign=Campaign%20Name&utm_term=Campaign%20Keyword&utm_source=campaign-source');
        Fixture::checkResponse($tracker->doTrackGoal($idGoal, 42));

        $conversion = Db::fetchRow('SELECT * FROM ' . Common::prefixTable('log_conversion') . ' LIMIT 1');

        return array_intersect_key($conversion, array_flip(MarketingCampaignsReporting::getAdvancedCampaignFields()));
    }

    private function setConsentAwareAnalytics(bool $enabled): void
    {
        $measurable = ConsentAwareAnalytics::getMeasurableSetting($this->idSite);
        $measurable->setValue($enabled);
        $measurable->save();
        Cache::deleteTrackerCache();
    }

    public function testCampaignPlaceholderIsFormattedForReports()
    {
        $placeholder = MarketingCampaignsReporting::getCampaignPlaceholderValue();
        if ($placeholder === '') {
            $this->markTestSkipped('CampaignParameterValuesMasked is not available in this core version.');
        }

        $formatter = new Formatter();
        $expectedLabel = Piwik::translate('PrivacyManager_CampaignParameterDiscarded');

        self::assertSame(
            $expectedLabel,
            (new CampaignName())->formatValue($placeholder, $this->idSite, $formatter)
        );
        self::assertSame(
            $expectedLabel,
            (new ReferrerName())->formatValue($placeholder, $this->idSite, $formatter)
        );
        self::assertSame(
            $expectedLabel,
            (new CampaignSourceMedium())->formatValue($placeholder . ' - ' . $placeholder, $this->idSite, $formatter)
        );
    }

    public function testFormatCombinedCampaignValueCollapsesFullyMaskedValues()
    {
        $placeholder = MarketingCampaignsReporting::getCampaignPlaceholderValue();
        if ($placeholder === '') {
            $this->markTestSkipped('CampaignParameterValuesMasked is not available in this core version.');
        }

        self::assertSame(
            Piwik::translate('PrivacyManager_CampaignParameterDiscarded'),
            MarketingCampaignsReporting::formatCombinedCampaignValue($placeholder . ' - ' . $placeholder)
        );
    }

    public function testFormatCombinedCampaignValuePreservesSeparatorsWhenOnlySomePartsAreMasked()
    {
        $placeholder = MarketingCampaignsReporting::getCampaignPlaceholderValue();
        if ($placeholder === '') {
            $this->markTestSkipped('CampaignParameterValuesMasked is not available in this core version.');
        }

        self::assertSame(
            Piwik::translate('PrivacyManager_CampaignParameterDiscarded') . ' - newsletter',
            MarketingCampaignsReporting::formatCombinedCampaignValue($placeholder . ' - newsletter')
        );
    }

    public function testCampaignPlaceholderIsFormattedInCampaignReportLabels()
    {
        $placeholder = MarketingCampaignsReporting::getCampaignPlaceholderValue();
        if ($placeholder === '') {
            $this->markTestSkipped('CampaignParameterValuesMasked is not available in this core version.');
        }

        $expectedLabel = Piwik::translate('PrivacyManager_CampaignParameterDiscarded');
        $comparisons = new DataTable();
        $comparisons->addRow(new Row([
            Row::COLUMNS => [
                'label' => $placeholder . ' - newsletter',
            ],
        ]));

        $subtable = new DataTable();
        $subtable->addRow(new Row([
            Row::COLUMNS => [
                'label' => $placeholder . ' - ' . $placeholder,
            ],
        ]));

        $table = new DataTable();
        $table->addRow(new Row([
            Row::COLUMNS => [
                'label' => $placeholder,
            ],
            Row::DATATABLE_ASSOCIATED => $subtable,
        ]));
        $table->getFirstRow()->setComparisons($comparisons);

        $table->filter(FormatCampaignLabels::class);

        self::assertSame($expectedLabel, $table->getFirstRow()->getColumn('label'));
        self::assertSame(
            $expectedLabel,
            $table->getFirstRow()->getSubtable()->getFirstRow()->getColumn('label')
        );
        self::assertSame(
            $expectedLabel . ' - newsletter',
            $table->getFirstRow()->getComparisons()->getFirstRow()->getColumn('label')
        );
    }

    public function testVisitorDetailsFormatsMaskedCampaignValuesForApi()
    {
        $placeholder = MarketingCampaignsReporting::getCampaignPlaceholderValue();
        if ($placeholder === '') {
            $this->markTestSkipped('CampaignParameterValuesMasked is not available in this core version.');
        }

        $expectedLabel = Piwik::translate('PrivacyManager_CampaignParameterDiscarded');

        $visitorDetails = new VisitorDetails();
        $visitorDetails->setDetails([
            'campaign_id' => $placeholder,
            'campaign_content' => $placeholder,
            'campaign_keyword' => $placeholder,
            'campaign_medium' => $placeholder,
            'campaign_name' => $placeholder,
            'campaign_source' => $placeholder,
            'campaign_group' => $placeholder,
            'campaign_placement' => $placeholder,
        ]);

        $visitor = [];
        $visitorDetails->extendVisitorDetails($visitor);

        self::assertSame($expectedLabel, $visitor['campaignId']);
        self::assertSame($expectedLabel, $visitor['campaignContent']);
        self::assertSame($expectedLabel, $visitor['campaignKeyword']);
        self::assertSame($expectedLabel, $visitor['campaignMedium']);
        self::assertSame($expectedLabel, $visitor['campaignName']);
        self::assertSame($expectedLabel, $visitor['campaignSource']);
        self::assertSame($expectedLabel, $visitor['campaignGroup']);
        self::assertSame($expectedLabel, $visitor['campaignPlacement']);
    }

    private function setDoNotChangeCaseOfUtmParameters(bool $value): void
    {
        $systemSettings = StaticContainer::get(SystemSettings::class);
        $systemSettings->doNotChangeCaseOfUtmParameters->setIsWritableByCurrentUser(true);
        $systemSettings->doNotChangeCaseOfUtmParameters->setValue($value);
        $systemSettings->save();
    }
}
