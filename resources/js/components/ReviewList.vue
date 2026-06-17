<script setup>
import { computed } from "vue";
import { useOrganizationStore } from "../stores/organization";

const store = useOrganizationStore();

const formatDate = (dateString) => {
    if (!dateString) return "Нет данных";
    return new Date(dateString).toLocaleDateString("ru-RU", {
        day: "numeric",
        month: "short",
        year: "numeric",
    });
};

const changePage = (page) => {
    if (page < 1 || page > store.reviewsMeta.last_page) return;
    store.fetchReviews(page);
};

const syncReviews = async () => {
    try {
        await store.syncOrganization();
    } catch (e) {
        // Store exposes syncError near this control.
    }
};

const authorInitial = (authorName) => {
    return authorName?.trim().charAt(0).toUpperCase() || "?";
};

const visiblePages = computed(() => {
    const currentPage = store.reviewsMeta.current_page || 1;
    const lastPage = store.reviewsMeta.last_page || 1;
    const start = Math.max(1, currentPage - 2);
    const end = Math.min(lastPage, start + 4);
    const adjustedStart = Math.max(1, end - 4);

    return Array.from(
        { length: end - adjustedStart + 1 },
        (_, index) => adjustedStart + index,
    );
});

const pageRange = computed(() => {
    const currentPage = store.reviewsMeta.current_page || 1;
    const perPage = store.reviewsMeta.per_page || 50;
    const total = store.reviewsMeta.total || store.reviews.length;
    const from = store.reviewsMeta.from || (currentPage - 1) * perPage + 1;
    const to = store.reviewsMeta.to || Math.min(currentPage * perPage, total);

    return { from, to, total };
});
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-bold text-zinc-950">Отзывы</h3>
            <button
                @click="syncReviews"
                class="text-sm font-medium text-zinc-600 hover:text-zinc-950 disabled:opacity-50"
                :disabled="store.isLoadingReviews || store.isSyncing"
            >
                {{
                    store.isSyncing || store.isLoadingReviews
                        ? "Обновляем..."
                        : "Обновить"
                }}
            </button>
        </div>

        <div
            v-if="store.syncError || store.reviewsError"
            class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700"
        >
            {{ store.syncError || store.reviewsError }}
        </div>

        <div
            v-else-if="store.isSyncing"
            class="rounded-md border border-sky-200 bg-sky-50 p-3 text-sm text-sky-800"
        >
            Идет синхронизация с Яндекс.Картами. Это может занять до пары минут.
        </div>

        <div
            v-if="store.isLoadingReviews && !store.reviews.length"
            class="py-12 text-center"
        >
            <span class="text-zinc-500 italic">Загружаем отзывы...</span>
        </div>

        <div
            v-else-if="!store.reviews.length"
            class="rounded-lg border border-dashed border-zinc-300 py-12 text-center"
        >
            <p class="text-zinc-500">
                Для этой организации отзывы пока не найдены.
            </p>
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="review in store.reviews"
                :key="review.id"
                class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-zinc-100 text-zinc-400 font-bold"
                        >
                            {{ authorInitial(review.author_name) }}
                        </div>
                        <div>
                            <p class="text-sm font-bold text-zinc-950">
                                {{ review.author_name }}
                            </p>
                            <p class="text-xs text-zinc-500">
                                {{ formatDate(review.publish_date) }}
                            </p>
                        </div>
                    </div>
                    <div
                        class="flex items-center gap-1 rounded bg-zinc-950 px-2 py-1 text-xs font-bold text-white"
                    >
                        <span>{{ review.rating }}</span>
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            class="h-3 w-3"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401Z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </div>
                </div>
                <div
                    class="mt-3 text-sm leading-relaxed text-zinc-700 whitespace-pre-wrap"
                >
                    {{ review.text }}
                </div>
            </div>

            <div
                class="flex flex-col gap-3 border-t border-zinc-200 pt-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <span class="text-xs text-zinc-500">
                    Показаны {{ pageRange.from }}-{{ pageRange.to }} из
                    {{ pageRange.total }} сохраненных отзывов
                </span>

                <div class="flex items-center gap-1">
                    <button
                        @click="changePage(store.reviewsMeta.current_page - 1)"
                        :disabled="
                            store.reviewsMeta.current_page === 1 ||
                            store.isLoadingReviews
                        "
                        class="h-8 rounded border border-zinc-300 px-3 text-xs font-semibold text-zinc-700 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-30"
                    >
                        Назад
                    </button>

                    <button
                        v-for="page in visiblePages"
                        :key="page"
                        @click="changePage(page)"
                        :disabled="
                            page === store.reviewsMeta.current_page ||
                            store.isLoadingReviews
                        "
                        class="h-8 min-w-8 rounded border px-2 text-xs font-semibold transition disabled:cursor-default"
                        :class="
                            page === store.reviewsMeta.current_page
                                ? 'border-zinc-950 bg-zinc-950 text-white'
                                : 'border-zinc-300 text-zinc-700 hover:bg-zinc-50'
                        "
                    >
                        {{ page }}
                    </button>

                    <button
                        @click="changePage(store.reviewsMeta.current_page + 1)"
                        :disabled="
                            store.reviewsMeta.current_page ===
                                store.reviewsMeta.last_page ||
                            store.isLoadingReviews
                        "
                        class="h-8 rounded border border-zinc-300 px-3 text-xs font-semibold text-zinc-700 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-30"
                    >
                        Вперед
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
