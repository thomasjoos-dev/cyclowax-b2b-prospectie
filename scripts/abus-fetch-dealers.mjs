#!/usr/bin/env node

/**
 * Fetches Abus dealer data for a given country using Playwright to bypass Cloudflare.
 *
 * Usage: node scripts/abus-fetch-dealers.mjs --country=CH|DE
 * Output: JSON array of dealer objects to stdout
 * Status/progress messages go to stderr
 *
 * The script navigates to the Abus Händlerkarte page (requires system Chrome to bypass
 * Cloudflare), extracts form parameters, and calls the /dealers.json API endpoint.
 *
 * For CH: all ~500 dealers are returned in a single API call.
 * For DE: the API returns max 500 per call; the script uses a location-based sweep
 *         to collect all ~5800 dealers across multiple calls.
 */

import { chromium } from 'playwright';

// --- Country configuration ---
const COUNTRIES = {
    CH: {
        path: 'ch_de',
        // All dealers fit in one call (maxPage=49, ~500 dealers)
        sweep: false,
    },
    DE: {
        path: 'de',
        // Needs location-based sweep (maxPage=580, ~5800 dealers)
        sweep: true,
        // Grid of postcodes covering all German Bundesländer
        postcodes: [
            '10115', '20095', '80331', '50667', '60311', '70173', '40210',
            '30159', '90402', '01067', '04109', '28195', '24103', '18055',
            '99084', '66111', '55116', '14467', '79098', '49074', '34117',
            '39104', '87435', '26122', '54290', '96047', '02826', '17489',
        ],
    },
};

// --- Parse CLI arguments ---
const countryArg = process.argv.find((a) => a.startsWith('--country='));
const country = countryArg ? countryArg.split('=')[1].toUpperCase() : null;

if (!country || !COUNTRIES[country]) {
    process.stderr.write('Usage: node scripts/abus-fetch-dealers.mjs --country=CH|DE\n');
    process.exit(1);
}

const config = COUNTRIES[country];
const allDealers = new Map(); // dealerID -> dealer object

/**
 * Launch browser and navigate to the Händlerkarte page.
 * Uses system Chrome (channel: 'chrome') to reliably bypass Cloudflare.
 */
async function initBrowser() {
    const browser = await chromium.launch({
        channel: 'chrome',
        headless: true,
        args: ['--disable-blink-features=AutomationControlled'],
    });

    const context = await browser.newContext({
        userAgent:
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
        viewport: { width: 1280, height: 720 },
    });

    const page = await context.newPage();
    await page.addInitScript(() => {
        Object.defineProperty(navigator, 'webdriver', { get: () => false });
    });

    return { browser, page };
}

/**
 * Extract the search form parameters from the Händlerkarte page.
 */
async function extractFormParams(page) {
    return page.evaluate(() => {
        const form = document.querySelector('form[data-dealer-search]');
        if (!form) return null;
        return {
            action: form.getAttribute('action'),
            contentId: form.querySelector('[name="contentId"]')?.value,
            blockId: form.querySelector('[name="blockId"]')?.value,
            maxPage: form.querySelector('[name="maxPage"]')?.value,
        };
    });
}

/**
 * Fetch dealers from the API endpoint.
 */
async function fetchDealers(page, formParams, place = '') {
    return page.evaluate(
        async ({ action, contentId, blockId, maxPage, place }) => {
            const params = new URLSearchParams({
                contentId,
                blockId,
                maxPage,
                showFacets: '0',
            });
            if (place) params.set('place', place);

            const resp = await fetch(`${action}?${params}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await resp.json();
            return data.dealer || [];
        },
        { ...formParams, place }
    );
}

async function main() {
    const { browser, page } = await initBrowser();

    const url = `https://www.abus.com/${config.path}/Haendlerkarte`;
    process.stderr.write(`Loading ${url}...\n`);

    try {
        await page.goto(url, { waitUntil: 'networkidle', timeout: 60000 });
    } catch {
        process.stderr.write('  Page load timeout (continuing)...\n');
    }

    // Dismiss cookie banner
    try {
        const btn = await page.$('#onetrust-accept-btn-handler');
        if (btn) {
            await btn.click();
            await page.waitForTimeout(500);
        }
    } catch {}

    // Extract form parameters
    const formParams = await extractFormParams(page);
    if (!formParams) {
        process.stderr.write('ERROR: Could not find dealer search form on page.\n');
        await browser.close();
        process.exit(1);
    }

    process.stderr.write(`Form params: contentId=${formParams.contentId}, blockId=${formParams.blockId}, maxPage=${formParams.maxPage}\n`);

    if (!config.sweep) {
        // --- Single call (CH) ---
        process.stderr.write('Fetching all dealers in one call...\n');
        const dealers = await fetchDealers(page, formParams);

        for (const dealer of dealers) {
            allDealers.set(dealer.dealerID, dealer);
        }

        process.stderr.write(`  ${dealers.length} dealers fetched.\n`);
    } else {
        // --- Location-based sweep (DE) ---
        const postcodes = config.postcodes;
        process.stderr.write(`Sweeping ${postcodes.length} locations...\n`);

        for (let i = 0; i < postcodes.length; i++) {
            const plz = postcodes[i];
            const before = allDealers.size;

            const dealers = await fetchDealers(page, formParams, plz);
            for (const dealer of dealers) {
                allDealers.set(dealer.dealerID, dealer);
            }

            const added = allDealers.size - before;
            process.stderr.write(
                `  [${i + 1}/${postcodes.length}] ${plz}: ${dealers.length} results, ${added} new (total: ${allDealers.size})\n`
            );

            // Small delay between requests
            await page.waitForTimeout(500);
        }
    }

    // Output results
    const output = Array.from(allDealers.values());
    process.stdout.write(JSON.stringify(output));
    process.stderr.write(`\nDone: ${output.length} unique dealers for ${country}.\n`);

    await browser.close();
}

main().catch((err) => {
    process.stderr.write(`Fatal error: ${err.message}\n`);
    process.exit(1);
});
