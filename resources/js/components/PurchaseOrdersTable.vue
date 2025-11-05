<script setup>
const props = defineProps({
  purchaseOrders: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalPurchaseOrders: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits([
  "update:options",
  "delete-purchaseOrder",
  "show-purchaseOrder",
]);

const headers = [
  { title: "Id", key: "id", sortable: false },
  { title: "Proveedor", key: "supplier_name", sortable: false },
  { title: "Unidades", key: "total_quantity", sortable: false },
  { title: "Monto", key: "total_amount", sortable: false },
  { title: "Estado", key: "status", sortable: false },
  { title: "Fecha", key: "order_date", sortable: false },
  { title: "Acciones", key: "actions", sortable: false },
];
</script>

<template>
  <VCard>
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.purchaseOrders"
      :items-length="props.totalPurchaseOrders"
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

      <template #item.total_quantity="{ item }">
        <span class="font-weight-medium">
          {{ item.total_quantity }}
        </span>
      </template>

      <template #item.total_amount="{ item }">
        <span class="font-weight-medium">{{
          Intl.NumberFormat("es", {
            maximumFractionDigits: 2,
            minimumFractionDigits: 2,
          }).format(item.total_amount)
        }}</span>
      </template>

      <template #item.debt="{ item }">
        <span class="font-weight-medium">
          {{ item.debt.toLocaleString("es-VE", { minimumFractionDigits: 2 }) }}
        </span>
      </template>

      <template #item.status="{ item }">
        <span
          class="font-weight-medium"
          :class="item.status === 0 ? 'text-error' : 'text-success'"
        >
          {{ item.status === 0 ? "Pendiente" : "Compleado" }}
        </span>
      </template>

      <template #item.actions="{ item }">
        <VTooltip text="Editar Órden de Compra" location="top">
          <template #activator="{ props }">
            <IconBtn v-bind="props" @click="emit('edit-purchaseOrder', item)">
              <VIcon icon="tabler-edit" />
            </IconBtn>
          </template>
        </VTooltip>
        <VTooltip text="Ver Órden de Compra" location="top">
          <template #activator="{ props }">
            <IconBtn v-bind="props" @click="emit('show-purchaseOrder', item)">
              <VIcon icon="tabler-eye" />
            </IconBtn>
          </template>
        </VTooltip>
        <VTooltip text="Eliminar Órden de Compra" location="top">
          <template #activator="{ props }">
            <IconBtn
              v-bind="props"
              @click="emit('delete-purchaseOrder', item.id)"
            >
              <VIcon icon="tabler-trash" />
            </IconBtn>
          </template>
        </VTooltip>
        <VTooltip text="Contactar Proveedor" location="top">
          <template #activator="{ props }">
            <VBtn
              icon
              :disabled="!item.phone"
              :href="
                item.phone
                  ? `https://wa.me/${item.phone.replace(
                      /\D/g,
                      ''
                    )}?text=%2ADebe%20adjuntar%20el%20archivo%20que%20descargó%20del%20detalle%20de%20la%20orden%20de%20compra%20${
                      item.id
                    }%2A`
                  : undefined
              "
              target="_blank"
              variant="text"
              v-bind="props"
            >
              <VIcon
                :icon="item.phone ? 'tabler-phone-ringing' : 'tabler-phone-off'"
              />
            </VBtn>
          </template>
        </VTooltip>
      </template>
    </VDataTableServer>
  </VCard>
</template>
