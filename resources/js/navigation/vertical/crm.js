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
