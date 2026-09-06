# Análisis: agente de IA financiero y camino a SaaS

> Fecha: 2026-09-05
> Alcance: evaluar con datos reales (verificados en código y fuentes de sep-2026) la
> viabilidad de (a) monetizar Caja Diaria con un agente de IA que analiza tus datos
> financieros, y (b) qué le falta a la app antes de operar como SaaS.
> Documento hermano de `plan-de-trabajo.md` (v1), `plan-de-trabajo-v1.1-pwa.md`,
> `plan-de-trabajo-v1.2-sandbox.md`, `plan-de-trabajo-v1.3-deudas.md`,
> `plan-de-trabajo-v1.4-metas.md` y `plan-de-trabajo-v2.md`.

---

## 1. Respuesta corta

1. **Sí es rentable y técnicamente viable.** El costo de tokens de un análisis
   financiero es de **US$0.001–0.03 por análisis** con modelos "workhorse"
   (GPT-5 mini, Gemini Flash, Claude Haiku). Con un plan de S/14.90/mes, el costo
   de IA es **menos del 2 %** del precio, incluso con modelos premium. **El costo
   del LLM no es el riesgo; el riesgo es todo lo demás** (adquisición, retención,
   billing, cumplimiento).
2. **La mitad del "agente IA" ya está construida.** Caja Diaria tiene motores
   deterministas que ninguna app de IA genera gratis: proyección de saldo,
   avalancha vs. bola de nieve, factor ponderado, presupuestos, conciliación. El
   patrón correcto es **"PHP calcula, el LLM interpreta"**: los números salen de
   tus servicios, el modelo solo narra, prioriza y explica. Eso elimina el riesgo
   de alucinación numérica.
3. **Los planes v1.2 (sandbox) y v1.4 (metas) son prerrequisitos directos** de las
   funciones de IA más vendibles: "¿puedo pagar este préstamo?" (sandbox +
   proyección) y "coach de metas" (v1.4). No es trabajo extra, es el mismo roadmap.
4. **Pagos desde Perú tiene una limitación dura y verificada:** Stripe **no
   opera con empresas registradas en Perú** (lista oficial: solo Brasil y México en
   LatAm). Las rutas reales son: **Paddle/Lemon Squeezy (Merchant of Record)** para
   internacional, o **Culqi/Mercado Pago/Izipay** para soles con Yape. Ambas
   requieren **RUC**.
5. **Lo que le falta para ser SaaS no es código de producto**, sino: policies de
   autorización, verificación de email + 2FA, worker de colas en un VPS (el shared
   hosting de `DEPLOY.md` no sirve para un SaaS con IA), backups, monitoreo,
   términos/privacidad y billing. Ninguno es difícil; es trabajo de
   infraestructura en ~5–8 sesiones.

---

## 2. Estado actual (verificado en código, no asumido)

| Módulo | Estado | Evidencia |
| --- | --- | --- |
| v1.0 núcleo (movimientos, categorías, cuentas, recurrentes, proyección, dashboard, personalización) | ✅ Completo | `docs/plan-de-trabajo.md` fases 0–9 cerradas |
| v1.3 Deudas (CRUD transaccional, payoff, avalancha/bola de nieve, factor ponderado) | ✅ Completo | `app/Models/Debt.php`, `app/Http/Controllers/DebtController.php`, `app/Services/DebtStrategy.php` + tests |
| v1.1 PWA | ❌ Solo plan | No existe `public/manifest.json` ni service worker |
| v1.2 Sandbox | ❌ Solo plan | Sin migraciones ni scopes `is_sandbox` |
| v1.4 Metas | ❌ Solo plan | Sin modelo `Goal`/`Meta` ni migraciones |
| v2 Comercial | ❌ Solo plan | Sin `account_type`, sin `customers` |
| Multi-usuario (aislamiento por `user_id`) | 🟡 Parcial | Todos los modelos tienen `user_id` y los controllers filtran; **pero no existe `app/Policies/`** — los checks son manuales (`$movement->user_id !== $request->user()->id`) |
| Auth | 🟡 Base buena | Fortify 1.37 instalado; `MustVerifyEmail` está **comentado** en `app/Models/User.php`; 2FA disponible pero sin activar |
| Colas | 🟡 Sin usar | `QUEUE_CONNECTION=database` (config por defecto), sin jobs ni worker |
| Billing | ❌ Nada | Sin cashier/paddle/spark en `composer.json` |
| IA | ❌ Nada | Sin paquetes AI; tampoco existe `laravel/ai` aún |
| Hosting objetivo | ⚠️ Personal | `DEPLOY.md`: shared hosting cPanel + MySQL + cron; "no Redis en hosting básico" |

