<script setup>
import { computed, nextTick } from "vue";
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import TicketHeader from "@/components/TicketHeader.vue";
import { formatCurrency } from "@/utils/currencyFormatter";

const props = defineProps({
  monthlyCashDataSellers: {
    type: Object,
    default: () => ({}),
  },
});

const chunkArray = (array, size) => {
  if (!array || !array.length) return [];
  const chunkedArr = [];
  for (let i = 0; i < array.length; i += size) {
    chunkedArr.push(array.slice(i, i + size));
  }
  return chunkedArr;
};

const groupedCashDataSellers = computed(() => {
    const grouped = {};
    for (const sellerKey in props.monthlyCashDataSellers) {
        let sellerClosings = props.monthlyCashDataSellers[sellerKey];
         const filteredClosings = sellerClosings.filter(cash => {
            return parseFloat(cash.total_sales) > 0;
        });
        if (filteredClosings.length > 0) {
            grouped[sellerKey] = chunkArray(filteredClosings, 2);
        }
    }
    return grouped;
});

</script>

<template>
  <VCard variant="outlined" class="pa-2 text-start ticket-bold">
    <TicketHeader :logoSrc="BASE64_LOGO_DATA" />

    <div
      v-for="(cashGroups, sellerKey) in groupedCashDataSellers"
      :key="sellerKey"
    >
      <SectionDivider
        :isPdf="true"
        :text="cashGroups[0][0].seller.username" 
        width="40%"
        class="center-block"
      />
      
      <table style="inline-size: 100%; padding-block-start: 20px;">
        <tbody>
          <tr v-for="(pair, rowIndex) in cashGroups" :key="rowIndex">
            
            <td 
              v-for="(cash, colIndex) in pair" 
              :key="colIndex" 
              style="inline-size: 50%; vertical-align: top;"
            >
              <span style="display: block; margin-block-end: 5px;">Cierre N° {{ cash.id }}</span>
              
              <table style="inline-size: 100%;">
              <tbody>
                <tr>
                  <td style="text-align: start;"><span>USD:</span></td>
                  <td style="text-align: end;">
                    <span>{{formatCurrency(parseFloat(cash.total_usd || 0) + parseFloat(cash.usd_credit || 0))}}$</span>
                  </td>
                  <td style="text-align: end;">
                    <span>{{formatCurrency(parseFloat(cash.total_usd || 0) + parseFloat(cash.usd_credit || 0))}}$</span>
                  </td>
                </tr>
                <tr>
                  <td style="text-align: start;"><span>BS:</span></td>
                  <td style="text-align: end;">
                    <span>{{ cash.total_bs }}BS</span>
                  </td>
                  <td style="text-align: end;">
                    <span>{{formatCurrency(cash.total_bs_in_usd)}}$</span>
                  </td>
                </tr>
                <tr>
                  <td style="text-align: start;"><span>COP:</span></td>
                  <td style="text-align: end;">
                    <span>{{ cash.total_cop }}COP</span>
                  </td>
                  <td style="text-align: end;">
                    <span>{{formatCurrency(cash.total_cop_in_usd)}}$</span>
                  </td>
                </tr>
                <tr style="margin-block-end: 2px;">
                    <td style="text-align: start;"><span></span></td>
                    <td style=" font-weight: bold;text-align: end;"><span>TOTAL VENTA</span></td>
                    <td style=" font-weight: bold;text-align: end;">
                      <span>{{ cash.total_sales}}$</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </td>
            <td v-if="pair.length === 1" style="inline-size: 50%;"></td>
          </tr>
        </tbody>
      </table>
    </div>
  </VCard>
</template>
