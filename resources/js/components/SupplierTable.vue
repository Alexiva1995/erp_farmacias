<script setup>
const props = defineProps({
  suppliers: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalSupplier: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  checkingApiId: { type: Number, default: null },
});

import { useDisplay } from "vuetify";

const { mobile } = useDisplay();

const emit = defineEmits([
  "update:options",
  "edit-supplier",
  "delete-supplier",
  "commercial-panel",
  "supplier-pending-invoices",
  "check-supplier-api",
  "config-connection",
]);

const headers = [
  { title: "id", key: "id", sortable: true },
  { title: "Nombre", key: "name", sortable: true },
  { title: "Teléfono", key: "sales_phone", sortable: true },
  { title: "Deuda", key: "debt", sortable: true },
  { title: "Calificación", key: "latest_score_value", sortable: true },
  { title: "Acciones", key: "actions", sortable: false },
];
</script>

<style scoped>
.spin-icon {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% {
    transform: rotate(0deg);
  }

  100% {
    transform: rotate(360deg);
  }
}
</style>

<template>
  <div class="supplier-table-container">
    <VCard v-if="!mobile" class="shadow-sm border-0">
      <VDataTableServer
        :items-per-page="props.itemsPerPage"
        :page="props.page"
        :headers="headers"
        :items="props.suppliers"
        :items-length="props.totalSupplier"
        :loading="props.loading"
        class="text-no-wrap premium-data-table"
        @update:options="(options) => emit('update:options', options)"
      >
        <!-- Columnas personalizadas existentes -->
        <template #item.id="{ item }">
          <span class="text-caption font-weight-bold text-disabled">#{{ item.id }}</span>
        </template>

        <template #item.name="{ item }">
          <div class="d-flex align-center gap-x-3">
            <VAvatar size="32" color="primary" variant="tonal">
              <span class="text-caption font-weight-bold">{{ item.name.charAt(0) }}</span>
            </VAvatar>
            <span class="text-body-2 font-weight-medium text-high-emphasis">
              {{ item.name }}
            </span>
          </div>
        </template>

        <template #item.sales_phone="{ item }">
          <div class="d-flex align-center gap-1">
            <VTooltip text="Contactar Ventas">
              <template #activator="{ props }">
                <VBtn
                  icon="tabler-brand-whatsapp"
                  size="small"
                  :disabled="!item.sales_phone"
                  :href="item.sales_phone ? `https://wa.me/${item.sales_phone.replace(/\D/g, '')}` : undefined"
                  target="_blank"
                  variant="text"
                  color="success"
                  v-bind="props"
                />
              </template>
            </VTooltip>
            <VTooltip text="Contactar Cobranza">
              <template #activator="{ props }">
                <VBtn
                  icon="tabler-phone-call"
                  size="small"
                  :disabled="!item.collections_phone"
                  :href="item.collections_phone ? `tel:${item.collections_phone}` : undefined"
                  target="_blank"
                  variant="text"
                  color="info"
                  v-bind="props"
                />
              </template>
            </VTooltip>
          </div>
        </template>

        <template #item.debt="{ item }">
          <div class="d-flex flex-column align-end">
            <VChip
              :color="item.debt > 0 ? 'error' : 'success'"
              label
              size="small"
              variant="flat"
              class="font-weight-bold"
            >
              {{ item.debt.toLocaleString("es-VE", { minimumFractionDigits: 2 }) }}
            </VChip>
            <span v-if="item.debt > 0" class="text-xxs text-error mt-1">Deuda Pendiente</span>
          </div>
        </template>

        <template #item.latest_score_value="{ item }">
          <div v-if="item.latest_score_value" class="d-flex align-center gap-2">
            <VRating
              :model-value="Number(item.latest_score_value) / 20"
              length="5"
              readonly
              size="16"
              color="warning"
              active-color="warning"
              half-increments
            />
            <span class="text-caption font-weight-bold">{{ Number(item.latest_score_value).toFixed(1) }}</span>
          </div>
          <span v-else class="text-caption text-disabled">N/A</span>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex align-center justify-center">
             <VTooltip text="Estado de Conexión" location="top">
              <template #activator="{ props }">
                <VIcon
                  v-bind="props"
                  size="10"
                  :color="checkingApiId === item.id ? 'warning' : 'success'"
                  icon="tabler-circle-filled"
                  :class="checkingApiId === item.id ? 'spin-icon' : 'pulse-icon'"
                  class="me-2"
                />
              </template>
            </VTooltip>

            <VMenu location="bottom end">
              <template #activator="{ props }">
                <IconBtn v-bind="props" size="small">
                  <VIcon icon="tabler-dots-vertical" size="18" />
                </IconBtn>
              </template>

              <VList density="compact" min-width="200">
                <VListItem @click="emit('edit-supplier', item)" prepend-icon="tabler-edit">
                  <VListItemTitle>Editar Datos</VListItemTitle>
                </VListItem>

                <VListItem @click="emit('config-connection', item)" prepend-icon="tabler-plug-connected" base-color="warning">
                  <VListItemTitle>Configurar Conexión</VListItemTitle>
                </VListItem>

                <VListItem :disabled="checkingApiId === item.id" @click="emit('check-supplier-api', item)" prepend-icon="tabler-api">
                  <VListItemTitle>Sincronizar</VListItemTitle>
                </VListItem>

                <VListItem @click="emit('commercial-panel', item)" prepend-icon="tabler-settings-dollar" base-color="primary">
                  <VListItemTitle>Configuración Comercial</VListItemTitle>
                </VListItem>

                <VListItem @click="emit('supplier-pending-invoices', item)" prepend-icon="tabler-credit-card-pay">
                   <VListItemTitle>Facturas Pendientes</VListItemTitle>
                </VListItem>

                <VDivider />

                <VListItem base-color="error" @click="emit('delete-supplier', item.id)" prepend-icon="tabler-trash">
                  <VListItemTitle>Eliminar</VListItemTitle>
                </VListItem>
              </VList>
            </VMenu>
          </div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Vista de Tarjetas para Móvil -->
    <div v-else class="mobile-supplier-cards d-flex flex-column gap-4">
      <div v-if="loading" class="d-flex justify-center py-10">
        <VProgressCircular indeterminate color="primary" />
      </div>

      <template v-else>
        <VCard
          v-for="item in props.suppliers"
          :key="item.id"
          class="supplier-mobile-card border-0"
        >
          <VCardText class="pa-4">
            <div class="d-flex justify-space-between align-start mb-3">
              <div class="d-flex align-center gap-3">
                <VAvatar color="primary" variant="tonal" size="40">
                  <span class="text-h6 font-weight-bold">{{ item.name.charAt(0) }}</span>
                </VAvatar>
                <div>
                  <div class="text-subtitle-1 font-weight-bold line-clamp-1">{{ item.name }}</div>
                  <div class="text-caption text-disabled">ID: #{{ item.id }}</div>
                </div>
              </div>
              <div class="d-flex align-center gap-1">
                <VIcon
                  size="10"
                  :color="checkingApiId === item.id ? 'warning' : 'success'"
                  icon="tabler-circle-filled"
                  :class="checkingApiId === item.id ? 'spin-icon' : ''"
                />
              </div>
            </div>

            <VDivider class="mb-3 border-dashed" />

            <div class="d-flex justify-space-between align-center mb-4">
              <div class="d-flex flex-column">
                <span class="text-caption text-disabled mb-1">Deuda Actual</span>
                <span :class="item.debt > 0 ? 'text-error' : 'text-success'" class="text-h6 font-weight-bold">
                  {{ item.debt.toLocaleString("es-VE", { minimumFractionDigits: 2 }) }} Bs
                </span>
              </div>
              <div class="d-flex flex-column align-end">
                 <span class="text-caption text-disabled mb-1">Calificación</span>
                 <div class="d-flex align-center">
                    <VIcon icon="tabler-star-filled" color="warning" size="14" class="me-1" />
                    <span class="text-body-2 font-weight-bold">{{ item.latest_score_value ? Number(item.latest_score_value).toFixed(1) : '—' }}</span>
                 </div>
              </div>
            </div>

            <div class="d-flex justify-space-between align-center mt-2 pa-2 bg-light-surface rounded-pill">
              <div class="d-flex gap-2">
                <VTooltip text="WhatsApp Ventas" location="top">
                  <template #activator="{ props }">
                    <VBtn
                      v-bind="props"
                      icon="tabler-brand-whatsapp"
                      variant="tonal"
                      color="success"
                      size="32"
                      :disabled="!item.sales_phone"
                      :href="item.sales_phone ? `https://wa.me/${item.sales_phone.replace(/\D/g, '')}` : undefined"
                      target="_blank"
                    />
                  </template>
                </VTooltip>
                <VTooltip text="Llamar Cobranza" location="top">
                  <template #activator="{ props }">
                    <VBtn
                      v-bind="props"
                      icon="tabler-phone"
                      variant="tonal"
                      color="info"
                      size="32"
                      :disabled="!item.collections_phone"
                      :href="item.collections_phone ? `tel:${item.collections_phone}` : undefined"
                    />
                  </template>
                </VTooltip>
              </div>

              <div class="d-flex gap-1">
                <VBtn
                  icon="tabler-edit"
                  variant="text"
                  color="primary"
                  size="32"
                  @click="emit('edit-supplier', item)"
                />
                <VBtn
                  icon="tabler-settings-dollar"
                  variant="text"
                  color="warning"
                  size="32"
                  @click="emit('commercial-panel', item)"
                />
                <VBtn
                  icon="tabler-credit-card-pay"
                  variant="text"
                  color="secondary"
                  size="32"
                  @click="emit('supplier-pending-invoices', item)"
                />
              </div>
            </div>
          </VCardText>
        </VCard>

        <div v-if="props.suppliers.length === 0" class="text-center py-10">
          <VIcon icon="tabler-search-off" size="48" color="disabled" class="mb-2" />
          <p class="text-body-2 text-disabled">No se encontraron proveedores</p>
        </div>

        <div class="mt-4 px-2">
          <VPagination
            :model-value="props.page"
            :length="Math.ceil(props.totalSupplier / props.itemsPerPage)"
            @update:model-value="emit('update:options', { page: $event, itemsPerPage: props.itemsPerPage, sortBy: [], groupBy: [] })"
            density="compact"
            active-color="primary"
          />
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
.spin-icon {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.pulse-icon {
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0% {
    opacity: 1;
    transform: scale(1);
  }

  50% {
    opacity: 0.5;
    transform: scale(1.2);
  }

  100% {
    opacity: 1;
    transform: scale(1);
  }
}

.premium-data-table :deep(th) {
  background-color: #f8fafc !important;
  color: #64748b !important;
  font-size: 0.7rem !important;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.premium-data-table :deep(td) {
  padding-block: 12px !important;
}

.supplier-mobile-card {
  border-radius: 16px;
  box-shadow: 0 4px 15px 0 rgba(0, 0, 0, 5%) !important;
  transition: transform 0.2s;
}

.supplier-mobile-card:active {
  transform: scale(0.98);
}

.text-xxs {
  font-size: 0.65rem;
}

.line-clamp-1 {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 1;
}

.bg-light-surface {
  background-color: rgba(var(--v-theme-on-surface), 4%) !important;
}
</style>