**Lectura honesta:** el modelo de datos ya es multi-usuario (todo lleva `user_id`),
así que "SaaS-ificar" no exige re-arquitectura de datos. Exige ponerse
serio en seguridad, billing y operación.

---

## 3. El agente de IA: diseño real (no magia)

### 3.1 El principio que lo hace seguro: PHP calcula, el LLM interpreta

Un LLM es malo para la aritmética y alucina cifras con confianza. Pero Caja Diaria
**ya tiene los cálculos**, deterministas y testeados:

- `Movement::realBalance()` / `Movement::openingBalance()` → balance real y de apertura.
- Motor de proyección (carry por página en `ProjectionController`) → saldo futuro por fecha.
- `DebtStrategy` → orden avalancha / bola de nieve y factor ponderado.
- Presupuestos por categoría y panel de conciliación.

El agente, entonces, **no genera números: los recibe**. El backend arma un
snapshot JSON con los números ya calculados, se lo pasa al LLM, y el LLM devuelve
**interpretación + prioridades + plan de acción**, validado contra un schema
(structured output). Si el modelo inventa un número, el schema y el UI lo
ignoran: la UI solo renderiza los campos que vienen del cálculo propio.

### 3.2 Features vendibles mapeadas a lo que ya existe

| Feature de pago | Motor determinista que ya tienes | Aporte del LLM |
| --- | --- | --- |
| **"Analiza mi situación financiera"** | Balances, gasto por categoría, presupuesto vs. límite, conciliación, recurrentes | Diagnóstico en español claro, 3–5 prioridades accionables, tono motivador |
| **"Plan para salir de deudas"** | `DebtStrategy` (avalancha/bola de nieve, factor ×) | Explica el trade-off entre métodos, cronograma narrado, respuestas a preguntas |
| **"¿Puedo pagar este préstamo?"** (pre-evaluación antes de tomarlo) | **Sandbox v1.2 + motor de proyección**: simulas la cuota como escenario y ves en qué fechas el saldo proyectado se rompe | Interpreta el resultado determinista (DTI, margen, fechas críticas) y sugiere condiciones más sanas |
| **"Coach de metas"** | v1.4 metas (aportes proyectados vs. fecha objetivo) | Confirma viabilidad, propone ajuste de aporte, celebración de hitos |
| **Chat conversacional** | Snapshot completo como contexto | Preguntas libres sobre la data propia del usuario |

**Conclusión:** sandbox (v1.2) y metas (v1.4) **son** los prerrequisitos del
agente. El roadmap que ya tienes planeado es el roadmap de la IA.

### 3.3 Flujo técnico recomendado

```
POST /ia/analisis (FormRequest: tipo de análisis)
   │
   ├─ CreditService: ¿tiene créditos? → reserva 1 crédito (ledger atómico)
   ├─ AiContextBuilder (service PHP puro):
   │    reúso de DashboardController + DebtStrategy + proyección
   │    → snapshot JSON acotado (~2–5K tokens, SIN movimientos crudos)
   │    → context_hash (para cachear análisis idénticos)
   │
   ├─ Dispatch RunAiAnalysis (job en cola, $tries=3, backoff,
   │    middleware RateLimited por usuario)
   │
   └─ Job: laravel/ai → structured output contra schema
        → guarda en tabla `ai_analyses` (contexto, tokens, costo, modelo, resultado)
        → si falla el proveedor: se devuelve el crédito reservado
```

Ejemplo de snapshot (acotado, agregado, sin PII innecesaria):

```json
{
  "currency": "PEN",
  "today": "2026-09-05",
  "balance": { "real_today": 5800.00, "projected_end_of_month": 6210.00 },
  "month": { "income": 4200.00, "expenses": 3105.00 },
  "budgets": [
    { "category": "Mercado", "limit": 400.00, "spent": 380.00 }
  ],
  "reconciliation": { "accounts_total": 5750.00, "difference": -50.00 },
  "debts": [
    { "name": "Falabella", "remaining": 2400.00, "factor": 1.50,
      "installment": 300.96, "next_payment": "2026-09-09" }
  ],
  "debt_strategy": { "avalanche": ["Falabella", "Huancayo"],
                     "snowball": ["Huancayo", "Falabella"],
                     "weighted_factor": 1.42 },
  "upcoming_recurring": [ { "name": "Huancayo 22", "amount": -350.00, "day": 22 } ]
}
```

