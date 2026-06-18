<template>
    <main class="flex min-h-screen flex-col items-center justify-center gap-5 bg-slate-50 p-6 text-center">
        <!-- Ilustración búho -->
        <div class="w-full max-w-md select-none">
            <svg viewBox="0 0 720 500" role="img" xmlns="http://www.w3.org/2000/svg" aria-label="Búho realista con cable cortado">
                <defs>
                    <radialGradient id="eyeAmber" cx="50%" cy="45%" r="55%">
                        <stop offset="0%" stop-color="#fde68a" />
                        <stop offset="55%" stop-color="#f59e0b" />
                        <stop offset="100%" stop-color="#b45309" />
                    </radialGradient>

                    <linearGradient id="bodyGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#475569" />
                        <stop offset="55%" stop-color="#334155" />
                        <stop offset="100%" stop-color="#1e293b" />
                    </linearGradient>

                    <linearGradient id="bellyGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#cbd5e1" />
                        <stop offset="100%" stop-color="#64748b" />
                    </linearGradient>

                    <filter id="softShadow" x="-20%" y="-20%" width="140%" height="140%">
                        <feDropShadow dx="0" dy="8" stdDeviation="8" flood-color="#0f172a" flood-opacity="0.16" />
                    </filter>
                </defs>

                <!-- Sombra -->
                <ellipse cx="360" cy="428" rx="180" ry="24" fill="#e2e8f0" />

                <!-- Cable izquierdo -->
                <rect x="70" y="398" width="258" height="22" rx="11" fill="#334155" />
                <rect x="70" y="398" width="258" height="7" rx="3.5" fill="#64748b" opacity="0.65" />

                <!-- Cable derecho -->
                <rect x="392" y="398" width="258" height="22" rx="11" fill="#334155" />
                <rect x="392" y="398" width="258" height="7" rx="3.5" fill="#64748b" opacity="0.65" />

                <!-- Cables internos -->
                <path d="M328 404 Q302 420 286 454" stroke="#ef4444" stroke-width="3" fill="none" stroke-linecap="round" />
                <path d="M328 408 Q310 424 302 456" stroke="#3b82f6" stroke-width="3" fill="none" stroke-linecap="round" />
                <path d="M328 412 Q318 430 318 456" stroke="#f59e0b" stroke-width="3" fill="none" stroke-linecap="round" />
                <path d="M392 404 Q418 420 434 454" stroke="#ef4444" stroke-width="3" fill="none" stroke-linecap="round" />
                <path d="M392 408 Q410 424 418 456" stroke="#3b82f6" stroke-width="3" fill="none" stroke-linecap="round" />
                <path d="M392 412 Q402 430 402 456" stroke="#22c55e" stroke-width="3" fill="none" stroke-linecap="round" />

                <!-- Chispas -->
                <path d="M358 396 L348 370 L365 354 L356 326" stroke="#fbbf24" stroke-width="3.2" fill="none" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M376 398 L388 374 L372 360" stroke="#f59e0b" stroke-width="2.4" fill="none" stroke-linecap="round" />
                <circle cx="345" cy="423" r="3" fill="#fbbf24" />
                <circle cx="374" cy="424" r="2.5" fill="#fbbf24" />
                <circle cx="389" cy="435" r="2" fill="#f59e0b" />

                <!-- Alas -->
                <path
                    d="M284 248 C226 282 210 350 226 410 C250 386 278 405 300 422 C304 360 318 298 342 255 Z"
                    fill="#1e293b"
                    filter="url(#softShadow)"
                />
                <path
                    d="M436 248 C494 282 510 350 494 410 C470 386 442 405 420 422 C416 360 402 298 378 255 Z"
                    fill="#1e293b"
                    filter="url(#softShadow)"
                />

                <!-- Plumas alas -->
                <path d="M260 300 C272 332 278 366 276 400" stroke="#475569" stroke-width="3" fill="none" stroke-linecap="round" opacity="0.7" />
                <path d="M238 334 C252 360 258 384 258 408" stroke="#475569" stroke-width="3" fill="none" stroke-linecap="round" opacity="0.6" />
                <path d="M460 300 C448 332 442 366 444 400" stroke="#475569" stroke-width="3" fill="none" stroke-linecap="round" opacity="0.7" />
                <path d="M482 334 C468 360 462 384 462 408" stroke="#475569" stroke-width="3" fill="none" stroke-linecap="round" opacity="0.6" />

                <!-- Cuerpo -->
                <ellipse cx="360" cy="322" rx="82" ry="104" fill="url(#bodyGrad)" filter="url(#softShadow)" />

                <!-- Pecho -->
                <ellipse cx="360" cy="336" rx="56" ry="78" fill="url(#bellyGrad)" opacity="0.95" />

                <!-- Plumaje pecho -->
                <path v-for="y in [292, 314, 336, 358, 380]" :key="y" :d="`M328 ${y} Q360 ${y + 22} 392 ${y}`" stroke="#475569" stroke-width="2" fill="none" opacity="0.5" />
                <path d="M342 305 Q360 320 378 305" stroke="#e2e8f0" stroke-width="1.6" fill="none" opacity="0.45" />
                <path d="M338 350 Q360 366 382 350" stroke="#e2e8f0" stroke-width="1.6" fill="none" opacity="0.35" />

                <!-- Cabeza -->
                <path
                    d="M268 166
                       C274 104 318 72 360 76
                       C402 72 446 104 452 166
                       C462 232 418 276 360 276
                       C302 276 258 232 268 166 Z"
                    fill="url(#bodyGrad)"
                    filter="url(#softShadow)"
                />

                <!-- Orejas / penachos -->
                <path d="M306 106 L286 54 L338 90 Z" fill="#1e293b" />
                <path d="M414 106 L434 54 L382 90 Z" fill="#1e293b" />

                <!-- Disco facial izquierdo -->
                <path
                    d="M286 178
                       C292 128 326 108 358 132
                       C348 172 344 212 320 232
                       C294 226 282 206 286 178 Z"
                    fill="#cbd5e1"
                    opacity="0.9"
                />

                <!-- Disco facial derecho -->
                <path
                    d="M434 178
                       C428 128 394 108 362 132
                       C372 172 376 212 400 232
                       C426 226 438 206 434 178 Z"
                    fill="#cbd5e1"
                    opacity="0.9"
                />

                <!-- Contorno facial -->
                <path d="M292 174 C312 134 338 126 360 145" stroke="#94a3b8" stroke-width="3" fill="none" opacity="0.75" />
                <path d="M428 174 C408 134 382 126 360 145" stroke="#94a3b8" stroke-width="3" fill="none" opacity="0.75" />

                <!-- Ojos -->
                <circle cx="326" cy="178" r="28" fill="#f8fafc" />
                <circle cx="394" cy="178" r="28" fill="#f8fafc" />

                <circle cx="326" cy="178" r="22" fill="url(#eyeAmber)" />
                <circle cx="394" cy="178" r="22" fill="url(#eyeAmber)" />

                <circle cx="326" cy="184" r="12" fill="#020617" />
                <circle cx="394" cy="184" r="12" fill="#020617" />

                <circle cx="333" cy="170" r="4" fill="#fff" />
                <circle cx="401" cy="170" r="4" fill="#fff" />

                <!-- Cejas / expresión -->
                <path d="M296 150 Q322 134 350 150" stroke="#1e293b" stroke-width="7" fill="none" stroke-linecap="round" />
                <path d="M424 150 Q398 134 370 150" stroke="#1e293b" stroke-width="7" fill="none" stroke-linecap="round" />

                <!-- Pico -->
                <path d="M360 199 L340 226 L360 238 L380 226 Z" fill="#f59e0b" />
                <path d="M360 199 L348 224 L360 232 L372 224 Z" fill="#d97706" opacity="0.8" />

                <!-- Plumas cabeza -->
                <path d="M320 112 C336 100 350 98 360 108 C370 98 384 100 400 112" stroke="#64748b" stroke-width="2" fill="none" opacity="0.5" />
                <path d="M300 220 C324 248 396 248 420 220" stroke="#64748b" stroke-width="2" fill="none" opacity="0.45" />

                <!-- Gota de sudor -->
                <path d="M466 158 C482 178 475 194 462 194 C449 194 444 178 466 158 Z" fill="#bfdbfe" />
                <circle cx="461" cy="174" r="3" fill="#eff6ff" opacity="0.8" />

                <!-- Patas -->
                <line x1="330" y1="416" x2="324" y2="396" stroke="#475569" stroke-width="6" stroke-linecap="round" />
                <line x1="390" y1="416" x2="396" y2="396" stroke="#475569" stroke-width="6" stroke-linecap="round" />

                <!-- Garras -->
                <path d="M324 414 L304 420 M324 414 L310 432 M324 414 L326 436 M324 414 L342 430"
                      stroke="#334155" stroke-width="4" fill="none" stroke-linecap="round" />
                <path d="M396 414 L416 420 M396 414 L410 432 M396 414 L394 436 M396 414 L378 430"
                      stroke="#334155" stroke-width="4" fill="none" stroke-linecap="round" />
            </svg>
        </div>

        <!-- Número 404 decorativo -->
        <p class="-mt-7 select-none text-[6rem] font-black leading-none tracking-tighter text-slate-100 sm:text-[8rem]">
            404
        </p>

        <!-- Mensaje -->
        <div class="-mt-3 space-y-2">
            <h1 class="text-2xl font-bold text-slate-900">
                Página no encontrada
            </h1>
            <p class="mx-auto max-w-sm text-sm text-slate-500">
                La dirección que buscas no existe o fue movida.<br />
                Verifica la URL o regresa al inicio.
            </p>
        </div>

        <!-- Ruta que intentó visitar -->
        <div class="max-w-full rounded-xl bg-slate-100 px-4 py-2">
            <code class="break-all text-xs text-slate-500">{{ $route.fullPath }}</code>
        </div>

        <!-- Acciones -->
        <div class="flex flex-wrap justify-center gap-3">
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                @click="router.back()"
            >
                <ArrowLeft class="h-4 w-4" />
                Volver
            </button>

            <RouterLink
                :to="{ name: 'dashboard' }"
                class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800"
            >
                <House class="h-4 w-4" />
                Ir al inicio
            </RouterLink>
        </div>
    </main>
</template>

<script setup>
import { useRouter } from "vue-router";
import { ArrowLeft, House } from "lucide-vue-next";

const router = useRouter();
</script>