<script setup>
import { ref, computed } from 'vue';
import { toast } from '@/plugins/sweetalert';

const props = defineProps({
  atRisk: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const search = ref('');
const currentPage = ref(1);
const itemsPerPage = ref(7);

const formatCurrency = (value) => {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0);
};

const sanitizePhoneForWa = (phone) => {
  if (!phone) return '';
  return phone.replace(/\D/g, '');
};

const copyPhone = async (phone) => {
  if (!phone) return;
  try {
    await navigator.clipboard.writeText(phone);
    toast.success(`Teléfono copiado: ${phone}`);
  } catch (e) {
    toast.info(`Teléfono: ${phone}`);
  }
};

const filteredClients = computed(() => {
  if (!search.value) return props.atRisk;
  const term = search.value.toLowerCase().trim();
  return props.atRisk.filter((c) => {
    const fullName = `${c.name || ''} ${c.last_name || ''}`.toLowerCase();
    const phone = (c.phone || '').toLowerCase();
    return fullName.includes(term) || phone.includes(term);
  });
});

const totalPages = computed(() => {
  return Math.ceil(filteredClients.value.length / itemsPerPage.value) || 1;
});

const paginatedClients = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value;
  return filteredClients.value.slice(start, start + itemsPerPage.value);
});

const getSeverityColor = (days) => {
  if (days >= 90) return 'error';
  if (days >= 60) return 'warning';
  return 'info';
};
</script>

<template>
  <VCard variant="outlined" class="rounded-lg elevation-1 h-100 d-flex flex-column">
    <VCardItem class="py-3 border-b">
      <div class="d-flex align-center justify-space-between flex-wrap gap-2">
        <VCardTitle class="d-flex align-center text-subtitle-2 font-weight-bold text-uppercase">
          <VIcon icon="tabler-alert-triangle" class="me-2 text-error" size="20" />
          Clientes Críticos en Riesgo (RFM)
        </VCardTitle>

        <div v-if="atRisk.length > 0" style="max-width: 180px;" class="flex-grow-1 flex-md-grow-0">
          <VTextField
            v-model="search"
            placeholder="Buscar..."
            density="compact"
            variant="outlined"
            hide-details="auto"
            prepend-inner-icon="tabler-search"
            clearable
            @update:model-value="currentPage = 1"
          />
        </div>
      </div>
    </VCardItem>

    <VCardText v-if="loading && atRisk.length === 0" class="pa-4 flex-grow-1">
      <VSkeletonLoader type="table-thead, table-tbody" />
    </VCardText>

    <template v-else-if="filteredClients.length > 0">
      <div class="overflow-x-auto flex-grow-1">
        <VTable density="comfortable" class="text-caption">
          <thead>
            <tr>
              <th class="text-uppercase font-weight-bold">Cliente</th>
              <th class="text-uppercase font-weight-bold text-end">Gasto Acum.</th>
              <th class="text-uppercase font-weight-bold text-center">Última Compra</th>
              <th class="text-uppercase font-weight-bold text-center">Inactividad</th>
              <th class="text-uppercase font-weight-bold text-center">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="client in paginatedClients" :key="client.id">
              <td>
                <div class="font-weight-bold text-primary">
                  {{ client.name }} {{ client.last_name }}
                </div>
                <div class="text-caption text-disabled">
                  {{ client.phone || 'Sin teléfono' }}
                </div>
              </td>
              <td class="text-end font-weight-bold text-error">
                {{ formatCurrency(client.monetary) }}
              </td>
              <td class="text-center">
                {{ client.last_order_date }}
              </td>
              <td class="text-center">
                <VChip
                  size="x-small"
                  label
                  :color="getSeverityColor(client.recency_days)"
                  variant="tonal"
                  class="font-weight-bold"
                >
                  {{ client.recency_days }} días
                </VChip>
              </td>
              <td class="text-center">
                <div class="d-inline-flex align-center justify-center gap-1">
                  <!-- Contactar vía WhatsApp -->
                  <VBtn
                    v-if="client.phone"
                    :href="`https://wa.me/${sanitizePhoneForWa(client.phone)}`"
                    target="_blank"
                    rel="noopener noreferrer"
                    icon
                    variant="text"
                    size="x-small"
                    color="success"
                  >
                    <VIcon icon="tabler-brand-whatsapp" size="17" />
                    <VTooltip activator="parent" location="top">Enviar WhatsApp</VTooltip>
                  </VBtn>

                  <!-- Copiar Teléfono -->
                  <VBtn
                    v-if="client.phone"
                    icon
                    variant="text"
                    size="x-small"
                    color="secondary"
                    @click="copyPhone(client.phone)"
                  >
                    <VIcon icon="tabler-copy" size="16" />
                    <VTooltip activator="parent" location="top">Copiar Teléfono</VTooltip>
                  </VBtn>

                  <span v-else class="text-disabled">-</span>
                </div>
              </td>
            </tr>
          </tbody>
        </VTable>
      </div>

      <!-- Paginación -->
      <div v-if="totalPages > 1" class="d-flex align-center justify-space-between px-4 py-2 border-t">
        <span class="text-caption text-medium-emphasis">
          Total: {{ filteredClients.length }} clientes
        </span>
        <VPagination
          v-model="currentPage"
          :length="totalPages"
          :total-visible="4"
          density="compact"
          size="small"
        />
      </div>
    </template>

    <VCardText v-else-if="search && atRisk.length > 0" class="pa-6 text-center flex-grow-1">
      <VIcon icon="tabler-search-off" size="36" class="text-disabled mb-2" />
      <div class="text-body-2 text-medium-emphasis">No se encontraron clientes con el término "{{ search }}".</div>
    </VCardText>

    <VCardText v-else class="pa-6 flex-grow-1 d-flex align-center justify-center">
      <VEmptyState
        icon="tabler-user-check"
        title="Sin clientes críticos"
        text="Excelente. No hay clientes valiosos en riesgo de abandono en este momento."
      />
    </VCardText>
  </VCard>
</template>
