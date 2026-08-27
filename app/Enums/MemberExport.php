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
    case BlsvExcel = 'blsv-xlsx';
    case Blsv = 'blsv';

    public function label(): string
    {
        return match ($this) {
            self::Addresses => __('PDF (addresses)'),
            self::Roles => __('PDF (roles)'),
            self::Csv => __('CSV'),
            self::VCard => __('vCard'),
            self::BlsvExcel => __('BLSV (Excel)'),
            self::Blsv => __('BLSV (CSV)'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Addresses => __('An address list to print'),
            self::Roles => __('Who holds which role'),
            self::Csv => __('For a spreadsheet'),
            self::VCard => __('Contacts for a phone or mail client'),
            self::BlsvExcel, self::Blsv => __('For a supplementary member report'),
        };
    }

    /**
     * What the browser saves the download as, minus the filename itself.
     */
    public function extension(): string
    {
        return match ($this) {
            self::Addresses, self::Roles => 'pdf',
            self::Csv, self::Blsv => 'csv',
            self::VCard => 'vcf',
            self::BlsvExcel => 'xlsx',
        };
    }

    public function contentType(): string
    {
        return match ($this) {
            self::Addresses, self::Roles => 'application/pdf',
            // text/csv would be the modern spelling; this is what lsverein7
            // sent and what the spreadsheet on the other end expects.
            self::Csv, self::Blsv => 'text/comma-separated-values',
            self::VCard => 'text/vcard',
            self::BlsvExcel => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };
    }

    /**
     * Whether this format may be handed out for the given selection.
     *
     * Only the two BLSV files say no. They carry a Spartenkennzeichen per
     * line, which exists only for a club that reports to the association, and
     * a Nachmeldung has to carry *every* member — the association reads the
     * file as the club's whole membership, not as a delta. Offering them for
     * "Ex-Mitglieder" or a single section would produce a file that looks
     * submittable and would under-report the club.
     * Enforced in MemberExportController as well as hidden in the menu.
     */
    public function isAvailableFor(string $filter): bool
    {
        if (! $this->isBlsv()) {
            return true;
        }

        return currentClub()->blsv_member && $filter === MemberFilter::Members->value;
    }

    /**
     * Whether this format is one of the two the BLSV accepts. They carry the
     * same rows in the same column order; only the container differs.
     */
    public function isBlsv(): bool
    {
        return $this === self::BlsvExcel || $this === self::Blsv;
    }

    /**
     * The formats offered for a selection, as {id, name} options for the
     * frontend.
     *
     * @return list<array{id: string, name: string, description: string}>
     */
    public static function optionsFor(string $filter): array
    {
        return array_values(array_map(
            fn (self $format): array => [
                'id' => $format->value,
                'name' => $format->label(),
                'description' => $format->description(),
            ],
            array_filter(self::cases(), fn (self $format): bool => $format->isAvailableFor($filter))
        ));
    }
}
