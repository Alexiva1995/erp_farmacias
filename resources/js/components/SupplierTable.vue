<script setup>
const props = defineProps({
  suppliers: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalSupplier: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  checkingApiId: { type: Number, default: null },
});

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
  { title: "Calificación", key: "latestScore.score", sortable: true },
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
  <VCard>
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.suppliers"
      :items-length="props.totalSupplier"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.id="{ item }">
        <span class="font-weight-medium">{{ item.id }}</span>
      </template>

      <template #item.name="{ item }">
        <div class="d-flex align-center gap-x-4">
          <div class="d-flex flex-column">
            <span class="text-body-1 font-weight-medium text-high-emphasis">
              {{ item.name }}
            </span>
          </div>
        </div>
      </template>

      <template #item.sales_phone="{ item }">
        <div class="d-flex align-center flex-wrap gap-x-4">
          <!-- Teléfono de ventas -->
          <VTooltip text="Ventas">
            <template #activator="{ props }">
              <VBtn
                icon
                :disabled="!item.sales_phone"
                :href="
                  item.sales_phone
                    ? `https://wa.me/${item.sales_phone.replace(/\D/g, '')}`
                    : undefined
                "
                target="_blank"
                variant="text"
                v-bind="props"
              >
                <VIcon
                  :icon="
                    item.sales_phone ? 'tabler-phone-call' : 'tabler-phone-off'
                  "
                />
              </VBtn>
            </template>
          </VTooltip>

          <!-- Teléfono de cobranza -->
          <VTooltip text="Cobranza">
            <template #activator="{ props }">
              <VBtn
                icon
                :disabled="!item.collections_phone"
                :href="
                  item.collections_phone
                    ? `https://wa.me/${item.collections_phone.replace(
                        /\D/g,
                        ''
                      )}`
                    : undefined
                "
                target="_blank"
                variant="text"
                v-bind="props"
              >
                <VIcon
                  :icon="
                    item.collections_phone
                      ? 'tabler-phone-ringing'
                      : 'tabler-phone-off'
                  "
                />
              </VBtn>
            </template>
          </VTooltip>
        </div>
      </template>

      <template #item.debt="{ item }">
        <VChip
          :color="item.debt > 0 ? 'error' : 'success'"
          label
          size="small"
          class="font-weight-bold"
        >
          {{ item.debt.toLocaleString("es-VE", { minimumFractionDigits: 2 }) }}
        </VChip>
      </template>

      <template #item.latestScore.score="{ item }">
        <VRating
          :model-value="(item.latestScore?.score ?? 0) / 20"
          length="5"
          readonly
          size="18"
          color="primary"
        />
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex align-center gap-1">
          <VTooltip text="Ver Conexión" location="top">
            <template #activator="{ props }">
              <VIcon
                v-bind="props"
                size="12"
                :color="checkingApiId === item.id ? 'warning' : 'success'"
                :icon="checkingApiId === item.id ? 'tabler-circle-filled' : 'tabler-circle-filled'"
                :class="checkingApiId === item.id ? 'spin-icon' : ''"
              />
            </template>
          </VTooltip>

          <VMenu>
            <template #activator="{ props }">
              <IconBtn v-bind="props">
                <VIcon icon="tabler-dots-vertical" />
              </IconBtn>
            </template>

            <VList density="compact">
              <VListItem @click="emit('edit-supplier', item)">
                <template #prepend>
                  <VIcon icon="tabler-edit" size="18" class="me-2" />
                </template>
                <VListItemTitle>Editar</VListItemTitle>
              </VListItem>

              <VListItem @click="emit('config-connection', item)">
                <template #prepend>
                  <VIcon icon="tabler-plug-connected" size="18" class="me-2" color="warning" />
                </template>
                <VListItemTitle>Configurar Conexión</VListItemTitle>
              </VListItem>

              <VListItem
                :disabled="checkingApiId === item.id"
                @click="emit('check-supplier-api', item)"
              >
                <template #prepend>
                  <VIcon
                    :icon="checkingApiId === item.id ? 'tabler-loader' : 'tabler-api'"
                    :class="checkingApiId === item.id ? 'spin-icon' : ''"
                    size="18"
                    class="me-2"
                  />
                </template>
                <VListItemTitle>Sincronizar</VListItemTitle>
              </VListItem>

              <VListItem @click="emit('commercial-panel', item)">
                <template #prepend>
                  <VIcon icon="tabler-settings-dollar" size="18" class="me-2" color="primary" />
                </template>
                <VListItemTitle>Configuración Comercial</VListItemTitle>
              </VListItem>

              <VListItem @click="emit('supplier-pending-invoices', item)">
                <template #prepend>
                  <VIcon icon="tabler-credit-card-pay" size="18" class="me-2" />
                </template>
                <VListItemTitle>Facturas Pendientes</VListItemTitle>
              </VListItem>

              <VDivider />

              <VListItem
                base-color="error"
                @click="emit('delete-supplier', item.id)"
              >
                <template #prepend>
                  <VIcon icon="tabler-trash" size="18" class="me-2" />
                </template>
                <VListItemTitle>Eliminar</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>
