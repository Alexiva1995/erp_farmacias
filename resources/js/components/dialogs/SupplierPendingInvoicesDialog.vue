<script setup>
    const props = defineProps({
        modelValue: { type: Boolean, required: true },
        pendingInvoices: { type: Object, default: () => ({}) },
        loading: { type: Boolean, default: false },
    });

    const emit = defineEmits(['update:modelValue'])

    const groupedInvoices = ref({})

    const closeDialog = () => {
        emit("update:modelValue", false);
        groupedInvoices.value = {}
    };

    const supplierPendingInvoiceHeaders = [
        { title: 'ID', key: 'id', sortable: false  },
        { title: '# Factura', key: 'invoice_number', sortable: false  },
        { title: 'Monto', key: 'total_amount', sortable: false  },
    ]

    watch(() => props.pendingInvoices, (newVal) => {
        groupedInvoices.value = newVal || {}
    }, { immediate: true })

    const formatDate = date => {
        const parts = date.split('-')
        return new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2])).toLocaleDateString('es-VE')
    }
</script>
<template>
    <VDialog
        :model-value="props.modelValue"
        max-width="800px"
        persistent
        @update:model-value="closeDialog"
        :scrollable="true"
        content-class="d-flex"
    >
        <VCard class="d-flex flex-column">
            <VCardTitle class="d-flex align-center">
                <span class="text-h5 font-weight-bold">Facturas Pendientes</span>

                <VSpacer />
                
                <VBtn icon variant="text" @click="closeDialog">
                    <VIcon>tabler-x</VIcon>
                </VBtn>
            </VCardTitle>

            <VDivider />

            <VCardText class="flex-grow-1" style="overflow-y: auto;">
                <template v-if="props.loading">
                    <div class="text-center text-medium-emphasis py-8">
                        <VProgressCircular indeterminate color="primary" size="32" />
                        <div class="mt-2">Cargando facturas pendientes...</div>
                    </div>
                </template>

                <template v-else-if="Object.keys(groupedInvoices).length > 0">
                    <template v-for="(invoices, date) in groupedInvoices" :key="date">
                        <h5 class="text-primary mt-4 mb-2">{{ formatDate(date) }}</h5>
                        <VDataTable
                            :headers="supplierPendingInvoiceHeaders"
                            :items="invoices"
                            density="compact"
                            hide-default-footer
                            class="mb-4"
                        />
                        <div class="text-right font-weight-bold text-success">
                            Total: {{ invoices.reduce((sum, i) => sum + Number(i.total_amount), 0).toFixed(2) }}
                        </div>
                    </template>
                </template>

                <template v-else>
                    <div class="text-center text-disabled py-8">Este proveedor no tiene facturas pendientes.</div>
                </template>
            </VCardText>
        </VCard>
    </VDialog>
</template>
