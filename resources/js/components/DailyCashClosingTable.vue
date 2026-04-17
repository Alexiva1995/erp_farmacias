<script setup>
import { useDisplay } from "vuetify";

const props = defineProps({
  dailyCash: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalDailyCash: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits([
  "update:options",
  "view-cash",
  "delivery",
  "reference",
  "closing-daily",
]);

const { mobile } = useDisplay();

const headers = [
  { title: "Fecha", key: "date", sortable: true },
  { title: "CUSD", key: "total_credits", sortable: true, align: "end" },
  { title: "USD", key: "total_usd", sortable: true, align: "end" },
  { title: "E. USD", key: "usd_delivered", sortable: true, align: "end" },
  { title: "COP", key: "total_cop", sortable: true, align: "end" },
  { title: "E. COP", key: "cop_delivered", sortable: true, align: "end" },
  { title: "Bs PM", key: "bs_mobile", sortable: true, align: "end" },
  { title: "Bs Tarjeta", key: "bs_card", sortable: true, align: "end" },
  { title: "Bs.", key: "total_bs", sortable: true, align: "end" },
  { title: "Total USD", key: "total_sales", sortable: true, align: "end" },
  {
    title: "Acciones",
    key: "actions",
    sortable: false,
    align: "center",
    width: "140px",
  },
];

const fmtDate = (v) => {
  if (!v) return "—";
  const date = new Date(v);
  const days = ["DOM", "LUN", "MAR", "MIÉ", "JUE", "VIE", "SÁB"];
  const months = [
    "ENE",
    "FEB",
    "MAR",
    "ABR",
    "MAY",
    "JUN",
    "JUL",
    "AGO",
    "SEP",
    "OCT",
    "NOV",
    "DIC",
  ];
  return `${days[date.getDay()]} ${date.getDate()} ${months[date.getMonth()]}`;
};

const fmtUsd = (v) =>
  new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(v ?? 0);
const fmtCop = (v) =>
  Math.round(v ?? 0)
    .toString()
    .replace(/\B(?=(\d{3})+(?!\d))/g, ".");
const fmtBs = (v) =>
  new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(v ?? 0);

const getAvatarColor = (id) => {
  const colors = ["primary", "success", "info", "warning", "purple", "cyan"];
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
          <VAvatar color="info" variant="tonal" size="38" class="rounded-lg">
            <VIcon icon="tabler-calendar-check" size="20" />
          </VAvatar>
        </template>
        <VCardTitle class="text-subtitle-1 font-weight-black uppercase"
          >Cierres Diarios</VCardTitle
        >
        <VCardSubtitle class="text-xs font-weight-medium text-disabled"
          >Consolidado de ventas por día con detalle de entregas</VCardSubtitle
        >
      </VCardItem>

      <VDataTableServer
        :items-per-page="props.itemsPerPage"
        :page="props.page"
        :headers="headers"
        :items="props.dailyCash"
        :items-length="props.totalDailyCash"
        :loading="props.loading"
        no-data-text="No hay cierres diarios registrados"
        class="text-no-wrap premium-table"
        @update:options="(opt) => emit('update:options', opt)"
      >
        <template #item.date="{ item }">
          <div class="d-flex align-center gap-3 py-2">
            <VAvatar
              size="32"
              :color="getAvatarColor(item.id)"
              variant="tonal"
              class="rounded-lg font-weight-black text-xs"
            >
              <VIcon icon="tabler-calendar" size="16" />
            </VAvatar>
            <span class="text-sm font-weight-black uppercase">{{
              fmtDate(item.created_at)
            }}</span>
          </div>
        </template>

        <template #item.total_credits="{ item }">
          <span class="text-sm font-weight-bold text-error">{{
            fmtUsd(item.total_credits)
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
            fmtBs(item.bs_card)
          }}</span>
        </template>
        <template #item.total_bs="{ item }">
          <span class="text-sm font-weight-bold text-warning">{{
            fmtBs(item.total_bs)
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

        <template #item.actions="{ item }">
          <div class="d-flex align-center justify-center gap-1">
            <VTooltip text="Ver Detalle" location="top">
              <template #activator="{ props: tip }">
                <VBtn
                  v-bind="tip"
                  icon="tabler-eye"
                  size="32"
                  variant="text"
                  color="info"
                  class="rounded-lg"
                  @click="emit('view-cash', item)"
                />
              </template>
            </VTooltip>
            <VTooltip text="Entregas" location="top">
              <template #activator="{ props: tip }">
                <VBtn
                  v-bind="tip"
                  icon="tabler-box"
                  size="32"
                  variant="text"
                  color="success"
                  class="rounded-lg"
                  @click="emit('delivery', item)"
                />
              </template>
            </VTooltip>
            <VTooltip text="Referencias" location="top">
              <template #activator="{ props: tip }">
                <VBtn
                  v-bind="tip"
                  icon="tabler-clipboard-list"
                  size="32"
                  variant="text"
                  color="secondary"
                  class="rounded-lg"
                  @click="emit('reference', item)"
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
        v-for="item in props.dailyCash"
        :key="item.id"
        class="rounded-lg border shadow-sm premium-card overflow-hidden"
      >
        <VCardText class="pa-5">
          <div class="d-flex align-center justify-space-between mb-4">
            <div class="d-flex align-center gap-3">
              <VAvatar
                size="42"
                :color="getAvatarColor(item.id)"
                variant="tonal"
                class="rounded-lg"
              >
                <VIcon icon="tabler-calendar-event" size="20" />
              </VAvatar>
              <div class="d-flex flex-column">
                <span
                  class="text-sm font-weight-black leading-tight uppercase"
                  >{{ fmtDate(item.created_at) }}</span
                >
                <span class="text-xs text-disabled font-weight-bold uppercase"
                  >Consolidado Diario {{ item.id }}</span
                >
              </div>
            </div>
            <VChip
              color="info"
              variant="tonal"
              size="x-small"
              class="font-weight-black rounded px-2"
            >
              DIARIO
            </VChip>
          </div>

          <VDivider class="mb-4 opacity-10" />

          <!-- Grid de Totales -->
          <div class="d-flex flex-column gap-2 mb-4">
            <div class="d-flex justify-space-between align-center px-2">
              <span class="text-xs text-disabled font-weight-bold uppercase"
                >Créditos (CUSD)</span
              >
              <span class="text-xs font-weight-black text-error">{{
                fmtUsd(item.total_credits)
              }}</span>
            </div>
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
                >E. USD (Físico)</span
              >
              <span class="text-xs font-weight-black">{{
                fmtUsd(item.usd_delivered)
              }}</span>
            </div>
            <VDivider class="my-1 opacity-5" />
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
                >E. COP (Físico)</span
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
                >Tarjeta Bs.</span
              >
              <span class="text-xs font-weight-black text-info">{{
                fmtBs(item.bs_card)
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
              color="primary"
              variant="tonal"
              class="rounded-lg font-weight-black text-xs h-10 flex-grow-1"
              prepend-icon="tabler-eye"
              @click="emit('view-cash', item)"
            >
              DETALLES
            </VBtn>
            <VBtn
              icon="tabler-box"
              color="success"
              variant="tonal"
              class="rounded-lg"
              size="40"
              min-width="40"
              @click="emit('delivery', item)"
            />
            <VBtn
              icon="tabler-clipboard-list"
              color="secondary"
              variant="tonal"
              class="rounded-lg"
              size="40"
              min-width="40"
              @click="emit('reference', item)"
            />
          </div>
        </VCardText>
      </VCard>

      <VAlert
        v-if="props.dailyCash.length === 0"
        type="info"
        variant="tonal"
        class="rounded-lg"
      >
        No hay registros diarios encontrados.
      </VAlert>
    </div>
  </div>
</template>

<style scoped>
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
</style>
