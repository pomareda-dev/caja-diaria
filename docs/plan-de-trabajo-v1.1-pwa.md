# Plan de trabajo v1.1 — Caja Diaria (PWA instalable)

> Extensión **posterior al MVP** (fases 0–9 de v1) y **independiente de v1.2**.
> Convierte la app web en una **PWA instalable** para añadir el ícono al home
> screen de celulares (Android/iOS) y abrir como una app nativa (ventana
> propia, sin barra del navegador). **No** incluye modo offline: la app sigue
> necesitando red para funcionar.

**Stack:** el mismo de v1 (Laravel 13 · Inertia 3 · Vue 3 + TypeScript ·
Tailwind · shadcn-vue). Sin dependencias nuevas.

**Prerequisito:** v1 completada (fases 0–9).

**Stack de referencia:** ver `plan-de-trabajo.md` para las decisiones de v1 que
este plan extiende.

---

## 1. Objetivo

Que el usuario pueda:

1. Abrir Caja Diaria en el celular desde un ícono en el home screen, como una
   app separada (standalone window), sin pasar por el navegador.
2. Recibir el prompt de instalación nativo del navegador (Android) y usar el
   flujo "Add to Home Screen" de iOS.
3. Mantener la misma experiencia que en desktop — no es una app aparte, es la
   misma web app con metadata PWA correcta.

**No** es objetivo de v1.1: funcionar sin conexión, sincronizar datos offline,
notificaciones push, ni servicio de background sync.

---

## 2. Alcance

### Dentro del alcance (v1.1)

- **Web App Manifest** (`public/manifest.json`) con `name`, `short_name`,
  `start_url`, `display: standalone`, `theme_color`, `background_color`,
  `orientation`, `icons` (192px, 512px, maskable).
- **Meta tags PWA** en el layout blade: `<link rel="manifest">`, `theme-color`,
  `apple-mobile-web-app-capable`, `apple-mobile-web-app-title`, íconos Apple
  touch (180px y 512px), splash screens iOS (opcional en una fase posterior).
- **Íconos de app**: 192px y 512px (PNG), más variante maskable para Android; y
  180px Apple touch icon. Generados desde el logo de la app.
- **Service worker mínimo** registrado desde el frontend: cachea **solo** el
  app shell estático (`/`, assets del build) con `cache-first` y un
  `SKIP_WAITING` + `CLIENTS_CLAIM` para updates simples. **No** cachea respuestas
  de la API ni datos de usuario. **No** implementa estrategias offline-first.
- **Botón "Instalar app"** en la UI cuando el navegador haiega el evento
  `beforeinstallprompt` (Android): banner discreto en el dashboard o en el
  profile/settings, con `userChoice` tracking.
- **Soporte iOS manual**: indicación en UI (tooltip o sección de ayuda) de
  "Compartir → Add to Home Screen" cuando se detecta iOS Safari sin
  `beforeinstallprompt`.
- **Lighthouse PWA ≥ 90** en build de producción (verificado en la fase final).

### Fuera del alcance (deferido a v3 o posterior)

- **Offline-capable (offline-first)**: cache de respuestas Inertia, resync de
  datos, resolución de conflictos al volver online → v3 o posterior. v1.1 solo
  cachea el shell; sin red, la app abre pero las páginas fallan al cargar datos.
- **Notificaciones push** (Web Push API, VAPID, suscripciones) → fuera.
- **Background sync / periodic sync** → fuera.
- **Splash screens iOS por modelo** (imágenes PNG por tamaño de dispositivo) →
  opcional en una fase posterior del propio v1.1; no bloquea instalación.
- **PWA en iOS con persistencia de sesión** — iOS PWAs tienen limitaciones de
  cookies/storage que no se abordan en v1.1; el usuario puede tener que volverse
  a loguear.
- **Actualización automática de la app** con notificaciones al usuario sobre
  nuevas versiones — el SW simple hace `SKIP_WAITING` pero no muestra UI de
  "nueva versión disponible". Se puede pulir en v1.1 fase final o derivar a v3.

---

## 3. Decisiones de arquitectura

### 3.1 Sin dependencias nuevas

Toda la PWA se arma con assets estáticos + service worker vanilla (sin
Workbox). El proyecto ya sirve `public/` desde Laravel; el manifest y el SW
viven ahí. Vite ya genera hashes de assets — el SW referenciará los assets por
URL patrón, no por nombre exacto.

### 3.2 Service worker mínimo, cache-first del app shell, sin cachear datos

El SW cachea **solo**:

- `/` (HTML del documento)
- `/build/**` (assets JS/CSS/Images del build de Vite)
- `/manifest.json`
- íconos en `/icons/**`

