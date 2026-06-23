import JsBarcode from "jsbarcode";
import { imprimirHtml } from "@/helpers/qzTray";

export function resolverCampo(datos, campo, precioImpresion = null) {
    if (campo === "compra.folio_fecha") {
        return [datos?.compra?.folio, formatearFecha(datos?.compra?.fecha)].filter(Boolean).join(" · ");
    }
    if (campo === "precios.venta" && precioImpresion !== null) return precioImpresion;
    return campo.split(".").reduce((valor, parte) => valor?.[parte], datos) ?? "";
}

export function formatearValor(elemento, datos, precioImpresion = null) {
    const valor = elemento.tipo === "texto" ? elemento.texto : resolverCampo(datos, elemento.campo, precioImpresion);
    if (elemento.tipo === "precio" || elemento.campo?.startsWith("precios.")) {
        return new Intl.NumberFormat("es-MX", { style: "currency", currency: "MXN" }).format(Number(valor || 0));
    }
    return String(valor ?? "");
}

export function crearSvgBarcode(valor, anchoMm, altoMm, mostrarTexto = true, fuentePt = null) {
    const str = String(valor ?? "").trim();
    if (!str) return `<span class="barcode-error" style="font-size:6pt;color:red;">[sin código]</span>`;

    // Generamos a 300 DPI para impresión nítida (11.811 px/mm a 300 DPI)
    const dpi = 300;
    const pxMm = dpi / 25.4;
    const anchoMmN = Number(anchoMm);
    const altoMmN = Number(altoMm);
    // Si hay fuente manual (pt) la convertimos a px a 300 DPI (1pt = 1/72 in = 300/72 px)
    const fontSizePx = fuentePt ? Math.round(Number(fuentePt) * (dpi / 72)) : Math.round(altoMmN * pxMm * 0.18);
    const fraccionTexto = mostrarTexto ? (fontSizePx / (altoMmN * pxMm)) + 0.06 : 0.08;
    const altoBarras = Math.round(altoMmN * pxMm * (1 - fraccionTexto));

    const opts = {
        format: "CODE128",
        displayValue: mostrarTexto,
        margin: 4,
        width: Math.max(2, Math.floor((anchoMmN * pxMm) / Math.max(22, str.length * 11))),
        height: altoBarras,
        fontSize: fontSizePx,
        textMargin: 3,
    };

    // canvas → PNG base64 a alta resolución
    try {
        const canvas = document.createElement("canvas");
        JsBarcode(canvas, str, opts);
        const dataUrl = canvas.toDataURL("image/png");
        if (dataUrl && dataUrl.length > 100) {
            return `<img src="${dataUrl}" style="display:block;width:100%;height:auto;max-height:100%;" />`;
        }
    } catch (e) {
        console.warn("[barcode canvas]", e, "valor:", str);
    }

    // Intento 2: SVG adjunto al DOM principal
    try {
        const svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
        document.body.appendChild(svg);
        JsBarcode(svg, str, { ...opts, margin: 0 });
        svg.setAttribute("preserveAspectRatio", "none");
        svg.setAttribute("width", "100%");
        svg.setAttribute("height", "100%");
        const html = svg.outerHTML;
        document.body.removeChild(svg);
        return html;
    } catch (e) {
        console.warn("[barcode svg]", e, "valor:", str);
    }

    return `<span class="barcode-error" style="font-size:6pt;">${escapeHtml(str)}</span>`;
}

function resolverDimensiones(plantilla, perfil) {
    const esContinua = perfil?.material === "continua";
    const ancho = Number((esContinua ? plantilla.ancho_mm : perfil?.ancho_mm) || plantilla.ancho_mm);
    const alto = Number((esContinua ? plantilla.alto_mm : perfil?.alto_mm) || plantilla.alto_mm);
    const transformacion = crearTransformacion({
        anchoPagina: ancho, altoPagina: alto,
        anchoDiseno: Number(plantilla.ancho_mm), altoDiseno: Number(plantilla.alto_mm),
        escala: Number(perfil?.escala || 1),
        offsetX: Number(perfil?.offset_x_mm || 0),
        offsetY: Number(perfil?.offset_y_mm || 0),
        rotacion: Number(perfil?.rotacion || 0),
    });
    return { ancho, alto, transformacion };
}

function envolver(cuerpo, ancho, alto, plantilla, transformacion) {
    return `<!doctype html><html><head><meta charset="utf-8"><title></title><style>
      @page { size: ${ancho}mm ${alto}mm; margin: 0mm; }
      * { box-sizing: border-box; margin: 0; padding: 0; }
      html, body { width: ${ancho}mm; height: ${alto}mm; margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; color: #000; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      .page { display: block; position: relative; width: ${ancho}mm; height: ${alto}mm; overflow: hidden; break-after: page; page-break-after: always; }
      .page:last-child { break-after: auto; page-break-after: auto; }
      .label { position: absolute; left: 0; top: 0; width: ${plantilla.ancho_mm}mm; height: ${plantilla.alto_mm}mm; overflow: hidden; transform: ${transformacion}; transform-origin: top left; }
      .barcode-error { font-size:7pt; overflow-wrap:anywhere; }
    </style></head><body>${cuerpo}</body></html>`;
}

function expandirItems(items) {
    const resultado = [];
    items.filter((item) => item.seleccionado && Number(item.cantidad) > 0).forEach((item) => {
        for (let i = 0; i < Number(item.cantidad); i += 1) resultado.push(item);
    });
    return resultado;
}

