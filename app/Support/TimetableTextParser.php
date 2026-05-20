<?php

namespace App\Support;

/**
 * Parses a plain-text weekly timetable (the format reps share in WhatsApp /
 * email) into structured slot rows that the importer can match against
 * courses, lecturers and venues.
 *
 * Accepted shape (very tolerant — extra blank lines, bullets, dashes, tabs
 * and a TIMETABLE heading are all stripped before parsing):
 *
 *     TIMETABLE
 *
 *     MONDAY
 *     9AM - 11AM
 *     BIT236 - SOFTWARE ENGINEERING
 *     MR. HILARY ACKAH-ARTHUR
 *     VENUE : OBTF 7
 *
 *     TUESDAY
 *     1 PM - 3 PM
 *     DTM202 - PRINCIPLES OF MANAGEMENT
 *     MR. AMOS KWASI AMOFA
 *     VENUE : OBFF 6
 */
final class TimetableTextParser
{
    private const DAYS = [
        'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
    ];

    /**
     * @return array{
     *   slots: list<array{
     *     day: string,
     *     start_time: string,
     *     end_time: string,
     *     course_code: ?string,
     *     course_name: ?string,
     *     lecturer: ?string,
     *     venue: ?string,
     *     raw: string
     *   }>,
     *   warnings: list<string>
     * }
     */
    public static function parse(string $input): array
    {
        $warnings = [];
        $slots = [];

        $lines = self::normaliseLines($input);
        $currentDay = null;
        $buffer = [];

        $flush = function () use (&$buffer, &$currentDay, &$slots, &$warnings): void {
            if ($currentDay === null || $buffer === []) {
                $buffer = [];
                return;
            }
            $slot = self::parseSlotLines($buffer, $currentDay);
            if ($slot !== null) {
                $slots[] = $slot;
            } else {
                $warnings[] = 'Skipped a block under '.$currentDay.': "'.implode(' | ', $buffer).'"';
            }
            $buffer = [];
        };

        foreach ($lines as $line) {
            $dayKey = self::dayKey($line);
            if ($dayKey !== null) {
                $flush();
                $currentDay = ucfirst($dayKey);
                continue;
            }

            if (strcasecmp($line, 'TIMETABLE') === 0) {
                continue;
            }

            if ($line === '') {
                $flush();
                continue;
            }

            if (self::looksLikeTimeRange($line)) {
                if ($buffer !== []) {
                    $flush();
                }
            }
            $buffer[] = $line;
        }
        $flush();

        return ['slots' => $slots, 'warnings' => $warnings];
    }

    /**
     * Extract readable text from an uploaded .docx file. Returns null on failure.
     */
    public static function extractDocxText(string $path): ?string
    {
        if (! is_readable($path)) {
            return null;
        }

        if (! class_exists(\ZipArchive::class)) {
            return null;
        }

        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            return null;
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            return null;
        }

        // Convert paragraph and line breaks into real newlines, then strip the
        // remaining XML tags so the result reads like the plain-text format.
        $xml = preg_replace('/<w:p[^>]*>/u', "\n", (string) $xml) ?? $xml;
        $xml = preg_replace('/<w:br\\s*\\/?\\s*>/u', "\n", (string) $xml) ?? $xml;
        $xml = preg_replace('/<w:tab\\s*\\/?\\s*>/u', "\t", (string) $xml) ?? $xml;
        $text = strip_tags((string) $xml);

        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * @return list<string>
     */
    private static function normaliseLines(string $input): array
    {
        $input = str_replace(["\r\n", "\r"], "\n", $input);
        $input = preg_replace('/^\xEF\xBB\xBF/u', '', $input) ?? $input;
        $input = preg_replace('/[\x{00A0}\x{2007}\x{202F}]/u', ' ', $input) ?? $input;

        $out = [];
        foreach (explode("\n", $input) as $raw) {
            $line = trim($raw);
            $line = ltrim($line, "-•*·\t ");
            $line = trim($line);
            $out[] = $line;
        }

        return $out;
    }

    private static function dayKey(string $line): ?string
    {
        $clean = strtolower(trim(preg_replace('/[^A-Za-z]/u', '', $line) ?? $line));
        return in_array($clean, self::DAYS, true) ? $clean : null;
    }

    private static function looksLikeTimeRange(string $line): bool
    {
        return (bool) preg_match(self::timeRangeRegex(), $line);
    }

    private static function timeRangeRegex(): string
    {
        return '/(\d{1,2})\s*(?::\s*(\d{1,2}))?\s*([AaPp][Mm]?)?\s*[-–—to]+\s*(\d{1,2})\s*(?::\s*(\d{1,2}))?\s*([AaPp][Mm]?)?/u';
    }

