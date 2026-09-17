<?php

declare(strict_types=1);

namespace WebScraping\VideoMerge\Support;

/**
 * Direct extraction of post_slug() from the original function.php.
 *
 * Every character map, regex, and operation order is preserved exactly —
 * including a quirk that looks like a bug (see the PRESERVED QUIRK note
 * below). This is a behavior-preserving refactor of *where* the logic
 * lives, not a rewrite of the slug rules themselves.
 */
final class Slugger
{
    /**
     * Cyrillic source characters. Despite the original docblock saying
     * "russion to english", this is actually a mixed Serbian/Macedonian
     * + Russian Cyrillic table (е.g. "Lj"/"Nj"/"dž" are Serbian Latin
     * digraphs, not Russian transliteration).
     *
     * @var array<int, string>
     */
    private const CYRILLIC = [
        'Љ', 'Њ', 'Џ', 'џ', 'ш', 'ђ', 'ч', 'ћ', 'ж', 'љ', 'њ', 'Ш', 'Ђ', 'Ч', 'Ћ', 'Ж', 'Ц', 'ц',
        'а', 'б', 'в', 'г', 'д', 'е', 'ё', 'ж', 'з', 'и', 'й', 'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф',
        'х', 'ц', 'ч', 'ш', 'щ', 'ъ', 'ы', 'ь', 'э', 'ю', 'я', 'А', 'Б', 'В', 'Г', 'Д', 'Е', 'Ё', 'Ж', 'З', 'И', 'Й',
        'К', 'Л', 'М', 'Н', 'О', 'П', 'Р', 'С', 'Т', 'У', 'Ф', 'Х', 'Ц', 'Ч', 'Ш', 'Щ', 'Ъ', 'Ы', 'Ь', 'Э', 'Ю', 'Я',
    ];

    /** Latin replacements, positionally matched to CYRILLIC. @var array<int, string> */
    private const LATIN = [
        'Lj', 'Nj', 'Dž', 'dž', 'š', 'đ', 'č', 'ć', 'ž', 'lj', 'nj', 'Š', 'Đ', 'Č', 'Ć', 'Ž', 'C',
        'c', 'a', 'b', 'v', 'g', 'd', 'e', 'io', 'zh', 'z', 'i', 'y', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u',
        'f', 'h', 'ts', 'ch', 'sh', 'sht', 'a', 'i', 'y', 'e', 'yu', 'ya', 'A', 'B', 'V', 'G', 'D', 'E', 'Io', 'Zh', 'Z',
        'I', 'Y', 'K', 'L', 'M', 'N', 'O', 'P', 'R', 'S', 'T', 'U', 'F', 'H', 'Ts', 'Ch', 'Sh', 'Sht', 'A', 'I', 'Y', 'e', 'Yu', 'Ya',
    ];

    /**
     * Transliterate Cyrillic to Latin, then reduce to a filename-safe slug.
     *
     * PRESERVED QUIRK: the first regex, `/[^A-Za-z0-9 -.]/`, has an
     * unescaped hyphen between a space and a period inside the character
     * class. That forms a *range* (space through period — 0x20-0x2E in
     * ASCII), not a literal "space, hyphen, or period" allowlist as the
     * pattern most likely intended. Confirmed by checking the actual byte
     * range, not guessed: it means punctuation like `! " # $ % & ' ( ) *
     * + ,` also survives this step (they all fall inside 0x20-0x2E). The
     * second regex mops up several of those survivors anyway, which is
     * probably why this was never noticed. Left exactly as-is — fixing a
     * regex that changes what characters get slugged out is a behavior
     * change, not a refactor.
     *
     * PRESERVED QUIRK #2 — four Cyrillic letters silently vanish instead
     * of transliterating: ш/Ш, ч/Ч, and ж/Ж each appear TWICE in CYRILLIC
     * (once in the leading Serbian/Macedonian block, once in the later
     * Russian block). str_replace() with array arguments applies search
     * terms in order against the string it has already partially
     * rewritten, so the FIRST occurrence of each duplicated key wins —
     * here, the Serbian entries (indices 4-15), which map to the
     * diacritic Latin letters š/č/ž/Š/Č/Ž, not the Russian block's
     * intended "sh"/"ch"/"zh" (indices 42-43, 58-ish — dead code,
     * reachable value already replaced by then). Those diacritic letters
     * are multi-byte UTF-8 and this class's regex operates byte-wise
     * with no /u modifier, so every byte of š/č/ž fails the
     * `[^A-Za-z0-9 -.]` allowlist and gets stripped completely — the
     * letter disappears rather than becoming "sh"/"ch"/"zh". ц/Ц have
     * the same duplicate-key shadowing but land on plain ASCII 'c'/'C'
     * (the Serbian mapping), so they survive, just as "c" instead of the
     * Russian block's intended "ts"/"Ts". Verified empirically, not
     * guessed: slug('школа') === 'kola' (the 'ш' vanishes; see
     * tests/Unit/SluggerTest.php). Left exactly as-is for the same
     * reason as quirk #1 above.
     */
    public static function slug(string $value): string
    {
        $value = str_replace(self::CYRILLIC, self::LATIN, $value);

        $value = preg_replace(
            ['/[^A-Za-z0-9 -.]/', '/[!@#$%:\&*(),\' -]+/', '/^-|-$/'],
            ['', '-', ''],
            $value
        );

        return strtolower((string) $value);
    }
}
