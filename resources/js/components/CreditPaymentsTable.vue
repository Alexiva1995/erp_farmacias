<script setup>
import { translateMethod } from "@/utils/paymentMethods";

const props = defineProps({
  payments: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalPayments: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  mobile: { type: Boolean, default: false },
});

const emit = defineEmits(["update:options"]);

const headers = [
  { title: "Fecha", key: "date", sortable: true },
  { title: "Cliente", key: "client", sortable: true },
  { title: "Vendedor", key: "seller", sortable: true },
  { title: "Monto", key: "amount", sortable: true },
  { title: "Método", key: "method", sortable: true },
  { title: "Moneda", key: "currency", sortable: true },
  { title: "Referencia", key: "reference", sortable: true },
];
</script>
<template>
  <VCard variant="flat" border class="rounded-lg overflow-hidden shadow-sm">
    <template v-if="props.mobile">
       <VDataIterator
        :items="props.payments"
        :items-per-page="props.itemsPerPage"
        :loading="props.loading"
      >
        <template v-slot:default="{ items }">
          <div class="pa-2 d-flex flex-column gap-2">
            <VCard
              v-for="item in items"
              :key="item.raw.id"
              variant="flat"
              border
              class="rounded-lg pa-3"
            >
              <div class="d-flex justify-space-between align-start mb-1">
                <div class="d-flex flex-column">
                  <span class="text-caption font-weight-bold text-primary leading-tight">ID: #{{ item.raw.id }}</span>
                  <div class="d-flex align-center gap-1 text-medium-emphasis mt-n1">
                    <VIcon size="12">tabler-calendar</VIcon>
                    <span style="font-size: 0.65rem;">{{ item.raw.date ? item.raw.date.split(" ")[0] : "N/A" }}</span>
                  </div>
                </div>
                <VChip size="x-small" color="secondary" variant="tonal" class="font-weight-bold px-1" style="block-size: 18px; font-size: 0.6rem;">
                  {{ item.raw.currency }}
                </VChip>
              </div>

              <div class="mb-2">
                <div class="text-body-2 font-weight-bold truncate">
                  {{ item.raw.client }}
                </div>
                <div class="d-flex align-center gap-1 text-medium-emphasis" style="font-size: 0.7rem;">
                  <VIcon size="12">tabler-user-check</VIcon>
                  <span>{{ item.raw.seller }}</span>
                </div>
              </div>

              <VDivider class="border-dashed mb-2" />

              <div class="d-flex justify-space-between align-center">
                <div class="d-flex flex-column">
                  <span style="font-size: 0.6rem;" class="text-medium-emphasis text-uppercase font-weight-bold mb-n1">Monto Pagado</span>
                  <span class="text-subtitle-1 font-weight-black text-success">
                    {{ item.raw.amount }}
                  </span>
                </div>
                <div class="text-end">
                   <div style="font-size: 0.6rem;" class="text-medium-emphasis text-uppercase font-weight-bold mb-n1">Método</div>
                  <div class="text-caption font-weight-bold">
                    {{ translateMethod(item.raw.method) }}
                  </div>
                </div>
              </div>
            </VCard>
          </div>
        </template>
        <template v-slot:no-data>
          <div class="pa-8 text-center text-medium-emphasis">
            No hay pagos registrados
          </div>
        </template>
      </VDataIterator>

       <!-- Paginación Móvil -->
      <div class="pa-4 border-t d-flex justify-center">
        <VPagination
          v-model="props.page"
          :length="Math.ceil(props.totalPayments / props.itemsPerPage)"
          size="small"
          total-visible="5"
          @update:model-value="(p) => emit('update:options', { ...props, page: p })"
        />
      </div>
    </template>

    <VDataTableServer
      v-else
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.payments"
      :items-length="props.totalPayments"
      :loading="props.loading"
      class="text-no-wrap"
      fixed-header
      height="auto"
      @update:options="(options) => emit('update:options', options)"
    >
      <template v-slot:loading>
        <tr v-for="n in 5" :key="n" class="border-b">
          <td><VSkeletonLoader type="text" class="mx-auto" style="max-inline-size: 80px;" /></td>
          <td><VSkeletonLoader type="text" class="mx-auto" style="max-inline-size: 140px;" /></td>
          <td><VSkeletonLoader type="text" class="mx-auto" style="max-inline-size: 100px;" /></td>
          <td><VSkeletonLoader type="text" class="mx-auto" style="max-inline-size: 70px;" /></td>
          <td><VSkeletonLoader type="text" class="mx-auto" style="max-inline-size: 80px;" /></td>
          <td><VSkeletonLoader type="text" class="mx-auto" style="max-inline-size: 40px;" /></td>
          <td><VSkeletonLoader type="text" class="mx-auto" style="max-inline-size: 90px;" /></td>
        </tr>
      </template>

      <template v-slot:item.date="{ item }">
        <span>{{ item.date ? item.date.split(" ")[0] : "N/A" }}</span>
      </template>

      <template v-slot:item.client="{ item }">
        {{ item.client }}
      </template>

      <template v-slot:item.seller="{ item }">
        {{ item.seller }}
      </template>

      <template v-slot:item.amount="{ item }">
        <span class="font-weight-black text-success">{{ item.amount }}</span>
      </template>

      <template v-slot:item.method="{ item }">
        <VChip size="small" variant="tonal" color="info" class="font-weight-medium">
          {{ translateMethod(item.method) }}
        </VChip>
      </template>
    </VDataTableServer>
  </VCard>
</template>

<style scoped>
:deep(.v-data-table-server .v-data-table-header__content span) {
  font-weight: 700 !important;
  text-transform: uppercase !important;
}

:deep(.v-data-table-server .v-table__th) {
  background-color: white !important;
}

.truncate {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 150px;
}
</style>
