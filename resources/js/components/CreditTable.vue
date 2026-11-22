<script setup>
import { computed } from "vue";
import { useAuthStore } from "@/stores/auth";

const props = defineProps({
  credits: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalCredits: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  mobile: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:options",
  "open-payment-modal",
  "reload",
  "view-order-modal",
  "print-order",
  "delete-credit",
  "whatsapp-reminder",
]);

const headers = [
  { title: "Fecha", key: "credit_date", sortable: true },
  { title: "Documento", key: "client_identification", sortable: false },
  { title: "Nombre", key: "client_full_name", sortable: true },
  { title: "Monto", key: "total_pending_amount", sortable: true },
  { title: "Estado", key: "status", sortable: true },
  { title: "Acciones", key: "action", sortable: false },
];

const authStore = useAuthStore();
const isAdmin = computed(() => authStore.user?.role_id === 1);

const getAgingBadge = (item) => {
  if (item.status === 2 || Number(item.total_pending_amount) <= 0) {
    return null;
  }
  if (item.days_overdue > 0) {
    return {
      text: `Vencido ${item.days_overdue}d`,
      color: "error",
    };
  }
  if (item.days_since_oldest >= 15) {
    return {
      text: `${item.days_since_oldest}d`,
      color: "warning",
    };
  }
  return null;
};
</script>

