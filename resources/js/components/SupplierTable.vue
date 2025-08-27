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
  "payment-rule",
  "supplier-laboratory",
  "supplier-pending-invoices",
  "supplier-discount-rule",
  "check-supplier-api",
  "supplier-discount",
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
                  :icon="item.sales_phone ? 'tabler-phone-call' : 'tabler-phone-off'"
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
                    ? `https://wa.me/${item.collections_phone.replace(/\D/g, '')}`
                    : undefined
                "
                target="_blank"
                variant="text"
                v-bind="props"
              >
                <VIcon
                  :icon="
                    item.collections_phone ? 'tabler-phone-ringing' : 'tabler-phone-off'
                  "
                />
              </VBtn>
            </template>
          </VTooltip>
        </div>
      </template>

      <template #item.debt="{ item }">
        <span class="font-weight-medium">{{
          item.debt.toLocaleString("es-VE", { minimumFractionDigits: 2 })
        }}</span>
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
        <VTooltip text="Editar Proveedor" location="top">
          <template #activator="{ props }">
            <IconBtn v-bind="props" @click="emit('edit-supplier', item)">
              <VIcon icon="tabler-edit" />
            </IconBtn>
          </template>
        </VTooltip>
        <VTooltip text="Eliminar Proveedor" location="top">
          <template #activator="{ props }">
            <IconBtn v-bind="props" @click="emit('delete-supplier', item.id)">
              <VIcon icon="tabler-trash" />
            </IconBtn>
          </template>
        </VTooltip>
        <VTooltip text="Conexión API" location="top">
          <template #activator="{ props }">
            <IconBtn
              v-bind="props"
              :disabled="checkingApiId === item.id"
              @click="emit('check-supplier-api', item)"
            >
              <VIcon
                :icon="checkingApiId === item.id ? 'tabler-loader' : 'tabler-api'"
                :class="checkingApiId === item.id ? 'spin-icon' : ''"
              />
            </IconBtn>
          </template>
        </VTooltip>
        <VTooltip text="Regla de Pronto Pago" location="top">
          <template #activator="{ props }">
            <IconBtn v-bind="props" @click="emit('payment-rule', item)">
              <VIcon icon="tabler-percentage" />
            </IconBtn>
          </template>
        </VTooltip>
        <VTooltip text="Laboratorios Asociados" location="top">
          <template #activator="{ props }">
            <IconBtn v-bind="props" @click="emit('supplier-laboratory', item)">
              <VIcon icon="tabler-test-pipe" />
            </IconBtn>
          </template>
        </VTooltip>
        <VTooltip text="Facturas Pendientes" location="top">
          <template #activator="{ props }">
            <IconBtn v-bind="props" @click="emit('supplier-pending-invoices', item)">
              <VIcon icon="tabler-credit-card-pay" />
            </IconBtn>
          </template>
        </VTooltip>
        <VTooltip text="Reglas de Descuento" location="top">
          <template #activator="{ props }">
            <IconBtn v-bind="props" @click="emit('supplier-discount-rule', item)">
              <VIcon icon="tabler-cash" />
            </IconBtn>
          </template>
        </VTooltip>
        <VTooltip text="Descuentos" location="top">
          <template #activator="{ props }">
            <IconBtn v-bind="props" @click="emit('supplier-discount', item)">
              <VIcon icon="tabler-discount" />
            </IconBtn>
          </template>
        </VTooltip>
      </template>
    </VDataTableServer>
  </VCard>
</template>
