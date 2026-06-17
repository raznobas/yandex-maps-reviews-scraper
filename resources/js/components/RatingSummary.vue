<script setup>
import { computed } from "vue";
import { useOrganizationStore } from "../stores/organization";

const store = useOrganizationStore();

const syncLabel = computed(() => {
    const status = store.organization?.sync_status || "idle";

    return (
        {
            idle: "Не синхронизировано",
            running: "Синхронизация",
            success: "Синхронизировано",
            empty: "Отзывы не найдены",
            partial: "Частично синхронизировано",
            failed: "Ошибка синхронизации",
        }[status] || status
    );
});

const formatDate = (dateString) => {
    if (!dateString) return "Нет данных";
    return new Date(dateString).toLocaleDateString("ru-RU", {
        day: "numeric",
        month: "long",
        year: "numeric",
    });
};
</script>

<template>
    <div
        v-if="store.organization"
        class="grid gap-4 rounded-lg border border-zinc-200 bg-white p-6 shadow-sm sm:grid-cols-4"
    >
        <div class="space-y-1">
            <p class="text-xs font-semibold uppercase text-zinc-500">
                Средний рейтинг
            </p>
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-black text-zinc-950">
                    {{ store.organization.rating || "0.0" }}
                </span>
                <span class="text-sm text-zinc-500">/ 5.0</span>
            </div>
        </div>

        <div class="space-y-1">
            <p class="text-xs font-semibold uppercase text-zinc-500">
                Всего оценок
            </p>
            <p class="text-3xl font-black text-zinc-950">
                {{ store.organization.rating_count || 0 }}
            </p>
        </div>

        <div class="space-y-1">
            <p class="text-xs font-semibold uppercase text-zinc-500">
                Всего отзывов
            </p>
            <div class="flex items-baseline gap-2">
                <p class="text-3xl font-black text-zinc-950">
                    {{ store.organization.review_count || 0 }}
                </p>
                <span
                    v-if="store.organization.last_synced_at"
                    class="text-xs text-zinc-400"
                    title="Последняя синхронизация"
                >
                    Обновлено:
                    {{ formatDate(store.organization.last_synced_at) }}
                </span>
            </div>
        </div>

        <div class="space-y-1">
            <p class="text-xs font-semibold uppercase text-zinc-500">Статус</p>
            <p class="text-lg font-bold text-zinc-950">{{ syncLabel }}</p>
            <p class="text-xs text-zinc-500">
                В кэше:
                {{ store.organization.synced_reviews_count || 0 }} отзывов
            </p>
            <p
                v-if="store.organization.sync_error"
                class="text-xs text-red-600"
                :title="store.organization.sync_error"
            >
                {{ store.organization.sync_error }}
            </p>
        </div>
    </div>
</template>
