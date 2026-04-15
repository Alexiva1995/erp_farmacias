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
        to: 'crm-clients',
        icon: {
          is: 'font-awesome-icon', 
          props: {
            icon: ['fas', 'users'],
            size: 'sm',
          },
        },
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
