const titles = {
    INVALID_DESIGN: 'Diseño inválido',
    QZ_UNAVAILABLE: 'QZ no disponible',
    PRINTER_NOT_FOUND: 'Impresora no encontrada',
    QZ_SEND_FAILED: 'Fallo al enviar a QZ',
    POPUP_BLOCKED: 'Popup del navegador bloqueado',
    PRINT_ERROR: 'Error de impresión',
};

export function printError(code, detail, cause) {
    const error = new Error(`${titles[code] ?? titles.PRINT_ERROR}: ${detail}`, { cause });
    error.code = code;
    return error;
}

export function printErrorTitle(error) {
    return titles[error?.code] ?? titles.PRINT_ERROR;
}

export function asPrintError(error) {
    return error?.code && titles[error.code] ? error
        : printError('PRINT_ERROR', error?.message || String(error), error);
}
