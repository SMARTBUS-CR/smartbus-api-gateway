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
### Enrutamiento, Caché e Identidad
* **Proxy Reverse Dinámico:** Redirección transparente de peticiones hacia microservicios backend basados en segmentos de URL (`/api/{service}/{path}`).
* **Introspección y Caché de Tokens:** Middleware `ValidateAuthToken` que valida la autenticidad de los tokens de sesión contra el microservicio de Auth. Implementa un mecanismo de caché centralizada con clave `hash('sha256')` para reducir la latencia y evitar peticiones repetidas entre microservicios. 
* **Inyección Contextual de Cabeceras:** Extracción de identidades y roles validados (`X-User-Id`, `X-User-Roles`) para adjuntarlos automáticamente a los encabezados HTTP antes de reenviar las peticiones a microservicios downstream.
* **Invalidación Proactiva de Sesión:** Limpieza automática de la memoria caché de introspección inmediatamente después de procesar peticiones al endpoint de cierre de sesión (`logout`). 

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
* **Suite de Pruebas Automatizadas (`pestphp/pest`):** Cobertura exhaustiva que valida la autenticación de rutas públicas y privadas, el comportamiento de la caché, la invalidación de tokens en logout, la inyección de headers contextuales, el Rate Limiting, la política CORS y las cabeceras de seguridad. 
* **Estandarización de Código (`laravel/pint`):** Aplicación de reglas de estilo de código para mantener uniformidad en el repositorio. 

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