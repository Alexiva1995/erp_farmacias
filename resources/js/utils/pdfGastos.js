import dayjs from 'dayjs';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';

export default function pdfGastos(data, nombreArchivo="gasto") {
    console.log("datos recibidos => ", data);
    
    // Crear documento en orientación horizontal (landscape)
    const doc = new jsPDF('landscape');
    const pageWidth = doc.internal.pageSize.getWidth();
    const pageHeight = doc.internal.pageSize.getHeight();
    
    // 1. Encabezado
    const headerY = 15;
    doc.setFontSize(18);
    doc.setFont('helvetica', 'bold');
    doc.text('REPORTE DE GASTOS PENDIENTES', pageWidth / 2, headerY, { align: 'center' });
    
    // 2. Fecha de generación
    const today = new Date();
    const dateStr = `Generado el: ${today.getDate()}/${String(today.getMonth() + 1).padStart(2, '0')}/${today.getFullYear()}`;
    doc.setFontSize(10);
    doc.setFont('helvetica', 'normal');
    doc.text(dateStr, pageWidth - 15, headerY + 10, { align: 'right' });
    
    // 3. Configuración de la tabla
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
    
    // 4. Generar tabla
    autoTable(doc, {
        startY: headerY + 15,
        head: headers,
        body: rows,
        theme: 'grid',
        headStyles: { 
            fillColor: [41, 128, 185],
            textColor: 255,
            halign: 'center'
        },
        alternateRowStyles: {
            fillColor: [245, 245, 245]
        },
        styles: {
            fontSize: 8,
            cellPadding: 2,
            overflow: 'linebreak'
        },
        // Ajustar anchos de columna para mejor visualización en horizontal
        columnStyles: {
            0: { cellWidth: 10, halign: 'center' },   // #
            1: { cellWidth: 20, halign: 'center' },   // ID
            2: { cellWidth: 35 },                    // Nombre
            3: { cellWidth: 30 },                    // Categoría
            4: { cellWidth: 25, halign: 'center' },  // Monto
            5: { cellWidth: 25, halign: 'center' },  // Monto USD
            6: { cellWidth: 20, halign: 'center' },  // Moneda
            7: { cellWidth: 20, halign: 'center' },  // Cuenta
            8: { cellWidth: 20, halign: 'center' },  // Deducible
            9: { cellWidth: 20, halign: 'center' },  // Estado
            10: { cellWidth: 25, halign: 'center' }, // Fecha
            11: { cellWidth: 25 }                    // Usuario
        },
        margin: { left: 10, right: 10 },
        tableWidth: 'auto'
    });
    
    // 5. Pie de página
    const pageCount = doc.internal.getNumberOfPages();
    for (let i = 1; i <= pageCount; i++) {
        doc.setPage(i);
        doc.setFontSize(10);
        doc.text(
            `Página ${i} de ${pageCount}`,
            pageWidth / 2,
            pageHeight - 10,
            { align: 'center' }
        );
    }
    
    // 6. Guardar PDF
    const fileName = `${nombreArchivo}_${today.toISOString().slice(0, 10)}.pdf`;
    doc.save(fileName);
}
