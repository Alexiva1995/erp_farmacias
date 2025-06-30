export default [
  {
    title: 'CRM',
    icon: {
      is: 'font-awesome-icon', 
      props: {
        icon: ['fas', 'users'],
        size: 'sm',
      },
    },
    children: [
      {
        title: 'clientes',
        to: 'crm-clients',
      },
      {
        title: 'Empresas',
        to: 'crm-companies',
      }
    ],
  },
]
