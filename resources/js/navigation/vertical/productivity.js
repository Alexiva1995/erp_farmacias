export default [
  {
    title: 'Productividad',
    icon: {
      is: 'font-awesome-icon', 
      props: {
        icon: ['fas', 'boxes-stacked'],
        size: 'sm',
      },
    },
    children: [
      {
        title: 'Tareas de Limpieza',
        to: 'productivity-cleaning',
      },
      {
        title: 'Laboratorios Por Empleados',
        to: 'productivity-laboratory',
      },
      {
        title: 'Productos Por Empleados',
        to: 'productivity-product',
      },
      {
        title: 'Tareas Por Empleados',
        to: 'productivity-employee-task',
      },
      {
        title: 'Mis Actividades de Limpieza',
        to: 'productivity-my-cleaning-activities',
      },
      {
        title: 'Revision de Actividades de Limpieza',
        to: 'productivity-supervisor-cleaning-activities',
      },
    ],
  }, 
]
