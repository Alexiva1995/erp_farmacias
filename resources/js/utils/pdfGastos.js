import dayjs from 'dayjs';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import { applyPremiumHeader, applyFooter } from './pdfBaseStyles';

export default function pdfGastos(data, nombreArchivo="gasto") {
    // Crear documento en orientación horizontal (landscape)
    const doc = new jsPDF('landscape');
    
    // Aplicar Encabezado Premium
    const startY = applyPremiumHeader(doc, 'Reporte de Gastos Pendientes');
    
    // Configuración de la tabla
    const headers = [
        [
            { content: '#', styles: { halign: 'center', fontStyle: 'bold' } },
            { content: 'ID', styles: { halign: 'center', fontStyle: 'bold' } },
            { content: 'Nombre', styles: { fontStyle: 'bold' } },
            { content: 'Categoría', styles: { fontStyle: 'bold' } },
            { content: 'Monto', styles: { halign: 'center', fontStyle: 'bold' } },
            { content: 'Monto USD', styles: { halign: 'center', fontStyle: 'bold' } },
            { content: 'Moneda', styles: { halign: 'center', fontStyle: 'bold' } },
            { content: 'Cuenta', styles: { halign: 'center', fontStyle: 'bold' } },
            { content: 'Deducible', styles: { halign: 'center', fontStyle: 'bold' } },
            { content: 'Estado', styles: { halign: 'center', fontStyle: 'bold' } },
            { content: 'Fecha', styles: { halign: 'center', fontStyle: 'bold' } },
            { content: 'Usuario', styles: { fontStyle: 'bold' } }
        ]
    ];
    
    const rows = data.map((item, index) => [
        { content: index + 1, styles: { halign: 'center' } },
        { content: item.id || 'N/A', styles: { halign: 'center' } },
        `${item.name} ${item.last_name || ''}`.trim(),
        item.category?.name || 'N/A',
        { content: item.amount || 'N/A', styles: { halign: 'center' } },
        { content: item.amount_usd || 'N/A', styles: { halign: 'center' } },
        { content: item.currency || 'N/A', styles: { halign: 'center' } },
        { content: item.count || 'N/A', styles: { halign: 'center' } },
        { 
            content: item.is_deductible === null ? '' : 
                     item.is_deductible === "1" ? "Si" : 
                     item.is_deductible === "0" ? "No" : 'N/A', 
            styles: { halign: 'center' } 
        },
        { content: item.status || 'N/A', styles: { halign: 'center' } },
        { 
            content: item.created_at ? 
                dayjs(item.created_at.replace('Z', '')).format('DD/MM/YYYY') : 'N/A', 
            styles: { halign: 'center' } 
        },
        item.user?.username || 'N/A'
    ]);
    
    // Generar tabla
    autoTable(doc, {
        startY: startY,
        head: headers,
        body: rows,
        theme: 'grid',
        headStyles: { 
            fillColor: [240, 240, 240],
            textColor: 0,
            halign: 'center',
            fontStyle: 'bold'
        },
        styles: {
            fontSize: 7,
            cellPadding: 2,
            overflow: 'linebreak'
        },
        margin: { left: 10, right: 10 }
    });
    
    // Aplicar Pie de Página
    applyFooter(doc);
    
    // Guardar PDF
    const todayStr = new Date().toISOString().slice(0, 10);
    doc.save(`${nombreArchivo}_${todayStr}.pdf`);
}
