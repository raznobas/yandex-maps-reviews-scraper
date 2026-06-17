<script setup>
import { onMounted } from "vue";
import { useAuthStore } from "../stores/auth";
import { useOrganizationStore } from "../stores/organization";
import OrganizationSettings from "./OrganizationSettings.vue";
import RatingSummary from "./RatingSummary.vue";
import ReviewList from "./ReviewList.vue";

const auth = useAuthStore();
const organizationStore = useOrganizationStore();

onMounted(() => {
    organizationStore.fetchOrganization();
});
</script>

<template>
    <section class="w-full space-y-6">
        <div
            class="flex flex-col gap-4 rounded-lg border border-zinc-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="space-y-1">
                <p class="text-sm font-semibold uppercase text-zinc-500">
                    Рабочая область
                </p>
                <h1 class="text-2xl font-bold text-zinc-950">
                    {{ auth.user.name }}
                </h1>
                <p class="text-sm text-zinc-600">
                    {{ auth.user.email }}
                </p>
            </div>

            <button
                type="button"
                class="rounded-md border border-zinc-300 px-4 py-2 text-sm font-semibold text-zinc-800 transition hover:border-zinc-500 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:text-zinc-400"
                :disabled="auth.isSubmitting"
                @click="auth.logout"
            >
                {{ auth.isSubmitting ? "Выходим..." : "Выйти" }}
            </button>
        </div>

        <OrganizationSettings />

        <div v-if="organizationStore.hasOrganization" class="space-y-6">
            <RatingSummary />

            <ReviewList />
        </div>
    </section>
</template>
