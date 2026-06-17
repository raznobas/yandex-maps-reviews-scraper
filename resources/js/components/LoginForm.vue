<script setup>
import { reactive } from "vue";

import { useAuthStore } from "../stores/auth";

const auth = useAuthStore();

const form = reactive({
    email: "test@example.com",
    password: "password",
});

async function submit() {
    await auth.login(form).catch(() => {});
}
</script>

<template>
    <section class="mx-auto w-full max-w-md space-y-6">
        <div class="space-y-2">
            <p class="text-sm font-semibold uppercase text-zinc-500">
                Отзывы Яндекс.Карт
            </p>
            <h1 class="text-3xl font-bold text-zinc-950">
                Войдите, чтобы продолжить
            </h1>
            <p class="text-sm leading-6 text-zinc-600">
                Используйте тестовую учетную запись, чтобы открыть настройки
                организации и отзывы.
            </p>
        </div>

        <form
            class="space-y-4 rounded-lg border border-zinc-200 bg-white p-5 shadow-sm"
            @submit.prevent="submit"
        >
            <label class="block space-y-2">
                <span class="text-sm font-medium text-zinc-700">Email</span>
                <input
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm text-zinc-950 outline-none transition focus:border-zinc-500 focus:ring-2 focus:ring-zinc-200"
                    required
                />
            </label>
            <p v-if="auth.errors.email" class="text-sm text-red-600">
                {{ auth.errors.email[0] }}
            </p>

            <label class="block space-y-2">
                <span class="text-sm font-medium text-zinc-700">Пароль</span>
                <input
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm text-zinc-950 outline-none transition focus:border-zinc-500 focus:ring-2 focus:ring-zinc-200"
                    required
                />
            </label>
            <p v-if="auth.errors.password" class="text-sm text-red-600">
                {{ auth.errors.password[0] }}
            </p>

            <button
                type="submit"
                class="w-full rounded-md bg-zinc-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-zinc-800 disabled:cursor-not-allowed disabled:bg-zinc-400"
                :disabled="auth.isSubmitting"
            >
                {{ auth.isSubmitting ? "Входим..." : "Войти" }}
            </button>
        </form>
    </section>
</template>