<template>
  <VCard variant="flat" border class="rounded-lg overflow-hidden shadow-sm">
    <template v-if="props.mobile">
      <VDataIterator
        :items="props.credits"
        :items-per-page="props.itemsPerPage"
        :loading="props.loading"
      >
        <template #default="{ items }">
          <div class="pa-2 d-flex flex-column gap-2">
            <VCard
              v-for="item in items"
              :key="item.raw.client_id"
              variant="flat"
              border
              class="rounded-lg pa-3"
            >
              <div class="d-flex justify-space-between align-start mb-1">
                <div class="d-flex flex-column">
                  <span class="text-caption font-weight-bold text-primary leading-tight">
                    {{ item.raw.client?.identification_type }}{{ item.raw.client?.identification }}
                  </span>
                  <div class="d-flex align-center gap-1 text-medium-emphasis mt-n1">
                    <VIcon size="12">tabler-calendar</VIcon>
                    <span style="font-size: 0.65rem;">
                      {{ item.raw.credit_date ? item.raw.credit_date.split(" ")[0] : "N/A" }}
                    </span>
                  </div>
                </div>
                <div class="d-flex align-center gap-1">
                  <VChip
                    v-if="getAgingBadge(item.raw)"
                    size="x-small"
                    :color="getAgingBadge(item.raw).color"
                    variant="tonal"
                    class="font-weight-bold"
                  >
                    {{ getAgingBadge(item.raw).text }}
                  </VChip>
                  <VChip
                    size="x-small"
                    :color="
                      item.raw.status === 0
                        ? 'error'
                        : item.raw.status === 1
                        ? 'info'
                        : 'success'
                    "
                    variant="tonal"
                    class="font-weight-bold text-uppercase px-1"
                    style="block-size: 18px; font-size: 0.6rem;"
                  >
                    {{
                      item.raw.status === 0
                        ? 'DEBE'
                        : item.raw.status === 1
                        ? 'PARCIAL'
                        : 'PAGADO'
                    }}
                  </VChip>
                </div>
              </div>

              <div class="text-body-2 font-weight-bold mb-2 truncate">
                {{ item.raw.client?.name }} {{ item.raw.client?.last_name }}
              </div>

              <VDivider class="border-dashed mb-2" />

              <div class="d-flex justify-space-between align-center">
                <div class="d-flex flex-column">
                  <span style="font-size: 0.6rem;" class="text-medium-emphasis text-uppercase font-weight-bold mb-n1">Pendiente</span>
                  <span class="text-subtitle-1 font-weight-black text-error">
                    ${{ Number(item.raw.total_pending_amount || 0).toFixed(2) }}
                  </span>
                </div>
                <div class="d-flex gap-1">
                  <VBtn
                    icon="tabler-wallet"
                    variant="tonal"
                    color="success"
                    size="32"
                    class="rounded-circle shadow-sm"
                    :disabled="item.raw.status === 2"
                    @click="emit('open-payment-modal', item.raw)"
                  />
                  <VBtn
                    v-if="item.raw.client?.phone && item.raw.status !== 2"
                    icon="tabler-brand-whatsapp"
                    variant="tonal"
                    color="success"
                    size="32"
                    class="rounded-circle shadow-sm"
                    @click="emit('whatsapp-reminder', item.raw)"
                  />
                  <VBtn
                    icon="tabler-eye"
                    variant="tonal"
                    color="info"
                    size="32"
                    class="rounded-circle shadow-sm"
                    @click="emit('view-order-modal', item.raw)"
                  />
                  <VBtn
                    icon="tabler-printer"
                    variant="tonal"
                    color="secondary"
                    size="32"
                    class="rounded-circle shadow-sm"
                    @click="emit('print-order', item.raw)"
                  />
                  <VBtn
                    v-if="isAdmin"
                    icon="tabler-trash"
                    variant="tonal"
                    color="error"
                    size="32"
                    class="rounded-circle shadow-sm"
                    @click="emit('delete-credit', item.raw)"
                  />
                </div>
              </div>
            </VCard>
          </div>
        </template>
        <template #no-data>
          <div class="pa-8 text-center text-medium-emphasis">
            No hay créditos registrados
          </div>
        </template>
      </VDataIterator>

      <div class="pa-4 border-t d-flex justify-center">
        <VPagination
          :model-value="props.page"
          :length="Math.max(1, Math.ceil(props.totalCredits / props.itemsPerPage))"
          size="small"
          total-visible="5"
          @update:model-value="(p) => emit('update:options', { ...props, page: p, sortBy: [] })"
        />
      </div>
    </template>

    <VDataTableServer
      v-else
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.credits"
      :items-length="props.totalCredits"
      :loading="props.loading"
      class="text-no-wrap"
      fixed-header
      height="auto"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #loading>
        <tr v-for="n in 5" :key="n" class="border-b">
          <td><VSkeletonLoader type="text" class="mx-auto" style="max-inline-size: 80px;" /></td>
          <td><VSkeletonLoader type="text" class="mx-auto" style="max-inline-size: 100px;" /></td>
          <td><VSkeletonLoader type="text" class="mx-auto" style="max-inline-size: 140px;" /></td>
          <td><VSkeletonLoader type="text" class="mx-auto" style="max-inline-size: 70px;" /></td>
          <td class="text-center"><VSkeletonLoader type="text" class="mx-auto" style="max-inline-size: 60px;" /></td>
          <td>
            <div class="d-flex align-center gap-2">
              <VSkeletonLoader type="button" style="max-inline-size: 32px; block-size: 32px;" />
              <VSkeletonLoader type="button" style="max-inline-size: 32px; block-size: 32px;" />
              <VSkeletonLoader type="button" style="max-inline-size: 32px; block-size: 32px;" />
            </div>
          </td>
        </tr>
      </template>

      <template #no-data>
        <div class="pa-8 text-center text-medium-emphasis">
          No hay clientes con créditos para el filtro seleccionado.
        </div>
      </template>

      <template #item.credit_date="{ item }">
        <span>{{ item.credit_date ? item.credit_date.split(" ")[0] : "N/A" }}</span>
      </template>

      <template #item.client_identification="{ item }">
        <span class="text-primary font-weight-black">
          {{ item.client?.identification_type }}{{ item.client?.identification }}
        </span>
      </template>

      <template #item.client_full_name="{ item }">
        <div class="d-flex align-center gap-2">
          <span>{{ item.client?.name }} {{ item.client?.last_name }}</span>
          <VChip
            v-if="getAgingBadge(item)"
            size="x-small"
            :color="getAgingBadge(item).color"
            variant="tonal"
            class="font-weight-bold"
          >
            {{ getAgingBadge(item).text }}
          </VChip>
        </div>
      </template>

      <template #item.total_pending_amount="{ item }">
        <span class="font-weight-black text-error">
          ${{ Number(item.total_pending_amount || 0).toFixed(2) }}
        </span>
      </template>

      <template #item.status="{ item }">
        <VChip
          size="small"
          :color="
            item.status === 0
              ? 'error'
              : item.status === 1
              ? 'info'
              : 'success'
          "
          variant="tonal"
          class="font-weight-bold text-uppercase"
        >
          {{
            item.status === 0
              ? "DEBE"
              : item.status === 1
              ? "PARCIAL"
              : "PAGADO"
          }}
        </VChip>
      </template>

      <template #item.action="{ item }">
        <div class="d-flex align-center gap-1">
          <VTooltip text="Registrar Pago" location="top">
            <template #activator="{ props: tooltipProps }">
              <IconBtn
                v-bind="tooltipProps"
                :disabled="item.status === 2"
                color="success"
                @click="emit('open-payment-modal', item)"
              >
                <VIcon icon="tabler-wallet" />
              </IconBtn>
            </template>
          </VTooltip>

          <VTooltip
            v-if="item.client?.phone && item.status !== 2"
            text="Recordar por WhatsApp"
            location="top"
          >
            <template #activator="{ props: tooltipProps }">
              <IconBtn
                v-bind="tooltipProps"
                color="success"
                @click="emit('whatsapp-reminder', item)"
              >
                <VIcon icon="tabler-brand-whatsapp" />
              </IconBtn>
            </template>
          </VTooltip>

          <VTooltip text="Ver Detalles" location="top">
            <template #activator="{ props: tooltipProps }">
              <IconBtn v-bind="tooltipProps" color="info" @click="emit('view-order-modal', item)">
                <VIcon icon="tabler-eye" />
              </IconBtn>
            </template>
          </VTooltip>

          <VTooltip text="Imprimir Ticket" location="top">
            <template #activator="{ props: tooltipProps }">
              <IconBtn v-bind="tooltipProps" @click="emit('print-order', item)">
                <VIcon icon="tabler-printer" />
              </IconBtn>
            </template>
          </VTooltip>

          <VTooltip v-if="isAdmin" text="Eliminar Crédito" location="top">
            <template #activator="{ props: tooltipProps }">
              <IconBtn
                v-bind="tooltipProps"
                color="error"
                @click="emit('delete-credit', item)"
              >
                <VIcon icon="tabler-trash" />
              </IconBtn>
            </template>
          </VTooltip>
        </div>
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
