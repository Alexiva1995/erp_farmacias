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
  <VCard variant="flat" border class="rounded-xl overflow-hidden shadow-sm">
    <template v-if="props.mobile">
       <VDataIterator
        :items="props.payments"
        :items-per-page="props.itemsPerPage"
        :loading="props.loading"
      >
        <template v-slot:default="{ items }">
          <div class="pa-4 d-flex flex-column gap-4">
            <VCard
              v-for="item in items"
              :key="item.raw.id"
              variant="flat"
              border
              class="rounded-lg pa-4"
            >
              <div class="d-flex justify-space-between align-center mb-2">
                <span class="text-caption font-weight-bold text-primary">ID: #{{ item.raw.id }}</span>
                <VChip size="x-small" color="secondary" variant="tonal" class="font-weight-bold">
                  {{ item.raw.currency }}
                </VChip>
              </div>

              <div class="mb-3">
                <div class="text-body-2 font-weight-bold mb-1">
                  {{ item.raw.client?.name }} {{ item.raw.client?.last_name }}
                </div>
                <div class="d-flex align-center gap-2 mb-1 text-medium-emphasis">
                  <VIcon size="14">tabler-user-check</VIcon>
                  <span class="text-caption">{{ item.raw.seller?.username }}</span>
                </div>
                <div class="d-flex align-center gap-2 text-medium-emphasis">
                  <VIcon size="14">tabler-calendar</VIcon>
                  <span class="text-caption">{{ item.raw.date ? item.raw.date.split(" ")[0] : "N/A" }}</span>
                </div>
              </div>

              <VDivider class="border-dashed mb-3" />

              <div class="d-flex justify-space-between align-center">
                <div>
                  <div class="text-caption text-medium-emphasis mb-n1">Monto Pagado</div>
                  <div class="text-h6 font-weight-black text-success">
                    {{ item.raw.amount }}
                  </div>
                </div>
                <div class="text-end">
                   <div class="text-caption text-medium-emphasis mb-n1">Método</div>
                  <div class="text-body-2 font-weight-bold">
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
      <!-- Skeleton Loader -->
      <template v-slot:loading>
        <VSkeletonLoader
          v-for="n in 5"
          :key="n"
          type="table-row"
          class="border-b"
        />
      </template>

      <template v-slot:item.date="{ item }">
        <span>{{ item.date ? item.date.split(" ")[0] : "N/A" }}</span>
      </template>

      <template v-slot:item.client="{ item }">
        {{ item.client?.name }} {{ item.client?.last_name }}
      </template>

      <template v-slot:item.seller="{ item }">
        {{ item.seller?.username }}
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
