<?php

declare(strict_types=1);

namespace WebScraping\VideoMerge\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WebScraping\VideoMerge\Support\Slugger;

final class SluggerTest extends TestCase
{
    public function testSlugifiesASimpleCourseName(): void
    {
        self::assertSame('msk-javascript-bootcamp', Slugger::slug('MSK JavaScript Bootcamp'));
    }

    public function testStripsPunctuationOutsideTheAllowedRange(): void
    {
        // Confirmed against the original post_slug() with a real
        // side-by-side comparison harness during the refactor (see
        // docs/refactor-notes.md) — this is a golden case, not a guess.
        self::assertSame('udemy-discord-clone-learn-mern-stack-with-webrtc-and-socketio-2022-1', Slugger::slug(
            'Udemy - Discord Clone - Learn MERN Stack with WebRTC and SocketIO 2022-1'
        ));
    }

    public function testCyrillicShAndChAndZhVanishInsteadOfTransliterating(): void
    {
        // NOT a bug introduced by this refactor — see Slugger's
        // "PRESERVED QUIRK #2" docblock. 'ш' hits an earlier duplicate
        // key in the transliteration table, becomes the diacritic 'š',
        // and that non-ASCII letter then gets stripped entirely by the
        // byte-wise regex — so "shkola" never appears; the result is
        // "kola", not "shkola". Confirmed against the original
        // post_slug() with a real comparison harness, not guessed.
        self::assertSame('kola', Slugger::slug('школа'));
    }

    public function testTrimsALeadingOrTrailingHyphenButOnlyOne(): void
    {
        self::assertSame('a-b', Slugger::slug('-a-b-'));
    }

    public function testEmptyStringStaysEmpty(): void
    {
        self::assertSame('', Slugger::slug(''));
    }
}
