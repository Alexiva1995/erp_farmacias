<script setup>
import { ref, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from "@/plugins/sweetalert";

const fiscalMode = ref('')
const specialStatus = ref('')
const allForeignSalesSpe = ref(false)
const blindCashClosure = ref(false)
const businessType = ref('pharmacy')
const tpvMode = ref('complete')

const configFiscal = [
  { label: "Demo", value: "demo" },
  { label: "Activa", value: "activa" },
]

const specialTaxpayerOptions = [
  { label: "Desactivada", value: "desactivada" },
  { label: "Activa", value: "activa" },
]

const businessTypeOptions = [
  { label: "Farmacia", value: "pharmacy" },
  { label: "Restaurante", value: "restaurant" },
  { label: "Alquiler Deportivo", value: "sports_rental" },
  { label: "Mini Market (Accesorios y Cosméticos)", value: "minimarket" },
]

const fetchSettings = async () => {
  try {
    const response = await axios.get('/general-settings')
    const settings = response.data.data
    fiscalMode.value = settings.fiscal_mode
    specialStatus.value = settings.special_taxpayer_status
    allForeignSalesSpe.value = !!settings.all_foreign_sales_spe
    blindCashClosure.value = !!settings.blind_cash_closure
    businessType.value = settings.business_type || 'pharmacy'
    tpvMode.value = settings.tpv_mode || 'complete'
  } catch (error) {
    console.error("Error cargando configuración:", error)
    toast.success("Error al cargar la configuración")
  }
}

const updateSettings = async () => {
  try {
    await axios.post('/general-settings', {
      fiscal_mode: fiscalMode.value,
      special_taxpayer_status: specialStatus.value,
      all_foreign_sales_spe: allForeignSalesSpe.value,
      blind_cash_closure: blindCashClosure.value,
      business_type: businessType.value,
      tpv_mode: tpvMode.value
    })
    toast.success("Configuración actualizada existosamente")
  } catch (error) {
    console.error("Error al guardar:", error)
    toast.success("Error al actualizada la configuración")
  }
}

onMounted(() => {
  fetchSettings()
})
</script>

<template>
  <VCard class="mb-6">
    <VCardItem class="py-2">
      <VCardTitle class="text-h6"> Tipo de Negocio </VCardTitle>
      <VRadioGroup
        v-model="businessType"
        inline
        @update:model-value="updateSettings"
      >
        <VRadio   
          v-for="item in businessTypeOptions"
          :key="item.value"
          :label="item.label"
          :value="item.value"
        />
      </VRadioGroup>
    </VCardItem>

    <VCardItem v-if="businessType !== 'minimarket'" class="py-2">
      <VCardTitle class="text-h6"> Configuración Fiscal </VCardTitle>
      <VRadioGroup
        v-model="fiscalMode"
        inline
        @update:model-value="updateSettings"
      >
        <VRadio   
          v-for="item in configFiscal"
          :key="item.value"
          :label="item.label"
          :value="item.value"
        />
      </VRadioGroup>
    </VCardItem>

    <VCardItem v-if="businessType !== 'minimarket'" class="py-2">
      <VCardTitle class="text-h6">Sujeto pasivo especial </VCardTitle>
      <VRadioGroup
        v-model="specialStatus"
        inline
        @update:model-value="updateSettings"
      >
        <VRadio   
          v-for="item in specialTaxpayerOptions"
          :key="item.value"
          :label="item.label"
          :value="item.value"
        />
      </VRadioGroup>
    </VCardItem>
    <VCardItem v-if="businessType !== 'minimarket'" class="py-2">
      <VCardTitle class="text-h6"> Recargo SPE Global </VCardTitle>
      <VSwitch
        v-model="allForeignSalesSpe"
        label="Aplicar recargo SPE a TODAS las ventas extranjeras (USD/COP)"
        class="mt-2"
        @update:model-value="updateSettings"
      />
    </VCardItem>
    <VCardItem class="py-2">
      <VCardTitle class="text-h6"> Modalidad de Cierre de Caja </VCardTitle>
      <VSwitch
        v-model="blindCashClosure"
        label="Habilitar Cierre de Caja Ciego (Ocultar tablas de reportes a los usuarios)"
        class="mt-2"
        @update:model-value="updateSettings"
      />
    </VCardItem>
  </VCard>
</template>