// Usado por window.print() — todas las etiquetas en un solo HTML con saltos de página
export function construirHtmlImpresion({ plantilla, perfil, items }) {
    const expandidos = expandirItems(items);
    if (!expandidos.length) throw new Error("Selecciona al menos una etiqueta.");
    const { ancho, alto, transformacion } = resolverDimensiones(plantilla, perfil);
    const cuerpo = expandidos.map((item) => crearEtiquetaHtml(plantilla, item)).join("");
    return { html: envolver(cuerpo, ancho, alto, plantilla, transformacion), ancho, alto };
}

export async function imprimirEtiquetas({ plantilla, perfil, items, impresoraQz = null }) {
    const expandidos = expandirItems(items);
    if (!expandidos.length) throw new Error("Selecciona al menos una etiqueta.");

    const { ancho, alto, transformacion } = resolverDimensiones(plantilla, perfil);

    if (impresoraQz) {
        // Cada etiqueta = trabajo separado → el driver Brother hace corte entre cada una
        for (const item of expandidos) {
            const html = envolver(crearEtiquetaHtml(plantilla, item), ancho, alto, plantilla, transformacion);
            await imprimirHtml(impresoraQz, html, ancho, alto);
        }
        return;
    }

    // Fallback: todas juntas en una ventana con saltos de página CSS
    const cuerpo = expandidos.map((item) => crearEtiquetaHtml(plantilla, item)).join("");
    const html = envolver(cuerpo, ancho, alto, plantilla, transformacion);
    const ventana = window.open("", "etiquetas_impresion", "width=1000,height=760");
    if (!ventana) throw new Error("El navegador bloqueó la ventana de impresión.");
    ventana.document.write(html.replace("</body>", "<script>window.onload=()=>{window.focus();window.print();}<\\/script></body>"));
    ventana.document.close();
}

function crearEtiquetaHtml(plantilla, item) {
    const elementos = plantilla.diseno?.elementos || [];
    return `<section class="page"><div class="label">${elementos.map((el) => {
        const baseStyle = `position:absolute;left:${el.x}mm;top:${el.y}mm;width:${el.ancho}mm;height:${el.alto}mm;overflow:hidden;`;
        if (["linea_h", "linea_v", "rectangulo", "rectangulo_relleno"].includes(el.tipo)) {
            const color = el.color || "#000000";
            const grosor = `${Number(el.grosor || 0.3)}mm`;
            let figStyle = "";
            if (el.tipo === "linea_h") figStyle = `position:absolute;left:0;right:0;top:50%;transform:translateY(-50%);height:${grosor};background:${color};`;
            else if (el.tipo === "linea_v") figStyle = `position:absolute;top:0;bottom:0;left:50%;transform:translateX(-50%);width:${grosor};background:${color};`;
            else if (el.tipo === "rectangulo") figStyle = `position:absolute;inset:0;border:${grosor} solid ${color};`;
            else if (el.tipo === "rectangulo_relleno") figStyle = `position:absolute;inset:0;background:${el.color_relleno || color};border:${grosor} solid ${color};`;
            return `<div style="${baseStyle}"><div style="${figStyle}"></div></div>`;
        }
        if (el.tipo === "codigo_barras") {
            let valorCodigo = resolverCampo(item.datos, el.campo, item.precio_impresion);
            // Si el campo configurado devuelve vacío, cae al código preferido calculado
            if (!String(valorCodigo ?? "").trim()) valorCodigo = resolverCampo(item.datos, "calculados.codigo_preferido");
            const barcodeHtml = crearSvgBarcode(valorCodigo, el.ancho, el.alto, el.mostrar_texto !== false, el.fuente_barcode || null);
            return `<div style="${baseStyle}">${barcodeHtml}</div>`;
        }
        const fontFamily = el.familia_fuente ? `font-family:${el.familia_fuente};` : "";
        const style = `${baseStyle}${fontFamily}font-size:${el.fuente || 8}pt;font-weight:${el.negrita ? 700 : 400};display:flex;align-items:center;justify-content:${alineacion(el.alineacion)};text-align:${el.alineacion || "izquierda"};line-height:1.05;white-space:normal;`;
        return `<div style="${style}">${escapeHtml(formatearValor(el, item.datos, item.precio_impresion))}</div>`;
    }).join("")}</div></section>`;
}

function crearTransformacion({ anchoPagina, altoPagina, anchoDiseno, altoDiseno, escala, offsetX, offsetY, rotacion }) {
    const girada = rotacion === 90 || rotacion === 270;
    const escalaX = ((girada ? altoPagina : anchoPagina) / anchoDiseno) * escala;
    const escalaY = ((girada ? anchoPagina : altoPagina) / altoDiseno) * escala;
    const posiciones = {
        0: `translate(${offsetX}mm, ${offsetY}mm)`,
        90: `translate(${anchoPagina + offsetX}mm, ${offsetY}mm) rotate(90deg)`,
        180: `translate(${anchoPagina + offsetX}mm, ${altoPagina + offsetY}mm) rotate(180deg)`,
        270: `translate(${offsetX}mm, ${altoPagina + offsetY}mm) rotate(270deg)`,
    };
    return `${posiciones[rotacion] || posiciones[0]} scale(${escalaX}, ${escalaY})`;
}

export function escapeHtml(value) {
    return String(value ?? "").replaceAll("&", "&amp;").replaceAll("<", "&lt;").replaceAll(">", "&gt;").replaceAll('"', "&quot;").replaceAll("'", "&#039;");
}

function alineacion(valor) {
    return valor === "centro" ? "center" : valor === "derecha" ? "flex-end" : "flex-start";
}

function formatearFecha(valor) {
    if (!valor) return "";
    const [y, m, d] = String(valor).slice(0, 10).split("-");
    return y && m && d ? `${d}/${m}/${y}` : valor;
}
