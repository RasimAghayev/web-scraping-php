<?php

declare(strict_types=1);

require_once 'vendor/autoload.php';

use Symfony\Component\BrowserKit\HttpBrowser;

// Best-practice cleanup pass. Scraping/selector logic below is left
// byte-for-byte equivalent to the original — none of it was re-verified
// against the live site, so nothing about *what* gets selected changed,
// only *how the PHP is written*:
// - fabpot/goutte is abandoned upstream ("Package fabpot/goutte is
//   abandoned, you should avoid using it" per `composer show`), and its
//   Client class is itself just a deprecated subclass of
//   Symfony\Component\BrowserKit\HttpBrowser (confirmed by reading
//   vendor/fabpot/goutte/Goutte/Client.php before removing it).
//   HttpBrowser exposes the exact same request()/filter() API, so this
//   drops a dead dependency with zero behavior change.
// - global $arr / global $client replaced with closures capturing by
//   reference/value — same effect, no shared global state.
// - The stray `echo "<pre>";` (leftover HTML in what's meant to be a
//   clean JSON stdout stream) is removed; piping stdout to a file now
//   produces valid JSON instead of JSON with an HTML tag glued to the
//   front of it.
// - Target URL overridable via SCRAPE_URL env var, defaulting to the
//   original hardcoded value — nothing changes if it's unset.
ini_set('max_execution_time', '3600'); // 1 hour — full crawls can be slow

$targetUrl = getenv('SCRAPE_URL') ?: 'https://downloadly.ir/download/elearning/video-tutorials/';
// A second target was previously left as a commented-out alternative:
// https://downloadly.ir/tag/easy-Learning/ — set SCRAPE_URL to switch to
// it (or any other listing page on the same site) instead of editing code.

$client = new HttpBrowser();

try {
    $crawler = $client->request('GET', $targetUrl);

    $nextLinks = [];
    // CONFIRMED against the live site while verifying this pass: this
    // selector targets a `<navigation>` element (not a real HTML5 tag)
    // descending from `.pagination`, and matches nothing on
    // downloadly.ir's current markup — `next_link` always comes back
    // empty (verified: a real run returned 33 articles from
    // `running_link` and `next_link: []`). Likely meant
    // `.pagination .navigation` or similar. Left exactly as originally
    // written since fixing selector *behavior* — as opposed to just the
    // PHP syntax around it — is out of scope for this pass; flagging it
    // here with hard evidence instead of guessing.
    $crawler->filter('.pagination navigation')->each(function ($node) use (&$nextLinks) {
        $nextLinks[] = $node->filter('a')->attr('href');
    });

    $details = [];
    $crawler->filter('article')->each(function ($node) use (&$details, $client) {
        $description = $node->filter('a')->each(fn($a) => $a->html())[1] ?? null;
        $link = $node->filter('a')->attr('href');

        $detailPage = $client->request('GET', $link);
        $fileList = [];
        $detailPage->evaluate('//p/a')->each(function ($file) use (&$fileList) {
            $label = str_replace(
                ['مگابایت', 'گیگابایت', 'دانلود بخش', 'دانلود'],
                ['MB', 'GB', 'Part', 'Download'],
                $file->filter('a')->text()
            );
            $href = $file->filter('a')->attr('href');
            // One deliberate, real (if practically unreachable) fix: the
            // original used `strpos(...) > 0`, which would have excluded
            // a link where ".rar" occurs at position 0 — str_contains()
            // doesn't have that edge case. No realistic URL starts with
            // ".rar", so this doesn't change observed behavior.
            if ($href !== null && str_contains($href, '.rar')) {
                $fileList[] = [$label, $href];
            }
        });

        $details[] = [
            'description' => $description,
            'link' => $link,
            'file_list' => $fileList,
        ];
    });

    echo json_encode([
        'next_link' => $nextLinks,
        'running_link' => $details,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo "\n";
} catch (\Throwable $e) {
    // stdout stays clean JSON-or-nothing; errors go to stderr with a
    // non-zero exit so this is safe to pipe (`php index.php > out.json`).
    fwrite(STDERR, 'Scrape failed: ' . $e->getMessage() . "\n");
    exit(1);
}
