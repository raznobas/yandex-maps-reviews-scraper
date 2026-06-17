import axios from "axios";
import { defineStore } from "pinia";
import { ref, computed } from "vue";
import { useAuthStore } from "./auth";

export const useOrganizationStore = defineStore("organization", () => {
    const auth = useAuthStore();
    const organization = ref(null);
    const reviews = ref([]);
    const reviewsMeta = ref({
        current_page: 1,
        last_page: 1,
        total: 0,
    });
    const sourceUrl = ref("");
    const isLoading = ref(false);
    const isLoadingReviews = ref(false);
    const isSaving = ref(false);
    const isSyncing = ref(false);
    const errors = ref({});
    const loadError = ref("");
    const saveError = ref("");
    const syncError = ref("");
    const reviewsError = ref("");
    const statusMessage = ref("");
    const terminalSyncStatuses = new Set([
        "success",
        "empty",
        "partial",
        "failed",
        "idle",
    ]);

    const hasOrganization = computed(() => Boolean(organization.value));

    function errorMessage(error, fallback) {
        return error.response?.data?.message || fallback;
    }

    function clearErrors() {
        errors.value = {};
        loadError.value = "";
        saveError.value = "";
        syncError.value = "";
        reviewsError.value = "";
        statusMessage.value = "";
    }

    function clearReviews() {
        reviews.value = [];
        reviewsMeta.value = {
            current_page: 1,
            last_page: 1,
            total: 0,
        };
    }

    async function fetchOrganization() {
        isLoading.value = true;
        loadError.value = "";

        try {
            const response = await axios.get("/api/organization");
            organization.value = response.data.data;
            if (organization.value) {
                sourceUrl.value = organization.value.source_url;
                await fetchReviews();
            } else {
                sourceUrl.value = "";
                clearReviews();
            }
        } catch (error) {
            loadError.value = errorMessage(
                error,
                "Не удалось загрузить настройки организации.",
            );
            await auth.handleExpiredSession(error);
        } finally {
            isLoading.value = false;
        }
    }

    async function fetchReviews(page = 1) {
        if (!hasOrganization.value) return;

        isLoadingReviews.value = true;
        reviewsError.value = "";
        try {
            const response = await axios.get("/api/organization/reviews", {
                params: { page },
            });
            reviews.value = response.data.data;
            reviewsMeta.value = response.data.meta;
        } catch (error) {
            reviewsError.value = errorMessage(
                error,
                "Не удалось загрузить отзывы.",
            );
            await auth.handleExpiredSession(error);
        } finally {
            isLoadingReviews.value = false;
        }
    }

    async function syncOrganization() {
        if (!hasOrganization.value) return;

        isSyncing.value = true;
        syncError.value = "";
        statusMessage.value = "";

        try {
            const response = await axios.post("/api/organization/sync");
            organization.value = response.data.data;

            if (organization.value?.sync_status === "running") {
                await pollSyncStatus();
            } else {
                await fetchReviews(1);
            }
        } catch (error) {
            syncError.value = errorMessage(
                error,
                "Не удалось запустить или проверить синхронизацию отзывов.",
            );
            await auth.handleExpiredSession(error);
            throw error;
        } finally {
            isSyncing.value = false;
        }
    }

    async function pollSyncStatus(maxAttempts = 120, intervalMs = 1000) {
        for (let attempt = 0; attempt < maxAttempts; attempt += 1) {
            if (attempt > 0) {
                await new Promise((resolve) => setTimeout(resolve, intervalMs));
            }

            const response = await axios.get("/api/organization");
            organization.value = response.data.data;

            const status = organization.value?.sync_status;
            if (!status || terminalSyncStatuses.has(status)) {
                if (organization.value) {
                    if (
                        organization.value.sync_status === "failed" ||
                        organization.value.sync_status === "partial"
                    ) {
                        syncError.value =
                            organization.value.sync_error ||
                            "Синхронизация завершилась не полностью. Попробуйте обновить отзывы позже.";
                    }

                    await fetchReviews(1);
                }

                return;
            }
        }

        syncError.value =
            "Синхронизация все еще выполняется. Обновите страницу или попробуйте повторить позже.";
    }

    async function saveOrganization() {
        isSaving.value = true;
        errors.value = {};
        saveError.value = "";
        statusMessage.value = "";
        let saved = false;

        try {
            const response = await axios.put("/api/organization", {
                source_url: sourceUrl.value,
            });
            const previousOrganizationId =
                organization.value?.yandex_organization_id;
            organization.value = response.data.data;
            sourceUrl.value = organization.value.source_url;
            saved = true;
            if (
                previousOrganizationId &&
                previousOrganizationId !==
                    organization.value.yandex_organization_id
            ) {
                clearReviews();
            }
            statusMessage.value = "Настройки сохранены. Загружаем отзывы...";

            await syncOrganization();
            statusMessage.value = "Настройки сохранены, отзывы обновлены.";

            setTimeout(() => {
                statusMessage.value = "";
            }, 3000);
        } catch (error) {
            if (saved) {
                throw error;
            }

            if (error.response?.status === 422) {
                errors.value = error.response.data.errors;
                saveError.value = errorMessage(
                    error,
                    "Проверьте ссылку на организацию.",
                );
            } else {
                saveError.value = errorMessage(
                    error,
                    "Не удалось сохранить настройки организации.",
                );
                await auth.handleExpiredSession(error);
            }
            throw error;
        } finally {
            isSaving.value = false;
        }
    }

    return {
        organization,
        reviews,
        reviewsMeta,
        sourceUrl,
        isLoading,
        isLoadingReviews,
        isSaving,
        isSyncing,
        errors,
        loadError,
        saveError,
        syncError,
        reviewsError,
        statusMessage,
        hasOrganization,
        fetchOrganization,
        fetchReviews,
        syncOrganization,
        saveOrganization,
        clearErrors,
        clearReviews,
    };
});
