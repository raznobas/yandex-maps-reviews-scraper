<script setup>
import { useOrganizationStore } from "../stores/organization";

const store = useOrganizationStore();

const submit = async () => {
    try {
        await store.saveOrganization();
    } catch (e) {
        // Handled by store errors
    }
};
</script>

<template>
    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm">
        <div class="mb-6">
            <h2 class="text-lg font-bold text-zinc-950">
                Настройки организации
            </h2>
            <p class="text-sm text-zinc-600">
                Вставьте ссылку на карточку организации в Яндекс.Картах, чтобы
                загрузить рейтинг и отзывы.
            </p>
        </div>

        <div
            v-if="store.isLoading"
            class="flex items-center justify-center py-4"
        >
            <span class="text-sm text-zinc-500 italic"
                >Загружаем настройки...</span
            >
        </div>

        <div
            v-else-if="store.loadError"
            class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700"
        >
            {{ store.loadError }}
        </div>

        <form v-else @submit.prevent="submit" class="space-y-4">
            <div class="space-y-1">
                <label
                    for="source_url"
                    class="text-sm font-semibold text-zinc-700"
                >
                    Ссылка на Яндекс.Карты
                </label>
                <input
                    id="source_url"
                    v-model="store.sourceUrl"
                    type="url"
                    placeholder="https://yandex.ru/maps/org/slug/id/"
                    class="block w-full rounded-md border border-zinc-300 bg-white px-4 py-2 text-zinc-950 shadow-sm transition focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 disabled:bg-zinc-50 disabled:text-zinc-500"
                    :disabled="store.isSaving"
                    required
                />
                <p v-if="store.errors.source_url" class="text-sm text-red-600">
                    {{ store.errors.source_url[0] }}
                </p>
            </div>

            <div v-if="store.organization" class="rounded-md bg-zinc-50 p-3">
                <p class="text-xs font-semibold uppercase text-zinc-500">
                    Активная организация:
                </p>
                <p class="mt-1 break-all text-sm text-zinc-700">
                    {{ store.organization.normalized_url }}
                </p>
            </div>

            <p v-if="store.saveError" class="text-sm text-red-600">
                {{ store.saveError }}
            </p>

            <div class="flex items-center gap-4">
                <button
                    type="submit"
                    class="rounded-md bg-zinc-950 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-zinc-400"
                    :disabled="store.isSaving"
                >
                    {{ store.isSaving ? "Сохраняем..." : "Сохранить" }}
                </button>

                <span
                    v-if="store.statusMessage"
                    class="text-sm font-medium text-emerald-600"
                >
                    {{ store.statusMessage }}
                </span>
            </div>
        </form>
    </div>
</template>
