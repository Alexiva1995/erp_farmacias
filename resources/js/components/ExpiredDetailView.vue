<script setup>
import ExpirationsFilters from "@/components/ExpirationsFilters.vue";

const props = defineProps({
  logs: { type: Array, required: true },
  totalLogs: { type: Number, required: true },
  loading: { type: Boolean, default: false },
  page: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  searchQuery: { type: String, default: "" },
});

const emit = defineEmits([
  "update:options",
  "update:searchQuery",
  "activate-offer",
]);

const detailHeaders = [
  { title: "Producto", key: "product_info", sortable: false },
  { title: "Unds.", key: "expired_quantity", align: "end" },
  { title: "Fecha Caducado", key: "created_at" },
  { title: "Acciones", key: "actions", sortable: false, align: "center" },
];

const formatDate = (dateString) => {
  if (!dateString) return "N/A";
  return new Date(dateString).toLocaleDateString("es-CO");
};
</script>

<template>
  <div>
    <ExpirationsFilters
      :search-query="props.searchQuery"
      @update:search-query="(value) => emit('update:searchQuery', value)"
    />

    <VCard>
      <VDataTableServer
        :headers="detailHeaders"
        :items="props.logs"
        :items-length="props.totalLogs"
        :loading="props.loading"
        :page="props.page"
        :items-per-page="props.itemsPerPage"
        class="text-no-wrap"
        @update:options="(options) => emit('update:options', options)"
      >
        <template #item.product_info="{ item }">
          <div class="d-flex align-center gap-x-4">
            <VAvatar
              v-if="item.product?.photo_url"
              size="38"
              variant="tonal"
              rounded
              :image="item.product.photo_url"
            />
            <div class="d-flex flex-column">
              <span class="text-body-1 font-weight-medium text-high-emphasis">{{
                item.product_name
              }}</span>
              <span class="text-sm text-disabled">{{
                item.product?.active_ingredient
              }}</span>
            </div>
          </div>
        </template>

        <template #item.expired_quantity="{ item }">
          <span class="font-weight-medium">{{ item.expired_quantity }}</span>
        </template>

        <template #item.created_at="{ item }">
          <span class="font-weight-medium">{{
            formatDate(item.created_at)
          }}</span>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex gap-1 justify-center">
            <VTooltip text="Activar Oferta">
              <template #activator="{ props: tooltipProps }">
                <IconBtn
                  v-bind="tooltipProps"
                  @click="emit('activate-offer', item)"
                >
                  <VIcon icon="tabler-tag" />
                </IconBtn>
              </template>
            </VTooltip>

            <VTooltip text="Este lote ya ha sido caducado">
              <template #activator="{ props: tooltipProps }">
                <div v-bind="tooltipProps">
                  <IconBtn disabled>
                    <VIcon icon="tabler-calendar-off" />
                  </IconBtn>
                </div>
              </template>
            </VTooltip>
          </div>
        </template>
      </VDataTableServer>
    </VCard>
  </div>
</template>
