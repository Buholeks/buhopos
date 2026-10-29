import { empaquetarMonocromo } from './raster.js';

// PDF de una página con una imagen monocroma sin pérdidas: las medidas físicas
// no dependen del área imprimible ni de la resolución que anuncie el controlador.
export function crearPdfMonocromo(imageData, anchoMm, altoMm) {
    if (![anchoMm, altoMm].every((n) => Number.isFinite(n) && n >= 10 && n <= 500)) {
        throw new Error('Medidas de etiqueta inválidas.');
    }
    const { bytes } = empaquetarMonocromo(imageData);
    const ancho = (anchoMm * 72 / 25.4).toFixed(6);
    const alto = (altoMm * 72 / 25.4).toFixed(6);
    let pixels = '';
    for (let i = 0; i < bytes.length; i += 8192) pixels += String.fromCharCode(...bytes.subarray(i, i + 8192));
    const dibujo = `q\n${ancho} 0 0 ${alto} 0 0 cm\n/Im0 Do\nQ\n`;
    const objetos = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        `<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${ancho} ${alto}] /Resources << /XObject << /Im0 4 0 R >> >> /Contents 5 0 R >>`,
        `<< /Type /XObject /Subtype /Image /Width ${imageData.width} /Height ${imageData.height} /ColorSpace /DeviceGray /BitsPerComponent 1 /Decode [1 0] /Interpolate false /Length ${pixels.length} >>\nstream\n${pixels}\nendstream`,
        `<< /Length ${dibujo.length} >>\nstream\n${dibujo}endstream`,
    ];
    let pdf = '%PDF-1.4\n';
    const offsets = [0];
    objetos.forEach((objeto, i) => {
        offsets.push(pdf.length);
        pdf += `${i + 1} 0 obj\n${objeto}\nendobj\n`;
    });
    const xref = pdf.length;
    pdf += `xref\n0 6\n0000000000 65535 f \n`;
    pdf += offsets.slice(1).map((offset) => `${String(offset).padStart(10, '0')} 00000 n \n`).join('');
    pdf += `trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n${xref}\n%%EOF\n`;
    return btoa(pdf);
}

export async function etiquetaHtmlAPdf(html, anchoMm, altoMm) {
    const { default: html2canvas } = await import('html2canvas');
    const frame = document.createElement('iframe');
    frame.setAttribute('sandbox', 'allow-same-origin');
    frame.setAttribute('aria-hidden', 'true');
    frame.style.cssText = `position:fixed;left:-10000px;top:0;border:0;width:${anchoMm}mm;height:${altoMm}mm;`;
    try {
        await new Promise((resolve, reject) => {
            const timeout = setTimeout(() => reject(new Error('No se pudo preparar la etiqueta.')), 15000);
            frame.onload = () => { clearTimeout(timeout); resolve(); };
            frame.srcdoc = html;
            document.body.appendChild(frame);
        });
        const doc = frame.contentDocument;
        await doc.fonts.ready;
        await Promise.all([...doc.images].map((img) => img.decode()));
        const pagina = doc.querySelector('.page');
        if (!pagina) throw new Error('No se encontró la página de la etiqueta.');
        const canvas = await html2canvas(pagina, {
            scale: 300 / 96, backgroundColor: '#ffffff', logging: false,
            width: anchoMm * 96 / 25.4, height: altoMm * 96 / 25.4,
        });
        return crearPdfMonocromo(canvas.getContext('2d').getImageData(0, 0, canvas.width, canvas.height), anchoMm, altoMm);
    } finally {
        frame.remove();
    }
}
