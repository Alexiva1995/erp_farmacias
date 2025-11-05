// src/utils/formatDate.js (Versión refactorizada y más flexible)

/**
 * Formatea una cadena de fecha ISO 8601 a un formato legible.
 *
 * @param {string} isoString - La cadena de fecha en formato ISO 8601.
 * @param {string} type - El tipo de formato deseado ('date', 'time', 'datetime').
 * @returns {string} La fecha/hora formateada.
 */
export const formatDateTime = (isoString, type = 'date') => {
    if (!isoString) {
        return '';
    }

    const date = new Date(isoString);
    const utcOptions = { timeZone: 'UTC' };
    const dateOptions = {...utcOptions, day: 'numeric', month: 'long', year: 'numeric' };
    const timeOptions = {...utcOptions, hour: 'numeric', minute: 'numeric', hour12: true };

    switch (type) {
        case 'date':
            return new Intl.DateTimeFormat('es-ES', dateOptions).format(date).replace(' de ', ' ');
        case 'time':
            return new Intl.DateTimeFormat('es-ES', timeOptions).format(date);
        case 'datetime':
            const formattedDate = new Intl.DateTimeFormat('es-ES', dateOptions).format(date).replace(' de ', ' ');
            const formattedTime = new Intl.DateTimeFormat('es-ES', timeOptions).format(date);
            return `${formattedDate} a las ${formattedTime}`;
        default:
            return new Intl.DateTimeFormat('es-ES', dateOptions).format(date).replace(' de ', ' ');
    }
};
