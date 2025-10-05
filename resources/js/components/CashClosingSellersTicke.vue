<script setup>
import { defineProps, defineEmits, computed, nextTick } from "vue";
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import TicketHeader from "@/components/TicketHeader.vue";

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
        grouped[sellerKey] = chunkArray(props.monthlyCashDataSellers[sellerKey], 2);
    }
    return grouped;
});

</script>

<template>
  <VCard variant="outlined" class="pa-2 text-start">
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
      
      <table style="width: 100%; padding-top: 20px;">
        <tbody>
          <tr v-for="(pair, rowIndex) in cashGroups" :key="rowIndex">
            
            <td 
              v-for="(cash, colIndex) in pair" 
              :key="colIndex" 
              style="width: 50%; vertical-align: top;"
            >
              <span style="display: block; margin-bottom: 5px;">Cierre N° {{ cash.id }}</span>
              
              <table style="width: 100%;">
              <tbody>
                <tr>
                  <td style="text-align: left"><span>USD:</span></td>
                  <td style="text-align: right">
                    <span>{{ cash.total_usd }}$</span>
                  </td>
                  <td style="text-align: right">
                    <span>{{cash.total_usd }}$</span>
                  </td>
                </tr>
                <tr>
                  <td style="text-align: left"><span>BS:</span></td>
                  <td style="text-align: right">
                    <span>{{ cash.total_bs }}BS</span>
                  </td>
                  <td style="text-align: right">
                    <span>{{ cash.total_bs_in_usd}}$</span>
                  </td>
                </tr>
                <tr>
                  <td style="text-align: left"><span>COP:</span></td>
                  <td style="text-align: right">
                    <span>{{ cash.total_cop }}COP</span>
                  </td>
                  <td style="text-align: right">
                    <span>{{ cash.total_cop_in_usd }}$</span>
                  </td>
                </tr>
                <tr style="margin-bottom: 2px;">
                    <td style="text-align: left;"><span></span></td>
                    <td style="text-align: right; font-weight: bold;"><span>TOTAL VENTA</span></td>
                    <td style="text-align: right; font-weight: bold;">
                      <span>{{ cash.total_sales}}$</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </td>
            <td v-if="pair.length === 1" style="width: 50%;"></td>
          </tr>
        </tbody>
      </table>
    </div>
  </VCard>
</template>
