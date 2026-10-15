<?php
/**
 * Clean Beans LLC - starting content for a fresh install.
 * Run once by install.php (and by the development test harness) into an empty database.
 *
 * Copy comes from the Clean Beans LLC Facebook page (services, 22 years, LLC / insured / bonded,
 * bloodborne-pathogens certification, Hudson WI, phone, TikTok) and avoids claims Becky hasn't made:
 * no prices, hours, products or guarantees. Everything here is editable in the admin.
 */
declare(strict_types=1);

function seed_cleanbeans(): void
{
    $now = now();

    /* ---------- Settings ---------- */
    $settings = [
        'company_name' => 'Clean Beans LLC',
        'brand_name' => 'Clean Beans',
        'tagline' => 'LLC · Hudson, WI',
        'phone' => '(715) 222-2444',
        'call_label' => 'Call or text Becky',
        'contact_email' => '',                 // set to info@cleanbeansllc.com once domain mail is live
        'address_line1' => '',
        'address_line2' => 'Hudson, WI 54016',
        'city' => 'Hudson', 'state' => 'WI', 'zip' => '54016',
        'service_area_summary' => 'Serving Hudson, WI and the St. Croix Valley.',
        'credentials' => '22 years of experience|LLC, insured & bonded|Bloodborne pathogens certified',
        'notify_email' => '',                  // Becky's alert address, set in Settings
        'notify_from' => 'hostmaster@cleanbeansllc.com',
        'social_facebook' => 'https://www.facebook.com/cleanbeansllc',
        'social_tiktok' => 'https://www.tiktok.com/@clean.beans.llc',
        'social_instagram' => '', 'social_youtube' => '', 'social_google' => '', 'social_twitter' => '',
        'social_linkedin' => '', 'social_pinterest' => '', 'social_snapchat' => '',
        'allow_indexing' => '0',      // staging: keep search engines out until launch
        'show_credits' => '1',
    ];
    foreach ($settings as $k => $v) {
        db_insert('settings', ['skey' => $k, 'svalue' => $v]);
    }

    /* ---------- Photos: none yet. Becky's job photos get uploaded in Photos and added to pages. ---------- */

    /* ---------- Pages ---------- */
    $page = function (string $slug, string $title, string $nav, int $order, ?int $hero, string $heading, string $intro, string $metaTitle, string $metaDesc, int $inNav = 1) use ($now): int {
        return db_insert('pages', ['slug' => $slug, 'title' => $title, 'nav_label' => $nav, 'show_in_nav' => $inNav, 'nav_order' => $order,
            'is_published' => 1, 'hero_media_id' => $hero, 'hero_heading' => $heading, 'hero_text' => $intro,
            'meta_title' => $metaTitle, 'meta_description' => $metaDesc, 'created_at' => $now, 'updated_at' => $now]);
    };
    $order = 0;
    $sec = function (int $pageId, string $type, string $heading, string $body = '', ?int $media = null, array $data = []) use ($now, &$order): void {
        $order += 10;
        db_insert('sections', ['page_id' => $pageId, 'type' => $type, 'heading' => $heading, 'body_html' => $body, 'media_id' => $media,
            'data' => json_encode($data), 'sort_order' => $order, 'is_enabled' => 1, 'created_at' => $now, 'updated_at' => $now]);
    };
    $cta = '<p>Tell Becky about the space and what you need. You\'ll get a straight answer on what it takes and what it costs.</p>';
    $quote = fn(string $p) => '<p>' . $p . '</p>';
    $faqCost = ['q' => 'How much does it cost?', 'a' => '<p>Every job is different. Call or text (715) 222-2444, or send a request with the size of the space and what you need, and you\'ll get a quote before any work starts.</p>'];

    // Home
    $home = $page('home', 'Home', 'Home', 10, null,
        'Cleaning for Hudson and the St. Croix Valley',
        'Move-outs, deep cleans, rental flips, offices and the tough jobs other cleaners turn down. 22 years of experience, and you talk directly with the person doing the work.',
        'Clean Beans LLC | Cleaning Services in Hudson, WI & the St. Croix Valley',
        'House cleaning, deep cleans, move-outs, rental flips, commercial, post-construction, hoarding and biohazard cleaning from Clean Beans LLC in Hudson, WI. Call (715) 222-2444.', 0);
    $order = 0;
    $sec($home, 'services', 'How we can help');
    $sec($home, 'text', 'A local cleaning company', '<p>Clean Beans LLC is a Hudson cleaning company serving homeowners, renters, landlords, realtors and businesses on both sides of the St. Croix.</p>'
        . '<p>When you call, you talk directly with Becky. Tell her about the space and what you need, and she\'ll tell you what it will take before any work starts.</p>'
        . '<p>From regular house cleaning to post-construction dust and hoarding cleanups, Clean Beans has done it in 22 years of cleaning. The company is an LLC, insured and bonded.</p>'
        . '<p><a href="about">More about Clean Beans</a> · <a href="service-area">See the area we cover</a></p>', null, ['band' => 1]);
    $sec($home, 'text', 'Recent work: listing prep in downtown Stillwater', '<p>Clean Beans recently prepped a remodeled 1930s home in downtown Stillwater for its listing photos: four bedrooms, two full baths, new appliances and a newly finished attic.</p>'
        . '<p>Follow along on <a href="https://www.facebook.com/cleanbeansllc" target="_blank" rel="noopener">Facebook</a> and <a href="https://www.tiktok.com/@clean.beans.llc" target="_blank" rel="noopener">TikTok</a> for before-and-afters and job stories.</p>');
    $sec($home, 'cta', 'Ready for a cleaner space?', $cta);
    $sec($home, 'request_form', 'Request a free quote', $quote('Send the details and Becky will get back to you.'), null, ['band' => 1]);

    // Residential & deep cleaning
    $res = $page('residential-cleaning', 'Residential & Deep Cleaning', 'House Cleaning', 20, null,
        'House cleaning and deep cleans',
        'Regular house cleaning or one serious top-to-bottom reset, for homes around Hudson and the valley.',
        'House Cleaning & Deep Cleaning in Hudson, WI | Clean Beans LLC',
        'Regular house cleaning and deep cleans for homes in Hudson, River Falls, Stillwater and the St. Croix Valley. Call or text Clean Beans at (715) 222-2444.');
    $order = 0;
    $sec($res, 'text', 'When to call', '<ul><li>You want a regular cleaning schedule so the house stays on top of itself</li><li>It\'s been a while and the home needs a full deep clean</li>'
        . '<li>You\'re hosting family or getting ready for the holidays</li><li>Someone in the house is recovering, expecting or just stretched thin</li>'
        . '<li>You\'re catching up after a renovation, a move or a long season</li></ul>'
        . '<p>Call or text with the size of the home and what you need. You\'ll get a quote before any work starts.</p>');
    $sec($res, 'text', 'Deep clean or regular clean?', '<p>A deep clean gets into the places a regular visit doesn\'t: baseboards, inside appliances, cabinet fronts and built-up grime in kitchens and bathrooms. Many homes start with one deep clean, then switch to regular visits.</p>', null, ['band' => 1]);
    $sec($res, 'faq', 'Questions', '', null, ['items' => [
        $faqCost,
        ['q' => 'Do I need to be home?', 'a' => '<p>Not necessarily. You can work out access when you book.</p>'],
        ['q' => 'Can I ask for specific products?', 'a' => '<p>Yes. If you have products you prefer, or ones you\'d like avoided, just say so when you book.</p>'],
    ]]);
    $sec($res, 'cta', 'Want a cleaner home?', $cta);
    $sec($res, 'request_form', 'Request a free quote', '', null, ['band' => 1]);

    // Move-in / move-out
    $mov = $page('move-in-move-out', 'Move-In / Move-Out Cleaning', 'Move In / Out', 30, null,
        'Move-in and move-out cleaning',
        'Empty-house cleans for renters, owners and landlords, so the keys change hands on a clean place.',
        'Move-In & Move-Out Cleaning in Hudson, WI | Clean Beans LLC',
        'Move-out and move-in cleaning for renters, homeowners and landlords around Hudson, WI and the St. Croix Valley. Call or text (715) 222-2444.');
    $order = 0;
    $sec($mov, 'text', 'When to call', '<ul><li>You\'re moving out and want the place ready for inspection</li><li>You just bought or rented a place and want it cleaned before your things arrive</li>'
        . '<li>You\'re a landlord or property manager turning a unit between tenants</li><li>An estate or family home needs to be cleaned out and cleaned up</li></ul>');
    $sec($mov, 'text', 'Tell us the dates', '<p>Moves run on deadlines. Include your closing or lease-end date with your request so the clean can be scheduled to fit.</p>', null, ['band' => 1]);
    $sec($mov, 'faq', 'Questions', '', null, ['items' => [
        $faqCost,
        ['q' => 'Do the utilities need to be on?', 'a' => '<p>Yes, water and power make the job possible. Mention it if that\'s a problem.</p>'],
        ['q' => 'Can you clean the same day as the move?', 'a' => '<p>Sometimes. Ask with your date and Becky will tell you what\'s possible.</p>'],
    ]]);
    $sec($mov, 'cta', 'Moving soon?', $cta);
    $sec($mov, 'request_form', 'Request a free quote', '', null, ['band' => 1]);

    // Rental flips & listing prep
    $flip = $page('rental-flips', 'Rental Flips & Listing Prep', 'Rentals', 40, null,
        'Rental flips and listing prep',
        'Turnovers for landlords and investors, and homes cleaned for listing photos and showings.',
        'Rental Turnover & Listing Prep Cleaning | Clean Beans LLC, Hudson WI',
        'Rental turnovers, flip cleanups and listing-photo prep for landlords, investors and realtors around Hudson, WI and Stillwater, MN. Call (715) 222-2444.');
    $order = 0;
    $sec($flip, 'text', 'When to call', '<ul><li>A rental is turning over and needs to be rent-ready fast</li><li>A flip or remodel is finished and the dust needs to go</li>'
        . '<li>A home is about to be photographed for its listing</li><li>You\'re a realtor or property manager who needs a cleaner you can count on</li></ul>');
    $sec($flip, 'text', 'Recent: a 1930s home in downtown Stillwater', '<p>Clean Beans prepped this remodeled four-bedroom, two-bath home for its listing photos, new appliances, finished attic and all.</p>', null, ['band' => 1]);
    $sec($flip, 'faq', 'Questions', '', null, ['items' => [
        ['q' => 'Do you work with realtors and property managers?', 'a' => '<p>Yes. Ongoing turnover work is welcome. Call to talk about your properties.</p>'],
        ['q' => 'Can you meet a photo or showing date?', 'a' => '<p>Send the date with your request so the clean can be scheduled ahead of it.</p>'],
    ]]);
    $sec($flip, 'cta', 'Got a property to turn over?', $cta);
    $sec($flip, 'request_form', 'Request a free quote', '', null, ['band' => 1]);

    // Commercial & office
    $com = $page('commercial-cleaning', 'Commercial & Office Cleaning', 'Commercial', 50, null,
        'Commercial and office cleaning',
        'Offices, office buildings and commercial spaces, on a schedule that works for the business.',
        'Commercial & Office Cleaning in Hudson, WI | Clean Beans LLC',
        'Office and commercial cleaning around Hudson, WI and the St. Croix Valley. Regular or one-time service. LLC, insured and bonded. Call (715) 222-2444.');
    $order = 0;
    $sec($com, 'text', 'When to call', '<ul><li>Your office or building needs regular cleaning</li><li>A space needs a deep clean before a move-in, opening or inspection</li>'
        . '<li>A tenant moved out of a commercial unit</li><li>Your current cleaning service isn\'t keeping up</li></ul>');
    $sec($com, 'text', 'Insured and bonded', '<p>Clean Beans is an LLC, insured and bonded. Ask if your building or property manager needs proof of insurance.</p>', null, ['band' => 1]);
    $sec($com, 'faq', 'Questions', '', null, ['items' => [
        $faqCost,
        ['q' => 'Can you clean after hours?', 'a' => '<p>Ask about your schedule. Work can be planned around when the business is open.</p>'],
    ]]);
    $sec($com, 'cta', 'Need your space cleaned?', $cta);
    $sec($com, 'request_form', 'Request a free quote', '', null, ['band' => 1]);

    // Post-construction
    $con = $page('post-construction', 'Post-Construction Cleaning', 'Construction', 60, null,
        'Post-construction cleaning',
        'Dust, debris and residue cleaned up after builds and remodels, so the space is ready to use.',
        'Post-Construction Cleaning in Hudson, WI | Clean Beans LLC',
        'Post-construction and remodel cleanup for builders, contractors and homeowners around Hudson, WI and the St. Croix Valley. Call (715) 222-2444.');
    $order = 0;
    $sec($con, 'text', 'When to call', '<ul><li>A new build is wrapping up and needs a final clean</li><li>A remodel left drywall dust through the whole house</li>'
        . '<li>A contractor needs the site ready for a walk-through or handoff</li><li>A commercial build-out is ready for the tenant</li></ul>'
        . '<p>Schedule after the trades are done and before the walk-through. Include the timeline with your request.</p>');
    $sec($con, 'text', 'For builders and contractors', '<p>If you build or remodel regularly, call to talk about cleaning your projects as they finish.</p>', null, ['band' => 1]);
    $sec($con, 'cta', 'Job site wrapping up?', $cta);
    $sec($con, 'request_form', 'Request a free quote', '', null, ['band' => 1]);

    // Hoarding, biohazard & complex cleans
    $spc = $page('specialty-cleaning', 'Hoarding, Biohazard & Complex Cleans', 'Specialty Cleans', 70, null,
        'Hoarding, biohazard and complex cleans',
        'Hard, personal jobs handled with care and discretion, by a cleaner certified in handling bloodborne pathogens.',
        'Hoarding & Biohazard Cleaning | Clean Beans LLC, Hudson WI',
        'Hoarding cleanups, post-mortem cleaning and bloodborne-pathogen situations handled with care and discretion around Hudson, WI. Call or text (715) 222-2444.');
    $order = 0;
    $sec($spc, 'text', 'When to call', '<ul><li>A home has become a hoarding situation and needs to be cleared and cleaned</li><li>A family needs help after an unattended death (post-mortem cleaning)</li>'
        . '<li>There\'s blood or other bodily fluid that needs to be cleaned safely</li><li>A property was left in severe condition by a tenant or occupant</li></ul>');
    $sec($spc, 'text', 'Discreet and respectful', '<p>These calls are often hard to make. You\'ll talk with Becky directly. Tell her what happened and she\'ll explain what it will take.</p>'
        . '<p>Clean Beans is certified in handling bloodborne pathogen situations.</p>', null, ['band' => 1]);
    $sec($spc, 'faq', 'Questions', '', null, ['items' => [
        ['q' => 'Can you help a family member who isn\'t local?', 'a' => '<p>Yes. Call to talk through access and arrangements.</p>'],
        ['q' => 'Is this kept confidential?', 'a' => '<p>Call Becky directly to talk it through.</p>'],
    ]]);
    $sec($spc, 'cta', 'Talk to Becky directly', '<p>Call or text. She\'ll listen and tell you what it will take.</p>');
    $sec($spc, 'request_form', 'Request a free quote', '', null, ['band' => 1]);

    // Service area
    $area = $page('service-area', 'Service Area', 'Service Area', 80, null,
        'Serving Hudson and the St. Croix Valley',
        'Based in Hudson, working on both sides of the river.',
        'Service Area: Hudson, River Falls, Stillwater & the St. Croix Valley | Clean Beans LLC',
        'Clean Beans LLC is based in Hudson, WI and cleans homes and businesses across western Wisconsin and the east Twin Cities metro. Call (715) 222-2444.');
    $order = 0;
    $sec($area, 'map', 'Where we work', '<p>Clean Beans is based in Hudson and works across western Wisconsin and the east Twin Cities metro. Recent jobs include listing prep in downtown Stillwater.</p>', null, [
        'address' => 'Hudson, WI 54016', 'lat' => 44.9747, 'lng' => -92.7568, 'radius_miles' => 40, 'zoom' => 9,
        'areas' => ['Hudson', 'North Hudson', 'River Falls', 'New Richmond', 'Baldwin', 'Roberts', 'Somerset', 'Hammond', 'Woodville', 'Prescott',
                    'Stillwater, MN', 'Bayport, MN', 'Lake Elmo, MN', 'Woodbury, MN', 'Afton, MN', 'Oakdale, MN'],
    ]);
    $sec($area, 'cta', 'Not sure if you\'re in range?', '<p>Call or text and ask. Becky will let you know.</p>');
    $sec($area, 'request_form', 'Request a free quote', '', null, ['band' => 1]);

    // About
    $about = $page('about', 'About Clean Beans', 'About', 90, null,
        'About Clean Beans',
        '22 years of cleaning homes, rentals, offices and the jobs nobody else wants.',
        'About Clean Beans LLC | Hudson, WI Cleaning Company',
        'Clean Beans LLC is a Hudson, WI cleaning company with 22 years of experience. LLC, insured, bonded and certified in bloodborne pathogens.');
    $order = 0;
    $sec($about, 'text', 'Experience that covers it all', '<p>Clean Beans LLC is Becky\'s cleaning company in Hudson, Wisconsin. In 22 years she has done move-ins and move-outs, deep cleans, residential and commercial work, rental flips, construction cleanups and office buildings.</p>'
        . '<p>She also takes on the complex jobs: hoarding situations, post-mortem cleanups and other situations involving bloodborne pathogens, which she is certified to handle.</p>'
        . '<ul><li>22 years of experience</li><li>LLC, insured and bonded</li><li>Certified in handling bloodborne pathogens</li><li>Residential, commercial and specialty work</li><li>Based in Hudson, WI</li></ul>');
    $sec($about, 'text', 'How it works', '<ol><li><strong>Reach out.</strong> Call, text or send a request with the location, the size of the space and what you need.</li>'
        . '<li><strong>Get a quote.</strong> Becky tells you what the job will take and what it will cost before anything starts.</li>'
        . '<li><strong>Get it cleaned.</strong> Clean Beans shows up and does the work, start to finish.</li></ol>', null, ['band' => 1]);
    $sec($about, 'cta', 'Ready to talk?', $cta);
    $sec($about, 'request_form', 'Request a free quote', '', null, ['band' => 1]);
}
