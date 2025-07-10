<script setup>
const props = defineProps({
  suppliers: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalSupplier: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "edit-supplier", "delete-supplier", "payment-rule"]);

const headers = [
  { title: "id", key: "id", sortable: true },
  { title: "Nombre", key: "name", sortable: true },
  { title: "Teléfono (Ventas, Cobranza)", key: "sales_phone", sortable: true },
  { title: "Deuda", key: "debt", sortable: true },
  { title: "Calificación", key: "latestScore.score", sortable: true },
  { title: "Acciones", key: "actions", sortable: false },
];
</script>

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
          <div v-if="item.sales_phone">
            <a
              :href="`https://wa.me/${item.sales_phone.replace(/\D/g, '')}`"
              target="_blank"
              class="text-decoration-none"
            >
              <img
                src="https://upload.wikimedia.org/wikipedia/commons/6/6b/WhatsApp.svg"
                alt="WhatsApp"
                width="20"
                height="20"
                class="me-2"
              />
            </a>
          </div>
          <div v-else>
            <span class="text-caption text-disabled">Sin teléfono de ventas</span>
          </div>

          <div v-if="item.collections_phone">
            <a
              :href="`https://wa.me/${item.collections_phone.replace(/\D/g, '')}`"
              target="_blank"
              class="text-decoration-none"
            >
              <img
                src="https://upload.wikimedia.org/wikipedia/commons/6/6b/WhatsApp.svg"
                alt="WhatsApp"
                width="20"
                height="20"
                class="me-2"
              />
            </a>
          </div>
          <div v-else>
            <span class="text-caption text-disabled">Sin teléfono de cobranza</span>
          </div>
        </div>
      </template>

      <template #item.debt="{ item }">
        <span class="font-weight-medium">{{
          item.debt.toLocaleString("es-VE", { minimumFractionDigits: 2 })
        }}</span>
      </template>

      <template #item.latestScore.score="{ item }">
        <span class="font-weight-medium">{{ item.latestScore?.score ?? 0 }}%</span>
      </template>

      <template #item.actions="{ item }">
        <IconBtn @click="emit('edit-supplier', item)">
          <VIcon icon="tabler-edit" />
        </IconBtn>
        <IconBtn @click="emit('delete-supplier', item.id)">
          <VIcon icon="tabler-trash" />
        </IconBtn>
        <IconBtn @click="emit('payment-rule', item)">
          <VIcon icon="tabler-percentage" />
        </IconBtn>
      </template>  
    </VDataTableServer>
  </VCard>
</template>
