<script setup lang="js">
import dayjs from 'dayjs';



const props= defineProps({
  items: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  total: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  // search: { type: String, required: true },
})

const emit= defineEmits(["edit",'delete','update:options',"cambiarEstado"])

const headers = [
  { title: 'id', key: 'id', sortable: true,},
  { title: 'Nombre', key: 'name', value: item => `${item.name} ${(item.last_name==null)?"":item.last_name}`, sortable: true, },
  { title: 'Categoria', key: 'category.name', sortable: false },
  { title: 'Monto', key: 'amount', sortable: true },
  { title: 'USD', key: 'amount_usd', sortable: true },
  { title: 'Moneda', key: 'currency', sortable: true },
  { title: 'Cuenta', key: 'count', sortable: true },
  { title: 'Deducible', key: 'is_deductible', sortable: false, value: (item) => {
    if(item.is_deductible==null){
      return ""
    }
    if(item.is_deductible=="1"){
      return "Si"
    }
    if(item.is_deductible=="0"){
      return "No"
    }
  }},
  { title: 'Fecha',    key: 'created_at', sortable: true, value: item =>{
    const fechaStr = item.created_at.replace('Z', '');
    const fecha = dayjs(fechaStr).format('DD/MM/YYYY');
    return fecha;
  }},
  { title: 'Usuario', key: 'user.username', sortable: false },
  { title: 'Acciones', key: 'actions', sortable: false },
];
</script>
<template>
  <VCard>
    <VDataTableServer
      :headers="headers"
      :items-per-page="props.itemsPerPage"
      :items="props.items"
      :items-length="props.total"
      :loading="loading"
      :page="props.page"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.id="{ item }">
        <span class="font-weight-medium">{{ item.id }}</span>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex align-center gap-2">
          <VIcon
            icon="tabler-check"
            @click="
              () => emit('cambiarEstado', { id: item.id, status: 'Approved' })
            "
          />
          <VIcon
            icon="tabler-x"
            @click="
              () => emit('cambiarEstado', { id: item.id, status: 'Cancelled' })
            "
          />
        </div>
      </template>
      <!-- <template #item.acciones="{ item }">
        <IconBtn @click="emit('edit', item.id)"
          ><VIcon icon="tabler-edit"
        /></IconBtn>
        <IconBtn @click="emit('delete', item.id)"
          ><VIcon icon="tabler-trash"
        /></IconBtn>
      </template> -->
    </VDataTableServer>
  </VCard>
</template>
