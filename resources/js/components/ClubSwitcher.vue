<script setup lang="ts">
import { Form, usePage } from '@inertiajs/vue3';
import { Check, ChevronsUpDown } from '@lucide/vue';
import { computed } from 'vue';
import ClubSwitchController from '@/actions/App/Http/Controllers/ClubSwitchController';
import ClubIdentity from '@/components/ClubIdentity.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

const page = usePage();

const currentClub = computed(() => page.props.currentClub);

/**
 * Empty unless the user belongs to more than one club, so a single-club user
 * sees the plain name and never a control that cannot do anything.
 */
const switchableClubs = computed(() => page.props.switchableClubs ?? []);
</script>

<template>
    <div v-if="currentClub">
        <!-- px-3, not the button's px-2: the wordmark SVG carries ~4px of
        leading whitespace before the glyph's stroke begins, so the avatar
        needs the same 12px inset to line up with it. -->
        <div
            v-if="switchableClubs.length === 0"
            class="flex items-center gap-2 px-3 py-1"
        >
            <ClubIdentity :club="currentClub" />
        </div>

        <DropdownMenu v-else>
            <DropdownMenuTrigger as-child>
                <button
                    type="button"
                    class="flex w-full items-center gap-2 rounded-md px-3 py-1 text-left hover:bg-sidebar-accent"
                    :aria-label="$t('Switch club')"
                >
                    <ClubIdentity :club="currentClub" />
                    <ChevronsUpDown
                        class="ml-auto size-4 shrink-0 text-sidebar-foreground/50 group-data-[collapsible=icon]:hidden"
                    />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" class="w-56">
                <DropdownMenuLabel>{{ $t('Switch club') }}</DropdownMenuLabel>
                <Form
                    v-for="club in switchableClubs"
                    :key="club.id"
                    v-bind="ClubSwitchController.store.form(club.id)"
                >
                    <DropdownMenuItem
                        as="button"
                        type="submit"
                        class="w-full"
                        :disabled="club.current"
                    >
                        <Check
                            :class="[
                                'size-4',
                                club.current ? 'opacity-100' : 'opacity-0',
                            ]"
                        />
                        <span class="truncate">{{ club.name }}</span>
                    </DropdownMenuItem>
                </Form>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