    /**
     * @param  list<string>  $lines
     * @return array{
     *   day: string,
     *   start_time: string,
     *   end_time: string,
     *   course_code: ?string,
     *   course_name: ?string,
     *   lecturer: ?string,
     *   venue: ?string,
     *   raw: string
     * }|null
     */
    private static function parseSlotLines(array $lines, string $day): ?array
    {
        $timeLine = null;
        $venueLine = null;
        $rest = [];
        foreach ($lines as $l) {
            if ($timeLine === null && self::looksLikeTimeRange($l)) {
                $timeLine = $l;
                continue;
            }
            if (stripos($l, 'venue') === 0) {
                $venueLine = $l;
                continue;
            }
            $rest[] = $l;
        }

        if ($timeLine === null) {
            return null;
        }
        $times = self::extractTimeRange($timeLine);
        if ($times === null) {
            return null;
        }

        $courseLine = $rest[0] ?? null;
        $lecturerLine = $rest[1] ?? null;

        [$code, $name] = self::splitCourseLine($courseLine);

        return [
            'day' => $day,
            'start_time' => $times[0],
            'end_time' => $times[1],
            'course_code' => $code,
            'course_name' => $name,
            'lecturer' => $lecturerLine !== null ? self::cleanLecturer($lecturerLine) : null,
            'venue' => $venueLine !== null ? self::cleanVenue($venueLine) : null,
            'raw' => implode("\n", $lines),
        ];
    }

    /**
     * @return array{0:string,1:string}|null Tuple of H:i strings.
     */
    private static function extractTimeRange(string $line): ?array
    {
        if (! preg_match(self::timeRangeRegex(), $line, $m)) {
            return null;
        }
        $startHour = (int) $m[1];
        $startMin = $m[2] !== '' ? (int) $m[2] : 0;
        $startMer = strtoupper((string) ($m[3] ?? ''));
        $endHour = (int) $m[4];
        $endMin = $m[5] !== '' ? (int) $m[5] : 0;
        $endMer = strtoupper((string) ($m[6] ?? ''));

        if ($endMer === '' && $startMer !== '') {
            $endMer = $startMer;
        }
        if ($startMer === '' && $endMer !== '') {
            $startMer = $endMer;
        }

        $start = self::toMinutes($startHour, $startMin, $startMer);
        $end = self::toMinutes($endHour, $endMin, $endMer);
        if ($start === null || $end === null) {
            return null;
        }
        if ($end <= $start) {
            // Best-effort: swing PM if the end appears to wrap (e.g. 1-3 with no
            // meridiem means afternoon-afternoon, but 11-1 means 11am-1pm).
            if ($endMer === 'AM' || $endMer === 'A') {
                $end += 12 * 60;
            } else {
                return null;
            }
        }

        $fmt = fn (int $m) => sprintf('%02d:%02d', intdiv($m, 60) % 24, $m % 60);
        return [$fmt($start), $fmt($end)];
    }

    private static function toMinutes(int $hour, int $minute, string $meridiem): ?int
    {
        if ($hour < 0 || $minute < 0 || $minute > 59) {
            return null;
        }
        $meridiem = strtoupper($meridiem);
        if ($meridiem === 'A' || $meridiem === 'AM') {
            $hour = $hour === 12 ? 0 : $hour;
        } elseif ($meridiem === 'P' || $meridiem === 'PM') {
            $hour = $hour === 12 ? 12 : $hour + 12;
        }
        if ($hour < 0 || $hour > 23) {
            return null;
        }

        return $hour * 60 + $minute;
    }

    /**
     * @return array{0:?string,1:?string} [code, name]
     */
    private static function splitCourseLine(?string $line): array
    {
        if ($line === null || trim($line) === '') {
            return [null, null];
        }
        $clean = trim(preg_replace('/\s+/u', ' ', $line) ?? $line);

        if (preg_match('/^([A-Za-z]{2,6}\\s?-?\\s?\\d{2,4}[A-Za-z]?)\\s*[-:–—]\\s*(.+)$/u', $clean, $m)) {
            return [strtoupper(preg_replace('/\\s+/u', '', $m[1]) ?? $m[1]), trim($m[2])];
        }
        if (preg_match('/^([A-Za-z]{2,6}\\s?-?\\s?\\d{2,4}[A-Za-z]?)\\b\\s*(.*)$/u', $clean, $m)) {
            $name = trim($m[2]);
            return [strtoupper(preg_replace('/\\s+/u', '', $m[1]) ?? $m[1]), $name === '' ? null : $name];
        }

        return [null, $clean];
    }

    private static function cleanLecturer(string $line): string
    {
        $line = trim(preg_replace('/\\s+/u', ' ', $line) ?? $line);
        return trim($line, " \t-:•·");
    }

    private static function cleanVenue(string $line): string
    {
        $line = preg_replace('/^\\s*venue\\s*[:\\-]\\s*/iu', '', $line) ?? $line;
        return trim(preg_replace('/\\s+/u', ' ', $line) ?? $line);
    }
}
