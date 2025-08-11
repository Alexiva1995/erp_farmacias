<script setup>
import CreditTable from "@/components/CreditTable.vue";

</script>

<template>
    <CreditTable
      :credits="credits"
      :loading="loading"
      :total-credits="totalCredits"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
    />
</template>
