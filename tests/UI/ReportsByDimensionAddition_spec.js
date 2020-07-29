/*!
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * Based on code from AdvancedCampaignReporting plugin by Piwik PRO released under GPL v3 or later: https://github.com/PiwikPRO/plugin-AdvancedCampaignReporting
 */
describe("MarketingCampaignsReporting_ReportsByDimensionAddition", function () {
    this.timeout(0);

    this.fixture = 'Piwik\\Plugins\\MarketingCampaignsReporting\\tests\\Fixtures\\TrackAdvancedCampaigns';

    var withVisitsParams = "&idSite=1&date=2013-01-23&period=week",
        urlPrefix = "?module=CoreHome&action=index&",
        goalsUrl = urlPrefix + withVisitsParams + "#?category=Goals_Goals&subcategory=General_Overview" + withVisitsParams,
        ecommerceUrl = urlPrefix + withVisitsParams + "#?category=Goals_Ecommerce&subcategory=Ecommerce_Sales" + withVisitsParams;

    before(async function () {
        await page.webpage.setViewport({
            width: 1500,
            height: 768
        });
    });

    it("should load correctly within the Goals page", async function () {
        await page.goto(goalsUrl);
        await page.evaluate(function () {
            $('.dimensionCategory .dimension:contains(Campaign Names)').click();
        });
        await page.waitForNetworkIdle();
        pageWrap = await page.$('.reportsByDimensionView');
        expect(await pageWrap.screenshot()).to.matchImage('loaded_goals');
    });

    it("should load correctly within the Ecommerce page", async function () {
        await page.goto(ecommerceUrl);
        await page.evaluate(function () {
            $('.dimensionCategory .dimension:contains(Campaign Names)').click();
        });
        await page.waitForNetworkIdle();
        pageWrap = await page.$('.reportsByDimensionView');
        expect(await pageWrap.screenshot()).to.matchImage('loaded_ecommerce');
    });
});