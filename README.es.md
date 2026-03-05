# 🎖️ RADAR DE FACCIONES GLOBAL

### Sistema de Gestión Táctica - War Thunder Soshy's Server

<p align="center">
  <a href="README.md"><b>🇺🇸 View English Version</b></a> | 
  <a href="README.es.md"><b>🇪🇸 Versión en Español</b></a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Estado-Operativo-green?style=for-the-badge&logo=opsgenie" />
  <img src="https://img.shields.io/badge/Hosting-InfinityFree-blue?style=for-the-badge&logo=php" />
  <img src="https://img.shields.io/badge/Autor-Tarquitet-orange?style=for-the-badge" />
</p>

Sistema integral de gestión de recursos, inventarios y jurisdicción territorial. Diseñado con una estética militar personalizada para simulaciones de roleplay de alto nivel.

<div align="center">
  <table border="0">
    <tr>
      <td><img src="images/README/1772743523519.avif" width="100%" alt="Vista Dashboard" /></td>
      <td><img src="images/README/1772743532007.avif" width="100%" alt="Vista Tienda" /></td>
    </tr>
    <tr>
      <td><img src="images/README/1772743548358.avif" width="100%" alt="Vista Inventario" /></td>
      <td><img src="images/README/1772743567261.avif" width="100%" alt="Vista Radar" /></td>
    </tr>
  </table>
</div>

## 🛰️ Enlace Operativo

🔗 **[Acceso al Servidor de Operaciones (SOHYS RP)](https://sohysmilitarrp.infinityfree.me/index.php)**

## 💬 Comunidad y Lore

**War Thunder Soshy's Server** Servidor de War Thunder de guerra ficticia entre naciones con aumento de BR progresivo. Ven a compartir y divertirte en un mundo en conflicto ficticio, con naciones únicas y lore formado por sus propios jugadores.  
🎮 **[Unirse al Discord](https://discord.gg/xXMjukyw)**

---

## 🛠️ Especificaciones Técnicas y Versiones

### ⚙️ Arquitectura del Sistema

- [**Enlace de Datos**](config/conexion.php): Conexión segura PDO a MySQL.
- [**Diccionario Táctico**](config/textos.php): Control centralizado de textos y alertas.
- [**Configuración Económica**](config/precios.php): Lógica de costos de activos por rango y clase.

### 🕹️ Módulos Operativos

- [**Mando Central**](views/staff_dashboard.php): Control global de recursos y naciones para Staff.
- [**Tienda Militar**](views/lider_tienda.php): Sistema de compras con "Niebla de Guerra".
- [**Hangar de Inventario**](views/lider_inventario.php): Gestión de stock y despliegue de flotas navales.
- [**Radar Público**](index.php): Monitor de estado de facciones en tiempo real.

### 📡 Detalles de Entorno

- **Hospedaje:** InfinityFree (Servicio PHP/MySQL).
- **Stack:** PHP 8.2+ | MySQL (MariaDB) | Tailwind CSS.
- **Diseño:** [Framework CSS Militar Personalizado](assets/css/military.css).

---

_Desarrollado por [Tarquitet](https://tarquitet.com) - 2026_
