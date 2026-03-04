#!/usr/bin/env node

/**
 * Fetches Orbea dealer data for a given country using Playwright.
 *
 * Usage: node scripts/orbea-fetch-dealers.mjs --country=BE|DE|CH
 * Output: JSON array of dealer objects to stdout
 * Status/progress messages go to stderr
 *
 * Orbea's dealer locator uses a direct URL pattern:
 *   https://www.orbea.com/{locale}/dealers/{country}/{postcode}
 * This renders an HTML table with dealer data and Google Maps markers.
 * By sweeping postcodes we collect all dealers per country.
 */

import { chromium } from 'playwright';

const log = (msg) => process.stderr.write(`${msg}\n`);

// --- Country configuration ---
const COUNTRIES = {
    BE: {
        locale: 'nl-be',
        code: 'be',
        postcodes: [
            '1000', '1300', '1400', '1500', '1600', '1700', '1800', '1900',
            '2000', '2100', '2200', '2300', '2400', '2500', '2600', '2800',
            '3000', '3200', '3300', '3400', '3500', '3600', '3700', '3800', '3900',
            '4000', '4100', '4300', '4400', '4500', '4600', '4700', '4800',
            '5000', '5100', '5300', '5500', '5600',
            '6000', '6200', '6400', '6600', '6700', '6800', '6900',
            '7000', '7100', '7300', '7500', '7700', '7800',
            '8000', '8200', '8300', '8400', '8500', '8600', '8700', '8800', '8900',
            '9000', '9100', '9200', '9300', '9400', '9500', '9600', '9700', '9800', '9900',
        ],
    },
    DE: {
        locale: 'de-de',
        code: 'de',
        postcodes: [
            // North
            '20095', '22041', '24103', '25746', '26122', '27568', '28195',
            '30159', '31134', '32052', '33098', '34117', '35037',
            // East
            '01067', '02826', '03046', '04109', '06108', '07743', '08056',
            '10115', '12043', '13347', '14467', '15230', '16816', '17489', '18055', '19053',
            // Central
            '36037', '37073', '38100', '39104',
            '40210', '41061', '42103', '44135', '45127', '46045', '47051', '48143', '49074',
            '50667', '51373', '52062', '53111', '54290', '55116', '56068', '57072', '58095', '59065',
            // South
            '60311', '61348', '63065', '64283', '65185', '66111', '67059', '68159', '69115',
            '70173', '71032', '72072', '73033', '74072', '75175', '76131', '77652', '78462', '79098',
            '80331', '81541', '82362', '83022', '84028', '85049', '86150', '87435', '88212', '89073',
            '90402', '91052', '92224', '93047', '94032', '95028', '96047', '97070', '98527', '99084',
        ],
    },
    CH: {
        locale: 'de-ch',
        code: 'ch',
        postcodes: [
            '1000', '1200', '1400', '1530', '1700', '1800', '1950',
            '2000', '2300', '2500', '2800',
            '3000', '3250', '3400', '3600', '3800', '3920',
            '4000', '4132', '4410', '4600', '4900',
            '5000', '5200', '5400', '5600',
            '6000', '6210', '6460', '6600', '6900',
            '7000', '7260', '7500', '7742',
            '8000', '8200', '8400', '8500', '8600', '8800', '8953',
            '9000', '9200', '9400', '9500',
        ],
    },
};

// --- Parse CLI arguments ---
const countryArg = process.argv.find((a) => a.startsWith('--country='));
const country = countryArg ? countryArg.split('=')[1].toUpperCase() : null;

if (!country || !COUNTRIES[country]) {
    log('Usage: node scripts/orbea-fetch-dealers.mjs --country=BE|DE|CH');
    process.exit(1);
}

const config = COUNTRIES[country];
const allDealers = new Map(); // "name|postal_code" -> dealer object

/**
 * Launch browser with anti-detection settings.
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
 * Parse dealer data from the HTML table on the results page.
 * Each row has two cells:
 *   - Cell 0: <h2>Name</h2> + Google Maps link with daddr=lat,lng
 *   - Cell 1: Street<br>PostalCode City (Province). CC<br>T. Phone<br><a href="url">Web</a>
 */
