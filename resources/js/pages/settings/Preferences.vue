<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import type { AcceptableValue } from 'reka-ui';
import { themeKey, setTheme } from '@/composables/useAppearance';
import { getInitials } from '@/composables/useInitials';
import { useSettings } from '@/composables/useSettings';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Preferences',
                href: '/settings/preferences',
            },
        ],
    },
});

const { settings, updateSettings } = useSettings();
const page = usePage();
const user = computed(() => page.props.auth.user as Record<string, unknown>);

// --- Palette ---
const palettes: { key: string; label: string }[] = [
    { key: 'slate', label: 'Slate' },
    { key: 'rose', label: 'Rose' },
    { key: 'blue', label: 'Blue' },
    { key: 'green', label: 'Green' },
    { key: 'amber', label: 'Amber' },
    { key: 'violet', label: 'Violet' },
    { key: 'teal', label: 'Teal' },
    { key: 'red', label: 'Red' },
];

function handlePaletteClick(key: string) {
    setTheme(key);
}

// --- Density ---
const densityOptions = [
    { value: 'compact' as const, label: 'Compacto' },
    { value: 'comfortable' as const, label: 'Cómodo' },
] as const;

function handleDensityChange(value: string) {
    updateSettings({ density: value as 'compact' | 'comfortable' });
}

// --- Start section ---
const startSectionOptions = [
    { value: 'dashboard', label: 'Dashboard' },
    { value: 'movements', label: 'Movimientos' },
    { value: 'categories', label: 'Categorías' },
    { value: 'accounts', label: 'Cuentas' },
    { value: 'recurring', label: 'Recurrentes' },
] as const;

function handleStartSectionChange(value: AcceptableValue) {
    updateSettings({ start_section: value as 'dashboard' | 'movements' | 'categories' | 'accounts' | 'recurring' });
}

// --- Projection horizon ---
function handleProjectionHorizonBlur(event: Event) {
    const target = event.target as HTMLInputElement;
    let value = parseInt(target.value, 10);

    if (isNaN(value) || value < 1) {
        value = 1;
    } else if (value > 24) {
        value = 24;
    }

    target.value = String(value);

    if (value !== settings.projection_horizon) {
        updateSettings({ projection_horizon: value });
    }
}

// --- Profile photo ---
const uploading = ref(false);
const uploadError = ref('');
const previewUrl = computed(() => {
    const avatarPath = settings.avatar_path as string | null;
    if (avatarPath) {
        return `/storage/${avatarPath}`;
    }
    return null;
});

const AVATAR_MAX_SIZE = 2 * 1024 * 1024; // 2 MB
const AVATAR_ACCEPT = 'image/jpeg,image/png,image/webp';

function handleFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];

    uploadError.value = '';

    if (!file) {
        return;
    }

    // Client-side validation
    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

    if (!allowedTypes.includes(file.type)) {
        uploadError.value = 'Solo se permiten imágenes JPG, PNG o WebP.';
        target.value = '';
        return;
    }

    if (file.size > AVATAR_MAX_SIZE) {
        uploadError.value = 'La imagen no debe superar los 2 MB.';
        target.value = '';
        return;
    }

    uploadPhoto(file);
}

async function uploadPhoto(file: File) {
    const csrfToken = getCsrfToken();

    if (!csrfToken) {
        return;
    }

    uploading.value = true;
    uploadError.value = '';

    try {
        const formData = new FormData();
        formData.append('photo', file);

        const response = await fetch('/settings/profile-photo', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': csrfToken,
            },
            body: formData,
        });

        if (!response.ok) {
            const body = await response.json().catch(() => null);
            throw new Error(body?.message ?? `Error del servidor (${response.status})`);
        }

        const data = (await response.json()) as { avatar_path: string; avatar_url: string };

        // Update local state
        settings.avatar_path = data.avatar_path;

        // Sync back to Inertia shared props
        const userRecord = user.value as Record<string, unknown>;
        const currentSettings = (userRecord.settings as Record<string, unknown>) ?? {};
        userRecord.settings = { ...currentSettings, avatar_path: data.avatar_path };
    } catch (err) {
        uploadError.value = err instanceof Error ? err.message : 'Error al subir la foto.';
    } finally {
        uploading.value = false;
    }
}

function getCsrfToken(): string | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    if (!match) {
        return null;
    }

    return decodeURIComponent(match[1]);
}
</script>

