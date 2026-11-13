<p align="center">
  <img src="public/assets/smartbus-logo.webp" width="300" alt="SmartBus Global Logo">
</p>

# SmartBus Global - API Gateway

[![PHP Version](https://img.shields.io/badge/PHP-8.5%2B-777BB4?style=flat-square&logo=php)](https://php.net/)
[![Laravel Version](https://img.shields.io/badge/Laravel-13.x-FF2D20?style=flat-square&logo=laravel)](https://laravel.com/)
[![Testing](https://img.shields.io/badge/Tested_with-Pest-F16529?style=flat-square)](https://pestphp.com/)
[![License](https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square)](LICENSE)

## Visión General y Arquitectura

**SmartBus Global** es una plataforma integral de transporte público (B2G enfocada en Costa Rica), diseñada para la visualización de autobuses en tiempo real, predicción de llegadas (ETA) y gestión eficiente de rutas.

Este repositorio corresponde al **API Gateway** del proyecto, el punto único de entrada que orquesta la comunicación, la seguridad en el tráfico de red, el enrutamiento dinámico hacia la infraestructura de microservicios y la inyección contextual de identidades de usuario

*   **Clientes Frontend:** Aplicación móvil unificada en Flutter que adapta su interfaz, estado y funcionalidades de forma dinámica (Conductor vs. Pasajero), y un Panel Web Administrativo (Filament).
*   **Ecosistema de Microservicios:**
    *   **API Gateway:** *Este repositorio (Laravel).*
    *   **Autenticación:** Emisión e introspección de tokens Sanctum
    *   **Panel Administrativo:** Gestión integral con Laravel/Filament.
    *   **Rastreo GPS en tiempo real:** Comunicación bidireccional usando Laravel Reverb/WebSockets.
    *   **Motor Predictivo ETA:** Inteligencia Artificial implementada en Python/TensorFlow.

---

## Stack Tecnológico y Características Principales
### Enrutamiento y Validación de Sesiones
* **Proxy inverso:** Reenvío de solicitudes hacia los microservicios mediante rutas `/api/{service}/{path}`.
* **Validación de tokens sin caché:** El middleware `ValidateAuthToken` consulta al servicio de autenticación en cada solicitud protegida. Las validaciones positivas anteriores no se reutilizan para autorizar solicitudes.
* **Revocación de sesiones:** Un token revocado por cierre de sesión, cambio de contraseña o eliminación de cuenta se rechaza en la siguiente validación contra autenticación.
* **Reenvío de cabeceras:** Se conservan las cabeceras pertinentes, como `Authorization` y `Accept-Language`, al reenviar la solicitud al servicio de destino.
* **Errores de autenticación:** Se conservan los rechazos devueltos por autenticación. Si no es posible conectarse para validar el token, se devuelve `503 Service Unavailable`.
* **Registros sin datos sensibles:** No se registran los cuerpos completos de solicitudes de login ni de respuestas de los microservicios. Se conserva información operativa, como el estado HTTP.

### Capa de Seguridad Estricta 
* **Cabeceras de Seguridad Avanzadas (`SecurityHeaders`):** Middleware global que inyecta automáticamente defensas clave en todas las respuestas HTTP: 
  * `X-Frame-Options: DENY` (Protección contra Clickjacking). 
  * `X-XSS-Protection: 1; mode=block` (Mitigación de Cross-Site Scripting). 
  * `X-Content-Type-Options: nosniff` (Prevención de sniffing de tipos MIME). 
  * `Strict-Transport-Security` (Cumplimiento de conexiones seguras HTTPS). 
  * `Content-Security-Policy: default-src 'none';` (Política estricta restrictiva para APIs). 
* **CORS Restrictivo Configurable:** Control dinámico de orígenes cruzados basado en la variable de entorno `ALLOWED_ORIGINS` para aislar el consumo de la API a clientes autorizados.
* **Protección contra Limitación de Tasa (`Rate Limiting`):** Restricción de tráfico de 60 peticiones por minuto por cliente (`throttle:api`), identificadas por Bearer Token en peticiones autenticadas o por dirección IP en rutas públicas.

### Calidad e Internacionalización 
* **Soporte Bilingüe (i18n):** Middleware `SetLocale` que procesa la cabecera `Accept-Language` para adaptar mensajes y respuestas del gateway (Español / Inglés). 
* **Suite de Pruebas Automatizadas (`pestphp/pest`):** Cobertura de proxies, validación de tokens por solicitud, rechazo de tokens revocados aunque exista una caché antigua, retiro de endpoints administrativos, eliminación de cuentas, propagación de errores y protección de datos sensibles en logs.
* **Estandarización de Código (`laravel/pint`):** Aplicación de reglas de estilo de código para mantener uniformidad en el repositorio. 

## Autogestión de Cuenta del Pasajero

| Método   | Ruta del Gateway          | Ruta en Autenticación |
| -------- | ------------------------- | --------------------- |
| `PATCH`  | `/api/auth/user`          | `/api/user`           |
| `PUT`    | `/api/auth/user/password` | `/api/user/password`  |
| `DELETE` | `/api/auth/user`          | `/api/user`           |

Estas rutas operan sobre el propietario del token, sin recibir un identificador de usuario de destino.

### Eliminación de Cuenta

Enviar `DELETE /api/auth/user` con las cabeceras:

```http
Authorization: Bearer <token>
Content-Type: application/json
Accept-Language: es
```

Y el cuerpo JSON:

```json
{
  "current_password": "TuContraseñaActual",
  "confirm_deletion": true
}
```

Autenticación verifica que la cuenta tenga el correo verificado, pertenezca exclusivamente a un pasajero y no tenga roles adicionales en ninguna compañía. También valida la contraseña actual y la confirmación.

Una eliminación exitosa devuelve `200 OK`:

```json
{
  "meta": {
    "message": "Tu cuenta ha sido eliminada correctamente."
  }
}
```

La cuenta se elimina lógicamente y se revocan todos sus tokens, incluido el utilizado en la solicitud. Se conservan los datos personales, el UUID y las referencias históricas; esto no constituye una eliminación definitiva ni una anonimización.

La operación en autenticación es transaccional: si falla, se revierten la eliminación y la revocación de tokens.

### Errores y Límites

| Estado | Significado                                                                  |
| ------ | ---------------------------------------------------------------------------- |
| `401`  | Token ausente, inválido, expirado o revocado.                                |
| `403`  | Correo sin verificar o cuenta que no pertenece exclusivamente a un pasajero. |
| `422`  | Contraseña actual o confirmación inválida.                                   |
| `429`  | Límite de solicitudes excedido.                                              |
| `500`  | Fallo inesperado del servicio.                                               |
| `503`  | Servicio de autenticación no disponible.                                     |

El Gateway conserva el estado y el cuerpo de los rechazos devueltos por autenticación.

El Gateway aplica su límite general de 60 solicitudes por minuto por token o dirección IP. Autenticación limita los intentos de eliminación a cinco por minuto por cuenta autenticada.

### Retiro de Proxies Administrativos

Se retiraron los proxies de usuarios, catálogos de roles y permisos, y asignación de roles y permisos a usuarios. Sus rutas ya no están disponibles.

La gestión administrativa se realiza directamente en el Dashboard. Se conservan los proxies de registro, login, verificación de correo, recuperación de contraseña, consulta del usuario autenticado, validación de tokens, logout y autogestión del pasajero.

--- 

## CI/CD y Despliegue 
1. **Integración Continua (CI):** Workflows de **GitHub Actions** ejecutan el linter (Laravel Pint) y la suite de pruebas (Pest PHP) con cada Pull Request, garantizando integridad dentro de la estrategia de ramificación *GitFlow*. 
2. **Despliegue y Contenedores (CD):** Empaquetado de producción mediante un `Dockerfile` optimizado en múltiples etapas (multistage build) listo para despliegue automatizado en la plataforma **Render**. 

---

## Instalación Local 

```bash
# 1. Clonar el repositorio 
git clone cd smartbus-api-gateway 

# 2. Instalar dependencias del proyecto 
composer install 

# 3. Configurar las variables de entorno 
cp .env.example .env php artisan key:generate 

# 4. Iniciar la base de datos de caché y migraciones 
php artisan migrate 

# 5. Ejecutar la suite de pruebas 
php artisan test 

# 6. Levantar el entorno local de desarrollo 
composer run dev 
```