<script setup>
import { useDisplay } from "vuetify";

const props = defineProps({
  sellerCash: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalSellerCash: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "print-cash", "download-cash"]);

const { mobile } = useDisplay();

const headers = [
  { title: "Vendedor", key: "seller.username", sortable: true },
  { title: "CUSD", key: "usd_credit", sortable: true, align: "end" },
  { title: "USD", key: "total_usd", sortable: true, align: "end" },
  { title: "E. USD", key: "usd_delivered", sortable: true, align: "end" },
  { title: "COP", key: "total_cop", sortable: true, align: "end" },
  { title: "E. COP", key: "cop_delivered", sortable: true, align: "end" },
  { title: "Bs PM", key: "bs_mobile", sortable: true, align: "end" },
  { title: "Banco Bs", key: "bs_card", sortable: false, align: "end" },
  { title: "Bs.", key: "total_bs", sortable: true, align: "end" },
  { title: "Total USD", key: "total_sales", sortable: true, align: "end" },
  { title: "Estado", key: "status", sortable: true, align: "center" },
  {
    title: "Acciones",
    key: "actions",
    sortable: false,
    align: "center",
    width: "120px",
  },
];

const statusMap = {
  closed: { label: "Cerrada", color: "success", icon: "tabler-lock" },
  open: { label: "Abierta", color: "warning", icon: "tabler-lock-open" },
};

const fmtUsd = (val) =>
  new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(val ?? 0) + " USD";
const fmtCop = (val) =>
  Math.round(val ?? 0)
    .toString()
    .replace(/\B(?=(\d{3})+(?!\d))/g, ".") + " COP";
const fmtBs = (val) =>
  new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(val ?? 0) + " Bs.";

const formatUsername = (username) => {
  if (!username) return "—";
  const parts = username
    .replace(/[._]/g, " ")
    .split(" ")
    .filter(word => word.length > 0);
  
  // Tomar solo los dos primeros elementos (Primer Nombre y Primer Apellido)
  return parts.slice(0, 2)
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
    .join(" ");
};

const getAvatarColor = (id) => {
  const colors = [
    "primary",
    "secondary",
    "success",
    "info",
    "warning",
    "error",
  ];
  return colors[id % colors.length];
};
</script>

