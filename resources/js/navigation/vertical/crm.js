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
        title: 'Empleados',
        to: 'crm-employees',
      },
      {
        title: 'Sorteo',
        to: 'crm-lottery',
      }
    ],
  },
]
