<script setup>
import { ref,computed  } from 'vue';
import { formatCurrency } from "@/utils/currencyFormatter";

const props = defineProps({
  cashClosureData: {
    type: Object,
    default: () => ({
      total_usd: "0.00",
      total_bs: "0.00",
      total_cop: "0.00",
      usd_credit: "0.00",
      total_bs_in_usd: "0.00",
      total_cop_in_usd: "0.00",
    }),
  },
});

const emit = defineEmits(['requestCloseCash']);

const isColorDark = (hex) => {
  const r = parseInt(hex.slice(1, 3), 16);
  const g = parseInt(hex.slice(3, 5), 16);
  const b = parseInt(hex.slice(5, 7), 16);
  const luminance = (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255;
  return luminance < 0.8;
};

const menuOptions = [
  { title: 'Cerrar Caja', value: 'closed_cash' },
];

const processedCashData = computed(() => {
  const data = props.cashClosureData;
  const totalUsd = parseFloat(data.total_usd) || 0;
  const totalBs = parseFloat(data.total_bs) || 0;
  const totalBsInUSD = parseFloat(data.total_bs_in_usd) || 0;
  const totalCop = parseFloat(data.total_cop) || 0;
  const totalCopInUSD = parseFloat(data.total_cop_in_usd) || 0;
  const totalCredits = parseFloat(data.usd_credit) || 0;

  const grandTotal = totalUsd + data.total_bs_in_usd + data.total_cop_in_usd + totalCredits;

 if (grandTotal === 0 || isNaN(grandTotal)) {
    return {
      hasData: false,
      items: [
        {
          status: 'Sin porcentajes',
          fullStatus: 'Sin porcentajes',
          amount: 0,
          amountUSD: 0,
          icon: 'tabler-circle-off',
          barColor: '#D9D9D9',
          percentage: 100,
          textColorClass: 'text-black',
          rounded: 'rounded-lg',
        },
      ],
      grandTotal: 0,
    };
  }

  const calculatePercentage = (value) => {
    if (grandTotal === 0) return 0;
    return (value / grandTotal) * 100;
  };

  const colors = {
    usd: '#D9D9D9',    
    bs: '#7F77E3',    
    cop: '#33CCCC',     
    credits: '#343B42', 
  };

  const cashItems = [
    {
      status: 'USD',
      currency: 'USD',
      fullStatus: 'Total USD',
      amount: totalUsd,
      amountUSD: totalUsd,
      icon: 'tabler-currency-dollar',
      barColor: colors.usd,
    },
    {
      status: 'BS',
      currency: 'BS',
      fullStatus: 'Total BS',
      amount: totalBs,
      amountUSD: totalBsInUSD,
      icon: 'tabler-cash',
      barColor: colors.bs,
    },
    {
      status: 'COP',
      currency: 'COP',
      fullStatus: 'Total COP',
      amount: totalCop,
      amountUSD: totalCopInUSD,
      icon: 'tabler-coin',
      barColor: colors.cop,
    },
    {
      status: 'Créd.',
      currency: 'USD',
      fullStatus: 'Total Créditos',
      amount: totalCredits,
      amountUSD: totalCredits,
      icon: 'tabler-credit-card',
      barColor: colors.credits,
    },
  ];


  const hadleCurrency = (value) => {
    if (grandTotal === 0) return 0;
    return (value / grandTotal) * 100;
  };

  cashItems.forEach(item => {
    item.percentage = calculatePercentage(item.amountUSD);
    item.text = item.status;
    item.textColorClass = isColorDark(item.barColor) ? 'text-white' : 'text-black';
  });

  const visibleItems = cashItems.filter(item => item.percentage > 0);
  if (visibleItems.length > 0) {
    visibleItems[0].rounded = 'rounded-e-0 rounded-lg';
    visibleItems[visibleItems.length - 1].rounded = 'rounded-s-0 rounded-lg';
    for (let i = 1; i < visibleItems.length - 1; i++) {
      visibleItems[i].rounded = 'rounded-0';
    }
  }

  return {
    hasData: true,
    items: cashItems,
    grandTotal: grandTotal,
  };
});


const handleMenuClick = (optionValue) => {
  if (optionValue === 'closed_cash') {
    emit('requestCloseCash');
  }
};
</script>
<template>
  <VCard>
    <VCardItem>
      <VCardTitle>Resumen de Caja</VCardTitle>
      <template #append>
        <VBtn
          icon
          variant="text"
          size="small"
          color="default"
        >
          <VIcon size="24" icon="tabler-dots-vertical" />
          <VMenu activator="parent">
            <VList>
              <VListItem
                v-for="(option, i) in menuOptions"
                :key="i"
                :value="option.value"
                @click="handleMenuClick(option.value)"
              >
                <VListItemTitle>{{ option.title }}</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>
        </VBtn>
      </template>
    </VCardItem>

    <VCardText>
      <VProgressLinear
        :model-value="100"
        height="46"
        class="mb-6 rounded-sm"
      >
        <template #default>
          <div class="d-flex w-100 h-100">
            <div
              v-if="!processedCashData.hasData"
              :style="{
                width: '100%',
                backgroundColor: processedCashData.items[0].barColor,
                height: '100%',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
              }"
              :class="processedCashData.items[0].rounded"
            >
              <span class="text-sm font-weight-medium" :class="processedCashData.items[0].textColorClass">
                {{ processedCashData.items[0].status }}
              </span>
            </div>

            <template v-else>
              <div
                v-for="(item, index) in processedCashData.items"
                :key="index"
                :style="{
                  width: item.percentage + '%',
                  backgroundColor: item.barColor,
                  height: '100%',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                }"
                :class="[item.rounded]"
              >
                <span v-if="item.percentage > 0" class="text-sm font-weight-medium" :class="item.textColorClass">
                  {{ item.percentage.toFixed(1) }}% {{ item.status }}
                </span>
              </div>
            </template>
          </div>
        </template>
      </VProgressLinear>

      <div class="cash-details-list">
        <div
          v-for="item in processedCashData.items"
          :key="item.fullStatus"
          class="d-flex align-center justify-space-between py-2"
        >
          <div class="d-flex align-center gap-x-2">
            <VIcon :icon="item.icon" :style="{ color: item.barColor }" size="24" />
            <span class="text-body-1 text-high-emphasis">{{ item.fullStatus }}</span>
          </div>
          <div class="d-flex align-center gap-x-4">
            <span class="text-body-1 text-high-emphasis">
              {{formatCurrency(item.amount, item.currency)}}
            </span>
          </div>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.v-progress-linear :deep(.v-progress-linear__background) {
  display: none;
}
.v-progress-linear :deep(.v-progress-linear__determinate) {
  width: 100% !important;
  background-color: transparent !important;
}
.v-progress-linear :deep(.text-white) {
  color: white !important;
}
.v-progress-linear :deep(.text-black) {
  color: black !important;
}
</style>