Estrategia: `cache-first` con fallback a red. **No** cachea rutas bajo
`/dashboard`, `/movimientos`, `/proyeccion`, etc. — esas son páginas Inertia que
dependen de la sesión y de datos vivos del backend; cachearlas mostraría
sesiones caducadas o datos viejos. Sin red, esas páginas fallan limpiamente con
la página de error de Inertia.

**Por qué no offline-first:** una app financiera con proyecciones que dependen
del backend no puede有意义mente funcionar offline sin invertir en sync +
conflict resolution. v1.1 explicita que la red es requerida.

### 3.3 Manifest con `display: standalone` y `start_url: "/"`

`display: standalone` quita la barra del navegador en la ventana instalada.
`start_url: "/"` — al abrir la app instalada, el router del frontend (Inertia +
Vue Router-equivalente vía Wayfinder) redirige al dashboard si hay sesión, o al
login si no. No hace falta un `start_url` con path fijo.

`scope: "/"` cubre toda la app.

### 3.4 Íconos maskable + Apple touch

Android 8+ usa `purpose: "any maskable"` para que el ícono se ajuste a
cualquier forma de ícono del launcher. iOS ignora el manifest y usa
`apple-touch-icon` (180px); también soporta 512px para "Add to Home Screen".

### 3.5 Detección de plataforma y `beforeinstallprompt`

El evento `beforeinstallprompt` sirve en Chrome/Edge/Android; Safari iOS no lo
emite. Se captura en `App.vue` (o un composable `useInstallPrompt`), se guarda
el evento diferido, y se muestra el botón "Instalar app" solo si el evento se
disparó **y** no está ya instalada (`navigator.getInstalledRelatedApps()` en
plataformas que soportan standalone detection). Para iOS se detecta
`navigator.standalone === false` + userAgent iOS y se muestra un tooltip
instruyendo al usuario usar Share → Add to Home Screen.

### 3.6 Sin múltiples PWAs

Un solo manifest, un solo SW. La PWA es la app entera; no hay sub-apps
instalables.

---

## 4. Modelo de datos

**No se agregan tablas ni columnas.** v1.1 es puramente frontend + assets en
`public/`. El backend de Laravel no cambia.

Archivos nuevos:

- `public/manifest.json`
- `public/sw.js` (service worker mínimo)
- `public/icons/icon-192.png`, `public/icons/icon-512.png` (maskable),
  `public/icons/apple-touch-180.png`
- `resources/js/composables/useInstallPrompt.ts`
- `resources/js/components/InstallBanner.vue` (o sección en
  `ProfileSettings`/`Dashboard`)

Archivos modificados:

- Layout blade que renderiza `<head>` (`resources/views/app.blade.php` o
  equivalente): agregar `<link rel="manifest">`, `<meta name="theme-color">`,
  `<meta name="apple-mobile-web-app-capable" content="yes">`,
  `<link rel="apple-touch-icon">`.
- `resources/js/App.vue`: registrar el SW vía `navigator.serviceWorker` y
  inicializar `useInstallPrompt`.

---

## 5. Fases del plan

### Fase V1.1-0 — Manifest, meta tags e íconos

**Objetivo:** llevar la metadata PWA mínima para que Lighthouse y el navegador
detecten instalable.

**Tareas**

1. Generar íconos 192px, 512px (maskable) y 180px (Apple touch) desde el logo
   de la app. Colocarlos en `public/icons/`.
2. Crear `public/manifest.json` con `name: "Caja Diaria"`,
   `short_name: "Caja"`, `start_url: "/"`, `scope: "/"`,
   `display: "standalone"`, `orientation: "any"`, `background_color`, `theme_color`
   (sincronizado con el color primario de la paleta base de v1 Fase 8),
   `icons` con `purpose: "any maskable"`.
3. En el layout blade, agregar:
   - `<link rel="manifest" href="/manifest.json">`
   - `<meta name="theme-color" content="{primary}">` (idealmente vía
     `@vite` o variable del backend si la paleta es dinámica por usuario → ver
     Fase 8 v1; si no es trivial usar la paleta por defecto y dejar TODO).
   - `<meta name="apple-mobile-web-app-capable" content="yes">`
   - `<meta name="apple-mobile-web-app-title" content="Caja Diaria">`
   - `<link rel="apple-touch-icon" href="/icons/apple-touch-180.png">`
4. Verificar con Lighthouse "Installable" green en desktop local.

**Criterios de aceptación**

- [ ] Lighthouse detecta `manifest.json` con `display: standalone` y `start_url`
  válidos.
- [ ] Ícono aparece en la pestaña de Android Chrome devtools instalable.
- [ ] Apple touch icon se resuelve correctamente.
- [ ] `theme-color` pinta la barra del navegador en móvil.

**Commits:** `feat: web app manifest`, `feat: PWA meta tags in layout`,
`chore: app icons for PWA`.

---

### Fase V1.1-1 — Service worker mínimo (app shell cache-first)

