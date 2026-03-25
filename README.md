# OctaBank - Sistema Financiero Estudiantil

Plataforma bancaria desarrollada para la gestión y transferencia de fondos entre compañeros del grupo de 8vo semestre. Arquitectura segura, ágil e interactiva construida en PHP 8+ y MySQL 8+.

## 🚀 Características Principales

- **Dashboard Dinámico:** Resumen financiero con estado de la cuenta, últimos movimientos, e ingresos/egresos totales.
- **Transferencias Flash:** Envíos P2P instantáneos entre miembros del grupo validando saldos y previniendo cuentas inválidas.
- **Microbanco Interno:** Retiros y depósitos (operaciones externas) sujetos a moderación para asegurar la integridad contable con el dinero físico/real.
- **Panel Administrativo (Auditoría):** Visualización global de métricas con `Chart.js`, aprobación manual de trámites y gestión de suspensión/activación de usuarios morosos.
- **Exportación a PDF:** Generación en el cliente (`html2pdf.js`) del estado de cuenta de los miembros y el directorio general.

## 🛡️ Estándares de Seguridad (OWASP Top 10)

1. **SQL Injection (SQLi) Prevenido:** Uso estricto del driver PDO y sentencias preparadas (Prepared Statements).
2. **Cross-Site Scripting (XSS) Prevenido:** Sanitización con `htmlspecialchars(trim())` antes de la impresión de cualquier variable proveniente de entrada del usuario.
3. **Cross-Site Request Forgery (CSRF) Prevenido:** Tokens criptográficos de 32 bytes dinámicos en cada formulario, destruidos tras un solo uso válido.
4. **Race Conditions Preventative (Doble Gasto):** Consultas pesimistas (`FOR UPDATE`), envoltorios transaccionales `beginTransaction()` e `InnoDB` Row-Level Locks para que dos peticiones simultáneas no logren vaciar la cuenta con el mismo saldo.
5. **Data Tampering Override (Manipulación HTTP):** Parches matemáticos que neutralizan valores ingresados por F12 al reescribir `float` menores a `1.00`.

## 📂 Arquitectura (Page Controller Pattern)

Se optó por evitar frameworks externos (MVC complejos) en favor de una arquitectura clásica Modular Procedural (Script -> Controlador + Vista). Esto mantiene el proyecto ligero, predetermina la trazabilidad rápida de bugs (en una vista por acción) y elimina sobreingeniería académica.

- **`/includes/`**: Componentes visuales (header/footer) reutilizables, control de sesiones.
- **`/classes/`**: Diseño Orientado a Objetos (Singleton Pattern) para persistencia e instanciación de Base de Datos.
- **`/transacciones/` & `/admin/`**: Flujos separados basados en la autenticación del usuario.

## 🛠️ Instalación Local (XAMPP / WAMP)

1. Clona el repositorio dentro de tu carpeta `htdocs` (si usas XAMPP):
   ```bash
   git clone https://github.com/tu-usuario/octabank.git
   ```
2. Inicia los servicios de Apache y MySQL.
3. Ve a tu cliente MySQL (phpMyAdmin o TablePlus) y crea una base de datos.
4. Importa el archivo raíz de la estructura: `sql/database.sql`.
5. Abre `config/config.php` (renómbralo si es necesario de `config.example.php`) y ajusta las credenciales de tu DB:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'octabank');
   ```
6. Accede al sistema en el navegador: `http://localhost/octabank` u `http://localhost/sistema` (dependiendo tu carpeta).

### 🔑 Credenciales por Defecto (Administrador)
- **Email:** `admin@octabank.com`
- **Contraseña:** `Admin123!`

---
*Orgullosamente desarrollado para un entorno académico, con estándares comerciales. UI en Dark Mode basada en lineamientos modernos de CSS Vanilla.*
