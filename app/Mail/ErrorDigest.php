<?php

namespace App\Mail;

use App\ErrorLog;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The daily digest of the errors in the application log.
 *
 * @phpstan-import-type Entry from ErrorLog
 *
 * @phpstan-type Row array{time: string, level: string, message: string, color: string}
 */
class ErrorDigest extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * One colour per level, worst first. The order is also the order the
     * summary badges are shown in, so the gravest level leads.
     */
    private const array COLORS = [
        'EMERGENCY' => '#7f1d1d',
        'ALERT' => '#9f1239',
        'CRITICAL' => '#b91c1c',
        'ERROR' => '#dc2626',
    ];

    /**
     * @param  list<Entry>  $entries  the entries to list, already capped
     * @param  int  $count  how many errors the window held, listed or not
     * @param  array<string, int>  $levels  how many errors per level, over the whole window
     */
    public function __construct(
        private array $entries,
        private int $count,
        private array $levels,
        private CarbonInterface $since,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: config('app.name').": {$this->count} error(s) in the last 24 hours",
        );
    }

    /**
     * The HTML part carries the table, the text part the bare log lines: a
     * digest read in a text client is better served by the lines themselves
     * than by a table flattened into prose.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.errors',
            text: 'emails.errors-text',
            with: [
                'rows' => $this->rows(),
                'summary' => $this->summary(),
                'count' => $this->count,
                'omitted' => $this->count - count($this->entries),
                'since' => $this->since,
                'until' => Carbon::now(),
                'dashboardUrl' => route('dashboard'),
            ],
        );
    }

    /**
     * The listed entries, split into the columns of the table.
     *
     * @return list<Row>
     */
    private function rows(): array
    {
        return array_map(fn (array $entry): array => [
            'time' => $entry['date']->format('Y-m-d H:i:s'),
            'level' => $entry['level'],
            'message' => ErrorLog::message($entry),
            'color' => self::color($entry['level']),
        ], $this->entries);
    }

    /**
     * The badges above the table, gravest level first. Levels nothing was
     * logged at are left out.
     *
     * @return list<array{level: string, count: int, color: string}>
     */
    private function summary(): array
    {
        $summary = [];

        foreach (array_keys(self::COLORS) as $level) {
            if (isset($this->levels[$level])) {
                $summary[] = ['level' => $level, 'count' => $this->levels[$level], 'color' => self::color($level)];
            }
        }

        return $summary;
    }

    private static function color(string $level): string
    {
        return self::COLORS[$level] ?? self::COLORS['ERROR'];
    }
}
