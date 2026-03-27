<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { toast } from "@/plugins/sweetalert";

const fiscalMode = ref('')
const specialStatus = ref('')
const allForeignSalesSpe = ref(false)

const configFiscal = [
  { label: "Demo", value: "demo" },
  { label: "Activa", value: "activa" },
]

const specialTaxpayerOptions = [
  { label: "Desactivada", value: "desactivada" },
  { label: "Activa", value: "activa" },
]

const fetchSettings = async () => {
  try {
    const response = await axios.get('/api/general-settings')
    fiscalMode.value = response.data.fiscal_mode
    specialStatus.value = response.data.special_taxpayer_status
    allForeignSalesSpe.value = !!response.data.all_foreign_sales_spe
  } catch (error) {
    console.error("Error cargando configuración:", error)
    toast.success("Error al cargar la configuración")
  }
}

const updateSettings = async () => {
  try {
    await axios.post('/api/general-settings', {
      fiscal_mode: fiscalMode.value,
      special_taxpayer_status: specialStatus.value,
      all_foreign_sales_spe: allForeignSalesSpe.value
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

    <VCardItem class="py-2">
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
    <VCardItem class="py-2">
      <VCardTitle class="text-h6"> Recargo SPE Global </VCardTitle>
      <VSwitch
        v-model="allForeignSalesSpe"
        label="Aplicar recargo SPE a TODAS las ventas extranjeras (USD/COP)"
        class="mt-2"
        @update:model-value="updateSettings"
      />
    </VCardItem>
  </VCard>
</template>
