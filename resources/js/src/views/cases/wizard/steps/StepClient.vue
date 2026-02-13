<template>
    <div>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
            {{ $t('wizard.step2.title') }}
        </h3>
        <p class="text-gray-500 dark:text-gray-400 mb-6">
            {{ $t('wizard.step2.description') }}
        </p>

        <!-- Selected Client Card -->
        <div v-if="selectedClient" class="mb-6">
            <ClientCard :client="selectedClient" :show-actions="true" @change="clearSelection" />
        </div>

        <!-- Search Section (shown when no client selected) -->
        <div v-else>
            <!-- Search Input -->
            <div class="relative mb-4">
                <label for="client-search" class="sr-only">{{ $t('wizard.step2.search_placeholder') }}</label>
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none" aria-hidden="true">
                    <icon-search class="w-5 h-5 text-gray-400" />
                </div>
                <input
                    id="client-search"
                    v-model="searchQuery"
                    type="text"
                    class="form-input pl-10"
                    :placeholder="$t('wizard.step2.search_placeholder')"
                    :aria-describedby="isSearching ? 'search-status' : undefined"
                    @input="handleSearchInput"
                />
                <div v-if="isSearching" id="search-status" class="absolute inset-y-0 right-0 pr-3 flex items-center" aria-live="polite">
                    <icon-loader class="w-5 h-5 text-gray-400 animate-spin" aria-hidden="true" />
                    <span class="sr-only">{{ $t('common.searching') }}</span>
                </div>
            </div>

            <!-- Search Results -->
            <div
                v-if="searchResults.length > 0"
                class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden mb-4"
                role="listbox"
                aria-label="Search results"
            >
                <div
                    v-for="client in searchResults"
                    :key="client.id"
                    role="option"
                    tabindex="0"
                    class="p-3 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer border-b last:border-b-0 border-gray-200 dark:border-gray-700 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-inset"
                    @click="selectClient(client)"
                    @keydown.enter="selectClient(client)"
                    @keydown.space.prevent="selectClient(client)"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="w-10 h-10 rounded-full bg-primary/20 text-primary flex items-center justify-center font-semibold"
                        >
                            {{ getInitials(client.first_name, client.last_name) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-gray-900 dark:text-white truncate">
                                {{ client.full_name || `${client.first_name} ${client.last_name}` }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400 truncate">
                                {{ client.email || client.phone || '' }}
                            </p>
                        </div>
                        <span
                            :class="[
                                'px-2 py-0.5 text-xs font-medium rounded-full',
                                getStatusClass(client.status),
                            ]"
                        >
                            {{ client.status }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- No Results -->
            <div
                v-else-if="searchQuery.length >= 2 && !isSearching && hasSearched"
                class="text-center py-6 text-gray-500 dark:text-gray-400"
                role="status"
                aria-live="polite"
            >
                <icon-users-group class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-3" aria-hidden="true" />
                <p>{{ $t('wizard.step2.no_results') }}</p>
            </div>

            <!-- Create New Client Button (only shown if user has permission) -->
            <div v-if="canCreateClients" class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <button
                    type="button"
                    class="btn btn-outline-primary w-full"
                    @click="showCreateModal = true"
                >
                    <icon-user-plus class="w-4 h-4 ltr:mr-2 rtl:ml-2" />
                    {{ $t('wizard.step2.create_new') }}
                </button>
            </div>
        </div>

        <!-- Create Client Modal (only rendered if user has permission) -->
        <CreateClientModal
            v-if="canCreateClients"
            v-model:open="showCreateModal"
            @created="handleClientCreated"
        />
    </div>
</template>

<script lang="ts" setup>
import { ref, computed, inject, watch, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import clientService from '@/services/clientService';
import type { Client } from '@/types/client';
import ClientCard from '../components/ClientCard.vue';
import CreateClientModal from '../components/CreateClientModal.vue';
import IconSearch from '@/components/icon/icon-search.vue';
import IconLoader from '@/components/icon/icon-loader.vue';
import IconUsersGroup from '@/components/icon/icon-users-group.vue';
import IconUserPlus from '@/components/icon/icon-user-plus.vue';

// Get wizard from parent
const wizard = inject<ReturnType<typeof import('@/composables/useCaseWizard').useCaseWizard>>('wizard')!;
const authStore = useAuthStore();
const route = useRoute();

// Check if user can create clients
const canCreateClients = computed(() => authStore.hasPermission('clients.create'));

const searchQuery = ref('');
const searchResults = ref<Client[]>([]);
const isSearching = ref(false);
const hasSearched = ref(false);
const showCreateModal = ref(false);
const selectedClient = ref<Client | null>(null);

// Debounce timer
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

// Load client from URL parameter if present
onMounted(async () => {
    const clientIdParam = route.query.client_id;
    if (clientIdParam) {
        const clientId = parseInt(clientIdParam as string, 10);
        if (!isNaN(clientId)) {
            try {
                const response = await clientService.getClient(clientId);
                const client = (response as any).data || response;
                selectedClient.value = client;
                wizard.setClient(client.id);
                // Set search query to client's email
                searchQuery.value = client.email || '';
            } catch (error) {
                console.error('Failed to load client from URL parameter:', error);
            }
        }
    }
});

// Load selected client if wizard state has one
watch(
    () => wizard.state.clientId,
    async (clientId) => {
        if (clientId && !selectedClient.value) {
            try {
                const response = await clientService.getClient(clientId);
                // Handle both {data: Client} and Client responses
                selectedClient.value = (response as any).data || response;
            } catch (error) {
                console.error('Failed to load selected client:', error);
            }
        }
    },
    { immediate: true }
);

// Handle search input with debounce
function handleSearchInput() {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    if (searchQuery.value.length < 2) {
        searchResults.value = [];
        hasSearched.value = false;
        return;
    }

    isSearching.value = true;

    searchTimeout = setTimeout(async () => {
        try {
            const response = await clientService.getClients({
                search: searchQuery.value,
                per_page: 10,
            });
            searchResults.value = response.data;
            hasSearched.value = true;
        } catch (error) {
            console.error('Search failed:', error);
            searchResults.value = [];
        } finally {
            isSearching.value = false;
        }
    }, 300);
}

// Select a client
function selectClient(client: Client) {
    selectedClient.value = client;
    wizard.setClient(client.id);
    searchQuery.value = '';
    searchResults.value = [];
    hasSearched.value = false;
}

// Clear selection
function clearSelection() {
    selectedClient.value = null;
    wizard.setClient(0); // This will be caught by validation
    wizard.state.clientId = null;
}

// Handle newly created client
function handleClientCreated(client: Client) {
    selectClient(client);
    showCreateModal.value = false;
}

function getInitials(firstName: string, lastName: string): string {
    return `${firstName?.charAt(0) || ''}${lastName?.charAt(0) || ''}`.toUpperCase();
}

function getStatusClass(status: string): string {
    const classes: Record<string, string> = {
        active: 'bg-success/20 text-success',
        prospect: 'bg-info/20 text-info',
        inactive: 'bg-warning/20 text-warning',
        archived: 'bg-gray-200 text-gray-600',
    };
    return classes[status] || 'bg-gray-200 text-gray-600';
}
</script>
