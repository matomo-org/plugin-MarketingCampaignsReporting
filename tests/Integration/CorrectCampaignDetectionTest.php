<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\MarketingCampaignsReporting\tests\Integration;

use Piwik\Common;
use Piwik\DataTable;
use Piwik\DataTable\Row;
use Piwik\Db;
use Piwik\Metrics\Formatter;
use Piwik\Piwik;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignName;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignSourceMedium;
use Piwik\Plugins\MarketingCampaignsReporting\DataTable\Filter\FormatCampaignLabels;
use Piwik\Plugins\MarketingCampaignsReporting\MarketingCampaignsReporting;
use Piwik\Plugins\MarketingCampaignsReporting\VisitorDetails;
use Piwik\Plugins\Referrers\Columns\ReferrerName;
use Piwik\Policy\CnilPolicy;
use Piwik\Policy\PolicyManager;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;
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

    public function testGoalConversionUsesReferrerAttributionCampaignCookie()
    {
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
        Fixture::checkResponse($tracker->doTrackPageView('Some page title'));

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
        self::assertSame('Campaign Name', $conversion['campaign_name']);
        self::assertSame('Campaign Keyword', $conversion['campaign_keyword']);
    }

    public function testGoalConversionSkipsReferrerAttributionCampaignCookieForAiAssistants()
    {
        if (version_compare(Version::VERSION, '5.5.0-b1', '<')) {
            $this->markTestSkipped('AI assistant referrer detection is not available in this core version.');
        }

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

        $tracker->setUrl('https://www.example.com/conversion-page');
        $tracker->setCustomTrackingParameter('_rcn', 'Campaign Name');
        $tracker->setCustomTrackingParameter('_rck', 'Campaign Keyword');
        Fixture::checkResponse($tracker->doTrackGoal($idGoal, 42));

        $conversion = Db::fetchRow(
            'SELECT referer_type, referer_name, referer_keyword, campaign_name, campaign_keyword FROM '
            . Common::prefixTable('log_conversion')
            . ' LIMIT 1'
        );

        self::assertSame(Common::REFERRER_TYPE_AI_ASSISTANT, (int) $conversion['referer_type']);
        self::assertSame('ChatGPT', $conversion['referer_name']);
        self::assertEmpty($conversion['campaign_name']);
        self::assertEmpty($conversion['campaign_keyword']);
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
}
