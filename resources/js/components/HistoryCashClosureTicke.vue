<script setup>
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import { formatDateTime } from "@/utils/formatDateTime";
import PaymentTable from "@/components/PaymentTable.vue";
import { computed, defineProps } from "vue";
import TicketHeader from "@/components/TicketHeader.vue";
import { formatCurrency } from "@/utils/currencyFormatter";

const props = defineProps({
  orderData: { type: Object, required: true },
  cashData: { type: Object, required: true },
  isPdf: { type: Boolean, default: true },
});

const logoSrc = computed(() => BASE64_LOGO_DATA);

</script>
<template>
  <div style="width: 100%">
    <VCard variant="outlined" class="pa-2 text-start ticket-bold">
      <TicketHeader :logoSrc="logoSrc" />

      <table style="width: 100%; margin: 5px 0">
        <tbody>
          <tr>
            <td style="text-align: left">
              <span>Cierre de caja N°: {{ props.cashData.id }}</span>
            </td>
            <td style="text-align: right">
              <span
                >Fecha:
                {{ formatDateTime(props.cashData.closing_date, "date") }}</span
              >
            </td>
          </tr>
        </tbody>
      </table>

      <table style="width: 100%; margin-bottom: 5px">
        <tbody>
          <tr>
            <td style="text-align: left">
              <span>Vendedor:</span>
            </td>
            <td style="text-align: right">
              <span>{{ props.cashData.seller_id }}</span>
            </td>
          </tr>
        </tbody>
      </table>

      <div class="pa-2">
        <div style="text-align: center">
          <hr
            style="
              width: 100%;
              border: 1px solid #ccc;
              display: inline-block;
              vertical-align: middle;
            "
          />
        </div>
        <table style="width: 100%; margin-top: 10px">
          <thead class="theadS">
            <tr>
              <th>#</th>
              <th>Productos</th>
              <th>Precio</th>
            </tr>
          </thead>
          <tbody class="tbody-bordered">
            <tr v-for="order in props.orderData" :key="order.id">
              <td>{{ order.id }}</td>
              <td>
                <p v-for="detail in order.details" :key="detail.id">
                  {{ detail.quantity }}x {{ detail.product.name }} ({{
                    detail.product_id
                  }})
                </p>
              </td>
              <td>
                <p v-for="detail in order.details" :key="detail.id">
                  {{ formatCurrency(parseFloat(detail.price), order.currency.toUpperCase()) }}
                </p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </VCard>
  </div>
</template>
