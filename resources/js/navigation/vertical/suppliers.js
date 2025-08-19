export default [
  {
    title: 'Proveedores',
    icon: {
      is: 'font-awesome-icon', 
      props: {
        icon: ['fas', 'address-book'],
        size: 'sm',
      },
    },
    children: [
      {
        title: 'Listado de Proveedores',
        to: 'suppliers-list',
      },
      {
        title: 'IA Assistence de Pedidos',
        to: 'suppliers-supplieriaorderassistant',
      },
      {
        title: 'IA Assistance Report',
        to: 'suppliers-supplieriaorderassistantreport',
      }
    ],
  },
]