El LLM devuelve un objeto tipado (schema), por ejemplo:

```json
{
  "headline": "Resumen en una línea",
  "health_score": 72,
  "findings": [ { "title": "...", "severity": "warning", "detail": "...",
                  "based_on": "budgets" } ],
  "actions": [ { "title": "...", "priority": 1 } ],
  "caveats": ["Educación financiera, no asesoría"]
}
```

### 3.4 Riesgos técnicos y mitigaciones

| Riesgo | Mitigación concreta |
| --- | --- |
| Alucinación numérica | El LLM nunca calcula; UI renderiza solo números del backend. Schema-validado |
| Prompt injection (las descripciones de movimientos son texto del usuario dentro del contexto) | Marcar el snapshot como "datos", truncar descripciones, system prompt firme, sin tool-calling en v1 |
| Latencia (2–10 s por análisis) | Job en cola + polling desde el front (Inertia 3 tiene deferred props / `useHttp`); MVP puede ser síncrono para análisis cortos |
| Abuso de créditos | Ledger atómico (reserva → settle/release), rate limit por usuario, snapshot cacheado por `context_hash` (mismo mes + mismos datos = no se gasta otro análisis) |
| Costo creciente | Batch API (−50 %) para análisis mensuales no urgentes; prompt caching del system prompt; presupuesto máximo de tokens por request |
| Privacidad | Se envía solo el snapshot agregado, nunca movimientos crudos; opt-in explícito del usuario; proveedores tipo API (OpenAI/Anthropic/Google) **no entrenan con datos de API por defecto** según sus términos vigentes — verificar al contratar y declararlo en la política de privacidad |

---

## 4. Servicios y APIs de IA (precios referenciales, sep-2026)

> **Aviso:** los precios de LLMs cambian seguido. Valores verificados contra
> fuentes de jul–sep 2026 (ver §10); **verifica la página oficial del proveedor
> antes de comprometerte**.

### 4.1 Proveedores

| Proveedor | Modelo | Input / Output (US$ por 1M tokens) | Comentario |
| --- | --- | --- | --- |
| OpenAI | GPT-5 nano | $0.05 / $0.40 | El más barato; suficiente para análisis simples |
| OpenAI | GPT-5 mini | $0.25 / $2.00 | Workhorse recomendado para v1 |
| OpenAI | GPT-5.4 | ~$2.50 / $15 | Solo para análisis premium |
| Anthropic | Claude Haiku 4.5 | $1.00 / $5.00 | Excelente calidad/precio; Batch −50 % ($0.50/$2.50); prompt caching −90 % en lectura |
| Anthropic | Claude Sonnet (gen. vigente) | ~$2–3 / $10–15 | Fuente sep-2026 reporta Sonnet 5 a $2/$10; verificar |
| Google | Gemini 2.5 Flash | $0.15 / $0.60 | Mejor precio/performance de la categoría flash; contexto 1M |
| Google | Gemini 3 Flash | ~$0.50 / $3.00 | Nueva generación (2026) |
| DeepSeek | V3 | $0.27 / $1.10 | Barato, pero **residencia de datos en China** — pensar dos veces con datos financieros de terceros |
| OpenRouter | (agregador) | varía | Una API para muchos modelos; útil como fallback/ruteo |

Dato operativo: en el tier inicial, OpenAI permite ~1000 requests/min vs ~50 RPM
de Anthropic. Para un SaaS chico ambas sobran, pero al escalar importa.

### 4.2 Costo real por análisis (estimación honesta)

Supuesto: snapshot de 4K tokens de entrada + 1.5K tokens de salida por análisis.

| Modelo | Costo por análisis | Costo/mes por usuario activo (30 análisis) |
| --- | --- | --- |
| GPT-5 nano | ~$0.0008 | ~$0.02 |
| Gemini 2.5 Flash | ~$0.0015 | ~$0.05 |
| GPT-5 mini | ~$0.004 | ~$0.12 |
| Haiku 4.5 (batch) | ~$0.006 | ~$0.18 |
| Claude Sonnet | ~$0.02–0.03 | ~$0.6–0.9 |
| GPT-5.4 (premium) | ~$0.03 | ~$1.00 |