<template>
  <div>
    <!-- Vista Escritorio -->
    <VCard
      v-if="!mobile"
      class="rounded-lg border shadow-sm overflow-hidden bg-surface"
    >
      <VCardItem class="pa-4 pb-0">
        <template #prepend>
          <VAvatar color="primary" variant="tonal" size="38" class="rounded-lg">
            <VIcon icon="tabler-users" size="20" />
          </VAvatar>
        </template>
        <VCardTitle class="text-subtitle-1 font-weight-black uppercase"
          >Cierres por Vendedor</VCardTitle
        >
        <VCardSubtitle class="text-xs font-weight-medium text-disabled"
          >Resumen de cierres individuales del período</VCardSubtitle
        >
      </VCardItem>

      <VDataTableServer
        :items-per-page="props.itemsPerPage"
        :page="props.page"
        :headers="headers"
        :items="props.sellerCash"
        :items-length="props.totalSellerCash"
        :loading="props.loading"
        no-data-text="No hay cierres registrados para el período seleccionado"
        class="text-no-wrap premium-table"
        @update:options="(opt) => emit('update:options', opt)"
      >
        <!-- Vendedor -->
        <template #item.seller.username="{ item }">
          <div class="d-flex align-center gap-3 py-2">
            <VAvatar
              size="32"
              :color="getAvatarColor(item.id)"
              variant="tonal"
              class="rounded-lg font-weight-black text-xs"
            >
              {{ (item.seller?.username ?? "?").charAt(0).toUpperCase() }}
            </VAvatar>
            <div class="d-flex flex-column">
              <span class="text-sm font-weight-black text-primary">{{
                formatUsername(item.seller?.username)
              }}</span>
              <span class="text-xs font-weight-medium text-disabled uppercase"
                >ID: {{ item.id }}</span
              >
            </div>
          </div>
        </template>

        <!-- Monedas -->
        <template #item.usd_credit="{ item }">
          <span class="text-sm font-weight-bold text-error">{{
            fmtUsd(item.usd_credit)
          }}</span>
        </template>
        <template #item.usd_delivered="{ item }">
          <span class="text-sm font-weight-bold">{{
            fmtUsd(item.usd_delivered)
          }}</span>
        </template>
        <template #item.total_usd="{ item }">
          <span class="text-sm font-weight-bold text-primary">{{
            fmtUsd(item.total_usd)
          }}</span>
        </template>
        <template #item.total_cop="{ item }">
          <span class="text-sm font-weight-bold text-success">{{
            fmtCop(item.total_cop)
          }}</span>
        </template>
        <template #item.total_bs="{ item }">
          <span class="text-sm font-weight-bold text-warning">{{
            fmtBs(item.total_bs)
          }}</span>
        </template>
        <template #item.cop_delivered="{ item }">
          <span class="text-sm font-weight-bold">{{
            fmtCop(item.cop_delivered)
          }}</span>
        </template>
        <template #item.bs_mobile="{ item }">
          <span class="text-xs font-weight-medium text-info">{{
            fmtBs(item.bs_mobile)
          }}</span>
        </template>
        <template #item.bs_card="{ item }">
          <span class="text-xs font-weight-medium text-info">{{
            fmtBs(parseFloat(item.bs_card_debito || 0) + parseFloat(item.bs_card_credit || 0) + parseFloat(item.bs_transfer || 0))
          }}</span>
        </template>
        <template #item.total_sales="{ item }">
          <VChip
            size="x-small"
            variant="flat"
            color="primary"
            class="font-weight-black rounded px-2"
          >
            {{ fmtUsd(item.total_sales) }}
          </VChip>
        </template>

        <!-- Estado -->
        <template #item.status="{ item }">
          <VTooltip :text="statusMap[item.status]?.label ?? item.status" location="top">
            <template #activator="{ props: tip }">
              <VAvatar
                v-bind="tip"
                :color="statusMap[item.status]?.color ?? 'default'"
                variant="tonal"
                size="28"
                class="rounded-lg"
              >
                <VIcon
                  :icon="statusMap[item.status]?.icon"
                  size="18"
                />
              </VAvatar>
            </template>
          </VTooltip>
        </template>

        <!-- Acciones -->
        <template #item.actions="{ item }">
          <div class="d-flex align-center justify-center gap-1">
            <VTooltip text="Ver / Imprimir" location="top">
              <template #activator="{ props: tip }">
                <VBtn
                  v-bind="tip"
                  icon="tabler-printer"
                  size="32"
                  variant="text"
                  color="info"
                  class="rounded-lg"
                  @click="emit('print-cash', item)"
                />
              </template>
            </VTooltip>
            <VTooltip text="Descargar PDF" location="top">
              <template #activator="{ props: tip }">
                <VBtn
                  v-bind="tip"
                  icon="tabler-file-type-pdf"
                  size="32"
                  variant="text"
                  color="primary"
                  class="rounded-lg"
                  @click="emit('download-cash', item)"
                />
              </template>
            </VTooltip>
          </div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Vista Móvil Cards -->
    <div v-else class="d-flex flex-column gap-4">
      <VCard
        v-for="item in props.sellerCash"
        :key="item.id"
        class="rounded-lg border shadow-sm premium-card overflow-hidden"
      >
        <div
          class="premium-card-decoration"
          :class="
            item.status === 'closed'
              ? 'bg-success-opacity'
              : 'bg-warning-opacity'
          "
        ></div>

        <VCardText class="pa-5">
          <div class="d-flex align-center justify-space-between mb-4">
            <div class="d-flex align-center gap-3">
              <VAvatar
                size="42"
                :color="getAvatarColor(item.id)"
                variant="tonal"
                class="rounded-lg font-weight-black text-sm"
              >
                {{ (item.seller?.username ?? "?").charAt(0).toUpperCase() }}
              </VAvatar>
              <div class="d-flex flex-column">
                <span class="text-sm font-weight-black leading-tight">{{
                  formatUsername(item.seller?.username)
                }}</span>
                <span class="text-sm font-weight-black text-disabled uppercase"
                  >Cierre {{ item.id }}</span
                >
              </div>
            </div>
            <VChip
              :color="statusMap[item.status]?.color ?? 'default'"
              variant="flat"
              size="x-small"
              class="font-weight-black rounded px-2"
            >
              {{ statusMap[item.status]?.label ?? item.status }}
            </VChip>
          </div>

          <VDivider class="mb-4 opacity-10" />

          <!-- Grid de Totales -->
          <div class="d-flex flex-column gap-2 mb-4">
            <div class="d-flex justify-space-between align-center px-2">
              <span class="text-xs text-disabled font-weight-bold uppercase"
                >Créditos USD (CUSD)</span
              >
              <span class="text-xs font-weight-black text-error">{{
                fmtUsd(item.usd_credit)
              }}</span>
            </div>
            <div class="d-flex justify-space-between align-center px-2">
              <span class="text-xs text-disabled font-weight-bold uppercase"
                >Efectivo USD</span
              >
              <span class="text-xs font-weight-black">{{
                fmtUsd(item.usd_delivered)
              }}</span>
            </div>
            <VDivider class="my-1 opacity-5" />
            <div class="d-flex justify-space-between align-center px-2">
              <span class="text-xs text-disabled font-weight-bold uppercase"
                >Venta USD</span
              >
              <span class="text-xs font-weight-black text-primary">{{
                fmtUsd(item.total_usd)
              }}</span>
            </div>
            <div class="d-flex justify-space-between align-center px-2">
              <span class="text-xs text-disabled font-weight-bold uppercase"
                >Venta COP</span
              >
              <span class="text-xs font-weight-black text-success">{{
                fmtCop(item.total_cop)
              }}</span>
            </div>
            <div class="d-flex justify-space-between align-center px-2">
              <span class="text-xs text-disabled font-weight-bold uppercase"
                >Venta Bs.</span
              >
              <span class="text-xs font-weight-black text-warning">{{
                fmtBs(item.total_bs)
              }}</span>
            </div>
            <VDivider class="my-1 opacity-5" />
            <div class="d-flex justify-space-between align-center px-2">
              <span class="text-xs text-disabled font-weight-bold uppercase"
                >Efectivo COP</span
              >
              <span class="text-xs font-weight-black">{{
                fmtCop(item.cop_delivered)
              }}</span>
            </div>
            <div class="d-flex justify-space-between align-center px-2">
              <span class="text-xs text-disabled font-weight-bold uppercase"
                >Pago Móvil Bs.</span
              >
              <span class="text-xs font-weight-black text-info">{{
                fmtBs(item.bs_mobile)
              }}</span>
            </div>
            <div class="d-flex justify-space-between align-center px-2">
              <span class="text-xs text-disabled font-weight-bold uppercase"
                >Banco Bs.</span
              >
              <span class="text-xs font-weight-black text-info">{{
                fmtBs(parseFloat(item.bs_card_debito || 0) + parseFloat(item.bs_card_credit || 0) + parseFloat(item.bs_transfer || 0))
              }}</span>
            </div>
          </div>

          <div
            class="bg-primary-gradient pa-3 rounded-lg d-flex justify-space-between align-center shadow-sm mb-4"
          >
            <span class="text-xs font-weight-black text-white uppercase"
              >Venta Total (USD)</span
            >
            <span class="text-sm font-weight-black text-white">{{
              fmtUsd(item.total_sales)
            }}</span>
          </div>

          <!-- Acciones Móvil -->
          <div class="d-flex align-center gap-2">
            <VBtn
              block
              color="primary"
              variant="tonal"
              class="rounded-lg font-weight-black text-xs h-10 flex-grow-1"
              prepend-icon="tabler-printer"
              @click="emit('print-cash', item)"
            >
              IMPRIMIR
            </VBtn>
            <VBtn
              icon="tabler-file-type-pdf"
              color="error"
              variant="tonal"
              class="rounded-lg"
              size="40"
              min-width="40"
              @click="emit('download-cash', item)"
            />
          </div>
        </VCardText>
      </VCard>

      <VAlert
        v-if="props.sellerCash.length === 0"
        type="info"
        variant="tonal"
        class="rounded-lg"
      >
        No hay cierres de vendedores en este periodo.
      </VAlert>
    </div>
  </div>
</template>

<style scoped>
.premium-table :deep(.v-data-table-header th) {
  background: white !important;
  color: rgba(
    var(--v-theme-on-surface),
    var(--v-high-emphasis-opacity)
  ) !important;
  block-size: 44px !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.05rem !important;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.05) !important;
}

.premium-table :deep(.v-data-table__td) {
  padding-block: 12px !important;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.03) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
}

.bg-primary-gradient {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    #9575cd 100%
  );
}

.premium-card {
  position: relative;
}

.premium-card-decoration {
  position: absolute;
  border-radius: 6px;
  block-size: 60px;
  inline-size: 60px;
  inset-block-start: 0;
  inset-inline-end: 8px;
}

.bg-success-opacity {
  background: linear-gradient(
    135deg,
    rgba(var(--v-theme-success), 0.1) 0%,
    transparent 100%
  );
}

.bg-warning-opacity {
  background: linear-gradient(
    135deg,
    rgba(var(--v-theme-warning), 0.1) 0%,
    transparent 100%
  );
}

.h-10 {
  block-size: 40px !important;
}

.leading-tight {
  line-height: 1.25;
}
</style>
