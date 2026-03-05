<script setup>
const props = defineProps({
  purchaseOrders: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalPurchaseOrders: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  isAdmin: { type: Boolean, required: true },
});

const emit = defineEmits([
  "update:options",
  "delete-purchaseOrder",
  "show-purchaseOrder",
  "show-requested-products",
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
        <div class="d-flex align-center">
          <VAvatar
            size="32"
            variant="tonal"
            color="primary"
            class="me-2"
          >
            <span class="text-xs">#{{ item.id }}</span>
          </VAvatar>
        </div>
      </template>

      <template #item.supplier_name="{ item }">
        <div class="d-flex flex-column">
          <span class="text-body-1 font-weight-bold text-high-emphasis">
            {{ item.supplier_name }}
          </span>
          <span v-if="item.phone" class="text-caption text-secondary">
            <VIcon icon="tabler-phone" size="12" class="me-1" />
            {{ item.phone }}
          </span>
        </div>
      </template>

      <template #item.total_quantity="{ item }">
        <div class="d-flex align-center">
          <VIcon icon="tabler-box" size="16" class="me-1 text-secondary" />
          <span class="font-weight-medium">
            {{ item.total_quantity }} u.
          </span>
        </div>
      </template>

      <template #item.total_amount="{ item }">
        <span class="font-weight-bold text-primary">{{
          Intl.NumberFormat("es-VE", {
            style: "currency",
            currency: "USD",
          }).format(item.total_amount)
        }}</span>
      </template>

      <template #item.order_date="{ item }">
        <div class="d-flex flex-column">
          <span class="text-body-2 font-weight-medium">
            {{ new Date(item.order_date).toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' }) }}
          </span>
          <span class="text-caption text-secondary">
            {{ new Date(item.order_date).toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' }) }}
          </span>
        </div>
      </template>

      <template #item.status="{ item }">
        <VChip
          size="small"
          :color="item.status ? 'success' : 'warning'"
          class="font-weight-bold"
          label
        >
          {{ item.status ? "COMPLETADO" : "PENDIENTE" }}
        </VChip>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex align-center gap-1">
          <VTooltip text="Gestionar Productos" location="top">
            <template #activator="{ props }">
              <VBtn
                v-bind="props"
                icon
                size="x-small"
                variant="tonal"
                color="primary"
                @click="emit('show-requested-products', item)"
              >
                <VIcon icon="tabler-list-check" size="20" />
              </VBtn>
            </template>
          </VTooltip>

          <VTooltip text="Ver Detalle" location="top">
            <template #activator="{ props }">
              <VBtn
                v-bind="props"
                icon
                size="x-small"
                variant="tonal"
                color="info"
                @click="emit('show-purchaseOrder', item)"
              >
                <VIcon icon="tabler-eye" size="20" />
              </VBtn>
            </template>
          </VTooltip>

          <VTooltip v-if="props.isAdmin" text="Editar" location="top">
            <template #activator="{ props }">
              <VBtn
                v-bind="props"
                icon
                size="x-small"
                variant="tonal"
                color="warning"
                @click="emit('edit-purchaseOrder', item)"
              >
                <VIcon icon="tabler-edit" size="20" />
              </VBtn>
            </template>
          </VTooltip>

          <VTooltip v-if="props.isAdmin" text="Eliminar" location="top">
            <template #activator="{ props }">
              <VBtn
                v-bind="props"
                icon
                size="x-small"
                variant="tonal"
                color="error"
                @click="emit('delete-purchaseOrder', item.id)"
              >
                <VIcon icon="tabler-trash" size="20" />
              </VBtn>
            </template>
          </VTooltip>

          <VTooltip text="Contactar WhatsApp" location="top">
            <template #activator="{ props }">
              <VBtn
                v-bind="props"
                icon
                size="x-small"
                variant="tonal"
                color="success"
                :disabled="!item.phone"
                :href="item.phone ? `https://wa.me/${item.phone.replace(/\D/g, '')}?text=%2ADebe%20adjuntar%20el%20archivo%20que%20descargó%20del%20detalle%20de%20la%20orden%20de%20compra%20${item.id}%2A` : undefined"
                target="_blank"
              >
                <VIcon icon="tabler-brand-whatsapp" size="20" />
              </VBtn>
            </template>
          </VTooltip>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>