**Traducción a negocio:** 100 usuarios de pago que usan el agente 30 veces al mes
cuestan entre **US$2 y US$18 al mes** en tokens. Con un plan de S/14.90 (~US$4),
el margen bruto sobre IA es >95 % incluso con modelo premium.
**El precio no lo justifica el token: lo justifica el valor.** El sistema de
créditos existe para **controlar abuso y crear percepción de valor**, no para
cubrir un costo marginal alto.

### 4.3 Integración en Laravel 13 (verificado)

| Paquete | Estado | Veredicto |
| --- | --- | --- |
| **`laravel/ai` (Laravel AI SDK oficial)** | Anunciado feb-2026; docs en `laravel.com/docs/13.x/ai-sdk`; v0.8.x con **14 proveedores** (OpenAI, Anthropic, Gemini, Groq, Mistral, DeepSeek, xAI, Ollama, Azure OpenAI, Cohere, OpenRouter, VoyageAI, ElevenLabs…); agents con instrucciones/memoria/tools/**structured output**; **fakes para tests**; fallbacks automáticos ante rate limits | **Recomendado.** Primera parte, encaja con Laravel 13.19, y el testeo con fakes encaja con tu suite Pest |
| `prism-php/prism` (comunidad) | v0.100.x, MIT, ~2.4k stars, API fluida, structured output | Alternativa sólida; en 2026 hubo un cambio de mantenimiento (fork "drop-in" de particle-academy) — si lo usas, fija versión y revisa estado del repo |
| `openai-php/laravel` | Solo OpenAI | Solo si quieres un único proveedor y dependencia mínima |

Con `laravel/ai` la elección de proveedor queda en `config/ai.php` y cambiar de
GPT-5 mini a Haiku/Gemini es una línea — el lock-in de proveedor deja de ser un
riesgo.

---

## 5. Monetización: cómo cobrarlo sin quemar la casa

### 5.1 Benchmarks reales (apps de finanzas, precios 2026, mercado EE.UU. con sync bancario)

| App | Precio | Nota |
| --- | --- | --- |
| YNAB | US$109/año (o $14.99/mes) | Metodología fuerte, trial 34 días |
| Monarch Money | US$99.99/año Core (o $14.99/mes); Plus $199/año | **Incluye asistente IA** en el plan Core |
| Copilot Money | US$95/año | Solo Apple |
| Rocket Money | ~US$7–14/mes (usuario elige) | Freemium |
| Lunch Money | ~US$120/año | **La más comparable a Caja Diaria**: indie, manual-first, sin banco obligatorio |

**Lectura para Perú:** el mercado gringo paga US$95–110/año con sync bancario
automático. Caja Diaria es captura manual y sin sync (no existe open banking
operativo en Perú para terceros tipo Plaid), así que el precio peruano realista
está en **S/99–149/año (S/9–15/mes)**, con los créditos de IA como diferenciador
que ninguna planilla ni app de banco da.

### 5.2 Modelo recomendado: híbrido freemium + créditos

- **Free:** todo el núcleo actual (movimientos, presupuestos, deudas, proyección) +
  **3 análisis IA/mes** (para que prueben el agente y quieran más).
- **Pro S/14.90/mes o S/99/año:** análisis ilimitados "workhorse" o cuota alta
  (p. ej. 60/mes), historial de análisis, sandbox ilimitado.
- **Packs de créditos (one-time, Yape-friendly):** p. ej. S/9.90 = 50 análisis.
  Nunca expiran. Encaja con la cultura de pago peruana (Yape por QR), donde la
  suscripción recién con tarjeta es fricción.
- **Análisis premium:** opción "responder con el modelo premium" que consume 2–3
  créditos (cubre el costo del token premium con margen).

### 5.3 Diseño del ledger de créditos (lo importante: que sea atómico)

```php
// migración propuesta
Schema::create('credit_ledger', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->integer('delta');              // +N otorgado/compra, -N consumo
    $table->integer('balance_after');      // snapshot para auditoría
    $table->string('reason');              // monthly_grant|purchase|consumption|refund
    $table->nullableMorphs('reference');   // el ai_analysis o la orden de pago
    $table->string('idempotency_key')->nullable()->unique();
    $table->json('metadata')->nullable();
    $table->timestamps();
});
```

Reglas: reserva-then-settle (reserva el crédito al despachar el job, confírmalo
al terminar, devuélvelo si el proveedor falla); grants mensuales vía scheduler
(el cron de `DEPLOY.md` ya está contemplado); toda compra entra con
`idempotency_key` del webhook del gateway para no duplicar créditos ante reintentos.

### 5.4 La trampa del fee fijo (haz esta cuenta antes de poner precio)

Tanto Paddle como Lemon Squeezy y Culqi cobran un **componente fijo por
transacción** ($0.50 o S/0.60). En tickets chicos es brutal:

| Ticket | Fee MoR internacional | % efectivo |
| --- | --- | --- |
| US$4.99/mes vía Paddle | 5% + $0.50 = $0.75 | **15 %** |
| US$39.99/año vía Paddle | 5% + $0.50 = $2.50 | **6.2 %** |
| S/9.90 pack vía Culqi | 3.99% + S/0.60 ≈ S/1.00 | **~10 %** |
| S/49.90 pack anual vía Culqi | ≈ S/2.59 | **~5.2 %** |

**Conclusión:** internacional cobra **anual**; local, apunta packs/suscripción a
tickets ≥ S/30 o acumula créditos por períodos. Además, Paddle bajo US$10 por
transacción pide pricing custom según fuentes de 2026 — otro motivo para el plan
anual.

---

## 6. Pagos desde Perú (verificado, sin fantasías)

### 6.1 Stripe: NO para empresas peruanas

La lista oficial de países de Stripe (`stripe.com/global`, verificada sep-2026)
incluye ~46–49 países; en LatAm **solo Brasil y México**. Perú no está. Las rutas
reales para cobrar como persona/empresa peruana:

1. **Merchant of Record (recomendada para internacional):** Paddle, Lemon
   Squeezy, Dodo, Polar. Ellos son el vendedor legal, cobran por ti, remiten
   VAT/GST global y te pagan por transferencia/PayPal. No necesitas empresa
   extranjera.
2. **Stripe Atlas:** crear LLC en EE.UU. (~US$500 + costos anuales + obligaciones
   fiscales US) y recién ahí Stripe. Tiene sentido con volumen; para arrancar es
   sobreingeniería.
3. **Pasarela local (para soles/Yape):** Culqi, Mercado Pago, Izipay, Niubiz.
   Requieren **RUC**.

### 6.2 Comparativa (comisiones referenciales 2026, verificar tarifario vigente)

| Opción | Comisión | Suscripciones | Yape | Notas |
| --- | --- | --- | --- | --- |
| **Paddle (MoR)** | 5% + US$0.50 todo incluido | ✅ Maduras | ❌ | Efectivo ~6.7 % en suscripción internacional; lo más simple para USD |
| **Lemon Squeezy (MoR)** | 5% + US$0.50 **+ recargos** (int'l +1.5 %, PayPal +1.5 %, subs +0.5 %) | ✅ | ❌ | Efectivo ~8.7 % en subs internacionales; payout a banco no-US +1 %; reportado con lista de espera en 2026 |
| **Culqi** | ~3.99 % + S/0.60 (nacional) | ✅ (tokenización) | ✅ (2.99 % + S/0.30) | Mejor API/SDK PHP del mercado peruano; ideal SaaS local |
| **Mercado Pago** | ~3.49–4.49 % + S/1 fijo | ✅ | ✅ (Checkout API con teléfono + OTP) | Marca conocida; PagoEfectivo incluido |
| **Izipay** | ~3.44 % + S/0.69 + IGV | ✅ | ✅ | Fuerte omnicanal |
| Niubiz | Negociado (~3.5–4.45 % + IGV) | ✅ | ✅ | Amex/Diners; negociación por volumen |
| Yape Empresa | 2.95 % | ❌ | — | **No tiene API pública**; se integra vía Culqi/Izipay/MP |

**Stack recomendado para Caja Diaria:** local con **Culqi** (packs de créditos en
PEN + suscripción anual, con Yape dentro del checkout) + internacional con
**Paddle** (suscripción anual en USD). Ambos por webhook + `idempotency_key` al
ledger de créditos. PCI DSS se simplifica usando su checkout embebido (nunca
tocas datos de tarjeta).

**Nota fiscal:** vender localmente con pasarela peruana implica facturar y
manejar IGV según tu régimen (RUC). Eso lo define un contador, no este
documento.

---

## 7. Gap analysis: qué le falta a la app para ser SaaS

Prioridad **B** = bloqueante para abrir registro público; **R** = recomendado
antes de cobrar; **M** = puede esperar.

| Área | Estado actual (verificado) | Falta | Esfuerzo | Prioridad |
| --- | --- | --- | --- | --- |
| Autorización | Checks manuales por controller | `app/Policies/` con `MovementPolicy`, `DebtPolicy`, etc. + `Gate` en `routes/web.php` | 1 sesión | **B** |
| Verificación de email | `MustVerifyEmail` comentado en `User` | Activar feature de Fortify + middleware `verified` en registro público | 0.5 sesión | **B** |
| 2FA | Fortify instalado, sin activar | Activar + UI de recovery codes | 0.5 sesión | **R** |
| Rate limiting auth | Parcial (starter kit) | Confirmar throttling en login/register | 0.25 sesión | **B** |
| Base de datos | SQLite local; MySQL previsto en `DEPLOY.md` | MySQL/Postgres en producción + **backups automáticos** (spatie/laravel-backup) | 0.5 sesión | **B** |
| Hosting | Shared hosting (cPanel) | **VPS** (Hetzner/DO, ~US$5–10/mes) con Supervisor para `queue:work` — el shared hosting no da daemon; alternativa gestionada: Laravel Cloud | 1 sesión | **B** |
| Colas | `database` driver, sin jobs ni worker | Worker + Supervisor + tabla `failed_jobs` monitoreada | incluido arriba | **B** |
| Email transaccional | No configurado | Resend/Postmark (verificación, recibos, alertas) | 0.5 sesión | **B** |
| Términos y privacidad | No existen | ToS + política de privacidad (Ley 29733; cláusula explícita de datos a proveedor IA con opt-out) | 1 sesión | **B** |
| Billing | Nada | Culqi + Paddle, webhooks, flags de plan | 2–3 sesiones | **R** |
| Ledger de créditos | Nada | §5.3 | 0.5 sesión | **R** |
| Módulo IA | Nada | `laravel/ai` + ContextBuilder + job + tabla `ai_analyses` + UI | 3–4 sesiones | **R** |
| Export/borrado de datos | Nada | Export CSV/JSON + borrado de cuenta (derecho de los titulares de datos) | 0.5 sesión | **R** |
| Monitoreo | Nada | Sentry (free tier) + Laravel Pulse | 0.5 sesión | **R** |
| PWA | Solo plan v1.1 | Ejecutar plan | 1–1.5 sesiones | **M** (pero clave para retención mobile) |
| Sandbox | Solo plan v1.2 | Ejecutar plan | según plan | **M** (prerrequisito del feature estrella) |
| Metas | Solo plan v1.4 | Ejecutar plan | según plan | **M** (prerrequisito del coach de metas) |

**Total estimado del bloque SaaS (B): ~5–6 sesiones.** El módulo IA (R): ~4
sesiones más. Nada de esto requiere reescribir lo existente.

---

## 8. Regulación y riesgos (la parte que nadie te cuenta)

1. **No es asesoría financiera.** En Perú la SBS regula a entidades del sistema
   financiero y ciertas actividades crediticias. Un app personal que **educa,
   ordena y explica** con TU propia data está en zona segura si: no recomienda
   productos financieros específicos, no evalúa créditos de terceros, y muestra
   disclaimer permanente ("contenido educativo, no constituye asesoría
   financiera"). El feature de préstamos debe llamarse **simulación personal**,
   jamás "pre-evaluación crediticia" hacia terceros.
2. **Datos personales (Ley 29733 / ANPD).** Tratas datos financieros de
   terceros a escala: mínimos exigibles son política de privacidad, consentimiento
   explícito, derecho de export/borrado, y evaluar registro ante la Autoridad
   Nacional de Protección de Datos Personales cuando escales. Con un contador y
   un abogado se resuelve; sin ello, no abras registro público.
3. **Datos hacia el proveedor IA.** Declarar en privacidad que se envían resúmenes
   financieros a un proveedor de IA, con opt-out. APIs de OpenAI/Anthropic/Google
   no usan datos de API para entrenar por defecto (verificar términos vigentes).
   **DeepSeek implica residencia de datos en China**: descartable para datos
   financieros de terceros.
4. **Alucinación numérica:** mitigada por arquitectura (§3.1). Nunca dejes que el
   LLM emita cifras que la UI renderice sin pasar por el backend.
5. **Prompt injection:** descripciones de movimientos son texto del usuario
   dentro del contexto del LLM. Sanitiza/trunca, estructura el snapshot como
   datos, y valida la salida contra schema.
6. **Riesgo real de negocio (el más serio): retención con captura manual.** Sin
   open banking en Perú, el usuario escribe todo a mano; el churn no viene de la
   IA sino del abandono de la captura. Mitigaciones que ya tienes planeadas: PWA
   (fricción mobile ↓), recurrentes y deudas (captura una vez, genera solo), y el
   agente IA como razón semanal para volver a abrir la app ("tu análisis de
   semana está listo").
7. **Costo fijo de operación:** VPS ~US$5–10/mes + email ~US$0–10 + dominio +
   Sentry free tier. Break-even con **2–4 suscriptores anuales**. No hay excusa
   financiera para no intentarlo.

---

## 9. Hoja de ruta sugerida

```
R0. Cerrar features planificadas (PREREQUISITO)
    v1.4 Metas ─► v1.2 Sandbox ─► v1.1 PWA
    (Metas y Sandbox son los motores del agente; PWA es retención mobile)

R1. SaaS core (5–6 sesiones) — bloque "B" del §7
    Policies + 2FA + verificación email + VPS + worker + backups +
    emails + ToS/privacidad + Sentry
    Hito: registro público seguro con datos respaldados

R2. IA MVP (4 sesiones)
    laravel/ai + AiContextBuilder + job + ledger de créditos + ai_analyses
    Features: análisis de situación + plan de deudas + chat con snapshot
    Tests con fakes del SDK (cero tokens en CI)
    Hito: alpha cerrada (10–20 usuarios) sin billing, créditos gratis

R3. Billing (2–3 sesiones)
    Culqi (PEN + Yape) para packs/anual + Paddle (USD anual)
    Webhooks → ledger; flags de plan
    Hito: primer pago real end-to-end

R4. Beta pública
    Precio de lanzamiento (p. ej. S/99/año early-bird), telemetría de
    uso del agente, iterar prompts según feedback
```

**Señal de éxito a vigilar:** conversión free→pago ≥5 % y uso del agente ≥1
análisis/semana por usuario activo. Si el agente no se usa, el problema es de
producto, no de modelo.

---

## 10. Fuentes verificadas (sep-2026)

- Laravel AI SDK (oficial): `laravel.com/ai`, `laravel.com/docs/13.x/ai-sdk`,
  `github.com/laravel/ai`, anuncio Laravel News feb-2026.
- Prism PHP: `github.com/prism-php/prism`, `packagist.org/packages/prism-php/prism`
  (v0.100.1, mar-2026).
- Precios LLM: comparativas abr–sep 2026 (apiscout.dev, simplifai.tools, G2,
  finout.io, justinmckelvey.com) contra páginas de precios oficiales de OpenAI /
  Anthropic / Google. **Re-verificar siempre contra la página oficial del
  proveedor antes de contratar.**
- Stripe países soportados: `stripe.com/global` (verificado directo, sep-2026;
  LatAm: solo Brasil y México) + guías 2026 (cs-cart, redstag, rapyd).
- MoR: comparativas Paddle vs Lemon Squeezy 2026 (apiscout, aibizhub,
  stackmatchup, paas.build) contra `paddle.com/pricing` y
  `docs.lemonsqueezy.com`.
- Pasarelas Perú: comparativas 2026 (adratech, devsprinters, impulsastudio,
  guiadesoftware) + docs de Mercado Pago Perú (Checkout API Yape) y Culqi.
  Comisiones referenciales; **pedir tarifario escrito antes de afiliarse**.
- Benchmarks de apps: páginas de pricing y análisis 2026 (YNAB $109/año,
  Monarch $99.99/año, Copilot $95/año, Rocket Money $7–14/mes).

> Este documento es análisis técnico, no asesoría legal, fiscal ni financiera.
