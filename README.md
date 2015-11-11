# Advanced Campaigns Reporting

Master [![Build Status](https://travis-ci.org/PiwikPRO/plugin-AdvancedCampaignReporting.svg?branch=master)](https://travis-ci.org/PiwikPRO/plugin-AdvancedCampaignReporting)
Develop [![Build Status](https://travis-ci.org/PiwikPRO/plugin-AdvancedCampaignReporting.svg?branch=develop)](https://travis-ci.org/PiwikPRO/plugin-AdvancedCampaignReporting)

## Description

Track up to five Campaigns parameters (name, source, medium, keyword, content), and access Campaign Analytics reports.

### Measuring campaigns

The default Campaign parameters are called: pk_campaign, pk_source, pk_medium, pk_keyword, pk_content and pk_cid.

If you already have URLs tagged with Google Analytics parameters these are supported: utm_campaign, utm_source, utm_medium, utm_term, utm_content and utm_id

An example landing page URL is:
```
/offer?pk_campaign=Best-Seller&pk_source=Newsletter_7&pk_medium=email
```

### Features
 * Real time Analytics Reports of all your Campaign Marketing
 * Detects Campaign parameters from the landing page URL, within the query string or in the #hash string
 * The Referrers>Overview report displays a left column "Referrers Overview" with a list of reports that can be loaded on click.
   This report viewer now also lists the new Campaign reports under "View Referrers by Campaign".
 * The content of Referrers> Campaign will be replaced with the new enhanced Campaigns reports.
 * The default Referrers Campaign widget and API are working as before.
 * The campaign reports are available in Piwik Mobile and can be sent as Scheduled reports (by email, as HTML or PDF)
 * Segment editor: a new "Campaigns" category lists the five new segment for each campaign dimension
 * The new Campaign reports can be added as widgets in your personalized Dashboard
 * Access the Campaign Report data by the API
 * Comes with automated tests to ensure the Plugin works as expected
 * Will track up to 250 characters for each of the five Campaign dimension

### Notes

In the Campaign reports by default Piwik will only archive the first 1000 rows. If you track many campaigns you can configure Piwik so it does not truncate your data. To have data truncated after 10,000 rows, edit your `config/config.ini.php` and add the following:

```
[General]
datatable_archiving_maximum_rows_referrers = 10000
datatable_archiving_maximum_rows_subtable_referrers = 10000
```


### Ideas for improvement
 * To improve data acquisition accuracy, we could extend the piwik.js class to store in first party cookies
 the five campaign dimensions. This would increase the accuracy of Goal conversions and Ecommerce conversions attributions
 for these conversions made at least one day after the first visit with a campaign set. [#10](https://github.com/PiwikPRO/plugin-AdvancedCampaignReporting/issues/10)
 * Add friendly Tracking API parameters to collect campaign dimensions.
 campaignName `cn`, campaignSource `cs`, campaignMedium `cm`, campaignContent `cc`, campaignId `ci`.

## Changelog
 * 1.2.0 (Nov 10th 2015) - Plugin comaptibility with Piwik 2.15.0
 * 1.1.1 (Sept 3rd 2015) - Campaign reports now display your campaign report data even for campaign data before you activated AdvancedCampaignReporting
 * 1.1.0 (July 28th 2015)
 * 1.0.8 (Apr 1st 2015) - Exclude Google Analytics campaign parameters from the Page URLs
 * 1.0.6 (Nov 17th 2014) - Documentation
 * 1.0.5 (Nov 14th 2014) - Detect new URL parameters: piwik_campaign, pk_cpn and for Keywords: pk_kwd, piwik_keyword
 * 1.0.4 (Nov 4th 2014) - View Goals by Campaign Dimension in the Goals & Ecommerce reports
 * 1.0.3 (Oct 1st 2014) - Released for free on the [Piwik Marketplace](http://plugins.piwik.org/)

 
## Support

Plugin provided by [Piwik PRO](https://piwik.pro) - Cloud and Enterprise analytics from the creators of Piwik.org

If you find a bug or have a suggestion please create an issue in: https://github.com/PiwikPRO/plugin-AdvancedCampaignReporting/issues
