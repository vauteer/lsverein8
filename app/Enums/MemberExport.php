<?php

namespace App\Enums;

/**
 * The formats the member list can be handed out in. Each one exports the
 * selection currently on screen, read against the same key date.
 *
 * Replaces `Member::EXPORT_FORMATS`, a const array of untranslated labels that
 * nothing ever read and that was missing the roles PDF entirely.
 */
enum MemberExport: string
{
    case Addresses = 'pdf';
    case Roles = 'roles';
    case Csv = 'csv';
    case VCard = 'vcf';

    public function label(): string
    {
        return match ($this) {
            self::Addresses => __('PDF (addresses)'),
            self::Roles => __('PDF (roles)'),
            self::Csv => __('CSV'),
            self::VCard => __('vCard'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Addresses => __('An address list to print'),
            self::Roles => __('Who holds which role'),
            self::Csv => __('For a spreadsheet'),
            self::VCard => __('Contacts for a phone or mail client'),
        };
    }

    /**
     * What the browser saves the download as, minus the filename itself.
     */
    public function extension(): string
    {
        return match ($this) {
            self::Addresses, self::Roles => 'pdf',
            self::Csv => 'csv',
            self::VCard => 'vcf',
        };
    }

    public function contentType(): string
    {
        return match ($this) {
            self::Addresses, self::Roles => 'application/pdf',
            // text/csv would be the modern spelling; this is what lsverein7
            // sent and what the spreadsheet on the other end expects.
            self::Csv => 'text/comma-separated-values',
            self::VCard => 'text/vcard',
        };
    }

    /**
     * The offered formats, as {id, name} options for the frontend.
     *
     * @return list<array{id: string, name: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $format): array => [
                'id' => $format->value,
                'name' => $format->label(),
                'description' => $format->description(),
            ],
            self::cases()
        );
    }
}
