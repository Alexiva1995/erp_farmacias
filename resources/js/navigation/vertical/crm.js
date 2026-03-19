export default [
  {
    title: 'CRM',
    icon: {
      is: 'font-awesome-icon', 
      props: {
        icon: ['fas', 'address-book'],
        size: 'sm',
      },
    },
    action: 'manage', 
    subject: 'admin',
    children: [
      {
        title: 'Doctores',
        to: 'crm-doctors',
      },
      {
        title: 'Clientes',
        children: [
          {
            title: 'Listado',
            to: 'crm-clients',
          },
          {
            title: 'Pendientes',
            to: 'crm-clients-pending',
          },
        ]
      },
      {
        title: 'Empresas',
        to: 'crm-companies',
      },
      {
        title: 'Sorteo',
        to: 'crm-lottery',
      }
    ],
  },
]