async function parseDealers(page) {
    return page.evaluate(() => {
        const dealers = [];
        // The dealer table is the one with <h2> elements (not the keyboard shortcuts table)
        const rows = document.querySelectorAll('.results table tbody tr, .table-dealers table tbody tr');

        // Fallback: find any table with h2 elements
        let tableRows = rows;
        if (tableRows.length === 0) {
            const tables = document.querySelectorAll('table');
            for (const table of tables) {
                if (table.querySelector('h2')) {
                    tableRows = table.querySelectorAll('tbody tr');
                    break;
                }
            }
        }

        // Another fallback: any table row with h2
        if (tableRows.length === 0) {
            tableRows = document.querySelectorAll('table tr');
        }

        for (const row of tableRows) {
            const cells = row.querySelectorAll('td');
            if (cells.length < 2) continue;

            const nameEl = cells[0].querySelector('h2');
            if (!nameEl) continue;

            const name = nameEl.textContent.trim();

            // Extract GPS from Google Maps link: daddr=lat,lng
            let latitude = null;
            let longitude = null;
            const mapsLink = cells[0].querySelector('a[href*="maps.google"]');
            if (mapsLink) {
                const match = mapsLink.href.match(/daddr=([-\d.]+),([-\d.]+)/);
                if (match) {
                    latitude = parseFloat(match[1]);
                    longitude = parseFloat(match[2]);
                }
            }

            // Parse the address cell
            const addressHtml = cells[1].innerHTML;
            const addressText = cells[1].textContent;

            // Split by <br> to get address lines
            const lines = addressHtml.split(/<br\s*\/?>/).map((l) =>
                l.replace(/<[^>]+>/g, '').trim()
            );

            let street = lines[0] || null;

            // Second line: "PostalCode City (Province). CC"
            let postalCode = null;
            let city = null;
            if (lines[1]) {
                const addrMatch = lines[1].match(/^(\d{4,5})\s+(.+?)(?:\s*\(.+?\))?\.?\s*\w{0,3}$/);
                if (addrMatch) {
                    postalCode = addrMatch[1];
                    city = addrMatch[2].trim();
                }
            }

            // Phone: look for "T." prefix
            let phone = null;
            const phoneMatch = addressText.match(/T\.\s*([\d\s+\-().]+)/);
            if (phoneMatch) {
                phone = phoneMatch[1].trim();
            }

            // Website
            let website = null;
            const webLink = cells[1].querySelector('a[href][rel="external"]');
            if (webLink) {
                website = webLink.href;
            }

            // Email
            let email = null;
            const emailLink = cells[1].querySelector('a[href^="mailto:"]');
            if (emailLink) {
                email = emailLink.href.replace('mailto:', '');
            }

            dealers.push({ name, street, postalCode, city, latitude, longitude, phone, email, website });
        }

        return dealers;
    });
}

async function main() {
    const { browser, page } = await initBrowser();
    const postcodes = config.postcodes;

    log(`Fetching Orbea dealers for ${country} (${postcodes.length} postcode sweeps)...`);

    // First navigate to the main dealers page to establish session/cookies
    const baseUrl = `https://www.orbea.com/${config.locale}/dealers`;
    log(`Loading ${baseUrl}...`);

    try {
        await page.goto(baseUrl, { waitUntil: 'networkidle', timeout: 60000 });
    } catch {
        log('  Page load timeout (continuing)...');
    }

    // Dismiss cookie banner
    try {
        const btn = await page.$('#CybotCookiebotDialogBodyButtonDecline');
        if (btn) {
            await btn.click();
            await page.waitForTimeout(500);
        }
    } catch {}

    // Sweep all postcodes
    for (let i = 0; i < postcodes.length; i++) {
        const postcode = postcodes[i];
        const before = allDealers.size;
        const url = `${baseUrl}/${config.code}/${postcode}`;

        try {
            const resp = await page.goto(url, { waitUntil: 'networkidle', timeout: 30000 });

            if (resp.status() === 200) {
                await page.waitForTimeout(500);
                const dealers = await parseDealers(page);

                for (const dealer of dealers) {
                    const key = `${(dealer.name || '').toLowerCase()}|${dealer.postalCode || ''}`;
                    if (!allDealers.has(key)) {
                        allDealers.set(key, dealer);
                    }
                }

                const added = allDealers.size - before;
                log(
                    `  [${i + 1}/${postcodes.length}] ${postcode}: ${dealers.length} results, ${added} new (total: ${allDealers.size})`
                );
            } else {
                log(`  [${i + 1}/${postcodes.length}] ${postcode}: HTTP ${resp.status()}`);
            }
        } catch (err) {
            log(`  [${i + 1}/${postcodes.length}] ${postcode}: ERROR ${err.message}`);
        }

        // Small delay between requests
        if (i < postcodes.length - 1) {
            await page.waitForTimeout(300);
        }
    }

    // Output results
    const output = Array.from(allDealers.values());
    process.stdout.write(JSON.stringify(output));
    log(`\nDone: ${output.length} unique dealers for ${country}.`);

    await browser.close();
}

main().catch((err) => {
    log(`Fatal error: ${err.message}`);
    process.exit(1);
});
