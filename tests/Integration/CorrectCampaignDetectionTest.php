<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\MarketingCampaignsReporting\tests\Integration;

use Piwik\Common;
use Piwik\Date;
use Piwik\Db;
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
}
