export default [
  {
    title: 'MASTER',
    icon: {
      is: 'font-awesome-icon',
      props: {
        icon: ['fas', 'database'],
        size: 'sm',
      },
    },
    action: 'manage',
    subject: 'admin',
    children: [
      {
        title: 'Inventario',
        to: 'master-products',
      },
      {
        title: 'Laboratorios',
        to: 'master-laboratories',
      },
      {
        title: 'Grupos',
        to: 'master-group-products',
      },
      {
        title: 'Proveedores',
        to: 'master-suppliers',
      },
    ],
  },
]