**Objetivo:** cachear el shell estático y abrir desde home sin parpadeos de
sin-estilos, sin tocar datos.

**Tareas**

1. Crear `public/sw.js` con:
   - `install`: `self.skipWaiting()`, abrir cache `caja-diaria-shell-vX`, pasar
     a `self.clients.claim()`.
   - `activate`: limpiar caches viejas (claves que no son la versión actual).
   - `fetch` handler: si el request es navigate (`mode: "navigate"`) o entra en
     `/build/**`, `/icons/**`, `/manifest.json` → `cache-first` con fallback a
     red. Cualquier otro request → pasar directo a red (`fetch(event.request)`),
     NO cache-ear rutas Inertia/datos.
   - Estampar el nombre de cache con el hash del build de Vite: si Vite genera
     un manifest con hashes, derivar la lista de assets cacheados desde ahí
     al hacer `install`. Alternativa simple: leer `import.meta.env` no aplica
     al SW; usar un literal en `sw.js` que se actualiza en cada build vía un
     pequeño plugin en `vite.config.ts`, o simplemente listar patrones
     (`/build/*.js`, `/build/*.css`) con `cache.match` por path.
2. Registrar el SW desde `resources/js/App.vue` (`navigator.serviceWorker.register('/sw.js')`)
   en `onMounted`, con `catch` silencioso (no romper la app si el SW falla).
3. Validar que al abrir `/dashboard` en instalación el HTML viene del cache y
   los datos llegan por la red.

**Criterios de aceptación**

- [ ] Lighthouse detecta service worker registrado.
- [ ] Tras abrir la app instalada, el shell carga desde cache (DevTools →
  Application → Cache Storage muestra entradas).
- [ ] Las páginas Inertia NO se cachean (sin entradas `/dashboard*` en cache
  storage).
- [ ] Recarga tras deploy: cache vieja se limpia y entra la nueva.

**Commits:** `feat: service worker for app shell cache`,
`feat: register service worker on app boot`.

---

### Fase V1.1-2 — Botón "Instalar app" y detección de plataforma

**Objetivo:** UX clara para Android (prompt nativo) y iOS (instrucciones Share
→ Add to Home Screen).

**Tareas**

1. `useInstallPrompt.ts`:
   - listener global del evento `beforeinstallprompt`: guarda el evento y
     expone `canInstall` (ref `true`).
   - `install()` llama `event.prompt()` y lee `userChoice`.
   - Al resolver `accepted` o `dismissed`, limpia el evento y `canInstall=false`.
   - Detecta standalone: `window.matchMedia('(display-mode: standalone)').matches`
     o `navigator.standalone === true` → `isInstalled=true`, no mostrar banner.
2. `InstallBanner.vue`: banner pequeño en el dashboard (o en la página de
   preferencias) con texto "Instalar Caja Diaria" + botón. Solo se renderiza
   cuando `canInstall && !isInstalled`.
3. Detección iOS: si userAgent matchea iOS Safari **y** no `navigator.standalone`:
   mostrar texto alternativo "En iOS: toca Compartir → Add to Home Screen".
4. Persistir dismissed state en `localStorage` por 30 días (no molestar).
5. Tests: al menos test de que el banner NO se muestra si `isInstalled`.

**Criterios de aceptación**

- [ ] En Chrome Android appearance hot-target, al abrir la app el banner
  aparece y al click abre el prompt nativo.
- [ ] Tras aceptar/dispensar, el banner no vuelve hasta el cooldown.
- [ ] Tras instalar, el banner no aparece (standalone detectado).
- [ ] En iOS Safari muestra el mensaje "Share → Add to Home Screen".

**Commits:** `feat: useInstallPrompt composable`,
`feat: install banner component`,
`test: install banner visibility`.

---

### Fase V1.1-3 — Sin Build Mitosis: Vite config + producción + Lighthouse

**Objetivo:** validar que la PWA funciona en build de producción (`npm run
build`) y en servidor local.

**Tareas**

1. Build de producción: `npm run build` genera assets en `public/build/`. El
   SW los cachea por patrón `/build/*`. Confirmar que el manifest de Vite
   (`public/build/manifest.json` no colisiona con `public/manifest.json` PWA —
   nombres distintos: el de Vite vive en `public/build/manifest.json`, el de la
   PWA en `public/manifest.json`, no se pisan).
2. Servir con `php artisan serve` + `assets estáticos` y correr Lighthouse en
   modo desktop y mobile; objetivo PWA category ≥ 90.
3. Probar instalación real en:
   - Android Chrome (y Edge móvil opcional).
   - iOS Safari (con el flujo Share → Add to Home Screen).
4. Verificar `theme-color` se ve en la barra con el color activo.
5. Documentar en `README.md` (o una nueva sección en `docs/`) los pasos
   manuales para testear PWA en dispositivos.

