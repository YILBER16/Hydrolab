<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="Configuración" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-2xl font-extrabold tracking-tight text-hl-navy sm:text-3xl">Configuración</h1>
            <p class="mt-1 text-sm text-hl-muted">Actualiza tu perfil, contraseña y preferencias de cuenta.</p>
        </template>

        <div class="mx-auto max-w-3xl space-y-5">
            <section class="hl-card p-6 sm:p-8">
                <UpdateProfileInformationForm
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                    class="max-w-xl"
                />
            </section>

            <section class="hl-card p-6 sm:p-8">
                <UpdatePasswordForm class="max-w-xl" />
            </section>

            <section class="hl-card p-6 sm:p-8">
                <DeleteUserForm class="max-w-xl" />
            </section>
        </div>
    </AuthenticatedLayout>
</template>
