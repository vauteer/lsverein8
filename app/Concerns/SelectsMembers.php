<?php

namespace App\Concerns;

use App\Enums\MemberFilter;
use App\Enums\MemberSort;
use App\Enums\PaymentMethod;
use App\Models\Event;
use App\Models\Item;
use App\Models\Member;
use App\Models\Role;
use App\Models\Section;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;

/**
 * Reading "which members, seen from when, in what order" out of a request.
 *
 * Shared by the member list and the exports so that a PDF or CSV can never
 * disagree with the screen it was started from.
 */
trait SelectsMembers
{
    /**
     * How far back the year picker reaches. The key date drives every age and
     * membership calculation, so "how did the club look in 2019" is a question
     * the list can answer.
     */
    private const int YEARS_BACK = 10;

    /**
     * Everything the index needs to answer "which members, seen from when, in
     * what order".
     *
     * Setting `Member::$_keyDate` here is the load-bearing side effect: every
     * age, membership and honour calculation downstream reads it, including
     * the ones inside MemberResource.
     *
     * @return array{query: Builder<Member>, search: string, filter: string, sort: MemberSort, year: int}
     */
    private function selection(Request $request): array
    {
        $search = $request->string('search')->trim()->toString();
        $filter = $request->string('filter')->toString() ?: MemberFilter::Members->value;
        $sort = MemberSort::tryFrom($request->string('sort')->toString()) ?? MemberSort::Name;
        $year = $this->resolveYear($request);

        Member::$_keyDate = $year === now()->year
            ? now()->endOfDay()
            : Date::create($year, 12, 31)->endOfDay();

        $query = Member::query();

        if ($search !== '') {
            $query->like($search);
        }

        $this->applyFilter($filter, $query, $request);
        $sort->apply($query);

        return [
            'query' => $query,
            'search' => $search,
            'filter' => $filter,
            'sort' => $sort,
            'year' => $year,
        ];
    }

    /**
     * Narrow the query to the chosen selection.
     *
     * A filter naming a row that no longer exists, or one a non-admin is not
     * offered, falls back to the default rather than 404ing: these values live
     * in bookmarks and in the back button.
     *
     * @param  Builder<Member>  $query
     */
    private function applyFilter(string $filter, Builder $query, Request $request): void
    {
        $fixed = MemberFilter::tryFrom($filter);

        if ($fixed !== null && $fixed->isVisibleTo($request->user())) {
            $fixed->apply($query);

            return;
        }

        if (preg_match('/^(section|role|ever_role|event|item|ever_item|subscription|payment)_(.+)$/', $filter, $match) !== 1) {
            MemberFilter::Members->apply($query);

            return;
        }

        [, $kind, $key] = $match;
        $id = (int) $key;

        match ($kind) {
            'section' => $query->members()->inSections($id),
            'role' => $query->members()->hasRole($id),
            'ever_role' => $query->everRole($id),
            'event' => $query->hadEvent($id),
            'item' => $query->members()->hasItem($id),
            'ever_item' => $query->everItem($id),
            'subscription' => $query->members()->hasSubscription($id),
            'payment' => $this->applyPaymentFilter($key, $query),
        };
    }

    /**
     * @param  Builder<Member>  $query
     */
    private function applyPaymentFilter(string $key, Builder $query): void
    {
        $method = PaymentMethod::tryFrom($key);

        if ($method === null) {
            MemberFilter::Members->apply($query);

            return;
        }

        $query->members()->paymentMethods($method);
    }

    /**
     * The human name of the current selection, for an export headline or
     * filename.
     *
     * The dynamic selections are looked up in dynamicFilters() rather than
     * mapped a second time, so a heading can never word a selection
     * differently from the dropdown that produced it. An id whose row has
     * since been deleted falls back to the default, matching applyFilter().
     */
    private function filterLabel(string $filter): string
    {
        $fixed = MemberFilter::tryFrom($filter);

        if ($fixed !== null) {
            return $fixed->label();
        }

        foreach ($this->dynamicFilters() as $option) {
            if ($option['id'] === $filter) {
                return $option['name'];
            }
        }

        return MemberFilter::Members->label();
    }

    /**
     * The selections built from the club's own rows: one per section, role,
     * honour, item, subscription and payment method.
     *
     * lsverein7 had these too but never listed them — they only worked if you
     * typed `?filter=hasSection_3` by hand, or arrived from a link elsewhere
     * in the app. The section block was commented out in its source.
     *
     * @return list<array{id: string, name: string}>
     */
    private function dynamicFilters(): array
    {
        $options = [];

        foreach (Section::query()->orderBy('name')->get() as $section) {
            $options[] = ['id' => "section_{$section->id}", 'name' => __('Section: :name', ['name' => $section->name])];
        }

        foreach (Role::query()->orderBy('name')->get() as $role) {
            $options[] = ['id' => "role_{$role->id}", 'name' => __('Role: :name (current)', ['name' => $role->name])];
            $options[] = ['id' => "ever_role_{$role->id}", 'name' => __('Role: :name (ever)', ['name' => $role->name])];
        }

        foreach (Event::query()->orderBy('name')->get() as $event) {
            $options[] = ['id' => "event_{$event->id}", 'name' => __('Honour: :name', ['name' => $event->name])];
        }

        foreach (Subscription::query()->orderBy('name')->get() as $subscription) {
            $options[] = ['id' => "subscription_{$subscription->id}", 'name' => __('Subscription: :name', ['name' => $subscription->name])];
        }

        // Only where the club keeps an inventory at all, matching ItemPolicy.
        if (currentClub()->use_items) {
            foreach (Item::query()->orderBy('name')->get() as $item) {
                $options[] = ['id' => "item_{$item->id}", 'name' => __('Item: :name (current)', ['name' => $item->name])];
                $options[] = ['id' => "ever_item_{$item->id}", 'name' => __('Item: :name (ever)', ['name' => $item->name])];
            }
        }

        foreach (PaymentMethod::cases() as $method) {
            $options[] = ['id' => "payment_{$method->value}", 'name' => __('Pays by: :name', ['name' => $method->label()])];
        }

        return $options;
    }

    private function resolveYear(Request $request): int
    {
        $year = $request->integer('year') ?: now()->year;
        $current = now()->year;

        return max($current - self::YEARS_BACK, min($current, $year));
    }
}