**Criterios de aceptación**

- [ ] Lighthouse PWA ≥ 90 en móvil y desktop.
- [ ] Instalación en Android abre ventana standalone sin barra del navegador.
- [ ] Instalación en iOS abre en fullscreen.
- [ ] Recarga tras deploy: cache vieja se limpia y entra la nueva.

**Commits:** `chore: production build PWA validation`,
`docs: PWA install test instructions`.

---

### Fase V1.1-4 — Pulido y tests PWA

**Objetivo:** cerrar v1.1 con excepciones cubiertas y tests mínimos.

**Tareas**

1. Test feature (Pest): el layout renderiza `<link rel="manifest">` y
   `apple-touch-icon` (sobre el HTML响应).
2. Test unit del composable `useInstallPrompt` (mock `beforeinstallprompt`).
3. Smoke manual: instalar, abrir, navegar (dashboard, movimientos, proyección),
   verificar que no hay errores en consola.
4. Si surge interés: splash screens iOS por modelo — evaluar si hacerlo en v1.1
   o derivarlo a v3. Decisión post-prototipo.
5. Actualizar `docs/plan-de-trabajo.md` (o el README) marcando v1.1 completa.

**Criterios de aceptación**

- [ ] Tests del layout y composable pasan.
- [ ] No hay errores en la consola del navegador sesión instalada.
- [ ] `docs/plan-de-trabajo-v1.1-pwa.md` marcado como completado.

**Commits:** `test: layout manifest and apple-touch assertions`,
`test: useInstallPrompt mocks`,
`docs: v1.1 PWA complete`.

---

## 6. Orden recomendado y dependencias

```
V1.1-0 (manifest + meta + íconos)
   ↓
V1.1-1 (service worker)
   ↓
V1.1-2 (UX instalación)
   ↓
V1.1-3 (build prod + Lighthouse)  ←── puede iterar con V1.1-1 y V1.1-2
   ↓
V1.1-4 (pulido + tests)
```

V1.1-3 debería arrancar apenas V1.1-1 está listo y revisarse cada vez que
V1.1-0 / V1.1-2 cambian algo que afecte el score Lighthouse. V1.1-4 cierra.

---

## 7. Estimación orientativa

| Fase     | Esfuerzo | Comentario                              |
| -------- | -------- | --------------------------------------- |
| V1.1-0   | S        | Generar íconos + 1 archivo JSON + meta. |
| V1.1-1   | M        | SW vanilla, cuidado con cache-paths.    |
| V1.1-2   | S–M      | Composable + componente pequeño.        |
| V1.1-3   | S        | Pruebas en dispositivo real.            |
| V1.1-4   | S        | Tests mínimos + smoke.                  |

**Total:** ~1.5–2 días de trabajo concentrado. Si no hay dispositivo Android
físico para probar, V1.1-3 puede demorar por logística y conviene de闸门ar
emulador.

---

## 8. Riesgos y mitigaciones

| Riesgo                                                 | Mitigación                                                                          |
| ------------------------------------------------------ | ----------------------------------------------------------------------------------- |
| SW cachea datos vivos y muestra sesión caducada        | Solo cachea `/`, `/build/**`, `/icons/**`, `/manifest.json` — nunca rutas Inertia.  |
| Cambios en Vite build rompen el SW                     | Cache keys versionadas (`caja-diaria-shell-vN`) + `skipWaiting` + `clients.claim`. |
| iOS tiene cookies/session limitadas en PWA             | Documentar en v1.1 que puede pedir re-login periódicamente. No intentar arreglar.  |
| theme-color dinámica por usuario (paleta de Fase 8 v1) | Si es complicado, usar paleta por defecto y dejar TODO. No bloquear v1.1.          |
| Íconos maskable se ven mal en launchers con padding    | Generar con safe-zone (40% del área), fondo coloreado.                              |

---

## 9. Próximos pasos inmediatos

1. Generar los íconos PNG (192, 512, 180) desde el logo. Pedir al usuario si
   no hay logo definido.
2. Definir `theme_color` y `background_color` desde el proyecto.
3. Empezar por Fase V1.1-0.

---

## 10. Decisiones confirmadas

- **Instalable, no offline.** La red sigue siendo requerida para usar la app.
- **Sin Workbox:** SW vanilla simple, sin dependencias nuevas.
- **Cache-first solo del app shell**, nunca de datos.
- **Un solo SW + un solo manifest** para toda la app.
- **iOS**: flujo manual "Share → Add to Home Screen", no prompt nativo.
- **Splash screens iOS por modelo** fuera de v1.1 (decisión post-prototipo).
- **Plan independiente de v1.2 (sandbox)**: ambos se pueden implementar en
  paralelo o en el orden que el usuario prefiera.