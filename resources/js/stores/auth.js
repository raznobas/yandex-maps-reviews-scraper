import axios from "axios";
import { computed, ref } from "vue";
import { defineStore } from "pinia";

export const useAuthStore = defineStore("auth", () => {
    const user = ref(null);
    const isLoading = ref(true);
    const isSubmitting = ref(false);
    const errors = ref({});

    const isAuthenticated = computed(() => Boolean(user.value));

    async function initializeCsrf() {
        await axios.get("/sanctum/csrf-cookie");
    }

    function clearAuthState() {
        user.value = null;
    }

    function setErrors(error) {
        errors.value = error.response?.data?.errors ?? {
            email: ["Не удалось выполнить запрос."],
        };
    }

    function handleExpiredSession(error) {
        if ([401, 419].includes(error.response?.status)) {
            clearAuthState();
        }

        return Promise.reject(error);
    }

    async function fetchUser() {
        isLoading.value = true;

        try {
            const response = await axios.get("/api/user");
            user.value = response.data;
        } catch (error) {
            if (error.response?.status !== 401) {
                await handleExpiredSession(error);
            }

            clearAuthState();
        } finally {
            isLoading.value = false;
        }
    }

    async function login(credentials) {
        isSubmitting.value = true;
        errors.value = {};

        try {
            await initializeCsrf();
            await axios.post("/login", credentials);
            await fetchUser();
        } catch (error) {
            if ([401, 419].includes(error.response?.status)) {
                clearAuthState();
            }

            setErrors(error);
            throw error;
        } finally {
            isSubmitting.value = false;
        }
    }

    async function logout() {
        isSubmitting.value = true;
        errors.value = {};

        try {
            await axios.post("/logout");
        } catch (error) {
            if (![401, 419].includes(error.response?.status)) {
                throw error;
            }
        } finally {
            clearAuthState();
            isSubmitting.value = false;
        }
    }

    return {
        user,
        isLoading,
        isSubmitting,
        errors,
        isAuthenticated,
        initializeCsrf,
        fetchUser,
        login,
        logout,
        handleExpiredSession,
    };
});
