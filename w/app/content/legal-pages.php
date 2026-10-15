<?php
/**
 * Privacy Policy and Text Message (SMS) Terms, added by content rev 4.
 * Written to cover what US carriers check for A2P 10DLC registration: what is collected, that phone numbers
 * and text opt-in data are never sold or shared for marketing, message frequency, "Msg & data rates may apply",
 * HELP and STOP. Editable afterwards in admin > Pages. Becky should read both before launch.
 */
$co = 'Clean Beans LLC';
$phone = '(715) 222-2444';
$updated = 'October 15, 2026';
return [
    'privacy' => [
        'title' => 'Privacy Policy',
        'intro' => 'How ' . $co . ' handles the information you give us through this website, by email and by text message.',
        'body' => <<<HTML
<p><em>Last updated $updated</em></p>
<h2>What we collect</h2>
<p>When you request a quote or contact us, we collect what you choose to give us: your name, phone number, email address,
the address or town of the job, and the details you write. If you text or email us, we keep those messages so we can
answer you and follow up on your job.</p>
<p>Like most websites, our server keeps short technical logs (such as your IP address and browser type) to keep the
site secure and stop spam. We do not use advertising trackers on this website.</p>
<h2>How we use it</h2>
<ul>
<li>To answer your request, give you a quote, schedule and do the work.</li>
<li>To contact you by phone, email or, if you agreed, by text message about your request or job.</li>
<li>To keep records of the work we do for you.</li>
</ul>
<h2>Text messages</h2>
<p>If you agree to receive text messages, we use your phone number only to text you about your request and your
jobs with $co. <strong>We do not sell, rent or share your phone number or your text message opt-in with anyone
for their marketing.</strong> Text messaging originator opt-in data and consent are not shared with any third parties,
except the text message service provider that delivers our messages. See our <a href="sms-terms">Text Message Terms</a>.</p>
<h2>Who sees your information</h2>
<p>Only $co and the companies that run this website and deliver our email and text messages for us (our web host and
our text message provider). They may use it only to provide that service to us. We do not sell your personal information.
We may share information if the law requires it.</p>
<h2>How long we keep it</h2>
<p>We keep your messages and job records for as long as we need them for your work, our records and the law.
You can ask us to delete your information at any time.</p>
<h2>Your choices</h2>
<p>You can ask to see, correct or delete the information we hold about you, or tell us to stop contacting you.
Reply STOP to any text to stop texts. Call or text $phone or use the quote form on this website.</p>
<h2>Contact</h2>
<p>$co, Hudson, Wisconsin. Phone $phone.</p>
HTML,
    ],
    'sms-terms' => [
        'title' => 'Text Message Terms',
        'intro' => 'Terms for text messages (SMS) from ' . $co . '.',
        'body' => <<<HTML
<p><em>Last updated $updated</em></p>
<h2>What you will get</h2>
<p>When you tick "Text me about this request" on our quote form, or text our business number, you agree to receive
text messages from $co about your quote request, scheduling and your cleaning jobs, including replies from Becky.
These are conversational and service messages, not marketing. Consent is not a condition of buying any service.</p>
<h2>How often</h2>
<p>Message frequency varies with your request; most customers get a few messages per job.</p>
<h2>Cost</h2>
<p><strong>Message and data rates may apply.</strong> Check your mobile plan for details.</p>
<h2>Help and stopping texts</h2>
<p>Reply <strong>HELP</strong> for help, or call $phone.<br>
Reply <strong>STOP</strong> at any time to stop receiving texts. You will get one message confirming you have been
unsubscribed and no further texts after that. Reply <strong>START</strong> to receive texts again.</p>
<h2>Carriers</h2>
<p>Mobile carriers are not liable for delayed or undelivered messages.</p>
<h2>Privacy</h2>
<p>We do not sell or share your phone number or your opt-in for marketing. See our <a href="privacy">Privacy Policy</a>.</p>
HTML,
    ],
];