<template>
    <Head title="Preferences" />

    <h1 class="sr-only">Preferences</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Preferences"
            description="Personaliza la apariencia y el comportamiento de la aplicación"
        />

        <!-- Palette selector -->
        <Card>
            <CardHeader>
                <CardTitle>Paleta de colores</CardTitle>
                <CardDescription>
                    Elige el color principal que se usará en botones, enlaces y elementos destacados
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="flex flex-wrap gap-3">
                    <button
                        v-for="palette in palettes"
                        :key="palette.key"
                        type="button"
                        :class="[
                            'theme-' + palette.key,
                            'group relative flex size-10 items-center justify-center rounded-full focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-offset-background',
                            themeKey === palette.key
                                ? 'ring-2 ring-offset-2 ring-offset-background'
                                : '',
                        ]"
                        :style="{
                            '--tw-ring-color': 'var(--primary)',
                        }"
                        :title="palette.label"
                        @click="handlePaletteClick(palette.key)"
                    >
                        <span
                            class="block size-6 rounded-full bg-primary"
                            :class="themeKey === palette.key ? 'ring-1 ring-inset ring-black/20 dark:ring-white/20' : ''"
                        />
                        <span class="sr-only">{{ palette.label }}</span>
                    </button>
                </div>
            </CardContent>
        </Card>

        <!-- Profile photo -->
        <Card>
            <CardHeader>
                <CardTitle>Foto de perfil</CardTitle>
                <CardDescription>
                    Sube una foto para que los demás te reconozcan. Formatos: JPG, PNG, WebP. Máximo 2 MB.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="flex items-start gap-6">
                    <Avatar class="size-20 overflow-hidden rounded-full">
                        <AvatarImage
                            v-if="previewUrl"
                            :src="previewUrl"
                            alt="Foto de perfil"
                        />
                        <AvatarFallback class="rounded-full text-lg font-semibold">
                            {{ getInitials(user.name as string | undefined) }}
                        </AvatarFallback>
                    </Avatar>

                    <div class="flex flex-col gap-3">
                        <Label
                            for="profile-photo"
                            class="cursor-pointer"
                        >
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="uploading"
                                as-child
                            >
                                <span>
                                    <Spinner v-if="uploading" class="mr-2" />
                                    {{ uploading ? 'Subiendo...' : 'Subir foto' }}
                                </span>
                            </Button>
                        </Label>

                        <input
                            id="profile-photo"
                            type="file"
                            :accept="AVATAR_ACCEPT"
                            class="hidden"
                            :disabled="uploading"
                            @change="handleFileChange"
                        />

                        <InputError :message="uploadError" />
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Density -->
        <Card>
            <CardHeader>
                <CardTitle>Densidad</CardTitle>
                <CardDescription>
                    Controla el espaciado de los elementos en las tablas y listas
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="inline-flex gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800">
                    <button
                        v-for="opt in densityOptions"
                        :key="opt.value"
                        type="button"
                        class="rounded-md px-3.5 py-1.5 text-sm transition-colors"
                        :class="
                            settings.density === opt.value
                                ? 'bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100'
                                : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60'
                        "
                        @click="handleDensityChange(opt.value)"
                    >
                        {{ opt.label }}
                    </button>
                </div>
            </CardContent>
        </Card>

        <!-- Start section -->
        <Card>
            <CardHeader>
                <CardTitle>Sección de inicio</CardTitle>
                <CardDescription>
                    ¿Qué pantalla quieres ver al iniciar sesión?
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Select
                    :model-value="settings.start_section"
                    @update:model-value="handleStartSectionChange"
                >
                    <SelectTrigger class="w-full md:w-64">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="opt in startSectionOptions"
                            :key="opt.value"
                            :value="opt.value"
                        >
                            {{ opt.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </CardContent>
        </Card>

        <!-- Projection horizon -->
        <Card>
            <CardHeader>
                <CardTitle>Horizonte de proyección</CardTitle>
                <CardDescription>
                    Número de meses que abarcará la proyección financiera
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="flex flex-col gap-2 md:max-w-48">
                    <Label for="projection-horizon">Meses (1 a 24)</Label>
                    <Input
                        id="projection-horizon"
                        type="number"
                        min="1"
                        max="24"
                        :default-value="settings.projection_horizon"
                        @blur="handleProjectionHorizonBlur"
                    />
                </div>
            </CardContent>
        </Card>
    </div>
</template>
