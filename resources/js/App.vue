<script setup>
import { onMounted } from "vue";

import AuthenticatedShell from "./components/AuthenticatedShell.vue";
import LoginForm from "./components/LoginForm.vue";
import { useAuthStore } from "./stores/auth";

const auth = useAuthStore();

onMounted(() => {
    auth.fetchUser();
});
</script>

<template>
    <main
        class="mx-auto flex min-h-screen w-full max-w-5xl flex-col justify-center px-6 py-10"
    >
        <section
            v-if="auth.isLoading"
            class="mx-auto w-full max-w-md rounded-lg border border-zinc-200 bg-white p-6 shadow-sm"
        >
            <p class="text-sm font-medium text-zinc-600">Проверяем сессию...</p>
        </section>

        <AuthenticatedShell v-else-if="auth.isAuthenticated" />

        <LoginForm v-else />
    </main>
</template>
